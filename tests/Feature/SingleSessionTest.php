<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Satu akun = satu sesi login aktif. Login baru menggantikan sesi lama di perangkat mana pun,
 * dan token lama ditolak di semua route yang memakai autentikasi Sanctum.
 */
class SingleSessionTest extends TestCase
{
    use RefreshDatabase;

    private const REVOKED_MESSAGE = 'Sesi Anda telah berakhir karena akun ini login pada perangkat lain.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_on_second_device_ends_the_first_session(): void
    {
        $device1 = $this->login('root');
        $this->getWith($device1, '/api/v1/auth/me')->assertOk();

        $device2 = $this->login('root');

        $this->getWith($device2, '/api/v1/auth/me')->assertOk();
        $this->getWith($device1, '/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'SESSION_REVOKED')
            ->assertJsonPath('message', self::REVOKED_MESSAGE);

        // Menolak token lama tidak boleh ikut mematikan sesi yang aktif.
        $this->getWith($device2, '/api/v1/auth/me')->assertOk();
    }

    public function test_superseded_token_is_rejected_on_staff_and_resident_routes(): void
    {
        $staffOld = $this->login('root');
        $this->login('root');

        $this->getWith($staffOld, '/api/v1/clusters')->assertUnauthorized()->assertJsonPath('code', 'SESSION_REVOKED');

        $customer = User::factory()->create(['username' => 'resident.single', 'password' => 'password']);
        $customer->assignRole('customer');

        $residentOld = $this->login('resident.single');
        $residentNew = $this->login('resident.single');

        $this->getWith($residentOld, '/api/v1/resident/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SESSION_REVOKED');
        $this->assertNotSame(401, $this->getWith($residentNew, '/api/v1/resident/dashboard')->getStatusCode());
    }

    public function test_logout_on_active_device_does_not_reactivate_the_superseded_session(): void
    {
        $device1 = $this->login('root');
        $device2 = $this->login('root');

        $this->postWith($device2, '/api/v1/auth/logout')->assertOk();

        $this->getWith($device2, '/api/v1/auth/me')->assertUnauthorized();
        $this->getWith($device1, '/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SESSION_REVOKED');

        // Login ulang kembali menghasilkan satu sesi aktif yang valid.
        $device3 = $this->login('root');
        $this->getWith($device3, '/api/v1/auth/me')->assertOk();
    }

    public function test_different_accounts_can_stay_logged_in_at_the_same_time(): void
    {
        $root = $this->login('root');
        $loket = $this->login('loket');

        $this->getWith($root, '/api/v1/auth/me')->assertOk();
        $this->getWith($loket, '/api/v1/auth/me')->assertOk();
    }

    public function test_repeated_logins_leave_only_the_latest_token_as_the_active_session(): void
    {
        $tokens = [$this->login('root'), $this->login('root'), $this->login('root')];

        $activeId = User::where('username', 'root')->value('active_token_id');
        $this->assertNotNull($activeId);

        $this->getWith($tokens[0], '/api/v1/auth/me')->assertJsonPath('code', 'SESSION_REVOKED');
        $this->getWith($tokens[1], '/api/v1/auth/me')->assertJsonPath('code', 'SESSION_REVOKED');
        $this->getWith($tokens[2], '/api/v1/auth/me')->assertOk();
    }

    public function test_token_never_registered_as_the_active_session_is_rejected(): void
    {
        // Token yang dibuat di luar alur login tidak punya sesi aktif, jadi harus ditolak (fail-closed).
        $token = User::where('username', 'root')->first()->createToken('manual')->plainTextToken;

        $this->getWith($token, '/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SESSION_REVOKED');
    }

    private function login(string $username, string $password = 'password'): string
    {
        return $this->postJson('/api/v1/auth/login', ['username' => $username, 'password' => $password])
            ->assertOk()
            ->json('data.token');
    }

    // Reset cache guard di antara request supaya setiap request benar-benar memverifikasi tokennya.
    private function getWith(string $token, string $uri)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson($uri);
    }

    private function postWith(string $token, string $uri)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson($uri);
    }
}
