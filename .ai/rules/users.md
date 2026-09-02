---
paths:
  - 'app/Http/Controllers/Landlord/Users/**'
---

# Users

## Keep user administration separate from self-service auth
User administration is UserService for admin CRUD, activate/deactivate, and role sync. AuthService handles login, logout, password recovery, profile updates, avatar management, and self-service password changes for the authenticated user. Inactive users fail login with the same email 422 as a bad password. The last Super Admin cannot be deactivated, deleted, or stripped of that role.
