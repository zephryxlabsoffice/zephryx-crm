<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One of the company's registration IDs, shown on every verify.zephryxlabs.in
 * page (owner, 2026-10-29): a type, its number, and an optional document.
 * Added one at a time from the verify dashboard, as the company picks them up.
 */
class CompanyRegistration extends Model
{
    protected $fillable = ['type', 'number', 'document_path', 'sort_order'];

    /**
     * The dropdown. Indian company, tax, labour and banking registrations,
     * with "Other" for anything not listed.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'cin' => 'Certificate of Incorporation (CIN)',
        'llpin' => 'LLP Identification Number (LLPIN)',
        'udyam' => 'Udyam Registration (MSME)',
        'startup_india' => 'Startup India (DPIIT Recognition)',
        'gstin' => 'GST Registration (GSTIN)',
        'pan' => 'Company PAN',
        'tan' => 'TAN',
        'iec' => 'Import Export Code (IEC)',
        'shops' => 'Shops & Establishment Registration',
        'trade_licence' => 'Trade Licence',
        'professional_tax' => 'Professional Tax Registration',
        'epfo' => 'EPFO Establishment Code',
        'esic' => 'ESIC Registration',
        'lei' => 'Legal Entity Identifier (LEI)',
        'duns' => 'D-U-N-S Number',
        'fssai' => 'FSSAI Licence',
        'iso' => 'ISO Certification',
        'trademark' => 'Trademark Registration',
        'bank_ifsc' => 'Bank IFSC',
        'other' => 'Other',
    ];

    public function label(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
