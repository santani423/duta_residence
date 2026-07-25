<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\NavMenuItem;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NavMenuItemController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = NavMenuItem::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('label', 'like', "%{$value}%"))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = NavMenuItem::query()->max('order') + 1;

        $item = NavMenuItem::query()->create($data);
        $auditService->log('landing_nav_menu_item_created', 'landing-cms', 'CREATE', $item, [], $item->toArray());
        LandingContentCache::forget();

        return $this->success($item, 'Menu navigasi berhasil dibuat.', 201);
    }

    public function update(Request $request, NavMenuItem $navMenuItem, AuditService $auditService)
    {
        $data = $this->validated($request);
        $old = $navMenuItem->toArray();
        $navMenuItem->update($data);
        $auditService->log('landing_nav_menu_item_updated', 'landing-cms', 'UPDATE', $navMenuItem, $old, $navMenuItem->toArray());
        LandingContentCache::forget();

        return $this->success($navMenuItem->refresh(), 'Menu navigasi berhasil diperbarui.');
    }

    public function destroy(NavMenuItem $navMenuItem, AuditService $auditService)
    {
        $old = $navMenuItem->toArray();
        $navMenuItem->delete();
        $auditService->log('landing_nav_menu_item_deleted', 'landing-cms', 'DELETE', $navMenuItem, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Menu navigasi berhasil dihapus.');
    }

    public function reorder(Request $request, NavMenuItem $navMenuItem, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $navMenuItem->moveOrder($data['direction']);
        $auditService->log('landing_nav_menu_item_reordered', 'landing-cms', 'UPDATE', $navMenuItem, [], $navMenuItem->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan menu navigasi berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:255'],
            'open_in_new_tab' => ['sometimes', 'boolean'],
            'show_in_footer' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
