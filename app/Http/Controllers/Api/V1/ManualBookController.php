<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ManagedFile;
use App\Models\ManualBookRead;
use App\Models\ManualBookSection;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManualBookController extends Controller
{
    use ApiResponse;

    /**
     * Daftar panduan yang boleh dilihat user yang sedang login: hanya yang aktif dan
     * roles-nya kosong (berlaku untuk semua) atau memuat salah satu role user tsb.
     */
    public function index(Request $request)
    {
        $roles = $request->user()->getRoleNames()->all();
        $readSectionIds = ManualBookRead::query()
            ->where('user_id', $request->user()->id)
            ->pluck('manual_book_section_id');

        $sections = ManualBookSection::query()
            ->where('is_active', true)
            ->visibleToRoles($roles)
            ->orderBy('module')
            ->orderBy('order')
            ->get()
            ->map(fn (ManualBookSection $section) => $this->summaryPayload($section, $readSectionIds));

        return $this->success($sections->values());
    }

    public function show(Request $request, ManualBookSection $section)
    {
        $roles = $request->user()->getRoleNames()->all();
        abort_unless($section->is_active && $this->visibleTo($section, $roles), 404);

        return $this->success($this->detailPayload($section));
    }

    public function markRead(Request $request, ManualBookSection $section)
    {
        $roles = $request->user()->getRoleNames()->all();
        abort_unless($section->is_active && $this->visibleTo($section, $roles), 404);

        ManualBookRead::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'manual_book_section_id' => $section->id],
            ['read_at' => now()]
        );

        return $this->success(null, 'Panduan ditandai sudah dibaca.');
    }

    public function adminIndex(Request $request)
    {
        $query = ManualBookSection::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$value}%")
                ->orWhere('module', 'like', "%{$value}%")))
            ->when($request->query('module'), fn ($q, $value) => $q->where('module', $value));

        return $this->paginated($query->orderBy('module')->orderBy('order')->paginate($request->integer('per_page', 20)));
    }

    public function adminShow(ManualBookSection $section)
    {
        return $this->success($this->detailPayload($section));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateSection($request);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $section = ManualBookSection::query()->create($data);
        $auditService->log('manual_book_section_created', 'manual-book', 'CREATE', $section, [], $section->toArray());

        return $this->success($this->detailPayload($section), 'Panduan berhasil dibuat.', 201);
    }

    public function update(Request $request, ManualBookSection $section, AuditService $auditService)
    {
        $data = $this->validateSection($request, $section);
        $data['updated_by'] = $request->user()->id;
        $data['version'] = $section->version + 1;
        $old = $section->toArray();
        $section->update($data);
        $auditService->log('manual_book_section_updated', 'manual-book', 'UPDATE', $section, $old, $section->toArray());

        return $this->success($this->detailPayload($section->refresh()), 'Panduan berhasil diperbarui.');
    }

    public function destroy(ManualBookSection $section, AuditService $auditService)
    {
        $old = $section->toArray();
        $section->delete();
        $auditService->log('manual_book_section_deleted', 'manual-book', 'DELETE', $section, $old, []);

        return $this->success(null, 'Panduan berhasil dihapus.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:manual_book_sections,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $index => $id) {
                ManualBookSection::query()->where('id', $id)->update(['order' => $index]);
            }
        });

        return $this->success(null, 'Urutan panduan berhasil disimpan.');
    }

    public function uploadImage(Request $request, ManualBookSection $section)
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $stored = $data['image']->store('manual-book', 'public');
        $file = ManagedFile::query()->create([
            'original_filename' => $data['image']->getClientOriginalName(),
            'stored_filename' => basename($stored),
            'path' => $stored,
            'disk' => 'public',
            'mime_type' => $data['image']->getMimeType(),
            'extension' => $data['image']->getClientOriginalExtension(),
            'size' => $data['image']->getSize(),
            'uploaded_by' => $request->user()->id,
            'entity_type' => ManualBookSection::class,
            'entity_id' => $section->id,
        ]);

        return $this->success($file, 'Gambar berhasil diunggah.', 201);
    }

    public function destroyImage(ManualBookSection $section, ManagedFile $image)
    {
        abort_unless($image->entity_type === ManualBookSection::class && $image->entity_id === $section->id, 404);
        $image->delete();

        return $this->success(null, 'Gambar berhasil dihapus.');
    }

    private function visibleTo(ManualBookSection $section, array $roles): bool
    {
        if (empty($section->roles)) {
            return true;
        }

        return (bool) array_intersect($section->roles, $roles);
    }

    /**
     * Jumlah total panduan dalam aplikasi ini kecil, jadi index() sekaligus mengirim
     * seluruh isi (bukan cuma ringkasan) supaya PageHelpButton dan halaman Manual Book
     * tidak perlu fetch detail satu-satu per section (hindari N+1 request).
     */
    private function summaryPayload(ManualBookSection $section, $readSectionIds): array
    {
        return [
            ...$this->detailPayload($section),
            'is_read' => $readSectionIds->contains($section->id),
        ];
    }

    private function detailPayload(ManualBookSection $section): array
    {
        return [
            ...$section->toArray(),
            'images' => $section->images()->get(['id', 'path', 'original_filename']),
        ];
    }

    private function validateSection(Request $request, ?ManualBookSection $section = null): array
    {
        return $request->validate([
            'module' => ['required', 'string', 'max:60'],
            'slug' => [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('manual_book_sections')->where('module', $request->input('module'))->ignore($section?->id),
            ],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'steps' => ['nullable', 'array'],
            'steps.*.title' => ['required_with:steps', 'string', 'max:200'],
            'steps.*.description' => ['nullable', 'string'],
            'tips' => ['nullable', 'array'],
            'tips.*' => ['string'],
            'warnings' => ['nullable', 'array'],
            'warnings.*' => ['string'],
            'faqs' => ['nullable', 'array'],
            'faqs.*.question' => ['required_with:faqs', 'string', 'max:300'],
            'faqs.*.answer' => ['required_with:faqs', 'string'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda -.',
        ]);
    }
}
