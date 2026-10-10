<?php

use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// SEC-01: avatar uploads must never keep a client-chosen extension or land in the web root.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    Storage::fake('local');
    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
});

/** Real file on disk so MIME detection reads the bytes (fake uploads derive it from the name). */
function avatarTestUpload(string $clientName, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'avt');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $clientName, null, null, true);
}

function avatarTestPng(string $trailer = ''): string
{
    $img = imagecreatetruecolor(8, 8);
    ob_start();
    imagepng($img);
    imagedestroy($img);

    return ob_get_clean() . $trailer;
}

test('an image polyglot uploaded as .html is stored privately under a content-derived extension', function () {
    $publicDir = public_path('uploads/avatars');
    $before = is_dir($publicDir) ? scandir($publicDir) : [];

    // Valid PNG followed by markup, with an attacker-chosen extension that the framework does not block.
    $file = avatarTestUpload('xss.html', avatarTestPng('<script>alert(document.cookie)</script>'));

    $this->actingAs($this->cashier)
        ->post(route('admin.profile.avatar'), ['avatar' => $file])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $path = $this->cashier->fresh()->avatar;

    expect($path)->toStartWith('avatars/')
        ->and($path)->toEndWith('.png')
        ->and($path)->not->toContain('html')
        ->and($path)->not->toContain('xss');
    Storage::disk('local')->assertExists($path);

    $after = is_dir($publicDir) ? scandir($publicDir) : [];
    expect($after)->toBe($before);
});

test('php-family client extensions are rejected', function () {
    $this->actingAs($this->cashier)
        ->post(route('admin.profile.avatar'), ['avatar' => avatarTestUpload('shell.php', avatarTestPng())])
        ->assertSessionHasErrors('avatar');

    expect($this->cashier->fresh()->avatar)->toBeNull();
});

test('non-image content is rejected even with an image extension', function () {
    $this->actingAs($this->cashier)
        ->post(route('admin.profile.avatar'), ['avatar' => avatarTestUpload('avatar.png', '<?php echo "x"; ?>')])
        ->assertSessionHasErrors('avatar');

    expect($this->cashier->fresh()->avatar)->toBeNull();
    expect(Storage::disk('local')->allFiles('avatars'))->toBe([]);
});

test('stored avatar is served to authenticated users with nosniff and hidden from guests', function () {
    $this->actingAs($this->cashier)
        ->post(route('admin.profile.avatar'), ['avatar' => UploadedFile::fake()->image('me.jpg')]);

    $url = route('admin.profile.avatar.show', $this->cashier);

    $this->get($url)
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    auth()->logout();
    $this->get($url)->assertRedirect(route('admin.login'));
});

test('replacing an avatar deletes the previous stored file', function () {
    $this->actingAs($this->cashier)->post(route('admin.profile.avatar'), ['avatar' => UploadedFile::fake()->image('a.png')]);
    $first = $this->cashier->fresh()->avatar;

    $this->actingAs($this->cashier)->post(route('admin.profile.avatar'), ['avatar' => UploadedFile::fake()->image('b.png')]);
    $second = $this->cashier->fresh()->avatar;

    expect($second)->not->toBe($first);
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);
});

test('avatar url only trusts legacy names that match the old image pattern', function () {
    $user = new User(['avatar' => 'avatar_5_1727000000.jpg']);
    expect($user->avatarUrl())->toEndWith('uploads/avatars/avatar_5_1727000000.jpg');

    $user = new User(['avatar' => 'avatar_5_1727000000.php']);
    expect($user->avatarUrl())->toEndWith('assets/images/users/avatar-1.jpg');

    $user = new User(['avatar' => null]);
    expect($user->avatarUrl())->toEndWith('assets/images/users/avatar-1.jpg');
});
