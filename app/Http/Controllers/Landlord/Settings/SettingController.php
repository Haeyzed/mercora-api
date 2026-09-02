<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Settings\UpdateSettingsDomainRequest;
use App\Http\Resources\Landlord\Settings\SettingsDomainResource;
use App\Models\Landlord\Setting;
use App\Services\Landlord\Settings\SettingService;
use App\Support\Settings\SettingsManager;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Landlord settings domains backed by schema definitions.
 */
#[Group('Landlord Settings')]
class SettingController extends Controller
{
    public function __construct(
        private SettingService $settingService,
        private SettingsManager $settings,
    ) {}

    /**
     * List settings domains with current values.
     *
     * @return AnonymousResourceCollection<int, SettingsDomainResource>
     */
    #[Endpoint(operationId: 'listLandlordSettingsDomains', title: 'List settings domains')]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Setting::class);

        return SettingsDomainResource::collection(
            collect($this->settings->domains())->map(
                fn (string $domain) => $this->settingService->showDomain($domain),
            ),
        );
    }

    /**
     * Show a settings domain.
     */
    #[Endpoint(operationId: 'showLandlordSettingsDomain', title: 'Show a settings domain')]
    public function show(string $domain): SettingsDomainResource
    {
        $this->authorize('viewAny', Setting::class);

        return new SettingsDomainResource($this->settingService->showDomain($domain));
    }

    /**
     * Update a settings domain.
     */
    #[Endpoint(operationId: 'updateLandlordSettingsDomain', title: 'Update a settings domain')]
    public function update(UpdateSettingsDomainRequest $request, string $domain): SettingsDomainResource
    {
        $this->authorize('update', Setting::class);

        return new SettingsDomainResource(
            $this->settingService->updateDomain($domain, $request->settingsPayload()),
        );
    }
}
