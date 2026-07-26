<?php

namespace Database\Seeders;

use App\Models\CollectionLetter;
use App\Models\CollectorAssignment;
use App\Models\CollectorLocation;
use App\Models\CollectorProfile;
use App\Models\CollectorTarget;
use App\Models\CollectorVisit;
use App\Models\CollectorVisitEvidence;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\ResidentComplaint;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for the Collector employee-management module. Deliberately avoids anything
 * slow or network-dependent (no PDF rendering, no real uploaded files, no external HTTP)
 * after LandingCmsSeeder's Unsplash-timeout lesson - collection letters are seeded with
 * pdf_path=null (CollectionLetterController::download already handles that gracefully),
 * and visit evidence is seeded as `gps`-only rows, which need no file on disk.
 *
 * Sample login credentials (password is 'password' for all, same convention as
 * AdminUserSeeder). Every account logs in with EITHER its username or its email.
 * Each covers a different account_status so QA can exercise every login scenario
 * without creating new accounts by hand:
 *
 *   username          email                            status     login should...
 *   kolektor.budi     kolektorbudi@grandduta.test       active     succeed (target met)
 *   kolektor.siti     kolektorsiti@grandduta.test       active     succeed (target met)
 *   kolektor.ahmad    kolektorahmad@grandduta.test      active     succeed (target missed)
 *   kolektor.dewi     kolektordewi@grandduta.test       active     succeed (target missed)
 *   kolektor.rudi     kolektorrudi@grandduta.test       inactive   be rejected (403)
 *   kolektor.maya     kolektormaya@grandduta.test       leave      be rejected (403)
 *   kolektor.eko      kolektoreko@grandduta.test        suspended  be rejected (403)
 */
class CollectorSeeder extends Seeder
{
    private int $sequence = 5000;

    public function run(): void
    {
        $admin = User::where('username', 'root')->first();

        $collectors = [
            $this->makeCollector('kolektor.budi', 'Budi Santoso', 'COL-0001', '081234500001', CollectorProfile::EMPLOYMENT_PERMANENT, CollectorProfile::STATUS_ACTIVE, $admin),
            $this->makeCollector('kolektor.siti', 'Siti Rahmawati', 'COL-0002', '081234500002', CollectorProfile::EMPLOYMENT_CONTRACT, CollectorProfile::STATUS_ACTIVE, $admin),
            $this->makeCollector('kolektor.ahmad', 'Ahmad Fauzi', 'COL-0003', '081234500003', CollectorProfile::EMPLOYMENT_PERMANENT, CollectorProfile::STATUS_ACTIVE, $admin),
            $this->makeCollector('kolektor.dewi', 'Dewi Lestari', 'COL-0004', '081234500004', CollectorProfile::EMPLOYMENT_DAILY, CollectorProfile::STATUS_ACTIVE, $admin),
            $this->makeCollector('kolektor.rudi', 'Rudi Hartono', 'COL-0005', '081234500005', CollectorProfile::EMPLOYMENT_PERMANENT, CollectorProfile::STATUS_INACTIVE, $admin),
            $this->makeCollector('kolektor.maya', 'Maya Anggraini', 'COL-0006', '081234500006', CollectorProfile::EMPLOYMENT_CONTRACT, CollectorProfile::STATUS_LEAVE, $admin),
            $this->makeCollector('kolektor.eko', 'Eko Prasetyo', 'COL-0007', '081234500007', CollectorProfile::EMPLOYMENT_PERMANENT, CollectorProfile::STATUS_SUSPENDED, $admin),
        ];
        [$budi, $siti, $ahmad, $dewi, $rudi, $maya, $eko] = $collectors;

        $auditService = app(AuditService::class);

        // --- Active assignments: cluster / block / unit / resident scopes ---
        $this->assign($budi, 'cluster', ['cluster_id' => 'AL'], $admin, 'high', 'Wilayah utama Cluster Alamanda.');
        $this->assign($siti, 'cluster', ['cluster_id' => 'BO'], $admin, 'normal', 'Wilayah utama Cluster Bougenville.');
        foreach (['CE001', 'CE007', 'CE013'] as $unitId) {
            $this->assign($ahmad, 'unit', ['unit_id' => $unitId], $admin, 'normal', 'Unit prioritas tunggakan lama.');
        }
        $this->assign($dewi, 'block', ['cluster_id' => 'DA', 'block' => 'A'], $admin, 'low', 'Blok A Cluster Dahlia.');
        $residentId = Unit::whereNotNull('resident_id')->where('cluster_id', 'ED')->first()?->resident_id;
        if ($residentId) {
            $this->assign($maya, 'resident', ['resident_id' => $residentId], $admin, 'normal', 'Penghuni VIP, tetap diikuti selama cuti.');
        }

        // --- History: a completed assignment (Rudi, before going inactive) and a transfer (Ahmad -> Eko -> back) ---
        $completed = CollectorAssignment::query()->firstOrCreate(
            ['collector_id' => $rudi->id, 'scope_type' => 'unit', 'unit_id' => 'GA001'],
            ['is_active' => false, 'status' => CollectorAssignment::STATUS_COMPLETED, 'start_date' => now()->subMonths(3)->toDateString(), 'end_date' => now()->subMonth()->toDateString(), 'priority' => 'normal', 'assigned_by' => $admin->id]
        );
        if ($completed->wasRecentlyCreated) {
            $auditService->log('collector_assignment_created', 'collector-assignments', 'CREATE', $completed, [], $completed->toArray());
        }

        $transferredOut = CollectorAssignment::query()->firstOrCreate(
            ['collector_id' => $eko->id, 'scope_type' => 'unit', 'unit_id' => 'GA007'],
            ['is_active' => false, 'status' => CollectorAssignment::STATUS_TRANSFERRED, 'start_date' => now()->subMonths(2)->toDateString(), 'end_date' => now()->subWeeks(2)->toDateString(), 'priority' => 'normal', 'assigned_by' => $admin->id, 'notes' => 'Dipindahkan karena status kepegawaian berubah.']
        );
        $transferredIn = CollectorAssignment::query()->firstOrCreate(
            ['collector_id' => $budi->id, 'scope_type' => 'unit', 'unit_id' => 'GA007'],
            ['is_active' => true, 'status' => CollectorAssignment::STATUS_ACTIVE, 'start_date' => now()->subWeeks(2)->toDateString(), 'priority' => 'normal', 'assigned_by' => $admin->id]
        );
        if ($transferredOut->wasRecentlyCreated) {
            $auditService->log('collector_assignment_transferred_out', 'collector-assignments', 'UPDATE', $transferredOut, [], $transferredOut->toArray());
        }
        if ($transferredIn->wasRecentlyCreated) {
            $auditService->log('collector_assignment_transferred_in', 'collector-assignments', 'CREATE', $transferredIn, [], $transferredIn->toArray());
        }

        // --- Targets: monthly, current period. Budi/Siti will meet it, Ahmad/Dewi won't. ---
        $periodStart = now()->startOf('month')->toDateString();
        $this->target($budi, $periodStart, 5_000_000, 20, $admin);
        $this->target($siti, $periodStart, 8_000_000, 25, $admin);
        $this->target($ahmad, $periodStart, 10_000_000, 15, $admin);
        $this->target($dewi, $periodStart, 4_000_000, 10, $admin);
        // A weekly and daily target too, for variety.
        $this->target($budi, now()->startOfWeek()->toDateString(), 1_200_000, 5, $admin, CollectorTarget::PERIOD_WEEKLY);
        $this->target($budi, now()->toDateString(), 300_000, 1, $admin, CollectorTarget::PERIOD_DAILY);

        // --- Loket payment transactions attributed to collectors, so CollectorPerformanceService reflects real achievement. ---
        $this->loketPayment($budi, 'AL001', 3_200_000);
        $this->loketPayment($budi, 'AL007', 2_600_000);
        $this->loketPayment($siti, 'BO001', 5_000_000);
        $this->loketPayment($siti, 'BO007', 4_100_000);
        $this->loketPayment($ahmad, 'CE001', 1_500_000); // well under target - "belum tercapai"

        // --- Field visits (with GPS-only evidence - no real files needed) ---
        $this->visit($budi, 'AL001', 'completed', 'Penagihan bulanan', -6.2088, 106.8456);
        $this->visit($budi, 'AL007', 'completed', 'Konfirmasi janji bayar', -6.2091, 106.8460);
        $this->visit($siti, 'BO001', 'no_answer', 'Penagihan bulanan', -6.2105, 106.8471);
        $this->visit($ahmad, 'CE001', 'refused', 'Penagihan tunggakan', -6.2120, 106.8480);
        $this->visit($dewi, 'DA001', 'rescheduled', 'Penagihan bulanan', -6.2130, 106.8490);

        // --- Promise to Pay ---
        $this->promise($budi, 'AL013', 1_800_000, 'pending');
        $this->promise($ahmad, 'CE007', 2_400_000, 'broken');
        $this->promise($dewi, 'DA007', 900_000, 'fulfilled');

        // --- Complaints about collection visits ---
        $this->complaint($budi, 'AL001', 'Kolektor datang di luar jam yang dijanjikan.', 'resolved');
        $this->complaint($ahmad, 'CE001', 'Nada bicara kolektor dianggap kurang sopan.', 'in_review');

        // --- Live-location pings (a few recent ones, for the "last known location" widget) ---
        $this->location($budi, -6.2088, 106.8456, now()->subMinutes(45));
        $this->location($budi, -6.2095, 106.8465, now()->subMinutes(10));
        $this->location($siti, -6.2105, 106.8471, now()->subMinutes(30));

        // --- Collection letters (no PDF rendering in seeders - pdf_path stays null) ---
        $this->letter($budi, 'AL001', 'reminder', 'Yth. Bapak/Ibu, kami informasikan bahwa terdapat tagihan IPL yang belum diselesaikan. Mohon segera melakukan pembayaran.');
        $this->letter($ahmad, 'CE001', 'warning', 'Surat peringatan kedua terkait tunggakan IPL yang belum diselesaikan lebih dari 2 bulan.');
    }

    private function makeCollector(string $username, string $name, string $code, string $whatsapp, string $employmentStatus, string $accountStatus, User $admin): User
    {
        $user = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => str_replace('.', '', $username).'@grandduta.test',
                'phone' => $whatsapp,
                'password' => Hash::make('password'),
                'is_active' => $accountStatus === CollectorProfile::STATUS_ACTIVE,
            ]
        );
        $user->syncRoles(['collector']);

        CollectorProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'collector_code' => $code,
                'whatsapp_number' => $whatsapp,
                'address' => 'Jl. Contoh Alamat No. '.random_int(1, 99).', Tangerang',
                'joined_at' => now()->subMonths(random_int(3, 36))->toDateString(),
                'employment_status' => $employmentStatus,
                'account_status' => $accountStatus,
                'working_area_notes' => null,
                'admin_notes' => $accountStatus === CollectorProfile::STATUS_SUSPENDED
                    ? 'Ditangguhkan sementara menunggu proses investigasi internal.'
                    : ($accountStatus === CollectorProfile::STATUS_LEAVE ? 'Cuti melahirkan, kembali bertugas bulan depan.' : null),
                'created_by' => $admin->id,
            ]
        );

        return $user->fresh();
    }

    private function assign(User $collector, string $scopeType, array $scope, User $admin, string $priority, string $notes): CollectorAssignment
    {
        $assignment = CollectorAssignment::query()->updateOrCreate(
            ['collector_id' => $collector->id, 'scope_type' => $scopeType, ...$scope],
            ['is_active' => true, 'status' => CollectorAssignment::STATUS_ACTIVE, 'start_date' => now()->subWeeks(4)->toDateString(), 'priority' => $priority, 'notes' => $notes, 'assigned_by' => $admin->id]
        );

        // Every collector should have at least one populated "Riwayat Penugasan" entry,
        // not just the two special history/transfer examples below - guarded so re-runs
        // don't append duplicate audit rows for an assignment that already existed.
        if ($assignment->wasRecentlyCreated) {
            app(AuditService::class)->log('collector_assignment_created', 'collector-assignments', 'CREATE', $assignment, [], $assignment->toArray());
        }

        return $assignment;
    }

    private function target(User $collector, string $periodStart, float $amount, int $visitTarget, User $admin, string $periodType = CollectorTarget::PERIOD_MONTHLY): CollectorTarget
    {
        return CollectorTarget::query()->updateOrCreate(
            ['collector_id' => $collector->id, 'period_type' => $periodType, 'period_start' => $periodStart],
            ['target_amount' => $amount, 'target_visit_count' => $visitTarget, 'created_by' => $admin->id]
        );
    }

    private function loketPayment(User $collector, string $unitId, float $total): PaymentTransaction
    {
        $number = 'COL-DEMO-'.str_pad((string) $this->sequence++, 6, '0', STR_PAD_LEFT);

        return PaymentTransaction::query()->updateOrCreate(
            ['transaction_number' => $number],
            [
                'invoice_number' => "INV-{$number}",
                'unit_id' => $unitId,
                'subtotal' => $total,
                'tax' => 0,
                'admin_fee' => 0,
                'total' => $total,
                'currency' => 'IDR',
                'payment_provider' => 'loket',
                'payment_method' => 'cash',
                'provider_reference' => "loket-demo-{$number}",
                'status' => 'paid',
                'paid_at' => now()->subDays(random_int(1, 10)),
                'created_by' => $collector->id,
            ]
        );
    }

    private function visit(User $collector, string $unitId, string $status, string $purpose, float $lat, float $lng): CollectorVisit
    {
        $visit = CollectorVisit::query()->firstOrCreate(
            ['collector_id' => $collector->id, 'unit_id' => $unitId, 'purpose' => $purpose],
            [
                'visit_date' => now()->subDays(random_int(1, 14)),
                'result' => $status === 'completed' ? 'Pembayaran diterima / dijadwalkan.' : 'Belum ada kepastian.',
                'met_with' => $status === 'completed' ? 'Pemilik unit' : null,
                'checkin_latitude' => $lat,
                'checkin_longitude' => $lng,
                'status' => $status,
                'next_visit_date' => $status !== 'completed' ? now()->addWeek()->toDateString() : null,
                'created_by' => $collector->id,
            ]
        );

        CollectorVisitEvidence::query()->firstOrCreate(
            ['visit_id' => $visit->id, 'type' => 'gps'],
            ['latitude' => $lat, 'longitude' => $lng, 'captured_at' => $visit->visit_date, 'uploaded_by' => $collector->id]
        );

        return $visit;
    }

    private function promise(User $collector, string $unitId, float $amount, string $status): PaymentPromise
    {
        return PaymentPromise::query()->firstOrCreate(
            ['unit_id' => $unitId, 'promised_amount' => $amount],
            [
                'promised_date' => now()->addDays(random_int(3, 10))->toDateString(),
                'payment_method' => 'transfer',
                'reason' => 'Menunggu gajian bulanan.',
                'follow_up_date' => now()->addDays(random_int(11, 20))->toDateString(),
                'status' => $status,
                'created_by' => $collector->id,
            ]
        );
    }

    private function complaint(User $collector, string $unitId, string $description, string $status): ResidentComplaint
    {
        $visit = CollectorVisit::where('collector_id', $collector->id)->where('unit_id', $unitId)->first();

        return ResidentComplaint::query()->firstOrCreate(
            ['unit_id' => $unitId, 'category' => 'penagihan', 'description' => $description],
            [
                'title' => 'Komplain proses penagihan',
                'priority' => 'normal',
                'status' => $status,
                'collector_id' => $collector->id,
                'related_visit_id' => $visit?->id,
                'created_by' => $collector->id,
            ]
        );
    }

    private function location(User $collector, float $lat, float $lng, \DateTimeInterface $recordedAt): CollectorLocation
    {
        // Keyed on the fixed coordinates (not the relative-to-now recordedAt) so a
        // re-run updates the same demo ping instead of inserting a new one each time.
        return CollectorLocation::query()->updateOrCreate(
            ['collector_id' => $collector->id, 'latitude' => $lat, 'longitude' => $lng],
            ['recorded_at' => $recordedAt, 'accuracy_meters' => random_int(5, 30)]
        );
    }

    private function letter(User $collector, string $unitId, string $type, string $content): CollectionLetter
    {
        $unit = Unit::find($unitId);

        return CollectionLetter::query()->firstOrCreate(
            ['unit_id' => $unitId, 'letter_type' => $type],
            [
                'resident_id' => $unit->resident_id,
                'content' => $content,
                'pdf_path' => null,
                'generated_by' => $collector->id,
                'generated_at' => now()->subDays(random_int(1, 5)),
            ]
        );
    }
}
