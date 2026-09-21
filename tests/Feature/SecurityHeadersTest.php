<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Response hardening — foundation spec §6.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_every_response_carries_the_hardening_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $response->assertHeaderMissing('X-Powered-By');
    }

    public function test_the_content_security_policy_is_locked_to_our_own_origin(): void
    {
        $policy = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);

        // The page renders no inline script, so the policy never has to allow one.
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringNotContainsString("'unsafe-inline'", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
    }

    public function test_an_application_pdf_response_gets_a_locked_content_security_policy_instead(): void
    {
        /*
         * App\Support\Documents\DocumentStore::viewInline() renders an
         * uploaded PDF in the browser. That response must not inherit the
         * ordinary same-origin policy above — see SalaryWritesTest and
         * ProfilePageTest for it exercised through an actual viewer route;
         * this proves the middleware's own rule directly, against a response
         * it did not have to build a whole payslip to produce.
         */
        $response = (new SecurityHeaders)->handle(
            Request::create('/whatever'),
            fn () => response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
        );

        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'none'", $policy);
        $this->assertStringContainsString("script-src 'none'", $policy);
        $this->assertStringContainsString("connect-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringNotContainsString("'self'", $policy);
    }

    public function test_hsts_is_only_asserted_over_tls(): void
    {
        $this->get('http://localhost/')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
