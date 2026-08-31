---
paths:
  - 'app/Http/Controllers/Landlord/Users/**'
---

# Users

## Keep AuthService separate from user administration
User administration is UserService, not AuthService. AuthService only logs in, logs out, and issues Sanctum tokens. Inactive users fail login with the same email 422 as a bad password. The last Super Admin cannot be deactivated, deleted, or stripped of that role.
