<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response hardening headers (foundation spec §6).
 *
 * Registered globally in bootstrap/app.php so that every page built from here
 * on inherits the policy automatically rather than needing one added by hand.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
        );

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicyFor($response));

        // HSTS is only meaningful over TLS, and asserting it from a local
        // http:// dev server would pin the developer's browser to https.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    /**
     * The app's own policy, or a locked one for a rendered document.
     *
     * App\Support\Documents\DocumentStore::viewInline() renders an UPLOADED
     * PDF in the browser — a payslip, an identity scan — which is content
     * this application did not produce and cannot vouch for the inside of.
     * That page still has to load at our own origin, so it cannot get
     * "no `default-src`" for free; it gets a policy that denies everything a
     * PDF viewer could otherwise be talked into doing (scripts, network,
     * embedded frames, form submission) while the plain HTML pages around it
     * keep the ordinary same-origin policy below.
     *
     * Checked by Content-Type rather than by route, so a future route
     * serving a PDF inherits this automatically instead of somebody having to
     * remember it exists.
     */
    protected function contentSecurityPolicyFor(Response $response): string
    {
        if (str_starts_with((string) $response->headers->get('Content-Type'), 'application/pdf')) {
            return $this->documentContentSecurityPolicy();
        }

        return $this->contentSecurityPolicy();
    }

    /**
     * Nothing, not even same-origin (plan doc, "In-browser viewing is ours,
     * not Drive's" — point 4: "a CSP that allows the document nothing — no
     * scripts, no network"). This is the condition
     * App\Support\Documents\DocumentStore::viewInline()'s own header comment
     * points back to.
     */
    protected function documentContentSecurityPolicy(): string
    {
        return collect([
            'default-src' => ["'none'"],
            'script-src' => ["'none'"],
            'style-src' => ["'none'"],
            'img-src' => ["'none'"],
            'font-src' => ["'none'"],
            'connect-src' => ["'none'"],
            'media-src' => ["'none'"],
            'object-src' => ["'none'"],
            'frame-src' => ["'none'"],
            'form-action' => ["'none'"],
            'base-uri' => ["'none'"],
            'frame-ancestors' => ["'none'"],
        ])
            ->map(fn (array $values, string $name) => $name.' '.implode(' ', $values))
            ->implode('; ');
    }

    /**
     * Everything is same-origin: fonts, styles and scripts are all built into
     * public/build by Vite (§6 — no third-party CDN assets in production).
     *
     * The Vite dev server is the one exception, and it is granted only when the
     * application is running locally in debug mode.
     */
    protected function contentSecurityPolicy(): string
    {
        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'font-src' => ["'self'", 'data:'],
            'img-src' => ["'self'", 'data:'],
            'script-src' => ["'self'"],
            'style-src' => ["'self'"],
            'connect-src' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'object-src' => ["'none'"],
        ];

        if ($this->allowsViteDevServer()) {
            $dev = ['http://localhost:5173', 'ws://localhost:5173', 'http://127.0.0.1:5173', 'ws://127.0.0.1:5173'];

            $directives['script-src'][] = "'unsafe-inline'";
            $directives['script-src'] = array_merge($directives['script-src'], $dev);
            $directives['style-src'][] = "'unsafe-inline'";
            $directives['style-src'] = array_merge($directives['style-src'], $dev);
            $directives['connect-src'] = array_merge($directives['connect-src'], $dev);
        }

        return collect($directives)
            ->map(fn (array $values, string $name) => $name.' '.implode(' ', $values))
            ->implode('; ');
    }

    protected function allowsViteDevServer(): bool
    {
        return app()->environment('local')
            && config('app.debug')
            && is_file(public_path('hot'));
    }
}
