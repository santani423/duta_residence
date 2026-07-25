<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Shared image pipeline (store original + generate WebP + responsive variants)
 * used by both MediaController (admin uploads) and LandingCmsSeeder (seeding
 * realistic first-run content from remote URLs), so the encoding logic only
 * lives in one place.
 */
class MediaProcessingService
{
    public function storeUploadedFile(UploadedFile $file, ?int $uploadedBy = null, ?string $altText = null): MediaAsset
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $directory = 'landing-media/'.now()->format('Y/m');
        $basename = (string) Str::uuid();
        $relativePath = $file->storeAs($directory, "{$basename}.{$extension}", 'public');

        return $this->buildAsset($relativePath, $directory, $basename, $uploadedBy, $altText);
    }

    /**
     * Download a remote image and process it through the same pipeline as a
     * direct upload. Used by LandingCmsSeeder to seed realistic first-run
     * content; not used anywhere in the request/response cycle.
     */
    public function storeFromUrl(string $url, ?int $uploadedBy = null, ?string $altText = null): ?MediaAsset
    {
        $response = Http::timeout(15)->get($url);

        if (! $response->successful()) {
            return null;
        }

        $extension = 'jpg';
        $directory = 'landing-media/'.now()->format('Y/m');
        $basename = (string) Str::uuid();
        $relativePath = "{$directory}/{$basename}.{$extension}";
        Storage::disk('public')->put($relativePath, $response->body());

        return $this->buildAsset($relativePath, $directory, $basename, $uploadedBy, $altText);
    }

    private function buildAsset(string $relativePath, string $directory, string $basename, ?int $uploadedBy, ?string $altText): MediaAsset
    {
        $absolutePath = Storage::disk('public')->path($relativePath);
        $probe = Image::decodePath($absolutePath);
        $width = $probe->width();
        $height = $probe->height();

        $variants = ['webp' => $this->encodeVariant($absolutePath, $directory, $basename, null)];

        foreach (['medium' => 960, 'thumb' => 480] as $label => $targetWidth) {
            if ($width > $targetWidth) {
                $variants["webp_{$label}"] = $this->encodeVariant($absolutePath, $directory, $basename, $targetWidth);
            }
        }

        return MediaAsset::query()->create([
            'disk' => 'public',
            'path' => $relativePath,
            'mime_type' => Storage::disk('public')->mimeType($relativePath) ?: 'image/jpeg',
            'width' => $width,
            'height' => $height,
            'size' => Storage::disk('public')->size($relativePath),
            'variants' => $variants,
            'alt_text' => $altText,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    private function encodeVariant(string $absoluteSourcePath, string $directory, string $basename, ?int $targetWidth): string
    {
        $image = Image::decodePath($absoluteSourcePath);

        if ($targetWidth) {
            $image->scaleDown(width: $targetWidth);
        }

        $suffix = $targetWidth ? "-{$targetWidth}" : '';
        $relativePath = "{$directory}/{$basename}{$suffix}.webp";
        $image->encode(new WebpEncoder(quality: 82))->save(Storage::disk('public')->path($relativePath));

        return $relativePath;
    }
}
