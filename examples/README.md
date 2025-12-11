# Blesta AI Client Library Examples

This directory contains example scripts demonstrating how to use the Blesta AI PHP Client Library.

## Setup

Before running any examples, make sure to:

1. Install dependencies: `composer install` (from the parent directory)
2. Replace `'sk_your_api_key_here'` with your actual API key in each example

## Examples

### Basic Examples

#### `check_credits.php`
Check your current credit balance.
```bash
php check_credits.php
```

#### `list_models.php`
List all available AI models with pricing information.
```bash
php list_models.php
```

### Chat Completion Examples

#### `chat_completion.php`
Send a non-streaming chat completion request and receive the full response at once.
```bash
php chat_completion.php
```

#### `streaming_chat.php`
Demonstrates streaming mode with a short response. Good for understanding the basics of streaming.
```bash
php streaming_chat.php
```

#### `streaming_chat_long.php`
Demonstrates streaming with a longer response, making it easier to see the streaming effect.
```bash
php streaming_chat_long.php
```

## Understanding Streaming

When using streaming mode:

- **From command line**: You should see text appear word-by-word as it's generated
- **In a browser**: You may see the entire response at once if:
  - The response is very short (completes in milliseconds)
  - Your web server has output buffering enabled
  - PHP output buffering is active

### Seeing True Streaming in a Browser

If you're not seeing streaming in a browser, try:

1. **Use a longer prompt** - Run `streaming_chat_long.php` instead of `streaming_chat.php`
2. **Check web server buffering**:
   - **Nginx**: Ensure `fastcgi_buffering off;` and `proxy_buffering off;` in your config
   - **Apache**: May need to adjust `output_buffering` in php.ini
3. **Use a different browser** - Some browsers buffer responses differently
4. **Test from command line** - Run `php streaming_chat_long.php` to see pure streaming without browser interference

## Testing Examples

### `test_connection.php`
Quick test to verify you can connect to the API.
```bash
php test_connection.php
```

### `debug_url.php`
Debug script that shows how URLs are constructed by the client library.
```bash
php debug_url.php
```

## Common Issues

### "The route auth/key could not be found" (404 Error)
- Make sure you're using the latest version of the client library
- Check that the base URL is correct (defaults to https://ai.blesta.com/api/v1)

### "Unauthorized - Invalid API key" (401 Error)
- Replace `'sk_your_api_key_here'` with your actual API key
- Verify your API key is active in your account

### "Insufficient balance" (402 Error)
- Add credits to your account at account.blesta.com
- Check your balance using `check_credits.php`

### Streaming shows all at once
- Try `streaming_chat_long.php` for a longer response
- Run from command line instead of browser
- Check your web server's buffering configuration

## Getting Help

- Documentation: https://ai.blesta.com/docs
- Support: support@blesta.com
- GitHub Issues: [Report issues here]
