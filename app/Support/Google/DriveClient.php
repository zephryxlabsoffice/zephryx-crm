<?php

namespace App\Support\Google;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Drive, through the Shared Drive the service account is a member of.
 *
 * No `impersonate` anywhere in this class — Drive access is Shared Drive
 * membership, not delegation (plan doc, "Note the asymmetry"). Calendar is
 * the one that impersonates, in GoogleMeetProvider, once that lands.
 */
class DriveClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive';

    protected const FILES_URL = 'https://www.googleapis.com/drive/v3/files';

    public function __construct(
        protected GoogleAuth $auth,
        protected string $sharedDriveId,
    ) {}

    /**
     * Create and delete a small file in the Shared Drive.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THIS IS THE POINT OF THE BUTTON
     *
     * A credential that can list a folder and cannot write to it is the
     * failure the "Test connection" button exists to catch — otherwise it is
     * discovered by the first person trying to upload a payslip (plan doc,
     * "Connecting it", rule 5).
     *
     * The file created is metadata only — a zero-byte object — because
     * proving write access does not need any actual content, and it keeps
     * this call a single JSON POST rather than a multipart upload.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @throws RuntimeException naming which half failed — creating or deleting
     */
    public function testConnection(): void
    {
        $token = $this->auth->token([self::SCOPE]);

        $created = Http::withToken($token)->post(self::FILES_URL.'?supportsAllDrives=true', [
            'name' => 'zephryx-connection-test-'.now()->format('Ymd-His'),
            'parents' => [$this->sharedDriveId],
        ]);

        if ($created->failed()) {
            throw new RuntimeException('Could not create a test file in the Shared Drive: '.$this->errorMessage($created));
        }

        $fileId = $created->json('id');

        $deleted = Http::withToken($token)->delete(self::FILES_URL.'/'.$fileId.'?supportsAllDrives=true');

        if ($deleted->failed()) {
            throw new RuntimeException('Created a test file but could not delete it: '.$this->errorMessage($deleted));
        }
    }

    protected function errorMessage(Response $response): string
    {
        return $response->json('error.message') ?? $response->body();
    }
}
