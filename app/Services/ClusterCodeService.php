<?php

namespace App\Services;

use App\Models\Cluster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya tempat yang membaca/menyusun kode properti (prefix cluster + nomor blok), mis.
 * "E3" = Emerald 48 blok 3 dan "E7" = Ruko Emerald blok 7. Prefix berasal dari clusters.code_prefix
 * (bukan pemotongan string), sehingga prefix berbeda panjang (B, AX, DD, JA) dan prefix yang
 * dipakai bersama (D, E) tetap dibedakan lewat cluster dan rentang blok yang diizinkan.
 */
class ClusterCodeService
{
    /** Awalan huruf, pemisah opsional (data lama memakai "AL-"), lalu nomor blok. */
    private const CODE_PATTERN = '/^([A-Z]+)[-\s]?(\d{1,5})$/';

    /** @var Collection<string, Cluster>|null */
    private ?Collection $clusters = null;

    /**
     * Baca kode gabungan. Hasil null bila format salah, prefix tidak dikenal, nomor blok di luar
     * konfigurasi cluster, atau (tanpa $clusterId) lebih dari satu cluster yang cocok.
     *
     * @return array{cluster: Cluster, prefix: string, block_number: int, code: string}|null
     */
    public function parse(string $code, ?string $clusterId = null): ?array
    {
        if (! preg_match(self::CODE_PATTERN, strtoupper(trim($code)), $match)) {
            return null;
        }

        [, $prefix, $number] = $match;
        $number = (int) $number;

        $candidates = $this->clusters()->filter(fn (Cluster $cluster) => $cluster->code_prefix === $prefix
            && ($clusterId === null || $cluster->id === $clusterId)
            && $cluster->allowsBlock($number));

        if ($candidates->count() !== 1) {
            return null;
        }

        $cluster = $candidates->first();

        return [
            'cluster' => $cluster,
            'prefix' => $prefix,
            'block_number' => $number,
            'code' => $this->compose($cluster, $number),
        ];
    }

    public function compose(Cluster $cluster, int $blockNumber): string
    {
        return $cluster->code_prefix.$blockNumber;
    }

    /** Nomor blok untuk kode lama $block pada cluster tertentu; null bila tidak dapat dibaca/valid. */
    public function blockNumberFor(?string $clusterId, ?string $block): ?int
    {
        if ($clusterId === null || $block === null || $block === '') {
            return null;
        }

        return $this->parse($block, $clusterId)['block_number'] ?? null;
    }

    /**
     * Nomor blok yang dipilih di dropdown: daftar dari konfigurasi cluster, atau (untuk cluster
     * "sesuai data blok") nomor blok yang sudah ada pada unit-unitnya.
     *
     * @return list<int>
     */
    public function blockNumbersFor(Cluster $cluster): array
    {
        if ($cluster->allowed_blocks !== null) {
            return array_values($cluster->allowed_blocks);
        }

        return DB::table('units')->where('cluster_id', $cluster->id)->whereNotNull('block_number')
            ->distinct()->orderBy('block_number')->pluck('block_number')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * Terjemahkan input blok dari form unit menjadi [block (kode gabungan), block_number].
     * Cluster yang belum punya prefix dibiarkan memakai teks blok apa adanya (kompatibilitas).
     * Menerima block_number (disarankan) atau kode gabungan di $block; keduanya divalidasi di sini.
     *
     * @return array{block: string, block_number: ?int}
     *
     * @throws ValidationException
     */
    public function resolveBlockInput(Cluster $cluster, ?string $block, int|string|null $blockNumber = null): array
    {
        if ($cluster->code_prefix === null) {
            return ['block' => (string) $block, 'block_number' => null];
        }

        if ($blockNumber === null || $blockNumber === '') {
            if (! preg_match(self::CODE_PATTERN, strtoupper(trim((string) $block)), $match) || $match[1] !== $cluster->code_prefix) {
                throw ValidationException::withMessages([
                    'block' => ["Kode blok untuk {$cluster->name} harus berawalan {$cluster->code_prefix} diikuti nomor blok, contoh: {$cluster->code_prefix}1."],
                ]);
            }

            $blockNumber = $match[2];
        }

        if (! is_numeric($blockNumber) || (int) $blockNumber < 1 || (int) $blockNumber != $blockNumber) {
            throw ValidationException::withMessages(['block_number' => ['Nomor blok harus berupa angka bulat positif.']]);
        }

        $blockNumber = (int) $blockNumber;

        if (! $cluster->allowsBlock($blockNumber)) {
            $allowed = implode(', ', $cluster->allowed_blocks ?? []);

            throw ValidationException::withMessages([
                'block_number' => ["Blok {$blockNumber} tidak valid untuk {$cluster->name}. Blok yang diizinkan: {$allowed}."],
            ]);
        }

        return ['block' => $this->compose($cluster, $blockNumber), 'block_number' => $blockNumber];
    }

    /**
     * Pastikan cluster dengan prefix sama tidak memakai nomor blok yang sama (blok null = semua
     * nomor, sehingga bentrok dengan cluster lain yang berprefix sama).
     *
     * @param  list<int>|null  $allowedBlocks
     *
     * @throws ValidationException
     */
    public function assertNoCodeConflict(string $prefix, ?array $allowedBlocks, ?string $ignoreClusterId = null): void
    {
        $conflict = Cluster::query()
            ->where('code_prefix', $prefix)
            ->when($ignoreClusterId, fn ($q) => $q->where('id', '!=', $ignoreClusterId))
            ->get()
            ->first(fn (Cluster $other) => $allowedBlocks === null
                || $other->allowed_blocks === null
                || array_intersect($allowedBlocks, $other->allowed_blocks) !== []);

        if ($conflict) {
            throw ValidationException::withMessages([
                'code_prefix' => ["Prefix {$prefix} bentrok dengan {$conflict->name}: nomor blok kedua cluster harus dibatasi dan tidak boleh tumpang tindih."],
            ]);
        }
    }

    /**
     * Terapkan konfigurasi resmi (config/cluster_codes.php) ke tabel clusters. Cluster yang
     * namanya tidak cocok dengan konfigurasi tidak disentuh dan dikembalikan sebagai peringatan.
     *
     * @return list<string> pesan cluster yang dilewati
     */
    public function syncClusters(): array
    {
        $skipped = [];

        foreach (config('cluster_codes.clusters') as $id => $definition) {
            $cluster = Cluster::query()->find($id);

            if (! $cluster || strcasecmp(trim($cluster->name), $definition['name']) !== 0) {
                $skipped[] = "Cluster {$id} ({$definition['name']}): ".($cluster ? "nama di database \"{$cluster->name}\" berbeda" : 'tidak ditemukan').', tidak disinkronkan.';

                continue;
            }

            DB::table('clusters')->where('id', $id)->update([
                'code_prefix' => $definition['prefix'],
                'area_type' => $definition['type'],
                'allowed_blocks' => $definition['blocks'] === null ? null : json_encode($definition['blocks']),
            ]);
        }

        $this->flush();

        return $skipped;
    }

    /**
     * Isi units.block_number dari kode lama di units.block. Hanya mengisi baris yang kodenya
     * valid untuk clusternya; kode lain dibiarkan NULL dan tidak pernah diubah.
     *
     * @return int jumlah unit yang terisi
     */
    public function backfillUnitBlockNumbers(): int
    {
        $filled = 0;

        DB::table('units')->whereNull('block_number')->orderBy('id')->select('id', 'cluster_id', 'block')
            ->chunk(500, function ($units) use (&$filled) {
                foreach ($units as $unit) {
                    $number = $this->blockNumberFor($unit->cluster_id, $unit->block);

                    if ($number !== null) {
                        DB::table('units')->where('id', $unit->id)->update(['block_number' => $number]);
                        $filled++;
                    }
                }
            });

        return $filled;
    }

    /**
     * Unit yang kode bloknya tidak sesuai konfigurasi clusternya (perlu konfirmasi manual, tidak
     * diubah otomatis). 'suggestion' berisi cluster lain yang cocok dengan kode tersebut, bila ada.
     *
     * @return list<array{unit_id: string, cluster_id: string, block: string, reason: string, suggestion: ?string}>
     */
    public function auditUnits(): array
    {
        $issues = [];

        DB::table('units')->whereNull('deleted_at')->orderBy('id')->select('id', 'cluster_id', 'block')
            ->chunk(500, function ($units) use (&$issues) {
                foreach ($units as $unit) {
                    $cluster = $this->clusters()->get($unit->cluster_id);

                    if (! $cluster || $cluster->code_prefix === null || $this->blockNumberFor($unit->cluster_id, $unit->block) !== null) {
                        continue;
                    }

                    $other = $this->parse((string) $unit->block);
                    $issues[] = [
                        'unit_id' => $unit->id,
                        'cluster_id' => $unit->cluster_id,
                        'block' => (string) $unit->block,
                        'reason' => $other
                            ? "Kode {$unit->block} sesuai {$other['cluster']->name}, bukan {$cluster->name}."
                            : "Kode \"{$unit->block}\" tidak sesuai prefix {$cluster->code_prefix} + nomor blok yang diizinkan {$cluster->name}.",
                        'suggestion' => $other['cluster']->id ?? null,
                    ];
                }
            });

        return $issues;
    }

    public function flush(): void
    {
        $this->clusters = null;
    }

    /** @return Collection<string, Cluster> semua cluster, diindeks id */
    private function clusters(): Collection
    {
        return $this->clusters ??= Cluster::query()->get()->keyBy('id');
    }
}
