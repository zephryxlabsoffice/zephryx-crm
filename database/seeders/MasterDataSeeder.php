<?php

namespace Database\Seeders;

use App\Models\MasterDataItem;
use App\Support\LeavePolicy;
use Illuminate\Database\Seeder;

/**
 * Master data, and this one DOES run in production.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY THESE ARE SEEDED RATHER THAN TYPED IN AFTER LAUNCH
 *
 * Unlike the demo people, these rows are not sample content — the application
 * does not function without them. An employee form with an empty department
 * list cannot create an employee, and leave types are referenced by policy code
 * that already exists. Shipping an empty database would mean an administrator
 * typing twenty rows correctly before anybody could be added, with the leave
 * types having to match `LeavePolicy` exactly or the balances would not resolve.
 *
 * They are starting points, not fixtures: every one of them can be renamed,
 * deactivated or added to through Admin Panel → Master Data.
 *
 * Idempotent on (list, code), so re-running after a deploy adds what is new and
 * leaves every existing row — including ones somebody renamed — alone.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->departments();
        $this->designations();
        $this->leaveTypes();
        $this->documentTypes();
        $this->ticketCategories();
        $this->announcementCategories();
    }

    protected function departments(): void
    {
        $this->put(MasterDataItem::DEPARTMENTS, [
            ['Design', 'DES'],
            ['Development', 'DEV'],
            ['Marketing', 'MKT'],
            ['HR', 'HR'],
            ['Finance', 'FIN'],
            ['Support', 'SUP'],
            ['Sales', 'SAL'],
        ]);
    }

    protected function designations(): void
    {
        $this->put(MasterDataItem::DESIGNATIONS, [
            ['UI/UX Designer', 'UIUX'],
            ['Motion Designer', 'MOTN'],
            ['Frontend Developer', 'FEDV'],
            ['Backend Developer', 'BEDV'],
            ['Digital Marketer', 'DMKT'],
            ['Marketing Executive', 'MEXE'],
            ['HR Executive', 'HREX'],
            ['Accountant', 'ACCT'],
            ['Support Specialist', 'SUPS'],
            ['Sales Executive', 'SLEX'],
        ]);
    }

    /**
     * Taken from LeavePolicy rather than retyped.
     *
     * The policy code already decides which types exist and what they are
     * called; a second hand-written list here would be a second source of truth
     * for the same fact, and the day they disagreed a leave request would be
     * filed against a type no balance could be computed for.
     */
    protected function leaveTypes(): void
    {
        $rows = [];

        foreach (LeavePolicy::types() as $key => $type) {
            $rows[] = [$type['label'], strtoupper($key)];
        }

        $this->put(MasterDataItem::LEAVE_TYPES, $rows);
    }

    protected function documentTypes(): void
    {
        $this->put(MasterDataItem::DOCUMENT_TYPES, [
            ['PAN card', 'PAN'],
            ['Aadhaar', 'AADH'],
            ['Offer letter', 'OFFER'],
            ['Educational certificate', 'EDU'],
            ['Previous payslip', 'PPAY'],
        ]);
    }

    /**
     * Joined master data 2026-09-21 — the same seven values
     * config('tickets.categories') carried before the move.
     */
    protected function ticketCategories(): void
    {
        $this->put(MasterDataItem::TICKET_CATEGORIES, [
            ['Access', 'ACCESS'],
            ['Billing', 'BILLING'],
            ['Bug', 'BUG'],
            ['Network', 'NETWORK'],
            ['Performance', 'PERFORMANCE'],
            ['Reporting', 'REPORTING'],
            ['Feature request', 'FEATURE'],
        ]);
    }

    /**
     * Joined master data 2026-09-21. The CODE is what
     * AnnouncementPresenter::categories() lowercases back to the key the rest
     * of the application already reads — 'HOLIDAY' has to stay 'HOLIDAY',
     * because App\Support\Holidays and AnnouncementPresenter::HOLIDAY are
     * written against the lowercase string 'holiday', and 'MILESTONE'
     * because `isAuthorable()` checks for the literal string 'milestone'.
     */
    protected function announcementCategories(): void
    {
        $this->put(MasterDataItem::ANNOUNCEMENT_CATEGORIES, [
            ['Policy', 'POLICY'],
            ['HR', 'HR'],
            ['Holiday', 'HOLIDAY'],
            ['Event', 'EVENT'],
            ['Training', 'TRAINING'],
            ['IT', 'IT'],
            ['Milestone', 'MILESTONE'],
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     */
    protected function put(string $list, array $rows): void
    {
        /*
         * firstOrCreate, not updateOrCreate. The name and the order are the
         * starting values only — a seeder that wrote them every run would undo
         * a rename on the next deploy, silently, and the administrator who
         * renamed the department would find it back the way it was with nothing
         * to explain why.
         */
        foreach ($rows as $order => [$name, $code]) {
            MasterDataItem::firstOrCreate(
                ['list' => $list, 'code' => $code],
                ['name' => $name, 'sort_order' => $order],
            );
        }
    }
}
