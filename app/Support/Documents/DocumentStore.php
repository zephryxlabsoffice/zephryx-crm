<?php

namespace App\Support\Documents;

use App\Models\GoogleConnection;
use App\Support\Google\DriveClient;
use App\Support\Google\GoogleAuth;
use App\Support\Google\GoogleServiceAccountKey;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files this application holds on somebody's behalf.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * TWO DRIVERS, ONE SEAM — AND THE METHOD CALLED IS THE ROUTING
 *
 * Decided 2026-09-16: photos stay on the local disk (small, few of them,
 * nothing to check them against); payslips, invoices and ticket/task
 * attachments go to Google Drive. The application never passes a "which
 * driver" flag to say so, because the two kinds of write already arrive
 * through different methods:
 *
 *   putBytes() — bytes this application composed (a resized photo). Always
 *   local. Every call site already is a photo; see App\Support\Images\
 *   PhotoIntake, the only producer of bytes this method receives.
 *
 *   put() — a file somebody uploaded (a payslip, a document). Always Drive.
 *
 * `exists()`, `copy()`, `download()` and `forget()` take a stored `path` back
 * and cannot ask the caller which driver wrote it, so the path itself carries
 * that: a Drive file lives behind the `drive:` prefix this class puts on it,
 * and everything else is a local disk path exactly as before. A local path
 * can never collide with the prefix — `put()`'s local paths are composed from
 * a folder and `Str::random(40)`, never typed by hand.
 *
 * NOTHING IS MIGRATED (decided 2026-09-16). Files already on local disk
 * before this landed — demo content — stay there and still work; `copy()` and
 * `download()` read the prefix to decide, not a global switch. The production
 * seeder ships no files, so the first real payslip goes straight to Drive.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * NOTHING THIS CLASS WRITES ON THE LOCAL DISK IS REACHABLE OVER HTTP
 *
 * Local files go to `storage/app/private`, outside the webroot — §6 requires
 * it, and a payslip or a PAN scan under a guessable public path is a link
 * somebody can forward, no amount of care afterwards takes it back. Drive
 * files carry the same rule a different way: the service account is a member
 * of the Shared Drive, nothing on it is shared "anyone with the link", and
 * `download()` is the only route back out — see the class-level note above
 * `stream()`.
 *
 * THE STORED NAME IS NOT THE UPLOADED NAME
 *
 * The path (or the Drive filename) is composed by the caller plus a random
 * token; the original filename is data, kept in the database for display. An
 * uploaded name reaching storage is how "../../.env" and "payslip.pdf.php"
 * become a problem, and the extension is taken from a whitelist rather than
 * from whatever was typed.
 */
class DocumentStore
{
    /**
     * What may be stored, by extension.
     *
     * A whitelist, not a blacklist: the interesting attack is always the
     * extension nobody thought to ban.
     *
     * @var list<string>
     */
    public const ALLOWED = ['pdf', 'png', 'jpg', 'jpeg'];

    /** 8 MB. A payslip is tens of kilobytes; a scan is a couple of megabytes. */
    public const MAX_BYTES = 8 * 1024 * 1024;

    /**
     * Marks a stored path as a Drive file id rather than a local disk path.
     * Never appears in a path `put()` composes for the local disk — those are
     * always `folder/random-token.ext`.
     */
    protected const DRIVE_PREFIX = 'drive:';

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * Store an uploaded file. Always Drive — see the class header.
     *
     * `$folder` is still `employees/{id}/documents` or `payslips/{period}`,
     * exactly as it was on the local disk: the first path segment becomes the
     * Drive module folder ("employees", "payslips"), and the rest is folded
     * into the stored filename so the file stays traceable inside a flat
     * Drive folder listing without needing nested Drive folders to exist.
     *
     * @return array{path: string, name: string, bytes: int}
     */
    public function put(string $folder, UploadedFile $file): array
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED, true)) {
            // Validation refuses this first; this is the second line, for a
            // caller that forgot to validate.
            throw new \InvalidArgumentException('That file type cannot be stored.');
        }

        $module = $this->moduleOf($folder);
        $stored = $this->storedName($folder, $extension);

        $fileId = $this->drive()->upload(
            $module,
            $stored,
            (string) file_get_contents($file->getRealPath()),
            $file->getMimeType() ?: 'application/octet-stream',
        );

        return [
            'path' => self::DRIVE_PREFIX.$fileId,
            // Trimmed to the base name: a browser can send a whole path, and
            // it is displayed rather than used, but displaying somebody's
            // folder structure is not something to do by accident.
            'name' => mb_substr(basename($file->getClientOriginalName()), 0, 190),
            'bytes' => (int) $file->getSize(),
        ];
    }

    /**
     * Store bytes we composed rather than a file we received. Always local —
     * see the class header. Every caller today is a photo.
     *
     * Added for the profile photo, which is not stored as it arrived: it is
     * parsed and rebuilt from its picture segments first (see
     * App\Support\Images\PhotoIntake), so what reaches the disk is a string
     * this application produced and the UploadedFile is long gone by then.
     *
     * The same validation rules apply — whitelisted extension, a name that is
     * ours, a path composed by the caller — because the reason for each of
     * them is where the file ENDS UP, not where it came from.
     *
     * @return array{path: string, bytes: int}
     */
    public function putBytes(string $folder, string $extension, string $contents): array
    {
        $extension = mb_strtolower($extension);

        if (! in_array($extension, self::ALLOWED, true)) {
            throw new \InvalidArgumentException('That file type cannot be stored.');
        }

        $path = trim($folder, '/').'/'.Str::random(40).'.'.$extension;

        $this->disk()->put($path, $contents);

        return ['path' => $path, 'bytes' => strlen($contents)];
    }

    public function exists(string $path): bool
    {
        /*
         * Trusted, not probed. A Drive file does not vanish out from under
         * its database row the way a local disk file theoretically can (a
         * cleanup script, a full volume), which is the actual reason this
         * method exists on the local side. Calling Drive on every profile
         * page load and every download to answer a question that is already
         * answered by the row existing would be a real cost for no real
         * safety.
         */
        if ($this->isDrivePath($path)) {
            return true;
        }

        return $this->disk()->exists($path);
    }

    /**
     * Duplicate a stored file into another folder, under a fresh name.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * A COPY, NOT A SECOND ROW POINTING AT ONE FILE
     *
     * Added for converting an intern (2026-09-16), where a photo and a set of
     * documents move from a closed record to a new one. The cheap version is
     * to write the same `path` onto both rows — and the cost lands later, when
     * the person replaces their photo and `forget()` deletes the file out
     * from under the record that is supposed to be history. One row, one
     * file, on whichever driver the source already lives on: a photo copy
     * (`$from` local) never touches Drive, and a document copy (`$from`
     * Drive, going forward) never touches the local disk.
     *
     * @return array{path: string, bytes: int}
     */
    public function copy(string $from, string $folder): array
    {
        if ($this->isDrivePath($from)) {
            $copied = $this->drive()->copyFile(
                $this->driveFileId($from),
                $this->moduleOf($folder),
                $this->storedName($folder, 'copy'),
            );

            return ['path' => self::DRIVE_PREFIX.$copied['id'], 'bytes' => $copied['bytes']];
        }

        $extension = mb_strtolower(pathinfo($from, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED, true)) {
            throw new \InvalidArgumentException('That file type cannot be stored.');
        }

        $contents = $this->disk()->get($from);

        if ($contents === null) {
            // The caller decides what a missing source means. For a
            // conversion it means that record simply has no photo, which is
            // survivable — and far better than half a conversion.
            throw new \RuntimeException('There is no file at '.$from.' to copy.');
        }

        return $this->putBytes($folder, $extension, $contents);
    }

    /**
     * Hand a stored file back, under the name it was uploaded with.
     */
    public function download(string $path, string $name): StreamedResponse
    {
        if ($this->isDrivePath($path)) {
            $contents = $this->drive()->download($this->driveFileId($path));

            return response()->streamDownload(
                fn () => print ($contents),
                $name,
                ['Content-Type' => 'application/octet-stream'],
            );
        }

        return $this->disk()->download($path, $name);
    }

    /**
     * Show a stored file in the browser rather than saving it — for things
     * THIS APPLICATION produced and knows the shape of. The profile photo,
     * rebuilt byte by byte from its picture segments before it is written
     * (see App\Support\Images\PhotoIntake). Always local: see the class
     * header — nothing that reaches this method is ever a Drive path, and it
     * refuses one outright rather than guess.
     *
     * Never for an uploaded document — see `viewInline()` for that, which
     * exists because this method used to be the only way to show something
     * inline, and an upload is not a shape this application chose.
     *
     * The content type is stated, not sniffed from the file, for the same
     * reason: a browser guessing at a type is a browser that can be talked
     * into guessing "html".
     */
    public function stream(string $path): StreamedResponse
    {
        if ($this->isDrivePath($path)) {
            throw new \InvalidArgumentException('That is a Drive file — this store streams inline only from the local disk.');
        }

        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $type = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => throw new \InvalidArgumentException('That file is not one this store streams inline.'),
        };

        return $this->disk()->response($path, null, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline',
            // Belt and braces: the type above is ours and correct, and this
            // tells the browser not to look for a better one anyway.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Show an UPLOADED file in the browser — a payslip, an identity scan.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE CONDITION stream() WAS WRITTEN TO WAIT FOR
     *
     * Rendering somebody's upload inline used to mean rendering it in this
     * application's own origin — the reason `stream()` refused everything but
     * bytes this application produced itself. That is no longer the whole
     * picture: every `application/pdf` response now gets a Content-Security-
     * Policy that allows the document nothing — no script, no network, no
     * form submission (see App\Http\Middleware\SecurityHeaders). A PDF
     * rendered under that policy cannot act inside our origin whatever it
     * contains, which is the plan doc's own condition for lifting the
     * refusal ("How, concretely", point 4).
     *
     * Images carry no such risk on their own — a PNG or JPEG cannot execute
     * anything — so they pass through unlocked, same as `stream()`.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * `$mimeType` is trusted: it comes from either a caller's own stored
     * `mime` column (content-sniffed at upload — see EmployeeDocument) or is
     * derived here from the display name's extension when there is no such
     * column (a payslip has none). Both are already constrained to
     * DocumentStore::ALLOWED at upload time; this method is not where that
     * boundary is enforced, only where the response is shaped.
     */
    public function viewInline(string $path, string $name, ?string $mimeType = null): StreamedResponse
    {
        $mimeType ??= self::mimeTypeFor(pathinfo($name, PATHINFO_EXTENSION));

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($this->isDrivePath($path)) {
            $contents = $this->drive()->download($this->driveFileId($path));

            return new StreamedResponse(fn () => print ($contents), 200, $headers);
        }

        return $this->disk()->response($path, null, $headers);
    }

    /**
     * The content type ALLOWED restricts uploads to, by extension.
     */
    public static function mimeTypeFor(string $extension): string
    {
        return match (mb_strtolower($extension)) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }

    /**
     * Remove a file. Used when one is replaced — see the callers.
     */
    public function forget(?string $path): void
    {
        if ($path === null) {
            return;
        }

        if ($this->isDrivePath($path)) {
            $this->drive()->delete($this->driveFileId($path));

            return;
        }

        if ($this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    protected function isDrivePath(string $path): bool
    {
        return str_starts_with($path, self::DRIVE_PREFIX);
    }

    protected function driveFileId(string $path): string
    {
        return mb_substr($path, mb_strlen(self::DRIVE_PREFIX));
    }

    protected function moduleOf(string $folder): string
    {
        return Str::before(trim($folder, '/'), '/');
    }

    /**
     * A name for the Drive file that keeps the caller's folder legible inside
     * a flat module folder — "employees/12/documents" becomes a filename
     * carrying "employees_12_documents", not a nested path Drive would have
     * to be taught to create.
     */
    protected function storedName(string $folder, string $extension): string
    {
        $trace = str_replace('/', '_', trim($folder, '/'));

        return $trace.'-'.Str::random(24).'.'.$extension;
    }

    /**
     * A Drive client built from the current connection. Fresh per call — the
     * connection can change between requests, and this class is resolved new
     * per request anyway (see the controllers that inject it).
     *
     * @throws \RuntimeException if Google is not connected
     */
    protected function drive(): DriveClient
    {
        $connection = GoogleConnection::current();

        if (! $connection->isConnected()) {
            throw new \RuntimeException(
                'Google Drive is not connected. An administrator has to connect it in the Admin Panel before files can be stored.'
            );
        }

        $auth = new GoogleAuth(GoogleServiceAccountKey::parse($connection->service_account_key));

        return new DriveClient($auth, (string) $connection->shared_drive_id);
    }
}
