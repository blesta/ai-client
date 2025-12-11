<?php
require_once __DIR__ . '/config.php';

// Handle clear conversation action
if (isset($_POST['action']) && $_POST['action'] === 'clear' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['messages'] = [];
    header('Location: index.php');
    exit;
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blesta AI Client Demo</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <h1>🤖 Blesta AI Client Demo</h1>
                <div class="header-actions">
                    <button id="theme-toggle" class="btn btn-icon" title="Toggle theme">
                        <span class="theme-icon">🌙</span>
                    </button>
                    <button id="clear-btn" class="btn btn-secondary" title="Clear conversation">
                        Clear Chat
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-section">
                    <h3>⚙️ Settings</h3>

                    <div class="form-group">
                        <label for="model-select">Model</label>
                        <select id="model-select" class="form-control">
                            <option value="">Loading models...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="streaming-toggle">
                            <input type="checkbox" id="streaming-toggle" checked>
                            Enable Streaming
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="temperature">Temperature: <span id="temperature-value">0.7</span></label>
                        <input type="range" id="temperature" class="slider" min="0" max="2" step="0.1" value="0.7">
                    </div>

                    <div class="form-group">
                        <label for="max-tokens">Max Tokens: <span id="max-tokens-value">1000</span></label>
                        <input type="range" id="max-tokens" class="slider" min="100" max="4000" step="100" value="1000">
                    </div>
                </div>

                <div class="sidebar-section">
                    <h3>💰 Credits</h3>
                    <div class="credit-display">
                        <div id="credit-balance" class="credit-balance">
                            <span class="loading">Loading...</span>
                        </div>
                        <button id="refresh-credits" class="btn btn-sm">Refresh</button>
                    </div>
                </div>

                <div class="sidebar-section">
                    <h3>📊 Current Session</h3>
                    <div class="stats">
                        <div class="stat-item">
                            <span class="stat-label">Total Messages:</span>
                            <span id="message-count" class="stat-value">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Tokens:</span>
                            <span id="total-tokens" class="stat-value">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Cost:</span>
                            <span id="total-cost" class="stat-value">$0.0000</span>
                        </div>
                    </div>
                </div>

                <?php if (!isConfigured()): ?>
                <div class="sidebar-section alert-warning">
                    <h3>⚠️ Configuration Required</h3>
                    <p>Please update <code>demo/config.php</code> with your API key.</p>
                </div>
                <?php endif; ?>
            </aside>

            <!-- Chat Area -->
            <main class="chat-container">
                <div id="chat-messages" class="chat-messages">
                    <div class="welcome-message">
                        <h2>Welcome to Blesta AI Client Demo</h2>
                        <p>This demo showcases the features of the Blesta AI PHP Client Library.</p>
                        <ul>
                            <li>💬 Chat with various AI models</li>
                            <li>⚡ Real-time streaming responses</li>
                            <li>📊 Track token usage and costs</li>
                            <li>🎨 Dark/Light theme support</li>
                        </ul>
                        <p><strong>Start chatting below!</strong></p>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="input-area">
                    <form id="chat-form">
                        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="input-wrapper">
                            <textarea
                                id="message-input"
                                placeholder="Type your message here..."
                                rows="1"
                                <?php echo !isConfigured() ? 'disabled' : ''; ?>
                            ></textarea>
                            <button
                                type="submit"
                                id="send-btn"
                                class="btn btn-primary"
                                <?php echo !isConfigured() ? 'disabled' : ''; ?>
                            >
                                <span class="btn-text">Send</span>
                                <span class="btn-icon">📤</span>
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container"></div>

    <!-- Hidden form for clearing conversation -->
    <form id="clear-form" method="POST" style="display: none;">
        <input type="hidden" name="action" value="clear">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    </form>

    <script src="assets/js/app.js"></script>
</body>
</html>
