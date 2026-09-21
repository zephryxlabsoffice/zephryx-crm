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
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ONE FOLDER PER MODULE
 *
 * "One Google account holds the files, in a folder per module" (plan doc,
 * "How, concretely"). `upload()` and `copyFile()` take a `$module` — a plain
 * name like `payslips` or `employees` — and file into a folder of that name,
 * found by listing the Shared Drive or created the first time it is needed.
 * Looked up once per request and cached on the instance: App\Support\
 * Documents\DocumentStore builds a fresh client per request, so there is
 * nothing to invalidate between them.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class DriveClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive';

    protected const FILES_URL = 'https://www.googleapis.com/drive/v3/files';

    protected const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';

    /** @var array<string, string> folder id by module name, for this instance */
    protected array $folders = [];

    public function __construct(
        protected GoogleAuth $auth,
        protected string $sharedDriveId,
    ) {}

    /**
     * Write a file into a module's folder and return its Drive file id.
     *
     * Two calls, not one multipart upload: create the metadata (name,
     * parent), then PATCH the content in as `uploadType=media`. Simpler than
     * assembling a `multipart/related` body by hand, and this is not a
     * high-frequency path — a payslip or an attachment, not a page load.
     *
     * @throws RuntimeException naming which half failed
     */
    public function upload(string $module, string $name, string $contents, string $mimeType): string
    {
        $token = $this->auth->token([self::SCOPE]);
        $folderId = $this->folderId($token, $module);

        $created = Http::withToken($token)->post(self::FILES_URL.'?supportsAllDrives=true', [
            'name' => $name,
            'parents' => [$folderId],
        ]);

        if ($created->failed()) {
            throw new RuntimeException('Could not create "'.$name.'" in the Shared Drive: '.$this->errorMessage($created));
        }

        $fileId = $created->json('id');

        $uploaded = Http::withToken($token)
            ->withBody($contents, $mimeType)
            ->patch(self::UPLOAD_URL.'/'.$fileId.'?uploadType=media&supportsAllDrives=true');

        if ($uploaded->failed()) {
            throw new RuntimeException('Created "'.$name.'" but could not write its contents: '.$this->errorMessage($uploaded));
        }

        return $fileId;
    }

    /**
     * Read a file's bytes back.
     *
     * @throws RuntimeException
     */
    public function download(string $fileId): string
    {
        $response = Http::withToken($this->auth->token([self::SCOPE]))
            ->get(self::FILES_URL.'/'.$fileId, ['alt' => 'media', 'supportsAllDrives' => 'true']);

        if ($response->failed()) {
            throw new RuntimeException('Could not read the file back from the Shared Drive: '.$this->errorMessage($response));
        }

        return $response->body();
    }

    /**
     * Copy a file within the Shared Drive, into a (possibly different)
     * module's folder, under a new stored name.
     *
     * Drive's own `files.copy` rather than download-then-reupload: one call
     * instead of two, and the bytes never pass through this application.
     * `fields=id,size` asked for on the same call, so the caller learns the
     * new file's size without a second round trip.
     *
     * @return array{id: string, bytes: int}
     *
     * @throws RuntimeException
     */
    public function copyFile(string $fileId, string $module, string $newName): array
    {
        $token = $this->auth->token([self::SCOPE]);
        $folderId = $this->folderId($token, $module);

        $response = Http::withToken($token)
            ->post(self::FILES_URL.'/'.$fileId.'/copy?supportsAllDrives=true&fields=id,size', [
                'name' => $newName,
                'parents' => [$folderId],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Could not copy the file within the Shared Drive: '.$this->errorMessage($response));
        }

        return [
            'id' => $response->json('id'),
            'bytes' => (int) $response->json('size', 0),
        ];
    }

    /**
     * Remove a file. Tolerant of it already being gone (404) — the same
     * shape as DocumentStore::forget() on the local disk, and for the same
     * reason: the caller is usually cleaning up after a replacement, and a
     * file that is already absent is success, not failure.
     *
     * @throws RuntimeException for anything other than "already gone"
     */
    public function delete(string $fileId): void
    {
        $this->deleteWithToken($this->auth->token([self::SCOPE]), $fileId);
    }

    /**
     * Create and delete a small file directly in the Shared Drive — not
     * inside a module folder, deliberately: a connectivity check should not
     * also be a test of the folder-lookup path, and should not leave a
     * `_connection_test` folder behind as clutter.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THIS IS THE POINT OF THE BUTTON
     *
     * A credential that can list a folder and cannot write to it is the
     * failure the "Test connection" button exists to catch — otherwise it is
     * discovered by the first person trying to upload a payslip (plan doc,
     * "Connecting it", rule 5).
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

        try {
            $this->deleteWithToken($token, $created->json('id'));
        } catch (RuntimeException $e) {
            throw new RuntimeException('Created a test file but could not delete it: '.$e->getMessage());
        }
    }

    protected function deleteWithToken(string $token, string $fileId): void
    {
        $response = Http::withToken($token)->delete(self::FILES_URL.'/'.$fileId.'?supportsAllDrives=true');

        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException($this->errorMessage($response));
        }
    }

    /**
     * The Drive file id of a module's folder, creating it the first time.
     *
     * @throws RuntimeException
     */
    protected function folderId(string $token, string $module): string
    {
        if (isset($this->folders[$module])) {
            return $this->folders[$module];
        }

        // Drive's query syntax escapes a literal quote with a backslash —
        // the module name is ours (a fixed word per call site, never user
        // input), but this costs nothing and removes the question.
        $escaped = str_replace("'", "\\'", $module);

        $found = Http::withToken($token)->get(self::FILES_URL, [
            'q' => "name = '{$escaped}' and mimeType = 'application/vnd.google-apps.folder' "
                ."and '{$this->sharedDriveId}' in parents and trashed = false",
            'corpora' => 'drive',
            'driveId' => $this->sharedDriveId,
            'includeItemsFromAllDrives' => 'true',
            'supportsAllDrives' => 'true',
            'fields' => 'files(id)',
        ]);

        if ($found->failed()) {
            throw new RuntimeException('Could not look up the "'.$module.'" folder in the Shared Drive: '.$this->errorMessage($found));
        }

        $existingId = $found->json('files.0.id');

        if ($existingId !== null) {
            return $this->folders[$module] = $existingId;
        }

        $created = Http::withToken($token)->post(self::FILES_URL.'?supportsAllDrives=true', [
            'name' => $module,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$this->sharedDriveId],
        ]);

        if ($created->failed()) {
            throw new RuntimeException('Could not create the "'.$module.'" folder in the Shared Drive: '.$this->errorMessage($created));
        }

        return $this->folders[$module] = $created->json('id');
    }

    protected function errorMessage(Response $response): string
    {
        return $response->json('error.message') ?? $response->body();
    }
}
