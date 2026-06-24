<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Customer::query()
            ->with(['cluster', 'propertyType', 'status', 'occupancy'])
            ->search($request->query('search'))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->where('cluster_id', $value))
            ->when($request->query('status_id'), fn ($q, $value) => $q->where('status_id', $value))
            ->when($request->query('property_type_id'), fn ($q, $value) => $q->where('property_type_id', $value));

        return $this->paginated($query->orderBy('cluster_id')->orderBy('block')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateCustomer($request);
        $data['created_by'] = $request->user()->id;
        $customer = Customer::query()->create($data);
        $auditService->log('customer_created', 'customers', 'CREATE', $customer, [], $customer->toArray());

        return $this->success($customer->load(['cluster', 'status']), 'Pelanggan berhasil dibuat.', 201);
    }

    public function show(Customer $customer)
    {
        return $this->success($customer->load(['cluster', 'propertyType', 'occupancy', 'status', 'district.regency', 'billings.status']));
    }

    public function update(Request $request, Customer $customer, AuditService $auditService)
    {
        $data = $this->validateCustomer($request, $customer);
        $data['updated_by'] = $request->user()->id;
        $old = $customer->toArray();
        $customer->update($data);
        $auditService->log('customer_updated', 'customers', 'UPDATE', $customer, $old, $customer->toArray());

        return $this->success($customer->refresh()->load(['cluster', 'status']), 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer, AuditService $auditService)
    {
        $old = $customer->toArray();
        $customer->delete();
        $auditService->log('customer_deleted', 'customers', 'DELETE', $customer, $old, []);

        return $this->success(null, 'Pelanggan berhasil dihapus.');
    }

    public function convertProperty(Request $request, Customer $customer, AuditService $auditService)
    {
        $data = $request->validate([
            'property_type_id' => ['required', Rule::in(['B'])],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);

        if ($customer->property_type_id !== 'K') {
            return $this->error('Hanya kavling developer yang dapat dikonversi menjadi bangunan.', 422);
        }

        $old = $customer->toArray();
        $customer->update([
            'property_type_id' => $data['property_type_id'],
            'handover_date' => now()->toDateString(),
            'notes' => $data['notes'] ?? $customer->notes,
            'updated_by' => $request->user()->id,
        ]);
        $auditService->log('customer_property_converted', 'customers', 'CONVERT_PROPERTY', $customer, $old, $customer->toArray());

        return $this->success($customer->refresh(), 'Properti berhasil dikonversi.');
    }

    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'id' => ['required', 'string', 'size:5', Rule::unique('customers', 'id')->ignore($customer?->id, 'id')],
            'name' => ['required', 'string', 'max:100'],
            'cluster_id' => ['required', 'exists:clusters,id'],
            'block' => ['required', 'string', 'max:5'],
            'lot_number' => ['required', 'string', 'max:10'],
            'property_type_id' => ['required', 'exists:property_types,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'id_card_address' => ['nullable', 'string', 'max:200'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'building_area' => ['nullable', 'numeric', 'min:0'],
            'land_area' => ['nullable', 'numeric', 'min:0'],
            'email' => ['nullable', 'email', 'max:100'],
            'handover_date' => ['nullable', 'date'],
            'occupancy_id' => ['required', 'exists:occupancy_statuses,id'],
            'status_id' => ['required', 'exists:customer_statuses,id'],
            'is_penalty_eligible' => ['sometimes', 'boolean'],
            'is_discount_eligible' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
