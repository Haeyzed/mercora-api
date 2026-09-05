<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Notifications\SyncNotificationPreferencesRequest;
use App\Models\Landlord\User;
use App\Services\Landlord\Notifications\NotificationPreferenceService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Landlord Notification Preferences')]
class NotificationPreferenceController extends Controller
{
    public function __construct(private NotificationPreferenceService $preferences) {}

    /**
     * List effective notification preferences for the authenticated user.
     */
    #[Endpoint(operationId: 'listLandlordNotificationPreferences', title: 'List notification preferences')]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->preferences->listForUser($user),
        ]);
    }

    /**
     * Sync notification preferences for the authenticated user.
     */
    #[Endpoint(operationId: 'syncLandlordNotificationPreferences', title: 'Sync notification preferences')]
    public function update(SyncNotificationPreferencesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->preferences->syncForUser($user, $request->validated('preferences')),
        ]);
    }
}
