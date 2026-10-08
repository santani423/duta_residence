<?php

namespace Tests\Feature;

use App\Models\CollectionAccountState;
use App\Models\Cluster;
use App\Models\CollectorAssignment;
use App\Models\CollectorProfile;
use App\Models\PaymentTransaction;
use App\Models\Resident;
use App\Models\SupervisorAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Rules\ActiveCollector;
use App\Rules\CollectorUser;
use App\Services\CollectionAccountRefreshQueue;
use App\Services\CollectionScopeService;
use App\Services\CollectorAssignmentService;
use App\Services\SupervisorAssignmentService;
use App\Support\Pagination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CollectionScopeTest extends TestCase
{
    use RefreshDatabase;

    private CollectionScopeService $scope;

    private Unit $unitQ;

    private Unit $unitQ2;

    private Unit $unitR;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->scope = app(CollectionScopeService::class);

        // Dua cluster baru yang tidak disentuh data seed, supaya cakupan bisa diverifikasi pasti.
        Cluster::query()->create(['id' => 'ZQ', 'name' => 'Cluster Uji Q', 'monthly_rate' => 100000]);
        Cluster::query()->create(['id' => 'ZR', 'name' => 'Cluster Uji R', 'monthly_rate' => 100000]);

        $this->unitQ = $this->makeUnit('ZQ001', 'ZQ', 'A');
        $this->unitQ2 = $this->makeUnit('ZQ002', 'ZQ', 'B');
        $this->unitR = $this->makeUnit('ZR001', 'ZR', 'A');

        // Buang antrean refresh dari proses seed supaya assert observer hanya melihat efek test.
        app(CollectionAccountRefreshQueue::class)->flush();
    }

    private function makeUnit(string $id, string $clusterId, string $block): Unit
    {
        $resident = Resident::factory()->create();

        return Unit::factory()->create([
            'id' => $id, 'cluster_id' => $clusterId, 'block' => $block, 'resident_id' => $resident->id,
        ]);
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['is_active' => true, ...$attributes]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCollector(array $attributes = [], ?string $accountStatus = null): User
    {
        $collector = $this->makeUser('collector', $attributes);

        if ($accountStatus !== null) {
            CollectorProfile::query()->create([
                'user_id' => $collector->id,
                'collector_code' => 'COL-T'.$collector->id,
                'account_status' => $accountStatus,
            ]);
        }

        return $collector;
    }

    private function assign(User $collector, array $scope): CollectorAssignment
    {
        return CollectorAssignment::query()->create([
            'collector_id' => $collector->id, 'is_active' => true, 'status' => 'active', ...$scope,
        ]);
    }

    private function assertForbidden(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Seharusnya ditolak 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_full_scope_roles_see_everything_including_collectors_without_assignment(): void
    {
        $unassigned = $this->makeCollector();

        foreach (['root', 'admin_estate', 'property_manager'] as $role) {
            $user = $this->makeUser($role);

            $this->assertTrue($this->scope->isFullScope($user), $role);
            $this->assertNull($this->scope->clusterIdsFor($user), $role);
            $this->assertNull($this->scope->collectorIdsFor($user), $role);
            $this->assertContains($unassigned->id, $this->scope->allCollectorsQuery($user)->pluck('users.id')->all(), $role);
            $this->assertSame(Unit::query()->count(), $this->scope->constrainUnits(Unit::query(), $user, 'units.id')->count(), $role);

            $this->scope->assertCollectorInScope($user, $unassigned->id);
            $this->scope->assertUnitInScope($user, $this->unitR->id);
            $this->scope->assertClusterInScope($user, null);
        }

        $this->assertTrue(app(SupervisorAssignmentService::class)->hasFullScope($this->makeUser('property_manager')));
    }

    public function test_supervisor_scope_is_limited_to_assigned_clusters_including_resident_scope_collectors(): void
    {
        $supervisor = $this->makeUser('supervisor');
        SupervisorAssignment::query()->create(['supervisor_id' => $supervisor->id, 'cluster_id' => 'ZQ', 'is_active' => true, 'status' => 'active']);

        $clusterCollector = $this->makeCollector();
        $this->assign($clusterCollector, ['scope_type' => 'cluster', 'cluster_id' => 'ZQ']);
        $residentCollector = $this->makeCollector();
        $this->assign($residentCollector, ['scope_type' => 'resident', 'resident_id' => $this->unitQ->resident_id]);
        $unitCollector = $this->makeCollector();
        $this->assign($unitCollector, ['scope_type' => 'unit', 'unit_id' => $this->unitQ2->id]);
        $otherCollector = $this->makeCollector();
        $this->assign($otherCollector, ['scope_type' => 'cluster', 'cluster_id' => 'ZR']);
        $idleCollector = $this->makeCollector();

        $this->assertFalse($this->scope->isFullScope($supervisor));
        $this->assertSame(['ZQ'], $this->scope->clusterIdsFor($supervisor));

        $collectorIds = $this->scope->collectorIdsFor($supervisor);
        $this->assertEqualsCanonicalizing([$clusterCollector->id, $residentCollector->id, $unitCollector->id], $collectorIds);
        $this->assertEqualsCanonicalizing($collectorIds, $this->scope->allCollectorsQuery($supervisor)->pluck('users.id')->all());
        $this->assertNotContains($idleCollector->id, $collectorIds);

        $units = $this->scope->constrainUnits(Unit::query(), $supervisor, 'units.id')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->unitQ->id, $this->unitQ2->id], $units);

        $constrained = $this->scope->constrainCollectors(CollectorAssignment::query(), $supervisor, 'collector_id')->pluck('collector_id')->unique()->all();
        $this->assertNotContains($otherCollector->id, $constrained);

        $this->scope->assertCollectorInScope($supervisor, $residentCollector->id);
        $this->scope->assertUnitInScope($supervisor, $this->unitQ->id);
        $this->scope->assertClusterInScope($supervisor, 'ZQ');
        $this->assertTrue(app(SupervisorAssignmentService::class)->isCollectorAssigned($supervisor, $residentCollector->id));

        $this->assertForbidden(fn () => $this->scope->assertCollectorInScope($supervisor, $otherCollector->id));
        $this->assertForbidden(fn () => $this->scope->assertUnitInScope($supervisor, $this->unitR->id));
        $this->assertForbidden(fn () => $this->scope->assertClusterInScope($supervisor, 'ZR'));
        $this->assertForbidden(fn () => $this->scope->assertClusterInScope($supervisor, null));
    }

    public function test_supervisor_without_assignment_and_other_roles_see_nothing(): void
    {
        $supervisor = $this->makeUser('supervisor');
        $finance = $this->makeUser('finance');

        foreach ([$supervisor, $finance] as $user) {
            $this->assertSame([], $this->scope->clusterIdsFor($user));
            $this->assertSame([], $this->scope->collectorIdsFor($user));
            $this->assertSame(0, $this->scope->allCollectorsQuery($user)->count());
            $this->assertSame(0, $this->scope->constrainUnits(Unit::query(), $user, 'units.id')->count());
        }
    }

    public function test_collector_scope_is_limited_to_self_and_own_units(): void
    {
        $collector = $this->makeCollector();
        $this->assign($collector, ['scope_type' => 'unit', 'unit_id' => $this->unitQ->id]);
        $other = $this->makeCollector();

        $this->assertSame([$collector->id], $this->scope->collectorIdsFor($collector));
        $this->assertSame(['ZQ'], $this->scope->clusterIdsFor($collector));
        $this->assertSame([$collector->id], $this->scope->allCollectorsQuery($collector)->pluck('users.id')->all());
        $this->assertSame([$this->unitQ->id], $this->scope->constrainUnits(Unit::query(), $collector, 'units.id')->pluck('id')->all());

        $this->scope->assertCollectorInScope($collector, $collector->id);
        $this->scope->assertUnitInScope($collector, $this->unitQ->id);
        $this->assertForbidden(fn () => $this->scope->assertCollectorInScope($collector, $other->id));
        $this->assertForbidden(fn () => $this->scope->assertUnitInScope($collector, $this->unitQ2->id));
    }

    public function test_deactivated_staff_token_is_rejected_with_account_inactive(): void
    {
        $supervisor = $this->makeUser('supervisor');
        $token = $supervisor->createToken('api-token')->plainTextToken;

        $supervisor->forceFill(['is_active' => false])->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/collectors')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code', 'ACCOUNT_INACTIVE');
    }

    public function test_active_staff_passes_active_user_middleware(): void
    {
        Sanctum::actingAs($this->makeUser('supervisor'));

        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_active_collector_rule_rejects_non_collector_inactive_and_trashed_users(): void
    {
        $passes = fn ($value, $rule) => Validator::make(['collector_id' => $value], ['collector_id' => [$rule]])->passes();

        $active = $this->makeCollector([], CollectorProfile::STATUS_ACTIVE);
        $legacyWithoutProfile = $this->makeCollector();
        $suspended = $this->makeCollector([], CollectorProfile::STATUS_SUSPENDED);
        $inactiveUser = $this->makeCollector(['is_active' => false], CollectorProfile::STATUS_ACTIVE);
        $trashed = $this->makeCollector([], CollectorProfile::STATUS_ACTIVE);
        $trashed->delete();
        $nonCollector = $this->makeUser('finance');

        $this->assertTrue($passes($active->id, new ActiveCollector));
        $this->assertTrue($passes((string) $active->id, new ActiveCollector));
        $this->assertTrue($passes($legacyWithoutProfile->id, new ActiveCollector));
        $this->assertFalse($passes($suspended->id, new ActiveCollector));
        $this->assertFalse($passes($inactiveUser->id, new ActiveCollector));
        $this->assertFalse($passes($trashed->id, new ActiveCollector));
        $this->assertFalse($passes($nonCollector->id, new ActiveCollector));
        $this->assertFalse($passes('abc', new ActiveCollector));
        $this->assertFalse($passes(999999, new ActiveCollector));

        // CollectorUser: collector non-aktif tetap boleh (filter/target/laporan), non-collector & terhapus tidak.
        $this->assertTrue($passes($suspended->id, new CollectorUser));
        $this->assertTrue($passes($inactiveUser->id, new CollectorUser));
        $this->assertFalse($passes($trashed->id, new CollectorUser));
        $this->assertFalse($passes($nonCollector->id, new CollectorUser));
    }

    public function test_assignment_observer_refreshes_primary_collector_in_account_state(): void
    {
        $queue = app(CollectionAccountRefreshQueue::class);
        $collector = $this->makeCollector();
        $clusterCollector = $this->makeCollector();

        $assignment = $this->assign($collector, ['scope_type' => 'unit', 'unit_id' => $this->unitQ->id]);
        $queue->flush();
        $this->assertSame($collector->id, CollectionAccountState::query()->find($this->unitQ->id)?->collector_id);

        // Pindah scope: unit lama dan unit baru sama-sama di-refresh.
        $assignment->update(['unit_id' => $this->unitQ2->id]);
        $queue->flush();
        $this->assertNull(CollectionAccountState::query()->find($this->unitQ->id)->collector_id);
        $this->assertSame($collector->id, CollectionAccountState::query()->find($this->unitQ2->id)->collector_id);

        // Dibatalkan → collector utama hilang.
        $assignment->update(['is_active' => false, 'status' => CollectorAssignment::STATUS_CANCELLED, 'end_date' => now()->toDateString()]);
        $queue->flush();
        $this->assertNull(CollectionAccountState::query()->find($this->unitQ2->id)->collector_id);

        // Scope cluster mencakup semua unit di cluster; dihapus → kembali kosong.
        $clusterAssignment = $this->assign($clusterCollector, ['scope_type' => 'cluster', 'cluster_id' => 'ZQ']);
        $queue->flush();
        $this->assertSame($clusterCollector->id, CollectionAccountState::query()->find($this->unitQ->id)->collector_id);
        $this->assertSame($clusterCollector->id, CollectionAccountState::query()->find($this->unitQ2->id)->collector_id);
        $this->assertNull(CollectionAccountState::query()->find($this->unitR->id)?->collector_id);

        $clusterAssignment->delete();
        $queue->flush();
        $this->assertNull(CollectionAccountState::query()->find($this->unitQ->id)->collector_id);
    }

    public function test_unit_ids_for_scope_matches_each_scope_type(): void
    {
        $service = app(CollectorAssignmentService::class);

        $this->assertEqualsCanonicalizing([$this->unitQ->id, $this->unitQ2->id], $service->unitIdsForScope(['scope_type' => 'cluster', 'cluster_id' => 'ZQ']));
        $this->assertSame([$this->unitQ2->id], $service->unitIdsForScope(['scope_type' => 'block', 'cluster_id' => 'ZQ', 'block' => 'B']));
        $this->assertSame([$this->unitR->id], $service->unitIdsForScope(['scope_type' => 'unit', 'unit_id' => $this->unitR->id]));
        $this->assertSame([$this->unitQ->id], $service->unitIdsForScope(['scope_type' => 'resident', 'resident_id' => $this->unitQ->resident_id]));
        $this->assertEqualsCanonicalizing([$this->unitQ->id, $this->unitR->id], $service->unitIdsForScope(['scope_type' => 'units', 'unit_ids' => [$this->unitQ->id, $this->unitR->id, 'XX999']]));
        $this->assertSame([], $service->unitIdsForScope(['scope_type' => 'block', 'cluster_id' => 'ZQ']));
    }

    public function test_supervisor_payments_is_empty_when_scope_is_empty(): void
    {
        if (PaymentTransaction::query()->count() === 0) {
            PaymentTransaction::query()->create([
                'transaction_number' => 'TRX-SCOPE-1', 'invoice_number' => 'INV-SCOPE-1', 'unit_id' => $this->unitQ->id,
                'subtotal' => 100000, 'total' => 100000, 'payment_provider' => 'loket', 'status' => 'paid',
                'paid_at' => now(), 'created_by' => User::where('username', 'root')->value('id'),
            ]);
        }
        $this->assertGreaterThan(0, PaymentTransaction::query()->count());

        Sanctum::actingAs($this->makeUser('supervisor'));

        $this->getJson('/api/v1/supervisor/payments')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_pagination_per_page_is_clamped(): void
    {
        $this->assertSame(15, Pagination::perPage(Request::create('/x')));
        $this->assertSame(100, Pagination::perPage(Request::create('/x', 'GET', ['per_page' => 100000])));
        $this->assertSame(15, Pagination::perPage(Request::create('/x', 'GET', ['per_page' => 0])));
        $this->assertSame(25, Pagination::perPage(Request::create('/x', 'GET', ['per_page' => 25])));
    }
}
