---
paths:
  - 'app/Http/Controllers/Landlord/ApiKeys/**'
---

# Api Keys

## API keys are a hashed ledger
Landlord API keys are a hashed key ledger, not a Sanctum token wrapper and not a new auth guard. Follow World HTTP conventions plus POST /api-keys/{api_key}/revoke. Store generates mrc_ plus 40 random characters, stores sha256 in key_hash, and returns the plaintext token only on create. The client cannot set token, prefix, key_hash, status, last_used_at, or revoked_at. Update and revoke require an active key and throw ValidationException on status with "The API key is not active." Soft delete. Integer ids. Skip import/export/template. Do not authenticate incoming requests with these keys yet.
