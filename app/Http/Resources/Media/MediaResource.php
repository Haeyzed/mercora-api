<?php

declare(strict_types=1);

namespace App\Http\Resources\Media;

use App\Enums\Media\MediaConversion;
use App\Services\Media\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Media $media */
        $media = $this->resource;
        $mediaService = app(MediaService::class);

        return [
            'id' => $media->id,
            'collection' => $media->collection_name,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $media->getUrl(),
            'thumbnail_url' => $mediaService->urlFor($media, MediaConversion::Thumb),
            'created_at' => $media->created_at,
            'updated_at' => $media->updated_at,
        ];
    }
}
