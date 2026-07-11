<?php

namespace Database\Seeders;

use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('username', 'admin.estate')->first() ?: User::where('username', 'root')->first();
        $districts = ['367101', '367102', '367103'];
        $unitProfiles = [
            ['type' => 'B', 'building' => 36, 'land' => 72],
            ['type' => 'B', 'building' => 45, 'land' => 90],
            ['type' => 'B', 'building' => 60, 'land' => 120],
            ['type' => 'B', 'building' => 90, 'land' => 160],
            ['type' => 'P', 'building' => 120, 'land' => 200],
        ];

        foreach (EstateSeeder::CLUSTERS as $clusterIndex => $cluster) {
            for ($i = 1; $i <= 30; $i++) {
                $id = $cluster['id'].str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $profile = $unitProfiles[($i + $clusterIndex) % count($unitProfiles)];
                $statusId = 'AK';
                $occupancyId = '1';
                $notes = $this->unitScenario($i, $cluster['profile']);

                if ($i >= 27 && $i <= 28) {
                    $statusId = 'RK';
                    $occupancyId = '2';
                    $notes .= ' Unit kosong untuk skenario ketersediaan.';
                }
                if ($i === 29) {
                    $statusId = 'RK';
                    $occupancyId = '2';
                    $notes .= ' Unit sedang direnovasi.';
                }
                if ($i === 30) {
                    $statusId = 'TA';
                    $notes .= ' Penghuni lama nonaktif.';
                }

                $special = ResidentSeeder::SPECIAL[$id] ?? null;
                if ($special) {
                    $statusId = ($special['inactive'] ?? false) ? 'TA' : 'AK';
                    $occupancyId = '1';
                    $notes .= ' '.$special['scenario'];
                    $resident = Resident::where('id', 'RS'.$id)->first();
                } else {
                    $resident = Resident::updateOrCreate(['id' => 'RS'.$id], [
                        'name' => fake()->name(),
                        'phone' => '08'.str_pad((string) (1200000000 + $clusterIndex * 10000 + $i), 10, '0', STR_PAD_LEFT),
                        'telephone' => '021'.str_pad((string) (7300000 + $clusterIndex * 100 + $i), 7, '0', STR_PAD_LEFT),
                        'id_card_address' => 'Grand Duta Residence '.$cluster['name'].' Blok '.chr(65 + (($i - 1) % 6)).' No '.$i,
                        'district_id' => $districts[($i + $clusterIndex) % count($districts)],
                        'email' => strtolower($id).'@resident.example.com',
                        'created_by' => $creator?->id,
                        'updated_by' => $creator?->id,
                    ]);
                }

                $unit = Unit::updateOrCreate(['id' => $id], [
                    'resident_id' => $resident->id,
                    'cluster_id' => $cluster['id'],
                    'block' => chr(65 + (($i - 1) % 6)),
                    'lot_number' => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'property_type_id' => $profile['type'],
                    'building_area' => $profile['building'],
                    'land_area' => $profile['land'],
                    'handover_date' => now()->subMonths(4 + (($i + $clusterIndex) % 48))->toDateString(),
                    'occupancy_id' => $occupancyId,
                    'status_id' => $statusId,
                    'is_penalty_eligible' => $i % 9 !== 0,
                    'is_discount_eligible' => $i % 11 === 0,
                    'notes' => trim($notes),
                    'created_by' => $creator?->id,
                    'updated_by' => $creator?->id,
                ]);

                if ($statusId !== 'RK') {
                    $username = $id === 'GA012' ? 'resident' : 'resident.'.strtolower($id);
                    $user = User::updateOrCreate(['username' => $username], [
                        'name' => $resident->name,
                        'email' => $resident->email,
                        'phone' => $resident->phone,
                        'unit_id' => $unit->id,
                        'password' => Hash::make('password'),
                        'is_active' => $statusId === 'AK',
                        'theme_preference' => ['system', 'light', 'dark'][($i + $clusterIndex) % 3],
                        'language_preference' => 'id',
                        'notification_preferences' => [
                            'billing' => true,
                            'payments' => true,
                            'complaints' => $i % 3 !== 0,
                            'maintenance' => true,
                            'documents' => true,
                            'announcements' => true,
                        ],
                        'last_login_at' => now()->subDays(($i + $clusterIndex) % 25),
                        'last_login_ip' => '36.80.'.($clusterIndex + 10).'.'.($i + 20),
                    ]);
                    $user->syncRoles(['customer']);
                }
            }
        }

        $this->assignMultiUnitOwnershipDemo();
    }

    private function assignMultiUnitOwnershipDemo(): void
    {
        $multiOwner = Resident::find('RSAL001');

        if (! $multiOwner) {
            return;
        }

        $orphanedResidentIds = Unit::whereIn('id', ['BO001', 'CE001'])->pluck('resident_id')->diff([$multiOwner->id]);

        Unit::whereIn('id', ['BO001', 'CE001'])->update(['resident_id' => $multiOwner->id]);
        Resident::whereIn('id', $orphanedResidentIds)->doesntHave('units')->delete();
    }

    private function unitScenario(int $i, string $clusterProfile): string
    {
        $types = [
            'owner menempati unit sendiri',
            'owner menyewakan unit kepada tenant',
            'tenant aktif dengan masa sewa hampir selesai',
            'anggota keluarga sebagai representative',
            'riwayat pindah unit dari blok lama',
        ];

        return Str::ucfirst($types[$i % count($types)])."; {$clusterProfile}.";
    }
}
