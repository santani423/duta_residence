<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Billing;
use App\Models\DiscountSetting;
use App\Models\PaymentScheme;
use App\Models\PaymentSchemeItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Skema pembayaran": Loket mengajukan diskon pokok + keringanan denda atas beberapa tagihan
 * sekaligus; setelah Admin menyetujui, tagihan-tagihan itu menjadi satu kewajiban dengan
 * nominal akhir yang tetap. Skema hanya sah untuk kondisi tagihan TERBARU - begitu salah satu
 * tagihan berubah (mis. ada pembayaran) saat skema masih Pending, skema dibatalkan otomatis.
 */
class PaymentSchemeService
{
    public function __construct(
        private readonly PenaltyService $penaltyService,
        private readonly DiscountService $discountService,
        private readonly AuditService $auditService,
        private readonly PaymentSchemeNotifier $notifier,
    ) {}

    /**
     * Read-only calculation of a scheme against the latest billing condition. Used by the
     * preview endpoint and by submit(), so what the officer sees is what gets stored.
     *
     * Penalty reduction is given either per billing (`penalty_reductions` = [billing_id => amount],
     * what the loket form sends) or as one total (`penalty_reduction`) that is split in proportion
     * to each billing's penalty.
     *
     * `$exceptSchemeId` lets Admin recalculate a Pending scheme without it colliding with itself.
     *
     * @param  array{billing_ids: array<int>, discount_type?: ?string, discount_value?: float|int|string|null, penalty_reduction?: float|int|string|null, penalty_reductions?: array<int|string, float|int|string|null>|null}  $data
     */
    public function calculate(Unit $unit, array $data, bool $lock = false, ?int $exceptSchemeId = null): array
    {
        $billingIds = array_values(array_unique(array_map('intval', $data['billing_ids'] ?? [])));
        $type = $data['discount_type'] ?? DiscountSetting::TYPE_NOMINAL;
        $discountValue = round((float) ($data['discount_value'] ?? 0), 2);
        $perBillReductions = $data['penalty_reductions'] ?? null;
        $penaltyReduction = round((float) ($data['penalty_reduction'] ?? 0), 2);

        if ($billingIds === []) {
            throw ValidationException::withMessages(['billing_ids' => ['Pilih minimal satu tagihan.']]);
        }

        $billings = $this->payableBillings($unit, $billingIds, $lock, $exceptSchemeId);
        $now = now();

        $rows = $billings->map(fn (Billing $billing) => [
            'billing' => $billing,
            'calc' => $this->penaltyService->calculateInvoiceTotal($billing, $now),
        ]);

        $principalCents = $rows->map(fn (array $row) => $this->cents($row['calc']['outstanding_principal']))->all();
        $penaltyCents = $rows->map(fn (array $row) => $this->cents($row['calc']['outstanding_penalty']))->all();
        $totalPrincipal = array_sum($principalCents) / 100;
        $totalPenalty = array_sum($penaltyCents) / 100;

        $discount = $this->resolveDiscount($type, $discountValue, $totalPrincipal);

        if (is_array($perBillReductions) && $perBillReductions !== []) {
            $penaltyShares = $this->perBillPenaltyShares($rows, $billingIds, $perBillReductions, $penaltyCents);
            $penaltyReduction = array_sum($penaltyShares) / 100;
        } else {
            if ($penaltyReduction < 0 || $penaltyReduction > $totalPenalty + 0.001) {
                throw ValidationException::withMessages([
                    'penalty_reduction' => ['Keringanan denda harus antara 0 dan Rp'.number_format($totalPenalty, 0, ',', '.').' (total denda berjalan).'],
                ]);
            }

            $penaltyShares = $this->allocate($this->cents($penaltyReduction), $penaltyCents);
        }

        $discountShares = $this->allocate($this->cents($discount), $principalCents);

        $items = $rows->values()->map(function (array $row, int $i) use ($principalCents, $penaltyCents, $discountShares, $penaltyShares) {
            /** @var Billing $billing */
            $billing = $row['billing'];

            return [
                'billing_id' => $billing->id,
                'period' => $row['calc']['period'],
                'original_principal' => $principalCents[$i] / 100,
                'principal_discount' => $discountShares[$i] / 100,
                'final_principal' => ($principalCents[$i] - $discountShares[$i]) / 100,
                'original_penalty' => $penaltyCents[$i] / 100,
                'penalty_reduction' => $penaltyShares[$i] / 100,
                'final_penalty' => ($penaltyCents[$i] - $penaltyShares[$i]) / 100,
                'previous_discount' => round((float) $billing->discount, 2),
            ];
        });

        $finalPrincipal = round($items->sum('final_principal'), 2);
        $finalPenalty = round($items->sum('final_penalty'), 2);

        return [
            'unit_id' => $unit->id,
            'discount_type' => $type,
            'items' => $items->all(),
            'original_principal' => round($totalPrincipal, 2),
            'principal_discount' => round($discount, 2),
            'original_penalty' => round($totalPenalty, 2),
            'penalty_reduction' => round($penaltyReduction, 2),
            'final_principal' => $finalPrincipal,
            'final_penalty' => $finalPenalty,
            'final_amount' => round($finalPrincipal + $finalPenalty, 2),
        ];
    }

    public function submit(Unit $unit, array $data, int $userId): PaymentScheme
    {
        return DB::transaction(function () use ($unit, $data, $userId) {
            $calc = $this->calculate($unit, $data, true);

            $scheme = PaymentScheme::query()->create([
                'unit_id' => $unit->id,
                'status' => PaymentScheme::STATUS_PENDING,
                'reason' => $data['reason'] ?? null,
                'discount_type' => $calc['discount_type'],
                'original_principal' => $calc['original_principal'],
                'principal_discount' => $calc['principal_discount'],
                'original_penalty' => $calc['original_penalty'],
                'penalty_reduction' => $calc['penalty_reduction'],
                'final_amount' => $calc['final_amount'],
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            foreach ($calc['items'] as $item) {
                $scheme->items()->create(collect($item)->except('period')->all());
            }

            $this->auditService->log('payment_scheme_submitted', 'payment-schemes', 'CREATE', $scheme, [], $scheme->load('items')->toArray());
            $this->notifier->submitted($scheme, User::query()->findOrFail($userId));

            return $scheme;
        });
    }

    /**
     * Admin may change the loket's request while approving by passing `$adjustments` (the complete
     * final terms: discount_type, discount_value, penalty_reductions, and optionally
     * rejected_billing_ids to drop single months from the scheme). The scheme is then
     * recalculated on the latest billing condition, the loket's original request is kept in
     * `requested_snapshot`, and everything - adjustment, limit check, applying - is one transaction.
     *
     * @param  array{discount_type?: ?string, discount_value?: float|int|string|null, penalty_reductions?: array<int|string, mixed>|null, rejected_billing_ids?: array<int>|null}|null  $adjustments
     */
    public function approve(PaymentScheme $scheme, int $userId, ?string $notes = null, ?array $adjustments = null): PaymentScheme
    {
        $this->ensureFresh($scheme);

        return DB::transaction(function () use ($scheme, $userId, $notes, $adjustments) {
            $scheme = PaymentScheme::query()->lockForUpdate()->findOrFail($scheme->id);
            $this->assertPending($scheme);

            if ($adjustments !== null) {
                $this->applyAdjustments($scheme, $adjustments, $userId);
            }

            $items = $scheme->includedItems()->get();
            $billings = Billing::query()->whereIn('id', $items->pluck('billing_id'))->lockForUpdate()->get()->keyBy('id');

            // The approver's limit (Admin) applies to the total discount each billing ends up with.
            foreach ($items as $item) {
                $billing = $billings[$item->billing_id];
                $this->discountService->assertManualDiscountWithinAdminLimit(
                    $userId, $billing, round((float) $item->previous_discount + (float) $item->principal_discount, 2)
                );
            }

            // Status first, so the Billing hook below never sees this scheme as still pending.
            $scheme->forceFill([
                'status' => PaymentScheme::STATUS_APPROVED,
                'decided_by' => $userId,
                'decided_at' => now(),
                'review_notes' => $notes,
            ])->save();

            foreach ($items as $item) {
                $billing = $billings[$item->billing_id];
                $old = $billing->toArray();
                $attributes = [
                    'payment_scheme_id' => $scheme->id,
                    'penalty_fixed' => $item->final_penalty,
                ];

                if ((float) $item->principal_discount > 0) {
                    $attributes += [
                        'discount' => round((float) $item->previous_discount + (float) $item->principal_discount, 2),
                        'discount_rule_id' => null,
                        'discount_set_by' => $userId,
                        'discount_set_at' => now(),
                        'discount_reason' => 'Skema pembayaran #'.$scheme->id,
                    ];
                }

                $billing->forceFill($attributes)->save();
                $this->auditService->log('payment_scheme_applied', 'billings', 'SCHEME_APPLY', $billing, $old, $billing->refresh()->toArray());
            }

            $this->auditService->log('payment_scheme_approved', 'payment-schemes', 'APPROVE', $scheme, [], $scheme->toArray(), 'success', $notes);
            $this->notifier->approved($scheme, User::query()->findOrFail($userId));

            return $scheme->refresh();
        });
    }

    /**
     * Does any included month carry more discount than the Admin limit (a % of the billing amount,
     * counting discounts it already had) allows? Same rule DiscountService enforces at approval.
     */
    public function exceedsAdminLimit(PaymentScheme $scheme, ?float $limitPercent = null): bool
    {
        $limit = $limitPercent ?? DiscountSetting::maximumAdminDiscount();
        $scheme->loadMissing('items.billing');

        return $scheme->items
            ->where('status', PaymentSchemeItem::STATUS_INCLUDED)
            ->contains(function (PaymentSchemeItem $item) use ($limit) {
                $billing = $item->billing;

                return $billing && round((float) $item->previous_discount + (float) $item->principal_discount, 2) > round((float) $billing->amount * $limit / 100, 2);
            });
    }

    /**
     * Attach what the UI needs to enforce the Admin limit: the limit, whether the viewer is bound
     * by it, and whether each Pending scheme is above it.
     *
     * @param  iterable<PaymentScheme>  $schemes
     */
    public function annotate(iterable $schemes, ?User $viewer): void
    {
        $limit = DiscountSetting::maximumAdminDiscount();
        $limited = $this->discountService->maximumPercentFor($viewer) !== null;

        foreach ($schemes as $scheme) {
            $scheme->setAttribute('admin_limit_percent', $limit);
            $scheme->setAttribute('viewer_limited', $limited);
            $scheme->setAttribute('exceeds_admin_limit', $scheme->isPending() && $this->exceedsAdminLimit($scheme, $limit));
            $scheme->setAttribute('accumulated_principal', $this->accumulatedPrincipal($scheme));
            $scheme->setAttribute('net_penalty', round((float) $scheme->original_penalty - (float) $scheme->penalty_reduction, 2));

            foreach ($this->paymentProgress($scheme) as $key => $value) {
                $scheme->setAttribute($key, $value);
            }
        }
    }

    /**
     * Sum of the original, untouched monthly IPL principal (`billing.amount`) across the scheme's
     * included periods - independent of discount, penalty, reduction, or payments already made.
     */
    private function accumulatedPrincipal(PaymentScheme $scheme): float
    {
        $scheme->loadMissing('items.billing');

        return round(
            $scheme->items
                ->where('status', PaymentSchemeItem::STATUS_INCLUDED)
                ->sum(fn (PaymentSchemeItem $item) => (float) ($item->billing->amount ?? 0)),
            2,
        );
    }

    /**
     * How far the customer has come in paying an approved scheme: nothing / some / all of it. Read from
     * the scheme's own billings (the source of truth for payments), so any channel - loket, transfer,
     * gateway - is reflected. Only approved schemes have anything to pay.
     *
     * @return array{payment_status: ?string, paid_amount: ?float, outstanding_amount: ?float}
     */
    public function paymentProgress(PaymentScheme $scheme): array
    {
        if ($scheme->status !== PaymentScheme::STATUS_APPROVED) {
            return ['payment_status' => null, 'paid_amount' => null, 'outstanding_amount' => null];
        }

        $scheme->loadMissing('items.billing.unit');
        $billings = $scheme->items
            ->where('status', PaymentSchemeItem::STATUS_INCLUDED)
            ->map(fn (PaymentSchemeItem $item) => $item->billing)
            ->filter();

        $outstanding = round($billings->sum(fn (Billing $billing) => $this->penaltyService->calculateInvoiceTotal($billing)['total_outstanding']), 2);
        $paid = round($billings->sum(fn (Billing $billing) => (float) $billing->principal_paid + (float) $billing->penalty_paid), 2);

        return [
            'payment_status' => $outstanding <= 0.01 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            'paid_amount' => $paid,
            'outstanding_amount' => $outstanding,
        ];
    }

    /** Dry run of what Admin's edited terms would give for a Pending scheme; nothing is stored. */
    public function previewAdjustment(PaymentScheme $scheme, array $adjustments): array
    {
        $this->assertPending($scheme);

        return $this->calculateAdjusted($scheme, $adjustments, false, $this->rejectedBillingIds($scheme, $adjustments));
    }

    public function reject(PaymentScheme $scheme, int $userId, ?string $notes = null): PaymentScheme
    {
        $this->assertPending($scheme);

        // A scheme above the Admin discount limit is Super Admin's call: Admin can only bring it down to the limit and approve it.
        if ($this->isLimited($userId) && $this->exceedsAdminLimit($scheme)) {
            throw ValidationException::withMessages(['status' => [
                'Skema ini melebihi batas diskon Admin ('.$this->limitLabel().'), jadi Admin tidak dapat menolaknya. Turunkan diskon hingga batas lalu setujui, atau biarkan Super Admin yang memutuskan.',
            ]]);
        }

        $scheme->forceFill([
            'status' => PaymentScheme::STATUS_REJECTED,
            'decided_by' => $userId,
            'decided_at' => now(),
            'review_notes' => $notes,
        ])->save();

        $this->auditService->log('payment_scheme_rejected', 'payment-schemes', 'REJECT', $scheme, [], $scheme->toArray(), 'success', $notes);
        $this->notifier->rejected($scheme, User::query()->findOrFail($userId), $notes);

        return $scheme->refresh();
    }

    /**
     * Cancel every Pending scheme that includes any of the given billings. Also closes the
     * linked Approval Center request so the two never disagree.
     */
    public function cancelPendingForBillings(iterable $billingIds, string $reason): Collection
    {
        $ids = collect($billingIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return PaymentScheme::query()
            ->where('status', PaymentScheme::STATUS_PENDING)
            ->whereHas('items', fn ($q) => $q->whereIn('billing_id', $ids))
            ->get()
            ->each(fn (PaymentScheme $scheme) => $this->cancel($scheme, $reason));
    }

    /**
     * A Pending scheme must still match the billings it was calculated from. Payments and
     * edits already cancel it through the Billing hook; this also catches time-based drift
     * (e.g. the month rolling over into a higher penalty tier) at the moment of approval.
     */
    public function ensureFresh(PaymentScheme $scheme): void
    {
        $this->assertPending($scheme);

        $now = now();
        $billings = Billing::query()->with('unit')->whereIn('id', $scheme->includedItems()->pluck('billing_id'))->get()->keyBy('id');

        $stale = $scheme->includedItems()->get()->contains(function (PaymentSchemeItem $item) use ($billings, $now) {
            $billing = $billings->get($item->billing_id);

            if (! $billing || ! $billing->isOutstanding()) {
                return true;
            }

            $calc = $this->penaltyService->calculateInvoiceTotal($billing, $now);

            return abs($calc['outstanding_principal'] - (float) $item->original_principal) > 0.01
                || abs($calc['outstanding_penalty'] - (float) $item->original_penalty) > 0.01;
        });

        if ($stale) {
            $this->cancel($scheme, 'Kondisi tagihan sudah berubah sejak skema diajukan (nominal pokok/denda tidak lagi sama).');

            throw ValidationException::withMessages(['status' => ['Skema pembayaran dibatalkan karena kondisi tagihan sudah berubah. Ajukan skema baru.']]);
        }
    }

    /**
     * Bills of an approved scheme are one obligation: they must be paid together, so a payment
     * that selects some of them but leaves other outstanding scheme bills out is refused.
     */
    public function assertSchemeBillsComplete(iterable $billingIds): void
    {
        $ids = collect($billingIds)->unique()->values();

        $schemeIds = Billing::query()->whereIn('id', $ids)->whereNotNull('payment_scheme_id')->pluck('payment_scheme_id')->unique();

        foreach ($schemeIds as $schemeId) {
            $missing = Billing::query()->where('payment_scheme_id', $schemeId)->outstanding()->pluck('id')->diff($ids);

            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'billing_ids' => ['Tagihan dalam skema pembayaran harus dibayar bersamaan. Sertakan seluruh tagihan skema #'.$schemeId.'.'],
                ]);
            }
        }
    }

    /** @param  array<int>  $rejectedIds */
    private function calculateAdjusted(PaymentScheme $scheme, array $adjustments, bool $lock, array $rejectedIds = []): array
    {
        return $this->calculate(Unit::query()->findOrFail($scheme->unit_id), [
            'billing_ids' => $scheme->includedItems()->pluck('billing_id')->diff($rejectedIds)->values()->all(),
            'discount_type' => $adjustments['discount_type'] ?? DiscountSetting::TYPE_NOMINAL,
            'discount_value' => $adjustments['discount_value'] ?? 0,
            'penalty_reductions' => $adjustments['penalty_reductions'] ?? null,
        ], $lock, $scheme->id);
    }

    /**
     * Billings Admin wants to drop from the scheme. They must belong to it, and at least one month
     * has to stay - rejecting everything is what "Tolak" on the whole scheme is for.
     *
     * @return array<int>
     */
    private function rejectedBillingIds(PaymentScheme $scheme, array $adjustments): array
    {
        $rejected = array_values(array_unique(array_map('intval', $adjustments['rejected_billing_ids'] ?? [])));

        if ($rejected === []) {
            return [];
        }

        $included = $scheme->includedItems()->pluck('billing_id')->map(fn ($id) => (int) $id);

        if (array_diff($rejected, $included->all()) !== []) {
            throw ValidationException::withMessages(['rejected_billing_ids' => ['Bulan yang ditolak harus bagian dari skema ini.']]);
        }

        if ($included->diff($rejected)->isEmpty()) {
            throw ValidationException::withMessages(['rejected_billing_ids' => ['Tidak bisa menolak semua bulan. Gunakan Tolak untuk menolak seluruh skema.']]);
        }

        return $rejected;
    }

    /**
     * Replace the scheme's terms with Admin's: new discount/penalty amounts and/or months dropped
     * from the scheme. Does nothing (and does not mark the scheme as adjusted) when the result is
     * identical to what the loket requested.
     */
    private function applyAdjustments(PaymentScheme $scheme, array $adjustments, int $userId): void
    {
        $items = $scheme->items()->get()->keyBy('billing_id');
        $rejectedIds = $this->rejectedBillingIds($scheme, $adjustments);
        $calc = $this->calculateAdjusted($scheme, $adjustments, true, $rejectedIds);

        $changed = $rejectedIds !== [] || collect($calc['items'])->contains(fn (array $row) => abs($row['principal_discount'] - (float) $items[$row['billing_id']]->principal_discount) > 0.001
            || abs($row['penalty_reduction'] - (float) $items[$row['billing_id']]->penalty_reduction) > 0.001);

        if (! $changed) {
            return;
        }

        $snapshot = [
            'discount_type' => $scheme->discount_type,
            'original_principal' => (float) $scheme->original_principal,
            'original_penalty' => (float) $scheme->original_penalty,
            'principal_discount' => (float) $scheme->principal_discount,
            'penalty_reduction' => (float) $scheme->penalty_reduction,
            'final_amount' => (float) $scheme->final_amount,
            'items' => $items->map(fn (PaymentSchemeItem $item) => [
                'billing_id' => $item->billing_id,
                'principal_discount' => (float) $item->principal_discount,
                'penalty_reduction' => (float) $item->penalty_reduction,
            ])->values()->all(),
        ];

        foreach ($calc['items'] as $row) {
            $items[$row['billing_id']]->forceFill([
                'principal_discount' => $row['principal_discount'],
                'final_principal' => $row['final_principal'],
                'penalty_reduction' => $row['penalty_reduction'],
                'final_penalty' => $row['final_penalty'],
            ])->save();
        }

        // A rejected month gets no scheme terms: it goes back to being an ordinary outstanding bill.
        foreach ($rejectedIds as $billingId) {
            $item = $items[$billingId];
            $item->forceFill([
                'status' => PaymentSchemeItem::STATUS_REJECTED,
                'principal_discount' => 0,
                'final_principal' => $item->original_principal,
                'penalty_reduction' => 0,
                'final_penalty' => $item->original_penalty,
            ])->save();
        }

        $scheme->forceFill([
            'discount_type' => $calc['discount_type'],
            'original_principal' => $calc['original_principal'],
            'original_penalty' => $calc['original_penalty'],
            'principal_discount' => $calc['principal_discount'],
            'penalty_reduction' => $calc['penalty_reduction'],
            'final_amount' => $calc['final_amount'],
            'requested_snapshot' => $snapshot,
            'adjusted_by' => $userId,
            'adjusted_at' => now(),
        ])->save();

        // Keep the Approval Center row showing the amount that will actually be approved.
        ApprovalRequest::query()
            ->where('requestable_type', PaymentScheme::class)
            ->where('requestable_id', $scheme->id)
            ->update([
                'amount' => $calc['final_amount'],
                'after_value' => json_encode(['principal_discount' => $calc['principal_discount'], 'penalty_reduction' => $calc['penalty_reduction'], 'final_amount' => $calc['final_amount']]),
            ]);

        $this->auditService->log('payment_scheme_adjusted', 'payment-schemes', 'ADJUST', $scheme, $snapshot, $scheme->toArray());
    }

    private function isLimited(int $userId): bool
    {
        return $this->discountService->maximumPercentFor(User::query()->find($userId)) !== null;
    }

    private function limitLabel(): string
    {
        return rtrim(rtrim(number_format(DiscountSetting::maximumAdminDiscount(), 2, ',', ''), '0'), ',').'%';
    }

    private function cancel(PaymentScheme $scheme, string $reason): void
    {
        $scheme->forceFill([
            'status' => PaymentScheme::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        ApprovalRequest::query()
            ->where('requestable_type', PaymentScheme::class)
            ->where('requestable_id', $scheme->id)
            ->pending()
            ->update(['status' => ApprovalRequest::STATUS_CANCELLED, 'supervisor_notes' => $reason, 'decided_at' => now()]);

        $this->auditService->log('payment_scheme_cancelled', 'payment-schemes', 'CANCEL', $scheme, [], $scheme->toArray(), 'success', $reason);
        $this->notifier->cancelled($scheme, $reason);
    }

    private function assertPending(PaymentScheme $scheme): void
    {
        if (! $scheme->isPending()) {
            throw ValidationException::withMessages(['status' => ['Skema pembayaran sudah diproses atau dibatalkan.']]);
        }
    }

    private function payableBillings(Unit $unit, array $billingIds, bool $lock, ?int $exceptSchemeId = null): Collection
    {
        $query = Billing::query()->with('unit')->where('unit_id', $unit->id)->whereIn('id', $billingIds);

        if ($lock) {
            $query->lockForUpdate();
        }

        $billings = $query->get();

        if ($billings->count() !== count($billingIds)) {
            throw ValidationException::withMessages(['billing_ids' => ['Tagihan tidak ditemukan atau bukan milik unit ini.']]);
        }

        if ($billings->contains(fn (Billing $b) => ! $b->isOutstanding() || blank($b->approved_at))) {
            throw ValidationException::withMessages(['billing_ids' => ['Semua tagihan harus sudah disetujui dan belum lunas.']]);
        }

        $hasActiveScheme = $billings->contains(fn (Billing $b) => $b->payment_scheme_id !== null)
            || PaymentSchemeItem::query()
                ->whereIn('billing_id', $billingIds)
                ->whereHas('scheme', fn ($q) => $q->where('status', PaymentScheme::STATUS_PENDING)->when($exceptSchemeId, fn ($inner) => $inner->where('id', '!=', $exceptSchemeId)))
                ->exists();

        if ($hasActiveScheme) {
            throw ValidationException::withMessages(['billing_ids' => ['Salah satu tagihan sudah termasuk dalam skema pembayaran yang aktif.']]);
        }

        return $billings->sortBy([['year', 'asc'], ['month', 'asc']])->values();
    }

    private function resolveDiscount(string $type, float $value, float $totalPrincipal): float
    {
        if (! in_array($type, [DiscountSetting::TYPE_PERCENTAGE, DiscountSetting::TYPE_NOMINAL], true)) {
            throw ValidationException::withMessages(['discount_type' => ['Tipe diskon harus Persentase (%) atau Nominal (Rp).']]);
        }

        if ($type === DiscountSetting::TYPE_PERCENTAGE) {
            if ($value < 0 || $value > 100) {
                throw ValidationException::withMessages(['discount_value' => ['Persentase diskon harus antara 0 dan 100.']]);
            }

            return round($totalPrincipal * $value / 100, 2);
        }

        if ($value < 0 || $value > $totalPrincipal + 0.001) {
            throw ValidationException::withMessages([
                'discount_value' => ['Diskon harus antara 0 dan Rp'.number_format($totalPrincipal, 0, ',', '.').' (total sisa pokok).'],
            ]);
        }

        return $value;
    }

    /**
     * Per-billing penalty reductions in cents, in the same order as `$rows`. A reduction can
     * only target a selected billing and never exceed that billing's own penalty.
     *
     * @param  array<int|string, mixed>  $reductions
     * @param  array<int, int>  $penaltyCents
     * @return array<int, int>
     */
    private function perBillPenaltyShares(Collection $rows, array $billingIds, array $reductions, array $penaltyCents): array
    {
        $unknown = array_diff(array_map('intval', array_keys($reductions)), $billingIds);

        if ($unknown !== []) {
            throw ValidationException::withMessages(['penalty_reductions' => ['Keringanan denda hanya boleh diberikan pada tagihan yang dipilih.']]);
        }

        return $rows->values()->map(function (array $row, int $i) use ($reductions, $penaltyCents) {
            $billingId = $row['billing']->id;
            $value = $reductions[$billingId] ?? $reductions[(string) $billingId] ?? 0;
            $cents = $this->cents($value ?? 0);

            if ($cents < 0 || $cents > $penaltyCents[$i]) {
                throw ValidationException::withMessages([
                    "penalty_reductions.{$billingId}" => ['Keringanan denda tagihan '.$row['calc']['period'].' harus antara 0 dan Rp'.number_format($penaltyCents[$i] / 100, 0, ',', '.').' (denda tagihan ini).'],
                ]);
            }

            return $cents;
        })->all();
    }

    /**
     * Split `$totalCents` across `$capsCents` in proportion to the caps, in whole cents, so the
     * shares always add up exactly and no share exceeds its own cap.
     *
     * @param  array<int, int>  $capsCents
     * @return array<int, int>
     */
    private function allocate(int $totalCents, array $capsCents): array
    {
        $capSum = array_sum($capsCents);

        if ($totalCents <= 0 || $capSum <= 0) {
            return array_fill(0, count($capsCents), 0);
        }

        $shares = array_map(fn (int $cap) => intdiv($totalCents * $cap, $capSum), $capsCents);
        $left = $totalCents - array_sum($shares);

        foreach ($shares as $i => $share) {
            if ($left <= 0) {
                break;
            }

            if ($share < $capsCents[$i]) {
                $shares[$i]++;
                $left--;
            }
        }

        return $shares;
    }

    private function cents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
