<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Customer::query()
            ->search($request->query('search'));

        return $this->paginated($query->orderBy('name')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateCustomer($request);
        $data['id'] = $this->generateCustomerId();
        $data['created_by'] = $request->user()->id;
        $customer = Customer::query()->create($data);
        $auditService->log('customer_created', 'customers', 'CREATE', $customer, [], $customer->toArray());

        return $this->success($customer, 'Pelanggan berhasil dibuat.', 201);
    }

    public function show(Customer $customer)
    {
        return $this->success($customer->load(['district.regency', 'units.cluster', 'units.status']));
    }

    public function update(Request $request, Customer $customer, AuditService $auditService)
    {
        $data = $this->validateCustomer($request, $customer);
        $data['updated_by'] = $request->user()->id;
        $old = $customer->toArray();
        $customer->update($data);
        $auditService->log('customer_updated', 'customers', 'UPDATE', $customer, $old, $customer->toArray());

        return $this->success($customer->refresh(), 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer, AuditService $auditService)
    {
        if ($customer->units()->exists()) {
            return $this->error('Pelanggan tidak dapat dihapus karena masih memiliki unit.', 422);
        }

        $old = $customer->toArray();
        $customer->delete();
        $auditService->log('customer_deleted', 'customers', 'DELETE', $customer, $old, []);

        return $this->success(null, 'Pelanggan berhasil dihapus.');
    }

    private function generateCustomerId(): string
    {
        return DB::transaction(function () {
            $last = Customer::query()
                ->where('id', 'like', 'CU%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('id');

            $next = $last ? ((int) substr($last, 2)) + 1 : 1;

            return 'CU'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'id_card_address' => ['nullable', 'string', 'max:200'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'email' => ['nullable', 'email', 'max:100'],
        ]);
    }
}
