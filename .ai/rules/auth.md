---
paths:
  - 'app/Services/Landlord/Auth/**'
---

# Auth

## Landlord routes require Sanctum except login and password recovery
Landlord API auth is Sanctum tokens on App\Models\Landlord\User. Public POST /api/landlord/auth/login, /forgot-password, and /reset-password are throttled via landlord-auth. Authenticated routes include logout, me, profile update, avatar replace/remove, and change-password. logout, me, profile, avatar, change-password, plus every other /api/landlord/* route, use auth:sanctum. World feature tests authenticate with actingAsLandlord() from tests/Pest.php.
