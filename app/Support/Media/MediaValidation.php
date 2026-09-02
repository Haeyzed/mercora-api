<?php

declare(strict_types=1);

namespace App\Support\Media;

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
            extensionsKey: 'image',
            mimesKey: 'image',
            extra: ['file', 'image'],
        );
    }

    /**
     * @param  list<string>  $extra
     * @return list<string>
     */
    protected static function rules(
        bool $required,
        string $maxKey,
        string $extensionsKey,
        string $mimesKey,
        array $extra,
    ): array {
        $max = (int) config("media.upload_limits.{$maxKey}", 10240);
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
}
