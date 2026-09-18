<?php

namespace Tests\Feature;

use App\Models\NotificationQueue;
use App\Models\SupervisorNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guards against IDOR on the notification endpoints: a user must never be able to
 * read, mark-read, or otherwise act on a notification that isn't theirs, even when
 * they know (or guess) its numeric id.
 */
class NotificationAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_index_never_returns_another_staff_members_private_notification(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();
        $finance2 = User::where('username', 'finance2')->first();

        $mine = NotificationQueue::factory()->unread()->create(['user_id' => $finance->id, 'type' => 'private_to_finance']);
        $othersPrivate = NotificationQueue::factory()->unread()->create(['user_id' => $finance2->id, 'type' => 'private_to_finance2']);

        Sanctum::actingAs($finance);
        // per_page large enough to cover seed data + our two fixtures, so pagination
        // doesn't hide the assertion either way.
        $response = $this->getJson('/api/v1/notifications?per_page=1000')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($othersPrivate->id));
    }

    public function test_staff_cannot_mark_another_staff_members_private_notification_as_read(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();
        $finance2 = User::where('username', 'finance2')->first();

        $othersNotification = NotificationQueue::factory()->unread()->create(['user_id' => $finance2->id]);

        Sanctum::actingAs($finance);
        $this->postJson("/api/v1/notifications/{$othersNotification->id}/read")->assertNotFound();

        $this->assertDatabaseHas('notification_queues', [
            'id' => $othersNotification->id,
            'read_status' => 'unread',
        ]);
    }

    public function test_staff_can_mark_own_or_global_notification_as_read(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();

        $own = NotificationQueue::factory()->unread()->create(['user_id' => $finance->id]);
        $global = NotificationQueue::factory()->unread()->create(['user_id' => null, 'recipient' => NotificationQueue::STAFF_RECIPIENT]);

        Sanctum::actingAs($finance);
        $this->postJson("/api/v1/notifications/{$own->id}/read")->assertOk();
        $this->postJson("/api/v1/notifications/{$global->id}/read")->assertOk();

        $this->assertDatabaseHas('notification_queues', ['id' => $own->id, 'read_status' => 'read']);
        $this->assertDatabaseHas('notification_queues', ['id' => $global->id, 'read_status' => 'read']);
    }

    public function test_mark_all_as_read_only_touches_the_authenticated_users_notifications(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();
        $finance2 = User::where('username', 'finance2')->first();

        $mine = NotificationQueue::factory()->unread()->create(['user_id' => $finance->id]);
        $others = NotificationQueue::factory()->unread()->create(['user_id' => $finance2->id]);

        Sanctum::actingAs($finance);
        $this->postJson('/api/v1/notifications/read-all')->assertOk();

        $this->assertDatabaseHas('notification_queues', ['id' => $mine->id, 'read_status' => 'read']);
        $this->assertDatabaseHas('notification_queues', ['id' => $others->id, 'read_status' => 'unread']);
    }

    public function test_supervisor_cannot_act_on_a_notification_scoped_to_another_supervisors_collector(): void
    {
        $this->seed();
        $rina = User::where('username', 'supervisor.rina')->first();
        $dewiCollector = User::where('username', 'kolektor.dewi')->first();

        // kolektor.dewi is only assigned to supervisor.hendra's cluster (DA), never rina's.
        $hendraOnly = SupervisorNotification::query()->create([
            'category' => 'ptp_due',
            'priority' => 'high',
            'title' => 'Test - scoped to hendra collector',
            'description' => 'Should be invisible/unactionable to other supervisors.',
            'related_collector_id' => $dewiCollector->id,
            'read_status' => 'unread',
            'handled_status' => SupervisorNotification::HANDLED_OPEN,
        ]);

        Sanctum::actingAs($rina);

        $this->postJson("/api/v1/supervisor-notifications/{$hendraOnly->id}/read")->assertNotFound();
        $this->postJson("/api/v1/supervisor-notifications/{$hendraOnly->id}/handled")->assertNotFound();
        $this->postJson("/api/v1/supervisor-notifications/{$hendraOnly->id}/escalate", [
            'escalated_to' => $rina->id,
        ])->assertNotFound();

        $this->assertDatabaseHas('supervisor_notifications', [
            'id' => $hendraOnly->id,
            'read_status' => 'unread',
            'handled_status' => SupervisorNotification::HANDLED_OPEN,
        ]);

        // Sanity check: the index listing rina already trusts also excludes it.
        $index = $this->getJson('/api/v1/supervisor-notifications')->assertOk();
        $this->assertFalse(collect($index->json('data'))->pluck('id')->contains($hendraOnly->id));
    }

    public function test_supervisor_can_act_on_a_notification_scoped_to_their_own_collector(): void
    {
        $this->seed();
        $rina = User::where('username', 'supervisor.rina')->first();
        $budiCollector = User::where('username', 'kolektor.budi')->first();

        // kolektor.budi is assigned to cluster AL, which supervisor.rina oversees.
        $mine = SupervisorNotification::query()->create([
            'category' => 'ptp_due',
            'priority' => 'high',
            'title' => 'Test - scoped to rina collector',
            'description' => 'Should be actionable by rina.',
            'related_collector_id' => $budiCollector->id,
            'read_status' => 'unread',
            'handled_status' => SupervisorNotification::HANDLED_OPEN,
        ]);

        Sanctum::actingAs($rina);
        $this->postJson("/api/v1/supervisor-notifications/{$mine->id}/read")->assertOk();

        $this->assertDatabaseHas('supervisor_notifications', [
            'id' => $mine->id,
            'read_status' => 'read',
        ]);
    }

    public function test_supervisor_detail_unread_notification_count_is_scoped_to_that_supervisors_collectors(): void
    {
        $this->seed();
        $root = User::where('username', 'root')->first();
        $rina = User::where('username', 'supervisor.rina')->first();
        $hendra = User::where('username', 'supervisor.hendra')->first();
        $budiCollector = User::where('username', 'kolektor.budi')->first();

        Sanctum::actingAs($root);

        // Baselines include whatever global (related_collector_id null) unread
        // notifications the seeder already created - those legitimately count for
        // every supervisor, so we assert the delta rather than an absolute number.
        $rinaBefore = $this->getJson("/api/v1/supervisors/{$rina->id}")->assertOk()->json('data.summary.unread_notifications');
        $hendraBefore = $this->getJson("/api/v1/supervisors/{$hendra->id}")->assertOk()->json('data.summary.unread_notifications');

        SupervisorNotification::query()->create([
            'category' => 'ptp_due',
            'priority' => 'high',
            'title' => 'Test - count scoping',
            'description' => 'Only rina should count this.',
            'related_collector_id' => $budiCollector->id,
            'read_status' => 'unread',
            'handled_status' => SupervisorNotification::HANDLED_OPEN,
        ]);

        $rinaAfter = $this->getJson("/api/v1/supervisors/{$rina->id}")->assertOk()->json('data.summary.unread_notifications');
        $hendraAfter = $this->getJson("/api/v1/supervisors/{$hendra->id}")->assertOk()->json('data.summary.unread_notifications');

        $this->assertSame($rinaBefore + 1, $rinaAfter);
        $this->assertSame($hendraBefore, $hendraAfter);
    }
}
