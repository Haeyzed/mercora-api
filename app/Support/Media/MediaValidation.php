<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Services\Landlord\SettingService;
use Illuminate\Support\Facades\Schema;

final class MediaValidation
{
    private function __construct() {}

    /**
     * @return list<string>
     */
    public static function image(bool $required = true): array
    {
        return self::rules(
            required: $required,
            maxKey: 'image',
            settingKey: 'storage.image_max_kb',
            extensionsKey: 'image',
            mimesKey: 'image',
            extra: ['file', 'image'],
        );
    }

    /**
     * Avatar uploads prefer the dedicated avatar ceiling when set.
     *
     * @return list<string>
     */
    public static function avatar(bool $required = true): array
    {
        return self::rules(
            required: $required,
            maxKey: 'image',
            settingKey: 'storage.avatar_max_kb',
            extensionsKey: 'image',
            mimesKey: 'image',
            extra: ['file', 'image'],
        );
    }

    /**
     * @return list<string>
     */
    public static function document(bool $required = true): array
    {
        return self::rules(
            required: $required,
            maxKey: 'document',
            settingKey: 'storage.document_max_kb',
            extensionsKey: 'document',
            mimesKey: 'document',
            extra: ['file'],
        );
    }

    /**
     * @return list<string>
     */
    public static function video(bool $required = true): array
    {
        return self::rules(
            required: $required,
            maxKey: 'video',
            settingKey: 'storage.video_max_kb',
            extensionsKey: 'video',
            mimesKey: 'video',
            extra: ['file'],
        );
    }

    /**
     * @param  list<string>  $extra
     * @return list<string>
     */
    protected static function rules(
        bool $required,
        string $maxKey,
        string $settingKey,
        string $extensionsKey,
        string $mimesKey,
        array $extra,
    ): array {
        $max = self::maxKilobytes($settingKey, $maxKey);
        $extensions = implode(',', config("media.extensions.{$extensionsKey}", []));
        $mimetypes = implode(',', config("media.mimes.{$mimesKey}", []));

        $rules = [$required ? 'required' : 'sometimes'];

        if (! $required) {
            $rules[] = 'nullable';
        }

        return [
            ...$rules,
            ...$extra,
            'mimes:'.$extensions,
            'mimetypes:'.$mimetypes,
            'max:'.$max,
        ];
    }

    /**
     * Prefer landlord storage settings, then config upload limits.
     */
    private static function maxKilobytes(string $settingKey, string $configKey): int
    {
        $fallback = (int) config("media.upload_limits.{$configKey}", 10240);

        try {
            if (! Schema::hasTable('settings')) {
                return $fallback;
            }

            return max(1, (int) app(SettingService::class)->value($settingKey, $fallback));
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
