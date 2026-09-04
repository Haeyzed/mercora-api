<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;

/**
 * Landlord media and upload policy.
 *
 * Domain: runtime upload ceilings that override config defaults for landlord uploads.
 */
final class StorageDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'storage';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'storage.image_max_kb' => new SettingDefinition(
                type: SettingType::Integer,
                default: 10240,
                rules: ['sometimes', 'integer', 'min:100', 'max:102400'],
            ),
            'storage.document_max_kb' => new SettingDefinition(
                type: SettingType::Integer,
                default: 20480,
                rules: ['sometimes', 'integer', 'min:100', 'max:204800'],
            ),
            'storage.video_max_kb' => new SettingDefinition(
                type: SettingType::Integer,
                default: 102400,
                rules: ['sometimes', 'integer', 'min:1000', 'max:1048576'],
            ),
            'storage.avatar_max_kb' => new SettingDefinition(
                type: SettingType::Integer,
                default: 2048,
                rules: ['sometimes', 'integer', 'min:50', 'max:10240'],
            ),
            'storage.generate_thumbnails' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'storage.retain_orphaned_media_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 7,
                rules: ['sometimes', 'integer', 'min:1', 'max:365'],
            ),
        ];
    }
}
