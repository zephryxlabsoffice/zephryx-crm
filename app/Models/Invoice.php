<?php

namespace App\Models;

use App\Support\InvoicePresenter;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An invoice.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE TOTAL AND THE STATUS ARE METHODS, NOT COLUMNS
 *
 * `total()` sums the lines and `paid()` sums the payments, every time they are
 * asked. That is deliberately not cached anywhere: a stored total is a number
 * that can disagree with the lines that produced it, and the first time it does
 * the argument is with a client about money.
 *
 * `status()` is derived from the dates, the lines and the payments. Nobody sets
 * an invoice to paid — they record the payment that makes it paid.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class Invoice extends Model
{
    protected $fillable = [
        'number', 'client_id', 'project_id', 'currency',
        'invoice_date', 'due_date', 'sent_at', 'cancelled_at', 'cancellation_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<InvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('received_on');
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * The sum of the lines.
     */
    public function total(): Money
    {
        return $this->lines->reduce(
            fn (Money $carry, InvoiceLine $line) => $carry->plus($line->amount($this->currency)),
            Money::zero($this->currency),
        );
    }

    /**
     * The sum of the payments.
     */
    public function paid(): Money
    {
        return $this->payments->reduce(
            fn (Money $carry, InvoicePayment $payment) => $carry->plus($payment->amount($this->currency)),
            Money::zero($this->currency),
        );
    }

    public function balance(): Money
    {
        return $this->total()->minus($this->paid());
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Where this invoice stands, through the one place that decides.
     */
    public function status(): string
    {
        return InvoicePresenter::statusOf($this->toRecordArray());
    }

    /**
     * The row shape InvoicePresenter and the views read.
     *
     * @return array<string, mixed>
     */
    public function toRecordArray(): array
    {
        $lines = $this->lines->map(fn (InvoiceLine $l) => $l->toRecordArray($this->currency))->all();
        $payments = $this->payments->map(fn (InvoicePayment $p) => $p->toRecordArray($this->currency))->all();

        $total = $this->total();
        $paid = $this->paid();

        $invoice = [
            'id' => $this->number,
            'client' => $this->client?->name,
            'client_reference' => $this->client?->reference,
            'project' => $this->project?->reference,
            'currency' => $this->currency,
            'lines' => $lines,
            'payments' => $payments,
            'total' => $total,
            'paid' => $paid,
            'balance' => $total->minus($paid),
            'invoice_date' => $this->invoice_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            // The two stored facts the presenter reads by these names.
            'issued' => $this->isSent(),
            'cancelled' => $this->isCancelled(),
            'cancellation_reason' => $this->cancellation_reason,
            'notes' => $this->notes,
        ];

        $invoice['status'] = InvoicePresenter::statusOf($invoice);

        return $invoice;
    }

    /**
     * Invoices somebody still owes money on.
     *
     * Not a status filter, because status is derived: this is the SQL
     * approximation — sent, not cancelled — and the exact answer comes from the
     * presenter once the rows are loaded.
     *
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeIssued(Builder $query): Builder
    {
        return $query->whereNotNull('sent_at')->whereNull('cancelled_at');
    }
}
