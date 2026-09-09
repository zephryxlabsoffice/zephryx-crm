<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money that arrived against an invoice.
 *
 * A row per payment rather than a running `amount_paid` on the invoice, because
 * a payment is a real event: it has a date, a method and a bank reference, and
 * reconciling one against a statement needs all three. The invoice's paid
 * figure is the sum of these.
 */
class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id', 'amount_minor', 'received_on', 'method', 'reference', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'received_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recorded_by');
    }

    public function amount(string $currency): Money
    {
        return Money::of($this->amount_minor, $currency);
    }

    /**
     * @return array<string, mixed>
     */
    public function toRecordArray(string $currency): array
    {
        return [
            'amount' => $this->amount_minor,
            'amount_money' => $this->amount($currency),
            'received_on' => $this->received_on->toDateString(),
            'method' => $this->method,
            'reference' => $this->reference,
        ];
    }
}
