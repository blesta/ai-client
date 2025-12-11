# Changelog

All notable changes to the Blesta AI PHP Client Library will be documented in this file.

## [Unreleased]

### Fixed
- **Critical**: Fixed `remaining_balance` field name inconsistency between API and client library
  - API was returning `balance_remaining` but client expected `remaining_balance`
  - Standardized on `remaining_balance` throughout
  - Streaming responses now include `cost` and `remaining_balance` in usage data
  - This fixes the issue where examples showed $0.0000 for remaining balance

- **Critical**: Fixed Guzzle base_uri path resolution issue that caused 404 errors
  - Changed all HTTP request paths from absolute (`/auth/key`) to relative (`auth/key`)
  - Added trailing slash normalization to `base_uri` in constructor
  - This fix ensures requests properly resolve to `https://ai.blesta.com/api/v1/[endpoint]`
  - Affects all endpoints: `/auth/key`, `/chat/completions`, `/models`

### Technical Details
When using Guzzle with a `base_uri` that includes a path component (e.g., `/api/v1`):
- Paths starting with `/` are treated as absolute from the domain root
- Paths without leading `/` are properly appended to the `base_uri`
- The `base_uri` must end with a trailing slash for correct resolution

**Before (incorrect):**
```php
base_uri: 'https://ai.blesta.com/api/v1'
path: '/auth/key'
result: https://ai.blesta.com/auth/key  ❌
```

**After (correct):**
```php
base_uri: 'https://ai.blesta.com/api/v1/'
path: 'auth/key'
result: https://ai.blesta.com/api/v1/auth/key  ✅
```

### Files Changed
- `src/BlestaAiClient.php`: Lines 51, 100, 164, 221, 255

### Testing
Run the included test scripts to verify the fix:
```bash
cd examples
php test_connection.php  # Should show "API endpoint reached successfully"
php debug_url.php        # Shows URL resolution details
```

## [1.0.0] - Initial Release

### Added
- OpenRouter-compatible API client for ai.blesta.com
- Support for chat completions (streaming and non-streaming)
- Model listing with pricing information
- Credit balance checking
- Comprehensive error handling with custom exceptions
- Full PHPDoc documentation
- Example scripts for common use cases
