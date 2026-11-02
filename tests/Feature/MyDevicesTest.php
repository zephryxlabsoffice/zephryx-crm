<?php

namespace Tests\Feature;

use App\Mail\NewDeviceMail;
use App\Mail\OneTimeCodeMail;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\Auth\DeviceDescription;
use App\Support\Auth\RememberMe;
use App\Support\Auth\TrustedDevices;
use App\Support\Auth\UserDevices;
use App\Support\Realm;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * My Devices and the new-device email (owner, 2026-11-02).
 */
class MyDevicesTest extends TestCase
{
    protected const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    protected const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        TrustProxies::flushState();

        parent::tearDown();
    }

    /**
     * Sign in through the whole form and code on a browser with these cookies,
     * returning the response of the code step.
     */
    protected function signInWithCode(User $user, array $cookies = [], string $agent = self::CHROME_WINDOWS)
    {
        Mail::fake();

        $this->withHeader('User-Agent', $agent);
        $this->defaultCookies = $cookies;

        $this->post('/login', ['identifier' => $user->email, 'password' => 'password']);

        $code = null;
        Mail::assertSent(OneTimeCodeMail::class, function (OneTimeCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $this->post('/login/verify', ['code' => str_split($code)]);
    }

    protected function deviceFor(User $user, array $attributes = []): UserDevice
    {
        return UserDevice::create($attributes + [
            'user_id' => $user->id,
            'browser_hash' => hash('sha256', 'other-browser'),
            'device_name' => 'Safari on iPhone',
            'location' => 'Mumbai, India',
            'ip_address' => '203.0.113.9',
            'sign_in_token' => 'other-token',
            'signed_in_at' => now()->subHour(),
            'last_seen_at' => now()->subHour(),
        ]);
    }

    /**
     * After an upload and before Admin -> Updates, the code is new and the
     * database is old. Signing in and reaching that page must still work, or
     * the update that would fix everything can never be applied.
     */
    public function test_an_admin_can_reach_updates_before_the_migration_is_applied(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        $this->assertFalse(Schema::hasTable('user_devices'));

        $admin = User::factory()->create(['account_type' => Realm::ADMIN]);

        $this->signInWithCode($admin)->assertRedirect();
        $this->assertAuthenticatedAs($admin);

        $this->get('/admin/updates')->assertOk()->assertSee('Create user devices');
        $this->get('/admin/mydevices')->assertOk();

        $this->post('/admin/updates')->assertRedirect('/admin/updates');
        $this->assertTrue(Schema::hasTable('user_devices'));

        $this->get('/admin/mydevices')->assertOk()->assertSee('This device');
    }

    public function test_signing_out_works_before_the_migration_is_applied(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);

        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_first_device_on_an_account_sends_no_email(): void
    {
        $user = User::factory()->create();

        $this->signInWithCode($user);

        Mail::assertNotSent(NewDeviceMail::class);
        $this->assertSame(1, UserDevice::where('user_id', $user->id)->count());
    }

    public function test_a_new_device_emails_its_name_and_location(): void
    {
        $user = User::factory()->create();
        $this->deviceFor($user);

        TrustProxies::at('127.0.0.1');
        $this->withHeaders(['CF-IPCity' => 'Kolkata', 'CF-Region' => 'West Bengal', 'CF-IPCountry' => 'IN']);

        $this->signInWithCode($user);

        Mail::assertSent(NewDeviceMail::class, function (NewDeviceMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->deviceName === 'Chrome on Windows'
                && $mail->location === 'Kolkata, West Bengal, India';
        });
    }

    public function test_the_same_browser_signing_in_again_is_not_new(): void
    {
        $user = User::factory()->create();
        $this->deviceFor($user);

        $first = $this->signInWithCode($user);
        Mail::assertSent(NewDeviceMail::class);

        $browser = $first->getCookie(UserDevices::COOKIE)->getValue();
        $this->post('/logout');

        $this->signInWithCode($user, [UserDevices::COOKIE => $browser]);

        Mail::assertNotSent(NewDeviceMail::class);
    }

    public function test_a_failed_email_does_not_stop_the_sign_in(): void
    {
        $user = User::factory()->create();
        $this->deviceFor($user);

        $this->signInWithCode($user);
        $this->assertAuthenticatedAs($user);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP is down'));

        $device = UserDevice::where('user_id', $user->id)->firstOrFail();

        app(UserDevices::class)->announce($user, $device->forceFill(['signed_in_at' => now()]), request());

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_open_session_is_adopted_without_an_email(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->deviceFor($user);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::CHROME_WINDOWS)
            ->get('/mydevices')
            ->assertOk()
            ->assertCookie(UserDevices::COOKIE)
            ->assertSee('Chrome on Windows')
            ->assertSee('This device');

        Mail::assertNothingSent();
    }

    public function test_the_page_lists_only_this_accounts_live_devices(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->deviceFor($user);
        $this->deviceFor($user, ['browser_hash' => 'b', 'device_name' => 'Signed Out Phone', 'sign_in_token' => null]);
        $this->deviceFor($user, ['browser_hash' => 'c', 'device_name' => 'Expired Tablet', 'sign_in_token' => 't', 'signed_in_at' => now()->subDays(8)]);
        $this->deviceFor($other, ['device_name' => 'Somebody Elses Laptop']);

        $this->actingAs($user)->get('/mydevices')
            ->assertOk()
            ->assertSee('Safari on iPhone')
            ->assertSee('Mumbai, India')
            ->assertSee('Sign Out')
            ->assertSee('Reset Password')
            ->assertDontSee('Signed Out Phone')
            ->assertDontSee('Expired Tablet')
            ->assertDontSee('Somebody Elses Laptop');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function realms(): array
    {
        return [
            'staff' => [Realm::STAFF, '/mydevices'],
            'client' => [Realm::CLIENT, '/client/mydevices'],
            'admin' => [Realm::ADMIN, '/admin/mydevices'],
        ];
    }

    #[DataProvider('realms')]
    public function test_each_realm_has_the_page_and_only_its_own(string $realm, string $path): void
    {
        $user = User::factory()->create(['account_type' => $realm]);

        $this->get($path)->assertRedirect();

        $this->actingAs($user)->get($path)->assertOk()->assertSee('My Devices');

        foreach (self::realms() as [$otherRealm, $otherPath]) {
            if ($otherRealm !== $realm) {
                $this->assertNotSame(200, $this->get($otherPath)->getStatusCode(), $otherPath);
            }
        }
    }

    public function test_signing_out_a_device_ends_its_session_and_its_trust(): void
    {
        $user = User::factory()->create();

        // The phone: a real sign-in with a code, so it holds a trust row.
        $phone = $this->signInWithCode($user, [], self::SAFARI_IPHONE);
        $phoneDevice = UserDevice::where('user_id', $user->id)->firstOrFail();
        $phoneSession = session()->all();

        $this->assertSame(1, DB::table('trusted_devices')
            ->where('user_device_id', $phoneDevice->id)->whereNull('revoked_at')->count());
        $this->assertNotNull($phone->getCookie(TrustedDevices::COOKIE, false));

        // Pressed from another browser on the same account.
        session()->flush();
        $this->actingAs($user)
            ->withHeader('User-Agent', self::CHROME_WINDOWS)
            ->post('/mydevices/'.$phoneDevice->id.'/sign-out')
            ->assertRedirect()
            ->assertSessionHas('status', 'Safari on iPhone has been signed out.');

        $this->assertNull($phoneDevice->fresh()->sign_in_token);
        $this->assertSame(0, DB::table('trusted_devices')
            ->where('user_device_id', $phoneDevice->id)->whereNull('revoked_at')->count());

        // The phone's next request.
        session()->flush();
        session($phoneSession);
        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This device was signed out from your account. Sign in again to continue.');
        $this->assertGuest();
    }

    public function test_a_remember_me_cookie_cannot_bring_a_signed_out_device_back(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        $device = $this->deviceFor($user);
        $trust = app(TrustedDevices::class)->trust($user, $request, $device->id);
        $remember = app(RememberMe::class)->issue($user, $request);

        app(UserDevices::class)->signOut($device);

        $this->withCookies([
            TrustedDevices::COOKIE => $trust->getValue(),
            RememberMe::COOKIE => $remember->getValue(),
        ])->get('/dashboard')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_somebody_elses_device_cannot_be_signed_out(): void
    {
        $user = User::factory()->create();
        $device = $this->deviceFor(User::factory()->create());

        $this->actingAs($user)->post('/mydevices/'.$device->id.'/sign-out')->assertNotFound();

        $this->assertNotNull($device->fresh()->sign_in_token);
    }

    public function test_reset_password_emails_a_link_and_signs_nothing_out(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $device = $this->deviceFor($user);

        $this->actingAs($user)->post('/mydevices/reset-password')->assertRedirect();

        Mail::assertSent(PasswordResetMail::class, fn (PasswordResetMail $mail) => $mail->hasTo($user->email));
        $this->assertNotNull($device->fresh()->sign_in_token);
        $this->assertAuthenticatedAs($user);
    }

    public function test_applying_a_reset_signs_out_every_device(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $device = $this->deviceFor($user);

        $this->actingAs($user)->post('/mydevices/reset-password');

        $url = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$url) {
            $url = $mail->url;

            return true;
        });

        $this->post('/reset-password', [
            'token' => basename($url),
            'password' => 'a-brand-new-long-passphrase',
            'password_confirmation' => 'a-brand-new-long-passphrase',
        ])->assertRedirect(route('login'));

        $this->assertNull($device->fresh()->sign_in_token);
    }

    public function test_changing_the_password_keeps_this_device_and_ends_the_others(): void
    {
        $user = User::factory()->create();
        $other = $this->deviceFor($user);

        $this->actingAs($user)->get('/mydevices')->assertOk();

        $this->post('/profile/password', [
            'current_password' => 'password',
            'password' => 'a-brand-new-long-passphrase',
            'password_confirmation' => 'a-brand-new-long-passphrase',
        ])->assertRedirect(route('profile.password'));

        $this->assertNull($other->fresh()->sign_in_token);
        $this->get('/mydevices')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_logging_out_takes_this_device_off_the_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/mydevices')->assertOk();
        $this->post('/logout');

        $this->assertNull(UserDevice::where('user_id', $user->id)->value('sign_in_token'));
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function agents(): array
    {
        return [
            'chrome on windows' => [self::CHROME_WINDOWS, 'Chrome on Windows'],
            'safari on iphone' => [self::SAFARI_IPHONE, 'Safari on iPhone'],
            'edge' => ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/130.0 Safari/537.36 Edg/130.0', 'Edge on Windows'],
            'firefox on mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14.0; rv:130.0) Gecko/20100101 Firefox/130.0', 'Firefox on Mac'],
            'android with a model' => ['Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36', 'Chrome on Android (SM-S918B)'],
            'android hiding it' => ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36', 'Chrome on Android'],
            'nothing' => [null, 'Unknown device'],
        ];
    }

    #[DataProvider('agents')]
    public function test_device_names(?string $agent, string $expected): void
    {
        $this->assertSame($expected, DeviceDescription::name($agent));
    }

    public function test_location_headers_count_only_from_a_trusted_proxy(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_CF_IPCOUNTRY' => 'IN',
            'HTTP_CF_IPCITY' => 'Kolkata',
        ]);

        $this->assertNull(DeviceDescription::location($request));

        Request::setTrustedProxies(['198.51.100.7'], Request::HEADER_X_FORWARDED_FOR);

        $this->assertSame('Kolkata, India', DeviceDescription::location($request));
    }

    public function test_without_cloudflare_headers_the_address_is_looked_up_once(): void
    {
        Http::fake(['api.db-ip.com/*' => Http::response([
            'city' => 'Kolkata', 'stateProv' => 'West Bengal', 'countryName' => 'India',
        ])]);

        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '8.8.8.8']);

        $this->assertSame('Kolkata, West Bengal, India', DeviceDescription::location($request));
        $this->assertSame('Kolkata, West Bengal, India', DeviceDescription::location($request));

        Http::assertSentCount(1);
    }

    public function test_a_failed_lookup_reads_unknown_and_is_not_retried_on_every_page(): void
    {
        Http::fake(['api.db-ip.com/*' => Http::failedConnection()]);

        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '8.8.8.8']);

        $this->assertNull(DeviceDescription::location($request));
        $this->assertNull(DeviceDescription::location($request));

        Http::assertSentCount(1);
    }

    public function test_a_private_address_is_never_sent_to_the_lookup(): void
    {
        Http::fake();

        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '192.168.1.20']);

        $this->assertNull(DeviceDescription::location($request));

        Http::assertNothingSent();
    }

    public function test_the_new_device_email_renders(): void
    {
        $html = (new NewDeviceMail('Asha', 'Chrome on Windows', 'Location unknown', '203.0.113.9', '2 Nov 2026, 4:30 PM', 'https://crm.test/mydevices', 'https://crm.test/not-me'))->render();

        $this->assertStringContainsString('Chrome on Windows', $html);
        $this->assertStringContainsString('Location unknown', $html);
        $this->assertStringContainsString('https://crm.test/not-me', $html);
    }
}
