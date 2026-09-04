<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;

/**
 * Landlord outbound mail identity defaults.
 *
 * Domain: from/reply-to presentation used by notifications. SMTP credentials stay in env/config.
 */
final class MailDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'mail';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'mail.from_name' => new SettingDefinition(
                type: SettingType::String,
                default: 'Mercora',
                rules: ['sometimes', 'string', 'max:255'],
            ),
            'mail.from_address' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'email', 'max:255'],
            ),
            'mail.reply_to_address' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'email', 'max:255'],
            ),
            'mail.support_inbox' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'email', 'max:255'],
            ),
            'mail.footer_text' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:1000'],
            ),
            'mail.logo_url' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'url', 'max:255'],
            ),
        ];
    }
}
