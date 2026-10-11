<?php

namespace Database\Seeders;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Seeder;

class ResidentSeeder extends Seeder
{
    /**
     * Penghuni diambil dari database/data/pelanggan.csv (lihat PelangganData). Sistem lama
     * mencatat satu pemilik per unit, jadi setiap unit berpemilik mendapat satu record
     * penghuni dengan id RS + kode unit (mis. RS001002 untuk unit 01002).
     */
    public function run(): void
    {
        $creator = User::where('username', 'admin.estate')->first() ?: User::where('username', 'root')->first();
        $now = now();

        $rows = collect(PelangganData::units())
            ->pluck('resident')
            ->filter()
            ->map(fn (array $resident) => $resident + [
                'district_id' => null,
                'is_active' => true,
                'created_by' => $creator?->id,
                'updated_by' => $creator?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values();

        foreach ($rows->chunk(500) as $chunk) {
            Resident::upsert(
                $chunk->all(),
                ['id'],
                ['name', 'phone', 'telephone', 'id_card_address', 'email', 'updated_by', 'updated_at']
            );
        }

        $skipped = PelangganData::skipped();
        if ($skipped && $this->command) {
            $this->command->warn(count($skipped).' baris pelanggan.csv tidak diimpor: '.implode('; ', $skipped));
        }
    }
}
