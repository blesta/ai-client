# Blesta AI Client Demo

A standalone web interface demonstrating the features of the Blesta AI PHP Client Library.

## Features

- 💬 **Interactive Chat Interface** - Chat with various AI models
- ⚡ **Real-time Streaming** - Toggle between streaming and non-streaming responses
- 🎨 **Dark/Light Theme** - Automatic theme switching with localStorage persistence
- 📊 **Usage Tracking** - Track tokens, costs, and conversation history
- 💰 **Credit Balance** - Real-time credit balance display
- 🔒 **Security** - CSRF protection, rate limiting, and input sanitization
- 📱 **Responsive Design** - Works on desktop, tablet, and mobile devices

## Prerequisites

- PHP 8.1 or higher
- Composer (for dependencies)
- A web server (Apache, Nginx, or PHP built-in server)
- Blesta AI API key from [ai.blesta.com](https://ai.blesta.com)

## Installation

### 1. Install Dependencies

From the root directory of the project:

```bash
composer install
```

### 2. Configure API Key

Copy the example configuration file and update with your API key:

```bash
cd demo
cp config.example.php config.php
```

Then edit `config.php` and replace the placeholder API key:

```php
define('BLESTA_AI_API_KEY', 'your-actual-api-key-here');
```

**Note:** The `config.php` file is gitignored to prevent accidentally committing your API key.

### 3. Start Web Server

**Option A: PHP Built-in Server (Development)**

From the `demo/` directory:

```bash
php -S localhost:8000
```

Then open http://localhost:8000 in your browser.

**Option B: Apache/Nginx**

Configure your web server to serve the `demo/` directory. Make sure the `.htaccess` file is processed (for Apache) or configure appropriate rules for Nginx.

Example Apache VirtualHost:
```apache
<VirtualHost *:80>
    ServerName blesta-ai-demo.local
    DocumentRoot "C:/wamp64/www/blesta-ai-client-library/demo"

    <Directory "C:/wamp64/www/blesta-ai-client-library/demo">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Usage

### Basic Chat

1. Open the demo in your browser
2. Select a model from the dropdown
3. Type a message and press Enter or click Send
4. View the AI response in real-time (if streaming is enabled)

### Settings

**Model Selection**
- Choose from various AI models (GPT-4, Claude, Gemini, etc.)
- Each model shows pricing per 1K tokens

**Streaming**
- Enable for real-time token-by-token responses
- Disable for complete responses at once

**Temperature** (0.0 - 2.0)
- Lower values: More focused and deterministic
- Higher values: More creative and random

**Max Tokens** (100 - 4000)
- Controls the maximum length of responses
- Higher values allow longer responses but cost more

### Theme

Toggle between dark and light themes using the moon/sun icon in the header. Your preference is saved automatically.

### Conversation Management

- **Clear Chat**: Removes all messages from the current session
- **Copy Messages**: Click the copy icon on any assistant message
- **Session Stats**: View total messages, tokens, and costs

## Project Structure

```
demo/
├── index.php              # Main chat interface
├── config.example.php     # Example configuration file
├── config.php             # Your configuration (gitignored)
├── .htaccess              # Apache security rules
├── api/
│   ├── chat.php         # Handle chat requests (streaming & non-streaming)
│   ├── models.php       # Fetch available models
│   └── credits.php      # Check credit balance
├── assets/
│   ├── css/
│   │   └── style.css    # Styling with light/dark theme
│   └── js/
│       └── app.js       # Frontend logic
└── README.md           # This file
```

## Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **Rate Limiting**: Session-based rate limiting (30 requests/hour by default)
- **Input Sanitization**: All user input is validated and sanitized
- **Config Protection**: `.htaccess` prevents direct access to `config.php`

## Configuration Options

Edit `config.php` to customize:

```php
// API Configuration
define('BLESTA_AI_API_KEY', 'your-api-key-here');
define('BLESTA_AI_BASE_URL', 'https://ai.blesta.com/api/v1');
define('BLESTA_AI_TIMEOUT', 60);

// Rate Limiting
define('RATE_LIMIT_REQUESTS', 30);
define('RATE_LIMIT_WINDOW', 3600);

// Defaults
define('DEFAULT_MODEL', 'openai/gpt-4o-mini');
define('DEFAULT_TEMPERATURE', 0.7);
define('DEFAULT_MAX_TOKENS', 1000);
```

## Troubleshooting

### "API key not configured" Error

Make sure you've:
1. Copied `config.example.php` to `config.php`
2. Updated `config.php` with your actual API key from ai.blesta.com

### Models Not Loading

Check that:
1. Your API key is valid
2. You have internet connectivity
3. The Blesta AI API is accessible

### Streaming Not Working

Some web servers may buffer output. For Apache, make sure output buffering is disabled. For Nginx, you may need to add:

```nginx
proxy_buffering off;
```

### Rate Limit Exceeded

Clear your browser cookies or wait for the rate limit window to reset (default: 1 hour).

## Development

### Customizing the UI

- **Styles**: Edit `assets/css/style.css`
- **Behavior**: Edit `assets/js/app.js`
- **Layout**: Edit `index.php`

### Adding Features

The demo is designed to be easily extensible. Some ideas:

- Add conversation persistence (database or localStorage)
- Implement user authentication
- Add file upload support
- Create conversation templates/presets
- Add export functionality (PDF, Markdown, etc.)

## API Endpoints

### POST /api/chat.php

Send a chat message.

**Request Body:**
```json
{
    "message": "Hello, AI!",
    "model": "openai/gpt-4o-mini",
    "streaming": true,
    "temperature": 0.7,
    "max_tokens": 1000,
    "csrf_token": "..."
}
```

**Response (Non-streaming):**
```json
{
    "success": true,
    "response": "Hello! How can I help you?",
    "model": "openai/gpt-4o-mini",
    "usage": {
        "promptTokens": 10,
        "completionTokens": 8,
        "totalTokens": 18,
        "cost": 0.000027,
        "remainingBalance": 9.99
    }
}
```

**Response (Streaming):** Server-Sent Events (SSE)

### GET /api/models.php

Get available AI models.

**Response:**
```json
{
    "success": true,
    "models": [...],
    "count": 50
}
```

### GET /api/credits.php

Get current credit balance.

**Response:**
```json
{
    "success": true,
    "balance": 10.5,
    "formatted": "$10.5000"
}
```

## License

This demo is part of the Blesta AI PHP Client Library and is licensed under the MIT License.

## Support

For issues or questions:
- Check the main project README at `../README.md`
- Review the API documentation at https://ai.blesta.com/docs/api
- Contact Blesta support

## Credits

Built with:
- Blesta AI Client Library
- Vanilla JavaScript (no frameworks)
- Modern CSS with CSS Variables
- PHP 8.1+ with type safety
