<?php

namespace Database\Seeders;

use App\Models\MaintenanceRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class MaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $techs = User::role('technician')->get();
        $categories = ['Plumbing', 'Electrical', 'AC', 'Atap bocor', 'Pintu dan jendela', 'Jalan lingkungan', 'Lampu jalan', 'Saluran air', 'Fasilitas umum', 'Lainnya'];
        $statuses = ['submitted', 'scheduled', 'assigned', 'in_progress', 'completed', 'cancelled', 'rejected'];

        $fixed = [
            ['AL009', 'AC', 'high', 'scheduled'],
            ['AL004', 'Lampu jalan', 'normal', 'submitted'],
            ['AL007', 'Plumbing', 'normal', 'completed'],
        ];

        foreach ($fixed as [$unitId, $category, $urgency, $status]) {
            $this->createMaintenance(Unit::find($unitId), $category, $urgency, $status, $techs->random());
        }

        Unit::where('status_id', 'AK')->inRandomOrder()->limit(130)->get()->each(function (Unit $unit, int $i) use ($categories, $statuses, $techs) {
            $this->createMaintenance($unit, $categories[$i % count($categories)], ['low', 'normal', 'high', 'emergency'][$i % 4], $statuses[$i % count($statuses)], $techs->random());
        });
    }

    private function createMaintenance(?Unit $unit, string $category, string $urgency, string $status, ?User $technician): void
    {
        if (! $unit) {
            return;
        }

        $user = User::where('unit_id', $unit->id)->first();
        $scheduled = in_array($status, ['scheduled', 'assigned', 'in_progress'], true) ? now()->addDays(rand(1, 7)) : null;
        $completed = $status === 'completed' ? now()->subDays(rand(1, 14)) : null;

        MaintenanceRequest::updateOrCreate(
            ['unit_id' => $unit->id, 'category' => $category, 'description' => "Permintaan {$category} untuk unit {$unit->block}/{$unit->lot_number}"],
            [
                'user_id' => $user?->id,
                'unit_label' => "{$unit->cluster?->name} {$unit->block}/{$unit->lot_number}",
                'urgency' => $urgency,
                'preferred_schedule' => now()->addDays(rand(1, 12)),
                'status' => $status,
                'technician_name' => $scheduled || $completed ? $technician?->name : null,
                'technician_schedule' => $scheduled,
                'technician_notes' => $completed ? 'Pekerjaan selesai. Foto sebelum/sesudah tersedia di dokumen dummy.' : ($scheduled ? 'Teknisi sudah dijadwalkan.' : null),
                'rating' => $completed ? rand(3, 5) : null,
                'rating_notes' => $completed ? 'Penghuni memberi rating setelah pekerjaan selesai.' : null,
                'attachment_path' => 'dummy/maintenance/'.$unit->id.'-'.$category.'.jpg',
                'completed_at' => $completed,
                'created_by' => $user?->id,
                'updated_by' => $technician?->id,
            ]
        );
    }
}
