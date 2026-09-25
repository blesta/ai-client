# Changelog

All notable changes to the Blesta AI PHP Client Library will be documented in this file.

## [1.1.0] - 2026-09-23

Additive release: no breaking changes. Code written for 1.0.x keeps working unchanged
(existing method signatures, constructor parameter order and return shapes are preserved).

### Added
- `BlestaAiClient::embeddings(string $model, string|array $inputs, array $options = []): EmbeddingResponse`
  - POSTs to the relative path `embeddings` (see the base_uri note below)
  - Options: `dimensions`, `input_type` (`query` | `document`; reserved: the server accepts it but
    currently ignores it), `timeout` (per-request Guzzle option, stripped from the payload like
    `chatCompletion()`)
  - Defaults to a 120 second per-request timeout (`BlestaAiClient::DEFAULT_EMBEDDINGS_TIMEOUT`)
    when no `timeout` is passed, since the server waits up to 90 seconds upstream; other methods
    keep the constructor default
  - Throws `BlestaAiException` unless the number of vectors equals the number of inputs (1 for a
    string input), the response model equals the requested model exactly, and (when `dimensions`
    was passed) the returned vector length equals the requested `dimensions`
  - Errors map to the existing exceptions: 401 `AuthenticationException`, 402
    `InsufficientCreditsException`, 422 `ValidationException`, 429 `RateLimitException`, anything
    else `BlestaAiException`
- `Models\EmbeddingResponse` (readonly): `model`, `dimensions`, `embeddings` (keyed and ordered by
  input index), `usage`, `requestId` (from the `X-Request-Id` header); `getModel()`,
  `getDimensions()`, `getEmbeddings()`, `getEmbedding(int $index)`, `getUsage()`, `getRequestId()`,
  `toArray()`. `fromArray()` throws `BlestaAiException` on a malformed body, including an empty
  `data` array; a negative, non-int, out-of-range or duplicate `index`; an empty vector; vectors
  of inconsistent length; or a declared `dimensions` that disagrees with the vector length.
- `getModels(?string $type = null)`: pass `'embedding'`, `'chat'` or `'all'` to filter. The default
  call is unchanged (no query string; the server returns chat models and aliases only).
- `Models\Model` gains nullable `type`, `dimensions`, `supportsDimensions`, `deprecatedAt` and
  `recommended` (parsed when present, appended after the existing constructor parameters), plus
  `isEmbedding()` and `isDeprecated()`. `toArray()` includes the new keys.
- PHPUnit test suite (`tests/`, Guzzle `MockHandler`) and `phpunit.xml.dist`; `composer test`.
  `phpunit/phpunit` is a `require-dev` dependency only.
- `examples/embeddings.php`

### Changed
- Minimum PHP version is now 8.2 (matches the canonical package).
- Server-side (no client code change): `GET /models` prices now carry up to 8 decimal places
  (e.g. `"0.00002500"`), exactly the rate requests are billed at. `Model::$promptPrice` and
  `$completionPrice` are still floats; format with 8 decimals if you display them.
- Server-side: an upstream provider's 429 `Retry-After` is now forwarded, so
  `RateLimitException::$retryAfter` is populated for those too.

## [1.0.x] - Fixes

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
- OpenAI-compatible API client for ai.blesta.com
- Support for chat completions (streaming and non-streaming)
- Model listing with pricing information
- Credit balance checking
- Comprehensive error handling with custom exceptions
- Full PHPDoc documentation
- Example scripts for common use cases
