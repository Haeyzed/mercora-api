---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Scan uploads with ClamAV in form requests
Scan every uploaded file with the sunspikes/clamav-validator clamav rule in the form request, after mime, extension, and size checks. Skip scanning only through config('clamav.skip_validation') (phpunit sets CLAMAV_SKIP_VALIDATION=true). Do not call ClamAV from controllers or services.
