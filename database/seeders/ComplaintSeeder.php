<?php

namespace Database\Seeders;

use App\Models\CustomerComplaint;
use App\Models\CustomerComplaintComment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class ComplaintSeeder extends Seeder
{
    public function run(): void
    {
        $cs = User::where('username', 'cs')->first();
        $categories = ['Keamanan', 'Kebersihan', 'Kebisingan', 'Fasilitas', 'Parkir', 'Tetangga', 'Lingkungan', 'Administrasi', 'Tagihan', 'Lainnya'];
        $statuses = ['submitted', 'in_review', 'in_progress', 'resolved', 'closed', 'rejected'];

        $fixed = [
            ['AL004', 'Kebisingan renovasi malam hari', 'Lingkungan', 'urgent', 'in_progress'],
            ['AL007', 'Lampu taman mati', 'Fasilitas', 'normal', 'resolved'],
            ['AL012', 'Tagihan perlu klarifikasi', 'Tagihan', 'high', 'submitted'],
        ];

        foreach ($fixed as [$unitId, $title, $category, $priority, $status]) {
            $this->createComplaint(Unit::find($unitId), $title, $category, $priority, $status, $cs, 4);
        }

        Unit::where('status_id', 'AK')->inRandomOrder()->limit(120)->get()->each(function (Unit $unit, int $i) use ($categories, $statuses, $cs) {
            $status = $statuses[$i % count($statuses)];
            $priority = ['low', 'normal', 'high', 'urgent'][$i % 4];
            $title = "{$categories[$i % count($categories)]} - laporan {$unit->id}";
            $this->createComplaint($unit, $title, $categories[$i % count($categories)], $priority, $status, $cs, $i % 3);
        });
    }

    private function createComplaint(?Unit $unit, string $title, string $category, string $priority, string $status, ?User $staff, int $comments): void
    {
        if (! $unit) {
            return;
        }

        $user = User::where('unit_id', $unit->id)->first();
        $complaint = CustomerComplaint::updateOrCreate(
            ['unit_id' => $unit->id, 'title' => $title],
            [
                'user_id' => $user?->id,
                'category' => $category,
                'priority' => $priority,
                'description' => fake()->paragraph(3),
                'status' => $status,
                'attachment_path' => $comments > 2 ? 'dummy/complaints/photo-'.$unit->id.'.jpg' : null,
                'closed_at' => in_array($status, ['closed', 'resolved'], true) ? now()->subDays(rand(1, 10)) : null,
                'created_by' => $user?->id,
                'updated_by' => $staff?->id,
            ]
        );

        for ($i = 1; $i <= $comments; $i++) {
            CustomerComplaintComment::updateOrCreate(
                ['customer_complaint_id' => $complaint->id, 'body' => "Komentar demo {$i} untuk {$title}"],
                [
                    'user_id' => $i % 2 === 0 ? $staff?->id : $user?->id,
                    'is_staff_response' => $i % 2 === 0,
                    'attachment_path' => $i === 3 ? 'dummy/complaints/comment-'.$complaint->id.'.pdf' : null,
                ]
            );
        }
    }
}
