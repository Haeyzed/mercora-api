<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Landlord\Settings\SettingService;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait LogsLandlordActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $except = $this->activitylogExcept();

        if ($this->shouldMaskPiiInLogs()) {
            $except = array_values(array_unique([
                ...$except,
                ...$this->piiActivitylogAttributes(),
            ]));
        }

        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept($except);
    }

    /**
     * @return list<string>
     */
    protected function activitylogExcept(): array
    {
        return [];
    }

    /**
     * Attributes commonly treated as PII across landlord models.
     *
     * @return list<string>
     */
    protected function piiActivitylogAttributes(): array
    {
        return [
            'email',
            'password',
            'phone',
            'support_email',
            'privacy_contact_email',
            'company_email',
            'company_vat_number',
            'company_address',
            'key_hash',
            'remember_token',
        ];
    }

    private function shouldMaskPiiInLogs(): bool
    {
        try {
            if (! Schema::hasTable('settings')) {
                return true;
            }

            return (bool) app(SettingService::class)->value('compliance.mask_pii_in_logs', true);
        } catch (\Throwable) {
            return true;
        }
    }
}
