<?php

namespace Tests\Unit;

use App\Support\Documents\DocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * DocumentStore's two drivers.
 *
 * The one property that matters: `putBytes()` (photos) never leaves the
 * local disk and `put()` (uploads) never touches it, whatever module calls
 * them — see the class header on DocumentStore for why the METHOD is the
 * routing rather than a flag anybody has to remember to pass.
 */
class DocumentStoreTest extends TestCase
{
    protected function fakePdf(string $name = 'payslip.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 40, 'application/pdf');
    }

    /* ══════════════════════════════════════════════════════════════════════
       put() — ALWAYS DRIVE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_put_stores_to_drive_and_returns_a_drive_prefixed_path(): void
    {
        $this->connectGoogleDrive();
        $this->fakeDriveUpload();

        $stored = (new DocumentStore)->put('payslips/2026-09', $this->fakePdf());

        $this->assertStringStartsWith('drive:', $stored['path']);
        $this->assertSame('payslip.pdf', $stored['name']);
        $this->assertGreaterThan(0, $stored['bytes']);

        // The module folder is looked up (or created) by name — "payslips",
        // the folder per module the plan doc calls for, not the whole
        // "payslips/2026-09" path, which Drive has no native concept of.
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), 'drive/v3/files')
            && str_contains(urldecode($request->url()), "name = 'payslips'"));
    }

    public function test_put_refuses_a_disallowed_extension_before_ever_reaching_drive(): void
    {
        $this->connectGoogleDrive();
        // Deliberately no Http::fake() — reaching the network here is itself
        // the failure this test exists to catch.

        $exe = UploadedFile::fake()->create('resume.exe', 10);

        $this->expectException(\InvalidArgumentException::class);

        (new DocumentStore)->put('employees/1/documents', $exe);
    }

    public function test_put_throws_a_clear_message_when_drive_is_not_connected(): void
    {
        // No connectGoogleDrive() call — the singleton row does not exist.

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Drive is not connected');

        (new DocumentStore)->put('payslips/2026-09', $this->fakePdf());
    }

    /* ══════════════════════════════════════════════════════════════════════
       putBytes() — ALWAYS LOCAL, REGARDLESS OF DRIVE STATE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_put_bytes_never_touches_drive_even_when_disconnected(): void
    {
        // No connectGoogleDrive() — if putBytes() ever tried to reach Drive,
        // this would throw exactly as the test above proves it does for
        // put(). It does not, because photos never go through that path.
        $stored = (new DocumentStore)->putBytes('employees/1/photo', 'png', 'fake-bytes');

        $this->assertStringNotContainsString('drive:', $stored['path']);
        $this->assertTrue(Storage::disk('local')->exists($stored['path']));
    }

    public function test_put_bytes_ignores_a_connected_drive_too(): void
    {
        $this->connectGoogleDrive();
        // No Http::fake() — a photo write must not attempt an HTTP call, so
        // one succeeding here would still be the wrong behaviour to assert.

        $stored = (new DocumentStore)->putBytes('clients/1/photo', 'jpg', 'fake-bytes');

        $this->assertStringNotContainsString('drive:', $stored['path']);
    }

    /* ══════════════════════════════════════════════════════════════════════
       exists() — TRUSTED FOR DRIVE, PROBED FOR LOCAL
       ══════════════════════════════════════════════════════════════════════ */

    public function test_exists_trusts_a_drive_path_without_any_http_call(): void
    {
        $this->connectGoogleDrive();
        Http::fake(); // any call at all fails this test

        $this->assertTrue((new DocumentStore)->exists('drive:some-file-id'));

        Http::assertNothingSent();
    }

    public function test_exists_still_probes_the_local_disk(): void
    {
        $store = new DocumentStore;

        $this->assertFalse($store->exists('employees/1/photo/nope.png'));

        $stored = $store->putBytes('employees/1/photo', 'png', 'fake-bytes');

        $this->assertTrue($store->exists($stored['path']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       download() and forget() — ROUTE ON THE PREFIX
       ══════════════════════════════════════════════════════════════════════ */

    public function test_download_streams_a_drive_files_bytes(): void
    {
        $this->connectGoogleDrive();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/drive/v3/files/file-id*' => Http::response('the payslip contents', 200),
        ]);

        $response = (new DocumentStore)->download('drive:file-id', 'payslip.pdf');

        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('payslip.pdf', $response->headers->get('content-disposition'));

        ob_start();
        $response->sendContent();
        $this->assertSame('the payslip contents', ob_get_clean());
    }

    public function test_view_inline_of_a_drive_pdf_sets_inline_disposition_not_attachment(): void
    {
        $this->connectGoogleDrive();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/drive/v3/files/file-id*' => Http::response('%PDF-1.4 fake bytes', 200),
        ]);

        $response = (new DocumentStore)->viewInline('drive:file-id', 'payslip.pdf');

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('attachment', $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $this->assertSame('%PDF-1.4 fake bytes', ob_get_clean());
    }

    public function test_view_inline_of_a_local_file_works_too(): void
    {
        $store = new DocumentStore;

        $stored = $store->putBytes('employees/1/photo', 'png', 'fake-png-bytes');

        $response = $store->viewInline($stored['path'], 'photo.png');

        $this->assertSame('image/png', $response->headers->get('Content-Type'));
    }

    public function test_view_inline_derives_the_content_type_from_the_name_when_none_is_given(): void
    {
        $this->connectGoogleDrive();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/drive/v3/files/file-id*' => Http::response('scan bytes', 200),
        ]);

        $response = (new DocumentStore)->viewInline('drive:file-id', 'id-scan.jpg');

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_view_inline_prefers_an_explicit_mime_type_over_the_name(): void
    {
        // EmployeeDocument stores a content-sniffed mime — more trustworthy
        // than a display name somebody could have typed anything into.
        $this->connectGoogleDrive();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/drive/v3/files/file-id*' => Http::response('bytes', 200),
        ]);

        $response = (new DocumentStore)->viewInline('drive:file-id', 'no-extension-here', 'application/pdf');

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_forget_deletes_a_drive_file_and_tolerates_it_already_being_gone(): void
    {
        $this->connectGoogleDrive();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/drive/v3/files/file-id*' => Http::response('', 404),
        ]);

        // A 404 on delete is success, not failure — see DriveClient::delete().
        (new DocumentStore)->forget('drive:file-id');

        $this->assertTrue(true); // reaching here without an exception is the assertion
    }

    public function test_forget_of_null_is_a_no_op(): void
    {
        Http::fake(); // any call fails this test

        (new DocumentStore)->forget(null);

        Http::assertNothingSent();
    }

    public function test_forget_still_deletes_from_the_local_disk(): void
    {
        $store = new DocumentStore;

        $stored = $store->putBytes('employees/1/photo', 'png', 'fake-bytes');
        $this->assertTrue($store->exists($stored['path']));

        $store->forget($stored['path']);

        $this->assertFalse(Storage::disk('local')->exists($stored['path']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       copy() — FOLLOWS THE SOURCE'S DRIVER, NOT THE DESTINATION'S
       ══════════════════════════════════════════════════════════════════════ */

    public function test_copy_of_a_local_photo_stays_local(): void
    {
        $store = new DocumentStore;

        $original = $store->putBytes('employees/1/photo', 'png', 'original-bytes');

        $copied = $store->copy($original['path'], 'employees/2/photo');

        $this->assertStringNotContainsString('drive:', $copied['path']);
        $this->assertTrue(Storage::disk('local')->exists($copied['path']));
        $this->assertNotSame($original['path'], $copied['path']);
    }

    public function test_copy_of_a_drive_document_uses_drives_own_copy_endpoint(): void
    {
        $this->connectGoogleDrive();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/drive/v3/files?*' => Http::sequence()
                ->push(['files' => [['id' => 'folder-id']]], 200), // folder already exists
            'https://www.googleapis.com/drive/v3/files/old-file-id/copy*' => Http::response(
                ['id' => 'new-file-id', 'size' => '2048'],
                200,
            ),
        ]);

        $copied = (new DocumentStore)->copy('drive:old-file-id', 'employees/2/documents');

        $this->assertSame('drive:new-file-id', $copied['path']);
        $this->assertSame(2048, $copied['bytes']);

        // A copy, never a download-and-reupload — the bytes never pass
        // through this application.
        Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), 'alt=media'));
    }

    public function test_copy_of_a_missing_local_file_throws(): void
    {
        $this->expectException(RuntimeException::class);

        (new DocumentStore)->copy('employees/1/photo/does-not-exist.png', 'employees/2/photo');
    }
}
