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
 * THE AMOUNT IS TYPED, LIKE A PAYSLIP'S NET FIGURE — NOT SUMMED FROM LINES
 *
 * Decided 2026-09-11, built 2026-09-21: "invoices are uploaded like payslips,
 * not generated." `amount_minor` is a column now, the same way
 * `SalaryRecord::net_minor` always was — HR types a figure once, and nothing
 * above it treats that as less trustworthy than three fields multiplied
 * together would have been.
 *
 * `status()` is still derived from the dates, the amount and the payments —
 * that part of the original design was never about where the total came
 * from. Nobody sets an invoice to paid; they record the payment that makes
 * it paid.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class Invoice extends Model
{
    protected $fillable = [
        'number', 'client_id', 'project_id', 'currency', 'amount_minor',
        'invoice_date', 'due_date', 'sent_at', 'cancelled_at', 'cancellation_reason', 'notes',
        'document_path', 'document_name', 'document_bytes', 'document_added_at', 'document_added_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'document_bytes' => 'integer',
            'document_added_at' => 'datetime',
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
     * @return HasMany<InvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('received_on');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function documentAddedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'document_added_by');
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function total(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
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

    public function hasDocument(): bool
    {
        return $this->document_path !== null;
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
        $payments = $this->payments->map(fn (InvoicePayment $p) => $p->toRecordArray($this->currency))->all();

        $total = $this->total();
        $paid = $this->paid();

        $invoice = [
            'id' => $this->number,
            'client' => $this->client?->name,
            'client_reference' => $this->client?->reference,
            'project' => $this->project?->reference,
            'currency' => $this->currency,
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
            'has_document' => $this->hasDocument(),
            'document_path' => $this->document_path,
            'document_name' => $this->document_name,
            'document_bytes' => $this->document_bytes,
            'document_added_at' => $this->document_added_at?->toDateString(),
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
