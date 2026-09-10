<?php

namespace App\Support\Images;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Taking in a profile photo on a host with no image library.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHAT THIS IS AND, JUST AS IMPORTANTLY, WHAT IT IS NOT
 *
 * My Profile has said since it was built that an uploaded photo is "re-encoded
 * rather than stored as received, size capped, stripped of EXIF — a phone photo
 * carries GPS coordinates, and a staff directory that publishes where everybody
 * lives is not a feature."
 *
 * This host has neither GD nor Imagick, and `exif` is not loaded either. There
 * is no re-encode available, and adding an image library is a deployment
 * decision rather than a code one — this application ships to shared hosting.
 *
 * So this does the next honest thing: it PARSES the container and rebuilds it
 * from the parts that carry picture, dropping every part that carries anything
 * else. The pixels are the bytes that arrived; nothing else survives.
 *
 * WHAT THAT BUYS, EXACTLY
 *
 *   - GPS coordinates, camera serial numbers, timestamps, thumbnails, colour
 *     profiles and XMP blocks are gone. That is the harm the rule was written
 *     for, and it is the harm this removes.
 *
 *   - A file that is not really an image is refused: the magic bytes are
 *     checked here, the content type is checked by the validator through finfo,
 *     and the parse itself fails on anything that is not a well-formed JPEG or
 *     PNG. A polyglot needs a valid container to hide in, and rebuilding the
 *     container from known segments drops whatever was hiding between them.
 *
 * WHAT IT DOES NOT BUY
 *
 *   A re-encode also normalises the pixel data itself, which defends against
 *   decoder bugs in whoever opens the file later. This does not, and cannot.
 *   The mitigation is that the dimensions are capped and the file is small, and
 *   the honest statement is that this is a strip and not a re-encode. If an
 *   image library ever becomes available, `clean()` is the one method to
 *   replace.
 *
 * ANIMATED AND EXOTIC FORMATS ARE NOT ACCEPTED
 *
 * JPEG and PNG only. GIF because an animated avatar is a decision nobody made,
 * WebP and AVIF because each is a third container to parse correctly and a
 * parser that is nearly right is worse than no upload.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class PhotoIntake
{
    /** 2 MB. A profile photo is tens of kilobytes; a phone photo is under this. */
    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * The largest side accepted, in pixels.
     *
     * Nothing here decodes the image, so this is not protecting this server —
     * it protects everybody who opens the page. A 20000×20000 PNG is a few
     * hundred kilobytes on disk and a gigabyte of memory in a browser, and a
     * profile photo has no reason to be larger than a screen.
     */
    public const MAX_SIDE = 2500;

    /** @var list<string> */
    public const ALLOWED = ['jpg', 'jpeg', 'png'];

    /**
     * PNG chunks that carry picture or are required to read it.
     *
     * A whitelist, like every other list in this application: the interesting
     * chunk is always the one nobody thought to name. `eXIf`, `tEXt`, `zTXt`,
     * `iTXt` and `tIME` are the ones that carry metadata, and they are absent
     * from this list rather than listed as banned.
     *
     * @var list<string>
     */
    protected const PNG_KEEP = [
        'IHDR', 'PLTE', 'IDAT', 'IEND',
        // Needed to render correctly rather than merely to render: transparency,
        // gamma, colour space, bit depth hints, physical dimensions.
        'tRNS', 'gAMA', 'cHRM', 'sRGB', 'sBIT', 'bKGD', 'pHYs',
        // Interlacing and animation are not here; neither is iCCP, which is a
        // colour profile and is also a place to hide several kilobytes.
    ];

    /**
     * Check an upload and return the bytes to store.
     *
     * @return array{contents: string, extension: string, bytes: int, width: int, height: int}
     */
    public function clean(UploadedFile $file): array
    {
        $contents = (string) file_get_contents($file->getRealPath());

        if ($contents === '') {
            throw new RuntimeException('That file is empty.');
        }

        if (strlen($contents) > self::MAX_BYTES) {
            throw new RuntimeException('That photo is larger than 2 MB.');
        }

        /*
         * `getimagesize` is in the standard library, not in GD — it reads the
         * header rather than decoding. It is used here for the dimensions and
         * for a second opinion on the format, never as the only type check.
         */
        $size = @getimagesize($file->getRealPath());

        if ($size === false) {
            throw new RuntimeException('That does not look like an image.');
        }

        [$width, $height, $type] = $size;

        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw new RuntimeException('That photo is larger than '.self::MAX_SIDE.' pixels on a side.');
        }

        if ($width < 1 || $height < 1) {
            throw new RuntimeException('That image has no size.');
        }

        $cleaned = match ($type) {
            IMAGETYPE_JPEG => ['contents' => $this->stripJpeg($contents), 'extension' => 'jpg'],
            IMAGETYPE_PNG => ['contents' => $this->stripPng($contents), 'extension' => 'png'],
            default => throw new RuntimeException('Only JPEG and PNG photos can be uploaded.'),
        };

        return $cleaned + [
            'bytes' => strlen($cleaned['contents']),
            'width' => $width,
            'height' => $height,
        ];
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE TWO PARSERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Rebuild a JPEG from its picture segments.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * EVERY APPn SEGMENT IS DROPPED, INCLUDING APP0
     *
     * APP1 is where Exif and XMP live — GPS, camera body, the original
     * timestamp, and frequently a full-size thumbnail that survives cropping.
     * APP13 is Photoshop's IPTC. APP2 is an ICC profile. APP0 is JFIF and is
     * merely a density hint, which nothing here reads.
     *
     * Dropping the lot is simpler than deciding which are harmless, and
     * "harmless" is a judgement that ages badly. A JPEG with no APPn segments
     * is a valid JPEG that every decoder opens.
     *
     * The scan is walked segment by segment until SOS (FFDA), after which the
     * remainder is entropy-coded picture data and is copied verbatim — walking
     * INTO it would be reading compressed pixels as markers.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function stripJpeg(string $in): string
    {
        if (substr($in, 0, 2) !== "\xFF\xD8") {
            throw new RuntimeException('That file is not a JPEG.');
        }

        $out = "\xFF\xD8";
        $at = 2;
        $length = strlen($in);

        while ($at < $length) {
            if ($in[$at] !== "\xFF") {
                throw new RuntimeException('That JPEG is malformed.');
            }

            // Fill bytes: a run of FFs before a marker is legal padding.
            while ($at < $length && $in[$at] === "\xFF") {
                $at++;
            }

            if ($at >= $length) {
                break;
            }

            $marker = ord($in[$at]);
            $at++;

            // Standalone markers carry no length. RSTn and TEM.
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $out .= "\xFF".chr($marker);

                continue;
            }

            if ($marker === 0xD9) {
                // End of image.
                $out .= "\xFF\xD9";

                break;
            }

            if ($at + 2 > $length) {
                throw new RuntimeException('That JPEG ends mid-segment.');
            }

            $segmentLength = (ord($in[$at]) << 8) + ord($in[$at + 1]);

            if ($segmentLength < 2 || $at + $segmentLength > $length) {
                throw new RuntimeException('That JPEG has an impossible segment length.');
            }

            $segment = substr($in, $at, $segmentLength);
            $at += $segmentLength;

            // APPn (E0–EF) and COM (FE). Everything the picture does not need.
            $isMetadata = ($marker >= 0xE0 && $marker <= 0xEF) || $marker === 0xFE;

            if (! $isMetadata) {
                $out .= "\xFF".chr($marker).$segment;
            }

            if ($marker === 0xDA) {
                // Start of scan: the rest is compressed picture, to the end.
                $out .= substr($in, $at);

                break;
            }
        }

        if (! str_contains($out, "\xFF\xDA")) {
            throw new RuntimeException('That JPEG has no image data in it.');
        }

        return $out;
    }

    /**
     * Rebuild a PNG from the chunks on the keep-list.
     *
     * Each chunk carries its own CRC, which is copied with it — the chunks that
     * survive are byte-identical, so nothing needs recomputing and a corrupt
     * chunk cannot be laundered into a valid-looking one.
     */
    protected function stripPng(string $in): string
    {
        $signature = "\x89PNG\r\n\x1A\n";

        if (! str_starts_with($in, $signature)) {
            throw new RuntimeException('That file is not a PNG.');
        }

        $out = $signature;
        $at = strlen($signature);
        $length = strlen($in);
        $sawData = false;

        while ($at + 8 <= $length) {
            $size = unpack('N', substr($in, $at, 4))[1];
            $type = substr($in, $at + 4, 4);

            // 4 length + 4 type + data + 4 CRC.
            $whole = 12 + $size;

            if ($size > $length || $at + $whole > $length) {
                throw new RuntimeException('That PNG has an impossible chunk length.');
            }

            if (in_array($type, self::PNG_KEEP, true)) {
                $out .= substr($in, $at, $whole);

                if ($type === 'IDAT') {
                    $sawData = true;
                }
            }

            $at += $whole;

            if ($type === 'IEND') {
                break;
            }
        }

        if (! $sawData) {
            throw new RuntimeException('That PNG has no image data in it.');
        }

        if (! str_ends_with($out, 'IEND'."\xAE\x42\x60\x82")) {
            // A PNG whose IEND was dropped or never arrived. Appended rather
            // than refused: every keep-listed chunk before it is intact, and
            // IEND carries nothing but the fact of being the end.
            $out .= "\x00\x00\x00\x00".'IEND'."\xAE\x42\x60\x82";
        }

        return $out;
    }
}
