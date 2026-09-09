<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on an invoice.
 *
 * The currency lives on the invoice, not here: a line in a different currency
 * from the invoice it is on is not a state anybody means, and storing it twice
 * is storing two chances to disagree.
 */
class InvoiceLine extends Model
{
    protected $fillable = ['invoice_id', 'description', 'quantity', 'unit_price_minor', 'position'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function unitPrice(string $currency): Money
    {
        return Money::of($this->unit_price_minor, $currency);
    }

    /**
     * Quantity times unit price, in integer minor units throughout.
     */
    public function amount(string $currency): Money
    {
        return $this->unitPrice($currency)->times($this->quantity);
    }

    /**
     * @return array<string, mixed>
     */
    public function toRecordArray(string $currency): array
    {
        return [
            'description' => $this->description,
            'qty' => $this->quantity,
            'unit' => $this->unit_price_minor,
            'unit_price' => $this->unitPrice($currency),
            'amount' => $this->amount($currency),
        ];
    }
}
