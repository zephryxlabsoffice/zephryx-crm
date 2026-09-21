<?php

namespace App\Support\Demo;

use Illuminate\Support\Collection;

/**
 * Sample client rows for reviewing the Clients page before the database exists.
 *
 * Local + debug only, like the login page's `?preview=` states. Everywhere else
 * this returns nothing, so a deployed site shows real (currently empty) data
 * and its empty states — never invented clients, projects or rupee figures.
 *
 * Deleted when the Clients module gets its migration and model. Nothing in the
 * controller or the views depends on it: they consume plain arrays either way.
 */
class DemoClients
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return collect([
            ['name' => 'DGL International School', 'industry' => 'Education',     'project' => 'Website Redesign',            'status' => 'active',   'country' => 'IN', 'currency' => 'INR', 'payment' => 'paid',    'activity' => '2 hrs ago'],
            ['name' => 'TechNova Solutions',       'industry' => 'Software',      'project' => 'CRM Setup',                   'status' => 'active',   'country' => 'IN', 'currency' => 'INR', 'payment' => 'due',     'activity' => 'Yesterday'],
            ['name' => 'ABC Pvt Ltd',              'industry' => 'Corporate',     'project' => 'Branding Kit',                'status' => 'active',   'country' => 'IN', 'currency' => 'INR', 'payment' => 'partial', 'activity' => '3 days ago'],
            ['name' => 'GreenLeaf Foods',          'industry' => 'Food Industry', 'project' => 'Social Media Marketing',      'status' => 'active',   'country' => 'IN', 'currency' => 'INR', 'payment' => 'paid',    'activity' => '4 days ago'],
            ['name' => 'Innovate Hub',             'industry' => 'Technology',    'project' => 'Mobile App',                  'status' => 'active',   'country' => 'US', 'currency' => 'USD', 'payment' => 'due',     'activity' => '5 days ago'],
            ['name' => 'Bright Future Academy',    'industry' => 'Education',     'project' => 'Learning Management System',  'status' => 'active',   'country' => 'IN', 'currency' => 'INR', 'payment' => 'paid',    'activity' => '1 week ago'],
            ['name' => 'MediCare Services',        'industry' => 'Healthcare',    'project' => 'Website Development',         'status' => 'inactive', 'country' => 'IN', 'currency' => 'INR', 'payment' => 'paid',    'activity' => '1 week ago'],
            ['name' => 'Urban Nest Interiors',     'industry' => 'Interior',      'project' => 'E-commerce Storefront',       'status' => 'active',   'country' => 'IN', 'currency' => 'INR', 'payment' => 'partial', 'activity' => '2 weeks ago'],
            ['name' => 'Sunrise Logistics',        'industry' => 'Logistics',     'project' => 'Fleet Tracking Dashboard',    'status' => 'active',   'country' => 'IN', 'currency' => 'INR', 'payment' => 'due',     'activity' => '2 weeks ago'],
            ['name' => 'Kolkata Craft Collective', 'industry' => 'Retail',        'project' => 'Brand Photography',           'status' => 'inactive', 'country' => 'IN', 'currency' => 'INR', 'payment' => 'paid',    'activity' => '3 weeks ago'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function stats(): array
    {
        $clients = self::all();

        if ($clients->isEmpty()) {
            return ['total' => 0, 'active' => 0, 'receivable' => 0, 'overdue' => 0, 'tickets' => 0, 'unresolved' => 0];
        }

        return [
            'total' => $clients->count(),
            'active' => $clients->where('status', 'active')->count(),
            // Invoices does not exist yet; these stand in for its totals.
            'receivable' => 185000,
            'overdue' => 4,
            'tickets' => 12,
            'unresolved' => 3,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function activity(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return [
            ['client' => 'DGL International School', 'what' => 'shared a feedback document', 'when' => '2 hrs ago',  'tone' => 'tone-accent', 'icon' => 'invoices'],
            ['client' => 'TechNova Solutions',       'what' => 'requested a revision',       'when' => 'Yesterday',  'tone' => 'tone-alt',    'icon' => 'tickets'],
            ['client' => 'ABC Pvt Ltd',              'what' => 'approved a proposal',        'when' => '3 days ago', 'tone' => '',            'icon' => 'tasks'],
            ['client' => 'GreenLeaf Foods',          'what' => 'made a payment',             'when' => '4 days ago', 'tone' => 'tone-warn',   'icon' => 'salary'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function meetings(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return [
            ['client' => 'DGL International School', 'when' => '24 May 2026 · 4:00 PM',  'platform' => 'Google Meet'],
            ['client' => 'TechNova Solutions',       'when' => '28 May 2026 · 11:00 AM', 'platform' => 'Zoom'],
            ['client' => 'ABC Pvt Ltd',              'when' => '30 May 2026 · 3:30 PM',  'platform' => 'Google Meet'],
        ];
    }
}
