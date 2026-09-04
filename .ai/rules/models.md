---
paths:
  - 'app/Models/**'
---

# Models

## Model methods require PHPDoc
Every model method gets a short PHPDoc summary. Relationships include generics (@return HasMany<Related, $this>). Scopes document purpose; keep @param shapes for filters. Prefer summaries over restating the method name.
