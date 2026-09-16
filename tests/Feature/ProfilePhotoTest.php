<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Support\Documents\DocumentStore;
use App\Support\Images\PhotoIntake;
use App\Support\Profile\ProfileChanges;
use App\Support\Rbac\Rbac;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The profile photo, on a host with no image library.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE ASSERTIONS THAT MATTER ARE ABOUT WHAT IS NOT IN THE STORED FILE
 *
 * This is a strip and not a re-encode — App\Support\Images\PhotoIntake says
 * exactly what that buys — so the tests are written against the thing the rule
 * was for: a phone photo carries GPS coordinates, and a staff photo that
 * publishes where somebody lives is not a feature. Every test below builds a
 * file with something identifiable hidden in its metadata and asserts it is
 * gone from what reached the disk.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ProfilePhotoTest extends TestCase
{
    protected Employee $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoWorkforce();

        $user = User::where('user_id', 'EMP002')->firstOrFail();

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        $this->viewer = Employee::where('user_id', $user->id)->firstOrFail();
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT REACHES THE DISK
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_jpegs_exif_block_does_not_reach_the_disk(): void
    {
        $secret = 'GPS 22.5726 N 88.3639 E';

        $this->upload($this->jpegWithExif($secret))->assertRedirect('/profile');

        $stored = $this->storedPhoto();

        $this->assertStringNotContainsString($secret, $stored, 'the Exif block survived the upload');
        $this->assertStringNotContainsString('Exif', $stored);

        // And it is still a JPEG with picture in it.
        $this->assertStringStartsWith("\xFF\xD8", $stored);
        $this->assertStringContainsString("\xFF\xDA", $stored);
    }

    public function test_a_jpeg_comment_does_not_reach_the_disk_either(): void
    {
        /*
         * COM (FFFE) is not Exif and is dropped by the same rule. Asserted
         * separately because "strip the Exif" is the version of this that
         * somebody writes when they are thinking about one marker.
         */
        $secret = 'written in the comment segment';

        $this->upload($this->jpegWithComment($secret))->assertRedirect();

        $this->assertStringNotContainsString($secret, $this->storedPhoto());
    }

    public function test_a_pngs_text_chunks_do_not_reach_the_disk(): void
    {
        $secret = 'taken at home';

        $this->upload($this->pngWithText($secret))->assertRedirect('/profile');

        $stored = $this->storedPhoto();

        $this->assertStringNotContainsString($secret, $stored);
        $this->assertStringNotContainsString('tEXt', $stored);
        $this->assertStringNotContainsString('eXIf', $stored);

        // Still a PNG, still has pixels, still ends properly.
        $this->assertStringStartsWith("\x89PNG\r\n\x1A\n", $stored);
        $this->assertStringContainsString('IDAT', $stored);
        $this->assertStringEndsWith('IEND'."\xAE\x42\x60\x82", $stored);
    }

    public function test_the_picture_itself_is_kept_byte_for_byte(): void
    {
        /*
         * The counterpart to every test above: a stripper that dropped the
         * picture too would pass all of them. The PNG's IDAT chunk is compared
         * against the one that went in.
         */
        $original = $this->pngWithText('anything');

        $this->upload($original)->assertRedirect();

        $idat = $this->chunkOf(file_get_contents($original->getRealPath()), 'IDAT');

        $this->assertNotSame('', $idat);
        $this->assertStringContainsString($idat, $this->storedPhoto());
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT IS REFUSED
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_file_that_is_not_an_image_is_refused_whatever_it_is_called(): void
    {
        // The extension is whatever somebody typed. The content is checked.
        $file = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "hello"; ?>');

        $this->upload($file)->assertSessionHasErrors('photo');

        $this->assertNull($this->viewer->fresh()->profile?->photo_path);
    }

    public function test_an_enormous_picture_is_refused(): void
    {
        /*
         * Nothing here decodes the image, so the cap is not protecting this
         * server — it protects everybody who opens the page. A very large PNG
         * is a few hundred kilobytes on disk and a great deal of memory in a
         * browser.
         */
        $this->upload($this->pngClaiming(PhotoIntake::MAX_SIDE + 10, 10))
            ->assertSessionHasErrors('photo');

        $this->assertNull($this->viewer->fresh()->profile?->photo_path);
    }

    public function test_a_format_this_module_does_not_parse_is_refused(): void
    {
        // GIF, because an animated avatar is a decision nobody made — and
        // because a parser that is nearly right is worse than no upload.
        $gif = "GIF89a".pack('vv', 1, 1)."\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF"
            ."\x21\xF9\x04\x00\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00"
            ."\x02\x02\x44\x01\x00\x3B";

        $this->upload(UploadedFile::fake()->createWithContent('avatar.gif', $gif))
            ->assertSessionHasErrors('photo');
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHERE IT LIVES AND WHO CAN SEE IT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_photo_is_not_reachable_without_the_route(): void
    {
        $this->upload($this->pngWithText('x'))->assertRedirect();
        $this->applyPending();

        $path = $this->viewer->fresh()->profile->photo_path;

        // On the private disk, and its path is nowhere on the page.
        $this->assertStringNotContainsString('public', $path);
        $this->assertStringNotContainsString($path, $this->get('/profile')->getContent());

        $this->get('/profile/photo')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_the_photo_route_takes_no_identifier(): void
    {
        /*
         * The whole module's rule. A route with an employee on it would make
         * "whose photo may I see" a question this module answers, and that is a
         * decision about the Employees directory.
         */
        $route = collect(app('router')->getRoutes())
            ->first(fn ($r) => $r->getName() === 'profile.photo.show');

        $this->assertSame([], $route->parameterNames());
    }

    public function test_an_upload_does_not_touch_the_record_until_hr_applies_it(): void
    {
        /*
         * The reversal, at its narrowest. The file is cleaned and stored the
         * moment it arrives — holding it is safe, because what is held is bytes
         * this application produced — but the photograph on the record is the
         * one HR last accepted.
         */
        $this->upload($this->pngWithText('x'))->assertRedirect();

        $this->assertNull($this->viewer->fresh()->profile?->photo_path);

        $candidate = app(ProfileChanges::class)->pendingFor($this->viewer)?->photo_path;

        $this->assertNotNull($candidate);
        $this->assertTrue(app(DocumentStore::class)->exists($candidate));

        $this->applyPending();

        $this->assertSame($candidate, $this->viewer->fresh()->profile->photo_path);
    }

    public function test_a_second_upload_is_refused_while_one_is_waiting(): void
    {
        // Quietly replacing the first would throw away something HR may already
        // have half-decided, and nobody would know it had happened.
        $this->upload($this->pngWithText('first'))->assertRedirect();

        $this->upload($this->pngWithText('second'))->assertSessionHasErrors('photo');
    }

    public function test_replacing_a_photo_removes_the_old_file(): void
    {
        $this->upload($this->pngWithText('first'))->assertRedirect();
        $this->applyPending();

        $first = $this->viewer->fresh()->profile->photo_path;

        $this->upload($this->pngWithText('second'))->assertRedirect();
        $this->applyPending();

        $second = $this->viewer->fresh()->profile->photo_path;

        $this->assertNotSame($first, $second);
        $this->assertFalse(app(DocumentStore::class)->exists($first), 'the replaced photo is still on disk');
        $this->assertTrue(app(DocumentStore::class)->exists($second));
    }

    public function test_withdrawing_a_request_deletes_the_photo_nobody_accepted(): void
    {
        /*
         * The one part of a spent request that is not kept. A photograph nobody
         * accepted is not a record of anything, and keeping every one of them
         * grows the disk forever.
         */
        $this->upload($this->pngWithText('x'))->assertRedirect();

        $candidate = app(ProfileChanges::class)->pendingFor($this->viewer)?->photo_path;

        $this->post('/profile/requests/withdraw')->assertRedirect('/profile');

        $this->assertFalse(app(DocumentStore::class)->exists($candidate));
    }

    public function test_no_photo_is_a_404_and_the_page_falls_back_to_initials(): void
    {
        $this->get('/profile/photo')->assertNotFound();

        // Initials rather than a broken image or a placeholder file.
        $this->assertStringContainsString('avatar-xl', $this->get('/profile')->getContent());
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    protected function upload(UploadedFile $file)
    {
        return $this->post('/profile/photo', ['photo' => $file]);
    }

    /**
     * The bytes of the CANDIDATE photo — the one sitting on the request.
     *
     * Since 2026-09-14 an upload does not touch the record: it stores the
     * cleaned file and asks HR. The stripping happens before it is written
     * either way, which is what every test above is actually about, so they
     * read the candidate rather than the live photo.
     */
    protected function storedPhoto(): string
    {
        $path = app(ProfileChanges::class)->pendingFor($this->viewer)?->photo_path;

        $this->assertNotNull($path, 'nothing was stored');

        return \Illuminate\Support\Facades\Storage::disk('local')->get($path);
    }

    /**
     * HR applies whatever is pending, so the photo reaches the record.
     *
     * Applied by somebody else, because nobody decides their own — the rule
     * lives in the controller, and this goes through the service directly.
     */
    protected function applyPending(): void
    {
        $pending = app(ProfileChanges::class)->pendingFor($this->viewer);

        $this->assertNotNull($pending, 'nothing was waiting to apply');

        $hr = User::factory()->create([
            'user_id' => 'HR-T'.fake()->unique()->numberBetween(100, 999),
            'account_type' => \App\Support\Realm::STAFF,
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        app(ProfileChanges::class)->apply($pending, $hr);
    }

    /**
     * A one-pixel JPEG with an APP1 Exif segment carrying the given string.
     */
    protected function jpegWithExif(string $secret): UploadedFile
    {
        $exif = "Exif\x00\x00".$secret;
        $segment = pack('n', strlen($exif) + 2).$exif;

        return $this->jpeg("\xFF\xE1".$segment);
    }

    protected function jpegWithComment(string $secret): UploadedFile
    {
        $segment = pack('n', strlen($secret) + 2).$secret;

        return $this->jpeg("\xFF\xFE".$segment);
    }

    /**
     * The smallest JPEG a decoder and `getimagesize` both accept, with an extra
     * segment injected after the SOI.
     */
    protected function jpeg(string $injected): UploadedFile
    {
        // A 1×1 grey JPEG, base64'd rather than assembled: the point of these
        // tests is the stripper, not a hand-written encoder.
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            .'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
            .'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        );

        // After the SOI, before everything else — exactly where a camera writes
        // its APP1.
        $with = substr($jpeg, 0, 2).$injected.substr($jpeg, 2);

        return UploadedFile::fake()->createWithContent('photo.jpg', $with);
    }

    /**
     * A one-pixel PNG with a tEXt chunk carrying the given string.
     */
    protected function pngWithText(string $secret): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $text = 'Comment'."\x00".$secret;
        $chunk = pack('N', strlen($text)).'tEXt'.$text.pack('N', crc32('tEXt'.$text));

        // After IHDR (8 signature + 25 chunk), which is where a writer puts one.
        return UploadedFile::fake()->createWithContent(
            'photo.png',
            substr($png, 0, 33).$chunk.substr($png, 33),
        );
    }

    /**
     * A valid PNG whose IHDR claims the given dimensions.
     *
     * Hand-built rather than drawn: `getimagesize` reads the header, so the
     * dimension cap is exercised by a header that says 2510 and a picture that
     * is one pixel — which is also the shape of the file somebody would use to
     * get past a check that trusted the file size instead.
     */
    protected function pngClaiming(int $width, int $height): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        // IHDR data is 13 bytes at offset 16; the CRC covers type + data.
        $data = pack('NN', $width, $height).substr($png, 24, 5);
        $ihdr = pack('N', 13).'IHDR'.$data.pack('N', crc32('IHDR'.$data));

        return UploadedFile::fake()->createWithContent('huge.png', substr($png, 0, 8).$ihdr.substr($png, 33));
    }

    /**
     * The raw bytes of the first chunk of a type, for comparing before/after.
     */
    protected function chunkOf(string $png, string $type): string
    {
        $at = 8;

        while ($at + 8 <= strlen($png)) {
            $size = unpack('N', substr($png, $at, 4))[1];

            if (substr($png, $at + 4, 4) === $type) {
                return substr($png, $at, 12 + $size);
            }

            $at += 12 + $size;
        }

        return '';
    }
}
