---
paths:
  - 'app/Models/Shared/**'
---

# Shared

## World filters live on model scopes
Apply World list filters through each model's #[Scope] filter() method, not in the service. Use AllowsIncludes withIncludes()/loadAllowedIncludes() for allowed relationships and ordered() for name+id sorting. Services only orchestrate those scopes.

## World search is a separate scope from filter
Keep filter[] field-specific (exact or one-column LIKE). Add a model #[Scope] search() for ?search= that ORs a trimmed term across that entity's useful text columns. Services chain ->search($request->query('search')) on paginate() and options(). Blank search is a no-op.
