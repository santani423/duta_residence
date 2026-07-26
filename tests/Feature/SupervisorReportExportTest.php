<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupervisorReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_collector_performance_report_processes_synchronously_in_tests_and_becomes_downloadable(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $exportId = $this->postJson('/api/v1/report-exports', [
            'type' => 'collector_performance',
        ])->assertCreated()->json('data.id');

        // QUEUE_CONNECTION=sync in phpunit.xml, so the job already ran by the time we check.
        $this->assertDatabaseHas('report_exports', ['id' => $exportId, 'status' => 'completed']);

        $this->getJson("/api/v1/report-exports/{$exportId}")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->getJson("/api/v1/report-exports/{$exportId}/download")->assertOk();
    }
}
