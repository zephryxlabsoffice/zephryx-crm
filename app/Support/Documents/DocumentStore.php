<?php

namespace App\Support\Documents;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files this application holds on somebody's behalf.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * NOTHING THIS CLASS WRITES IS REACHABLE OVER HTTP
 *
 * Everything goes to the `local` disk, which is `storage/app/private` — outside
 * the webroot. §6 requires it and the reason is one sentence: a payslip or a
 * PAN scan under a guessable public path is a link somebody can forward, and no
 * amount of care in the application can take it back.
 *
 * So there is no `url()` here and there never will be. Files come back through
 * `download()`, from a controller that has already decided the person asking
 * may have this one and has written an audit entry saying they did.
 *
 * THE STORED NAME IS NOT THE UPLOADED NAME
 *
 * The path is composed by the caller plus a random token; the original filename
 * is data, kept in the database for display. An uploaded name reaching the
 * filesystem is how "../../.env" and "payslip.pdf.php" become a problem, and
 * the extension is taken from a whitelist rather than from whatever was typed.
 * ═════════════════════════════════════════════════════════════════════════════
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

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * Store a file under a folder, and return what the database needs.
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

        // The stored name is ours. The uploaded one is data.
        $stored = Str::random(40).'.'.$extension;
        $path = trim($folder, '/').'/'.$stored;

        $this->disk()->putFileAs(dirname($path), $file, basename($path));

        return [
            'path' => $path,
            // Trimmed to the base name: a browser can send a whole path, and it
            // is displayed rather than used, but displaying somebody's folder
            // structure is not something to do by accident.
            'name' => mb_substr(basename($file->getClientOriginalName()), 0, 190),
            'bytes' => (int) $file->getSize(),
        ];
    }

    /**
     * Store bytes we composed rather than a file we received.
     *
     * Added for the profile photo, which is not stored as it arrived: it is
     * parsed and rebuilt from its picture segments first (see
     * App\Support\Images\PhotoIntake), so what reaches the disk is a string
     * this application produced and the UploadedFile is long gone by then.
     *
     * The same rules apply — whitelisted extension, a name that is ours, a path
     * composed by the caller — because the reason for each of them is where the
     * file ENDS UP, not where it came from.
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
        return $this->disk()->exists($path);
    }

    /**
     * Hand a stored file back, under the name it was uploaded with.
     */
    public function download(string $path, string $name): StreamedResponse
    {
        return $this->disk()->download($path, $name);
    }

    /**
     * Show a stored file in the browser rather than saving it.
     *
     * Only for things this application produced and knows the shape of — the
     * profile photo, which is rebuilt byte by byte from its picture segments
     * before it is written (see App\Support\Images\PhotoIntake).
     *
     * Never for an uploaded document. `download()` sends
     * `Content-Disposition: attachment`, which is what stops a browser
     * rendering somebody's upload in the page's own origin; inline is only safe
     * when the bytes are ours.
     *
     * The content type is stated, not sniffed from the file, for the same
     * reason: a browser guessing at a type is a browser that can be talked into
     * guessing "html".
     */
    public function stream(string $path): StreamedResponse
    {
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
     * Remove a file. Used when one is replaced — see the callers.
     */
    public function forget(?string $path): void
    {
        if ($path !== null && $this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }
}
