<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\Demo\DemoTickets;
use Illuminate\Database\Seeder;

/**
 * The demo tickets and their threads. Local + debug only.
 *
 * The internal notes are reproduced as internal — they are the point. A seed
 * where every comment is public would hide the whole reason the visibility
 * column exists, and the fixture's notes are exactly the sentences that must
 * not reach a client.
 */
class TicketSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $employees = Employee::query()
            ->with('user')
            ->get()
            ->keyBy(fn (Employee $e) => (string) $e->user?->user_id);

        $clients = Client::pluck('id', 'name');
        $projects = Project::pluck('id', 'reference');

        foreach (DemoTickets::all() as $row) {
            $ticket = Ticket::updateOrCreate(
                ['reference' => $row['id']],
                [
                    'type' => $row['type'],
                    'subject' => $row['subject'],
                    'description' => $row['description'],
                    'raised_by' => $row['raised_by'] ? $employees->get($row['raised_by'])?->id : null,
                    'client_id' => $row['client'] ? ($clients[$row['client']] ?? null) : null,
                    'project_id' => $row['project'] ? ($projects[$row['project']] ?? null) : null,
                    'assignee_id' => $row['assignee'] ? $employees->get($row['assignee'])?->id : null,
                    'status' => $row['status'],
                    'priority' => $row['priority'],
                    'category' => $row['category'],
                    'department' => $row['department'],
                    'escalated_by' => $row['escalated_by'] ? $employees->get($row['escalated_by'])?->id : null,
                    'escalated_at' => $row['escalated_by'] ? $row['updated_at'] : null,
                    'resolved_at' => $row['status'] === 'resolved' ? $row['updated_at'] : null,
                ],
            );

            /*
             * `created_at` and `updated_at` are the fixture's, not the
             * seeder's: the queue is ordered by when a ticket was last touched,
             * and a board where everything moved in the same second is not a
             * queue anybody can review.
             */
            $ticket->forceFill([
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ])->saveQuietly();

            $this->thread($ticket, $row, $employees);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  \Illuminate\Support\Collection<string, Employee>  $employees
     */
    protected function thread(Ticket $ticket, array $row, $employees): void
    {
        // Re-seeded whole: the fixture is the thread, and a second run must not
        // append it again.
        $ticket->comments()->delete();

        $accounts = User::pluck('id', 'name');

        foreach (DemoTickets::commentsFor($row, DemoTickets::AUDIENCE_STAFF) as $comment) {
            $created = TicketComment::create([
                'ticket_id' => $ticket->id,
                // Matched by name where an account exists, and null where the
                // author is a client contact the demo names but no account
                // belongs to. The label carries the name either way.
                'author_id' => $accounts[$comment['author']] ?? null,
                'author_label' => $comment['author'],
                'author_role' => $comment['role'],
                'body' => $comment['body'],
                'visibility' => $comment['visibility'],
            ]);

            $created->forceFill([
                'created_at' => $comment['at'],
                'updated_at' => $comment['at'],
            ])->saveQuietly();
        }
    }
}
