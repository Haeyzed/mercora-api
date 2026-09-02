<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class MediaService
{
    /**
     * @param  array{name?: string|null, custom_properties?: array<string, mixed>}  $options
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function replace(
        HasMedia $model,
        UploadedFile $file,
        MediaCollection|string $collection,
        array $options = [],
    ): Media {
        $collectionName = $this->collectionName($collection);
        $enum = $collection instanceof MediaCollection
            ? $collection
            : MediaCollection::tryFrom($collectionName);

        $adder = $model->addMedia($file);

        if (! empty($options['name'])) {
            $adder->usingName((string) $options['name']);
        }

        if (! empty($options['custom_properties']) && is_array($options['custom_properties'])) {
            $adder->withCustomProperties($options['custom_properties']);
        }

        $media = $adder->toMediaCollection($collectionName);

        if ($enum === null || ! $enum->isSingleFile()) {
            $model->getMedia($collectionName)
                ->reject(fn (Media $item): bool => $item->is($media))
                ->each(fn (Media $item) => $item->delete());
        }

        return $media;
    }

    public function removeCollection(HasMedia $model, MediaCollection|string $collection): void
    {
        $model->clearMediaCollection($this->collectionName($collection));
    }

    public function getFirst(HasMedia $model, MediaCollection|string $collection): ?Media
    {
        return $model->getFirstMedia($this->collectionName($collection));
    }

    public function urlFor(Media $media, MediaConversion|string|null $conversion = null): ?string
    {
        if ($conversion === null) {
            return $media->getUrl();
        }

        $name = $conversion instanceof MediaConversion ? $conversion->value : $conversion;

        if (! $media->hasGeneratedConversion($name)) {
            return null;
        }

        return $media->getUrl($name);
    }

    public function assertBelongsTo(HasMedia $model, Media $media): void
    {
        if ($media->model_type !== $model->getMorphClass() || (string) $media->model_id !== (string) $model->getKey()) {
            throw new NotFoundHttpException('Media not found.');
        }
    }

    protected function collectionName(MediaCollection|string $collection): string
    {
        return $collection instanceof MediaCollection ? $collection->value : $collection;
    }
}
