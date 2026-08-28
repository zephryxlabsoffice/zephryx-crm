<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A page's own content, with the app shell stripped off.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * WHY THIS EXISTS
     *
     * The shell is on every page: sidebar, topbar, and — since Announcements
     * landed (2026-08-28) — the notification bell, which links to whatever the
     * viewer has been notified about. A notification reading "Website Redesign
     * is due in 5 days" puts `/projects/WD-2024-001` into the HTML of every
     * page in the application.
     *
     * So `assertDontSee('/projects/WD-2024-001')` on a filtered project list
     * stopped meaning "this row is filtered out" and started meaning "this id
     * appears nowhere in the document", which is a different and much weaker
     * claim. Assertions about what a PAGE shows should be made against the
     * page.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function pageBody(string $url): string
    {
        $html = $this->get($url)->getContent();

        return $this->bodyOf($html, $url);
    }

    /**
     * The same, for HTML already fetched.
     */
    protected function bodyOf(string $html, string $context = ''): string
    {
        $start = strpos($html, '<main class="page"');
        $end = strrpos($html, '</main>');

        $this->assertNotFalse($start, "no page body found".($context ? " in {$context}" : ''));
        $this->assertNotFalse($end, "no page body end found".($context ? " in {$context}" : ''));

        return substr($html, $start, $end - $start);
    }
}
