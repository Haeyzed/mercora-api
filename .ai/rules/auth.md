---
paths:
  - 'app/Services/Landlord/Auth/**'
---

# Auth

## Landlord routes require Sanctum except login
Landlord API auth is Sanctum tokens on App\Models\Landlord\User. Public POST /api/landlord/auth/login (throttled landlord-login) issues a token. logout and me, plus every other /api/landlord/* route, use auth:sanctum. World feature tests authenticate with actingAsLandlord() from tests/Pest.php.
