<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Support\Documents\DocumentStore;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The client's own picture (settled 2026-09-17).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THIS ROUTE WAS A 501 FOR THREE WEEKS, ON PURPOSE
 *
 * A client account is an ORGANISATION, so what it uploads is a company logo —
 * and a logo appears on invoices, which made "may a client set the image on
 * their own invoice" a question nobody had answered. Building it anyway would
 * have been answering a business question by writing code.
 *
 * The invoices reversal removed the question instead: an invoice is an uploaded
 * PDF now, and nothing this application generates carries a client logo
 * anywhere. What is left is an avatar on their own portal.
 *
 * AND IT SAVES IMMEDIATELY, UNLIKE THE STAFF PHOTO
 *
 * A staff photograph became a request to HR on 2026-09-14, because the profile
 * is the company's record of a person and is corrected against documents. A
 * client's avatar is a record of nothing — there is no document to check it
 * against and no HR relationship to check it — so queueing it would put a
 * picture in front of somebody whose only possible answer is "fine".
 * ═════════════════════════════════════════════════════════════════════════════
 */
class ClientPhotoTest extends TestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoWorkforce();

        $this->client = Client::firstOrFail();

        $this->actingAs(User::where('client_ref', $this->client->reference)->firstOrFail());
    }

    public function test_a_client_may_set_their_own_picture(): void
    {
        $this->post('/client/profile/photo', ['photo' => $this->png('x')])
            ->assertRedirect('/client/profile');

        $path = $this->client->fresh()->photo_path;

        $this->assertNotNull($path);
        $this->assertTrue(app(DocumentStore::class)->exists($path));
    }

    public function test_the_metadata_does_not_reach_the_disk(): void
    {
        /*
         * The same cleaning as the staff photo, and the same reason: a picture
         * taken on a phone carries the coordinates of wherever it was taken.
         * Nothing about the uploader being a company changes that.
         */
        $secret = 'taken at the office';

        $this->post('/client/profile/photo', ['photo' => $this->png($secret)])->assertRedirect();

        $stored = \Illuminate\Support\Facades\Storage::disk('local')
            ->get($this->client->fresh()->photo_path);

        $this->assertStringNotContainsString($secret, $stored);
        $this->assertStringNotContainsString('tEXt', $stored);
        // And it is still a PNG with pixels in it.
        $this->assertStringContainsString('IDAT', $stored);
    }

    public function test_it_is_not_in_the_webroot_and_needs_the_route(): void
    {
        $this->post('/client/profile/photo', ['photo' => $this->png('x')])->assertRedirect();

        $path = $this->client->fresh()->photo_path;

        $this->assertStringNotContainsString('public', $path);
        $this->assertStringNotContainsString($path, $this->get('/client/profile')->getContent());

        $this->get('/client/profile/photo')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_the_route_takes_no_identifier(): void
    {
        /*
         * The portal's whole rule. A route carrying a client reference would
         * make "whose picture may I see" a question this controller answers,
         * and the answer would be decided by a URL somebody can edit.
         */
        $route = collect(app('router')->getRoutes())
            ->first(fn ($r) => $r->getName() === 'client.profile.photo.show');

        $this->assertSame([], $route->parameterNames());
    }

    public function test_replacing_it_removes_the_old_file(): void
    {
        $this->post('/client/profile/photo', ['photo' => $this->png('first')])->assertRedirect();
        $first = $this->client->fresh()->photo_path;

        $this->post('/client/profile/photo', ['photo' => $this->png('second')])->assertRedirect();
        $second = $this->client->fresh()->photo_path;

        $this->assertNotSame($first, $second);
        $this->assertFalse(app(DocumentStore::class)->exists($first));
        $this->assertTrue(app(DocumentStore::class)->exists($second));
    }

    public function test_a_file_that_is_not_an_image_is_refused_whatever_it_is_called(): void
    {
        // The extension is whatever somebody typed. The content is checked.
        $this->post('/client/profile/photo', [
            'photo' => UploadedFile::fake()->createWithContent('logo.png', '<?php echo "hello"; ?>'),
        ])->assertSessionHasErrors('photo');

        $this->assertNull($this->client->fresh()->photo_path);
    }

    public function test_no_picture_is_a_404(): void
    {
        $this->get('/client/profile/photo')->assertNotFound();
    }

    /**
     * A one-pixel PNG with a tEXt chunk carrying the given string.
     */
    protected function png(string $secret): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $text = 'Comment'."\x00".$secret;
        $chunk = pack('N', strlen($text)).'tEXt'.$text.pack('N', crc32('tEXt'.$text));

        // After IHDR, which is where a writer puts one.
        return UploadedFile::fake()->createWithContent(
            'logo.png',
            substr($png, 0, 33).$chunk.substr($png, 33),
        );
    }
}
