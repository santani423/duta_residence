<?php

namespace Database\Seeders;

use App\Models\ClusterMapComponentType;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClusterMapComponentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('username', 'root')->first();

        collect([
            ['name' => 'Jalan Utama', 'code' => 'jalan-utama', 'category' => 'Jalan', 'icon' => 'road', 'fill_color' => '#8c8c8c', 'stroke_color' => '#595959', 'default_shape_type' => 'polygon'],
            ['name' => 'Jalan Lingkungan', 'code' => 'jalan-lingkungan', 'category' => 'Jalan', 'icon' => 'road', 'fill_color' => '#bfbfbf', 'stroke_color' => '#8c8c8c', 'default_shape_type' => 'polygon'],
            ['name' => 'Taman', 'code' => 'taman', 'category' => 'Fasilitas', 'icon' => 'tree', 'fill_color' => '#95de64', 'stroke_color' => '#52c41a', 'default_shape_type' => 'polygon'],
            ['name' => 'Pos Keamanan', 'code' => 'pos-keamanan', 'category' => 'Fasilitas', 'icon' => 'safety', 'fill_color' => '#ffd666', 'stroke_color' => '#d4b106', 'default_shape_type' => 'rect'],
            ['name' => 'Gerbang Masuk', 'code' => 'gerbang-masuk', 'category' => 'Gerbang', 'icon' => 'login', 'fill_color' => '#69b1ff', 'stroke_color' => '#1677ff', 'default_shape_type' => 'rect'],
            ['name' => 'Gerbang Keluar', 'code' => 'gerbang-keluar', 'category' => 'Gerbang', 'icon' => 'logout', 'fill_color' => '#69b1ff', 'stroke_color' => '#1677ff', 'default_shape_type' => 'rect'],
            ['name' => 'Tempat Ibadah', 'code' => 'tempat-ibadah', 'category' => 'Fasilitas', 'icon' => 'home', 'fill_color' => '#d3adf7', 'stroke_color' => '#9254de', 'default_shape_type' => 'rect'],
            ['name' => 'Area Parkir', 'code' => 'area-parkir', 'category' => 'Fasilitas', 'icon' => 'car', 'fill_color' => '#d9d9d9', 'stroke_color' => '#8c8c8c', 'default_shape_type' => 'polygon'],
            ['name' => 'Kolam', 'code' => 'kolam', 'category' => 'Fasilitas', 'icon' => 'water', 'fill_color' => '#87e8de', 'stroke_color' => '#13a8a8', 'default_shape_type' => 'circle'],
            ['name' => 'Lapangan', 'code' => 'lapangan', 'category' => 'Fasilitas', 'icon' => 'border', 'fill_color' => '#b7eb8f', 'stroke_color' => '#73d13d', 'default_shape_type' => 'rect'],
        ])->each(fn (array $type) => ClusterMapComponentType::query()->firstOrCreate(
            ['code' => $type['code']],
            [
                'name' => $type['name'],
                'category' => $type['category'],
                'icon' => $type['icon'],
                'fill_color' => $type['fill_color'],
                'stroke_color' => $type['stroke_color'],
                'default_shape_type' => $type['default_shape_type'],
                'is_active' => true,
                'created_by' => $creator?->id,
            ]
        ));
    }
}
