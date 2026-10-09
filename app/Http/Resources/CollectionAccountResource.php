<?php

namespace App\Http\Resources;

use App\Models\CollectionAccountState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu baris monitoring akun penagihan (GET /collection/accounts). Relasi `unit.cluster`,
 * `customer` dan `collector` harus sudah di-eager-load pemanggil (tanpa N+1).
 *
 * @mixin CollectionAccountState
 */
class CollectionAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unit = $this->unit;

        return [
            'unit_id' => $this->unit_id,
            'unit' => [
                'id' => $this->unit_id,
                'cluster_id' => $unit?->cluster_id,
                'cluster_name' => $unit?->cluster?->name,
                'block' => $unit?->block,
                'lot_number' => $unit?->lot_number,
            ],
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ] : null,
            'collector' => $this->collector ? [
                'id' => (int) $this->collector->id,
                'name' => $this->collector->name,
            ] : null,
            'outstanding_principal' => (float) $this->outstanding_principal,
            'outstanding_penalty' => (float) $this->outstanding_penalty,
            'outstanding_total' => (float) $this->outstanding_total,
            'open_invoice_count' => (int) $this->open_invoice_count,
            'oldest_due_date' => $this->oldest_due_date?->toDateString(),
            'next_due_date' => $this->next_due_date?->toDateString(),
            'aging_days' => (int) $this->aging_days,
            'aging_bucket' => $this->aging_bucket,
            'status' => $this->status,
            'priority_score' => (int) $this->priority_score,
            'priority_level' => $this->priority_level,
            'last_contact_at' => $this->last_contact_at?->toJSON(),
            'last_contact_result' => $this->last_contact_result,
            'next_follow_up_at' => $this->next_follow_up_at?->toJSON(),
            'failed_contact_count' => (int) $this->failed_contact_count,
            'failed_visit_count' => (int) $this->failed_visit_count,
            'broken_ptp_count' => (int) $this->broken_ptp_count,
            'refreshed_at' => $this->refreshed_at?->toJSON(),
        ];
    }
}
