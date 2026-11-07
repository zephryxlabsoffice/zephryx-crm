<?php

namespace App\Support\SupportMail;

use App\Mail\SupportAckMail;
use App\Models\Client;
use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\SupportMailbox;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Diagnostics;
use App\Support\Documents\DocumentStore;
use App\Support\Notifier;
use App\Support\Realm;
use App\Support\TicketDirectory;
use App\Support\TicketLifecycle;
use App\Support\TicketWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mail to support@ becomes a ticket (owner, 2026-11-06). Run every five minutes
 * by `tickets:fetch-mail`.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHAT HAPPENS TO ONE MESSAGE
 *
 * 1. An auto-reply, bounce or mailing list: noted and left alone.
 * 2. A reply about a ticket (its reference in the subject, or the same email
 *    thread) from the person who asked: added to that ticket as a reply, and a
 *    resolved ticket reopens. A closed one gets a new ticket that replaces it.
 * 3. Anything else is a new ticket, its category guessed from keywords:
 *    - a client's address (portal account or contact email): a client ticket,
 *      and "Ticket created, reference ..." back;
 *    - an employee's: an internal ticket from them, the same answer;
 *    - anybody else: a ticket from "unknown sender", and the owner's automated
 *      reply asking them to write from their registered email.
 *
 * Every message is written to `support_mail_messages` once, by its Gmail id,
 * so a message is never made into two tickets however often this runs.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class SupportMailIntake
{
    /** Answers to one sender per day, so a loop with a robot stops quickly. */
    public const MAX_ANSWERS_PER_DAY = 5;

    public function __construct(
        protected Notifier $notify,
        protected AuditLog $audit,
        protected DocumentStore $documents,
    ) {}

    /**
     * @return array{tickets: int, replies: int, ignored: int, failed: int}
     */
    public function run(): array
    {
        $counts = ['tickets' => 0, 'replies' => 0, 'ignored' => 0, 'failed' => 0];
        $mailbox = SupportMailbox::current();

        if (! SupportMailbox::ready() || ! $mailbox->isConnected()) {
            return $counts;
        }

        try {
            $gmail = new GmailClient($mailbox);
            $since = ($mailbox->connected_at ?? Carbon::now()->subDay())->timestamp;
            $found = $gmail->search('deliveredto:'.$mailbox->address.' after:'.$since.' -in:sent -in:chats');

            $seen = DB::table('support_mail_messages')
                ->whereIn('gmail_id', array_column($found, 'id'))
                ->pluck('gmail_id')
                ->all();

            // Oldest first, so a reply is handled after the message it answers.
            foreach (array_reverse($found) as $item) {
                if (in_array($item['id'], $seen, true)) {
                    continue;
                }

                $outcome = $this->handle($gmail, $mailbox, (string) $item['id']);
                $counts[$outcome === 'ticket' ? 'tickets' : ($outcome === 'reply' ? 'replies' : $outcome)]++;
            }

            $mailbox->forceFill(['last_checked_at' => Carbon::now(), 'last_error' => null])->save();
        } catch (Throwable $e) {
            report($e);
            $mailbox->forceFill(['last_checked_at' => Carbon::now(), 'last_error' => Str::limit($e->getMessage(), 990)])->save();
        }

        return $counts;
    }

    /**
     * @return 'ticket'|'reply'|'ignored'|'failed'
     */
    protected function handle(GmailClient $gmail, SupportMailbox $mailbox, string $id): string
    {
        try {
            $mail = IncomingMail::fromGmail($gmail->message($id));
        } catch (Throwable $e) {
            report($e);
            $this->log($id, null, null, null, 'failed', $e->getMessage());

            return 'failed';
        }

        if ($mail->isAutomated((string) $mailbox->address)) {
            $this->log($mail->id, $mail->threadId, null, $mail, 'ignored', 'Automated or our own mail');

            return 'ignored';
        }

        try {
            return DB::transaction(function () use ($gmail, $mailbox, $mail) {
                $existing = $this->ticketItAnswers($mail);

                if ($existing !== null && $existing->status !== 'closed') {
                    $this->addReply($gmail, $existing, $mail);
                    $this->log($mail->id, $mail->threadId, $existing->id, $mail, 'reply');

                    return 'reply';
                }

                $ticket = $this->newTicket($gmail, $mailbox, $mail, $existing);
                $this->log($mail->id, $mail->threadId, $ticket->id, $mail, 'ticket');

                return 'ticket';
            });
        } catch (Throwable $e) {
            report($e);
            Diagnostics::recordException($e);
            $this->log($mail->id, $mail->threadId, null, $mail, 'failed', $e->getMessage());

            return 'failed';
        }
    }

    /**
     * The ticket this message is about, when it is from somebody who may add to it.
     */
    protected function ticketItAnswers(IncomingMail $mail): ?Ticket
    {
        $ticket = null;

        if (preg_match('/\bTKT-\d{4}-\d{3,}\b/i', $mail->subject, $m) === 1) {
            $ticket = Ticket::where('reference', strtoupper($m[0]))->first();
        }

        $ticket ??= $mail->threadId === '' ? null : Ticket::where('mail_thread_id', $mail->threadId)->latest('id')->first();

        return $ticket !== null && $this->belongsTo($ticket, $mail->fromEmail) ? $ticket : null;
    }

    protected function belongsTo(Ticket $ticket, string $email): bool
    {
        $ticket->loadMissing(['client', 'raiser.user']);

        $addresses = collect([$ticket->requester_email, $ticket->raiser?->user?->email, $ticket->client?->contact_email])
            ->merge($ticket->client?->accounts()->pluck('email') ?? [])
            ->filter()
            ->map(fn (string $address) => strtolower($address));

        return $addresses->contains(strtolower($email));
    }

    protected function addReply(GmailClient $gmail, Ticket $ticket, IncomingMail $mail): void
    {
        $user = $this->userFor($mail->fromEmail);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'author_id' => $user?->id,
            'author_label' => $user?->name ?? $mail->fromName,
            'author_role' => 'By email',
            'body' => Str::limit($mail->newText(), 4990),
            'visibility' => TicketComment::PUBLIC,
        ]);

        $ticket->touch();
        $this->attach($gmail, $ticket, $mail, $user);

        $this->audit->record(
            action: AuditLog::TICKET_COMMENTED,
            actor: $user,
            actorLabel: $user === null ? $mail->fromEmail : null,
            entityType: 'ticket',
            entityId: $ticket->reference,
            after: 'Replied by email',
        );

        TicketLifecycle::reopenIfResolved($ticket, $user);
        $this->notify->ticketCommented($ticket->load(['assignee.user', 'raiser.user']), $comment, $user);
    }

    protected function newTicket(GmailClient $gmail, SupportMailbox $mailbox, IncomingMail $mail, ?Ticket $replaces): Ticket
    {
        $user = $this->userFor($mail->fromEmail);
        $employee = $user !== null && $user->account_type === Realm::STAFF
            ? Employee::where('user_id', $user->id)->first()
            : null;
        $client = $this->clientFor($mail->fromEmail, $user);
        $known = $employee !== null || $client !== null;

        $category = $this->guessCategory($mailbox, $mail->subject.' '.$mail->body);
        $desk = TicketWorkflow::deskFor($category);
        $agent = $employee !== null && ($desk['route_on_raise'] ?? false)
            ? TicketDirectory::agentFor($desk['role'], $employee)
            : null;

        $ticket = Ticket::create([
            'reference' => TicketDirectory::nextReference(),
            'type' => $client !== null && $employee === null ? 'client' : 'internal',
            'subject' => Str::limit($mail->subject, 195),
            'description' => Str::limit($mail->body !== '' ? $mail->body : '(The email had no text.)', 4990),
            'raised_by' => $employee?->id,
            'client_id' => $employee === null ? $client?->id : null,
            'category' => $category,
            'assignee_id' => $agent?->id,
            'status' => $agent === null ? 'unassigned' : 'open',
            'supersedes_ticket_id' => $replaces?->id,
            'source' => 'email',
            'requester_email' => $mail->fromEmail,
            'requester_name' => Str::limit($mail->fromName, 185),
            'mail_thread_id' => $mail->threadId,
        ]);

        $this->audit->record(
            action: AuditLog::TICKET_RAISED,
            actor: $user,
            actorLabel: $user === null ? $mail->fromEmail : null,
            entityType: 'ticket',
            entityId: $ticket->reference,
            after: 'By email from '.$mail->fromEmail.($known ? '' : ' (unknown sender)').': '.$ticket->subject,
        );

        $this->attach($gmail, $ticket, $mail, $user);

        if ($agent !== null) {
            $this->notify->ticketAssigned($ticket->load('assignee.user'), null, $user);
        } elseif ($ticket->type === 'client') {
            $this->notify->ticketRaised($ticket->load(['project.manager.user', 'assignee.user']), null);
        } else {
            $this->notify->ticketArrivedByEmail($ticket, $known);
        }

        Diagnostics::attachTo($ticket, $user);

        $this->answer($mailbox, $mail, $known ? $ticket : null, $client !== null && $employee === null);

        return $ticket;
    }

    /**
     * "Ticket created, reference ..." to somebody we know; the owner's automated
     * reply to anybody else. Never more than a few a day to one address.
     */
    protected function answer(SupportMailbox $mailbox, IncomingMail $mail, ?Ticket $ticket, bool $isClient): void
    {
        $answeredToday = DB::table('support_mail_messages')
            ->where('from_email', $mail->fromEmail)
            ->where('outcome', 'ticket')
            ->where('created_at', '>=', Carbon::today())
            ->count();

        if ($answeredToday >= self::MAX_ANSWERS_PER_DAY) {
            return;
        }

        try {
            Mail::to($mail->fromEmail)->send(new SupportAckMail(
                known: $ticket !== null,
                fromAddress: (string) $mailbox->address,
                originalSubject: $mail->subject,
                reference: $ticket?->reference,
                url: $ticket === null ? null : ($isClient
                    ? route('client.tickets.show', ['ticket' => $ticket->reference])
                    : route('tickets.show', ['ticket' => $ticket->reference])),
                inReplyTo: $mail->messageId(),
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The category whose keywords the message mentions most. Keywords are edited
     * in Admin > Integrations; a category not in master data is skipped.
     */
    public function guessCategory(SupportMailbox $mailbox, string $text): ?string
    {
        $haystack = ' '.Str::lower(preg_replace('/\s+/', ' ', $text) ?? $text).' ';
        $known = MasterDataItem::query()->inList(MasterDataItem::TICKET_CATEGORIES)->active()->pluck('name')->all();
        $best = null;
        $bestHits = 0;

        foreach (preg_split('/\R/', (string) ($mailbox->keywords ?? SupportMailbox::DEFAULT_KEYWORDS)) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            $category = trim(Str::before($line, ':'));

            if (! in_array($category, $known, true)) {
                continue;
            }

            $hits = 0;

            foreach (explode(',', Str::after($line, ':')) as $word) {
                $word = Str::lower(trim($word));

                if ($word !== '' && preg_match('/\b'.preg_quote($word, '/').'\b/u', $haystack) === 1) {
                    $hits++;
                }
            }

            if ($hits > $bestHits) {
                [$best, $bestHits] = [$category, $hits];
            }
        }

        return $best;
    }

    protected function userFor(string $email): ?User
    {
        return User::query()->where('email', $email)->where('status', 'active')->first();
    }

    protected function clientFor(string $email, ?User $user): ?Client
    {
        if ($user !== null && $user->account_type === Realm::CLIENT && filled($user->client_ref)) {
            return Client::where('reference', $user->client_ref)->first();
        }

        return Client::query()->whereRaw('lower(contact_email) = ?', [strtolower($email)])->first();
    }

    /**
     * The message's files, where they are a kind and size the store accepts.
     */
    protected function attach(GmailClient $gmail, Ticket $ticket, IncomingMail $mail, ?User $user): void
    {
        foreach (array_slice($mail->attachments, 0, 5) as $file) {
            $extension = Str::lower(pathinfo($file['filename'], PATHINFO_EXTENSION));

            if (! in_array($extension, DocumentStore::ALLOWED, true) || $file['size'] > DocumentStore::MAX_BYTES) {
                continue;
            }

            $temp = null;

            try {
                $bytes = $file['data'] !== null
                    ? GmailClient::decode($file['data'])
                    : $gmail->attachment($mail->id, (string) $file['attachmentId']);

                $temp = tempnam(sys_get_temp_dir(), 'mail');
                file_put_contents($temp, $bytes);

                $stored = $this->documents->put('tickets/'.$ticket->reference, new UploadedFile($temp, $file['filename'], $file['mimeType'], null, true));

                TicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'uploaded_by' => $user?->id,
                    'uploaded_by_label' => Str::limit($user?->name ?? $mail->fromName, 115),
                    'document_path' => $stored['path'],
                    'document_name' => $stored['name'],
                    'document_bytes' => $stored['bytes'],
                ]);
            } catch (Throwable $e) {
                report($e);
            } finally {
                if ($temp !== null && is_file($temp)) {
                    @unlink($temp);
                }
            }
        }
    }

    protected function log(string $gmailId, ?string $threadId, ?int $ticketId, ?IncomingMail $mail, string $outcome, ?string $note = null): void
    {
        DB::table('support_mail_messages')->insertOrIgnore([
            'gmail_id' => $gmailId,
            'thread_id' => $threadId,
            'ticket_id' => $ticketId,
            'from_email' => $mail?->fromEmail,
            'subject' => $mail === null ? null : Str::limit($mail->subject, 250),
            'outcome' => $outcome,
            'note' => $note === null ? null : Str::limit($note, 250),
            'created_at' => Carbon::now(),
        ]);
    }
}
