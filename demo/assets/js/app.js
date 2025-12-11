/**
 * Blesta AI Client Demo - Frontend JavaScript
 */

// State management
const state = {
    models: [],
    currentModel: null,
    isStreaming: true,
    temperature: 0.7,
    maxTokens: 1000,
    sessionStats: {
        messageCount: 0,
        totalTokens: 0,
        totalCost: 0
    },
    isProcessing: false
};

// DOM Elements
const elements = {
    chatMessages: document.getElementById('chat-messages'),
    messageInput: document.getElementById('message-input'),
    chatForm: document.getElementById('chat-form'),
    sendBtn: document.getElementById('send-btn'),
    modelSelect: document.getElementById('model-select'),
    streamingToggle: document.getElementById('streaming-toggle'),
    temperatureSlider: document.getElementById('temperature'),
    temperatureValue: document.getElementById('temperature-value'),
    maxTokensSlider: document.getElementById('max-tokens'),
    maxTokensValue: document.getElementById('max-tokens-value'),
    creditBalance: document.getElementById('credit-balance'),
    refreshCredits: document.getElementById('refresh-credits'),
    messageCount: document.getElementById('message-count'),
    totalTokens: document.getElementById('total-tokens'),
    totalCost: document.getElementById('total-cost'),
    themeToggle: document.getElementById('theme-toggle'),
    clearBtn: document.getElementById('clear-btn'),
    csrfToken: document.getElementById('csrf-token')
};

// Initialize app
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    loadModels();
    loadCredits();
    attachEventListeners();
    autoResizeTextarea();
});

// Theme management
function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcon(newTheme);
}

function updateThemeIcon(theme) {
    const icon = elements.themeToggle.querySelector('.theme-icon');
    icon.textContent = theme === 'dark' ? '☀️' : '🌙';
}

// Load available models
async function loadModels() {
    try {
        const response = await fetch('api/models.php');
        const data = await response.json();

        if (data.success) {
            state.models = data.models;
            populateModelSelect(data.models);
        } else {
            showToast('Failed to load models: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error loading models:', error);
        showToast('Failed to load models', 'error');
    }
}

function populateModelSelect(models) {
    elements.modelSelect.innerHTML = '';

    models.forEach(model => {
        const option = document.createElement('option');
        option.value = model.id;
        option.textContent = model.displayName;
        elements.modelSelect.appendChild(option);
    });

    // Set default model
    const defaultModel = localStorage.getItem('selectedModel');
    if (defaultModel && models.find(m => m.id === defaultModel)) {
        elements.modelSelect.value = defaultModel;
    }

    state.currentModel = elements.modelSelect.value;
}

// Load credit balance
async function loadCredits() {
    try {
        const response = await fetch('api/credits.php');
        const data = await response.json();

        if (data.success) {
            elements.creditBalance.innerHTML = `<strong>${data.formatted}</strong>`;
        } else {
            elements.creditBalance.innerHTML = '<span class="error">Error loading</span>';
        }
    } catch (error) {
        console.error('Error loading credits:', error);
        elements.creditBalance.innerHTML = '<span class="error">Error loading</span>';
    }
}

// Attach event listeners
function attachEventListeners() {
    // Chat form submission
    elements.chatForm.addEventListener('submit', handleChatSubmit);

    // Model selection
    elements.modelSelect.addEventListener('change', (e) => {
        state.currentModel = e.target.value;
        localStorage.setItem('selectedModel', e.target.value);
    });

    // Streaming toggle
    elements.streamingToggle.addEventListener('change', (e) => {
        state.isStreaming = e.target.checked;
    });

    // Temperature slider
    elements.temperatureSlider.addEventListener('input', (e) => {
        state.temperature = parseFloat(e.target.value);
        elements.temperatureValue.textContent = state.temperature;
    });

    // Max tokens slider
    elements.maxTokensSlider.addEventListener('input', (e) => {
        state.maxTokens = parseInt(e.target.value);
        elements.maxTokensValue.textContent = state.maxTokens;
    });

    // Refresh credits
    elements.refreshCredits.addEventListener('click', loadCredits);

    // Theme toggle
    elements.themeToggle.addEventListener('click', toggleTheme);

    // Clear conversation
    elements.clearBtn.addEventListener('click', () => {
        if (confirm('Are you sure you want to clear the conversation?')) {
            document.getElementById('clear-form').submit();
        }
    });

    // Auto-resize textarea
    elements.messageInput.addEventListener('input', autoResizeTextarea);

    // Submit on Enter (but not Shift+Enter)
    elements.messageInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            elements.chatForm.dispatchEvent(new Event('submit'));
        }
    });
}

// Handle chat form submission
async function handleChatSubmit(e) {
    e.preventDefault();

    const message = elements.messageInput.value.trim();
    if (!message || state.isProcessing) return;

    // Clear input
    elements.messageInput.value = '';
    autoResizeTextarea();

    // Add user message to chat
    addMessage('user', message);

    // Set processing state
    state.isProcessing = true;
    elements.sendBtn.disabled = true;
    elements.messageInput.disabled = true;
    elements.sendBtn.querySelector('.btn-text').textContent = 'Sending...';

    try {
        if (state.isStreaming) {
            await handleStreamingChat(message);
        } else {
            await handleNonStreamingChat(message);
        }
    } catch (error) {
        console.error('Chat error:', error);
        showToast('Failed to send message: ' + error.message, 'error');
    } finally {
        // Reset processing state
        state.isProcessing = false;
        elements.sendBtn.disabled = false;
        elements.messageInput.disabled = false;
        elements.sendBtn.querySelector('.btn-text').textContent = 'Send';
        elements.messageInput.focus();
    }
}

// Handle non-streaming chat
async function handleNonStreamingChat(message) {
    const loadingMessageId = addMessage('assistant', 'Thinking...', null, true);

    try {
        const response = await fetch('api/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: message,
                model: state.currentModel,
                streaming: false,
                temperature: state.temperature,
                max_tokens: state.maxTokens,
                csrf_token: elements.csrfToken.value
            })
        });

        const data = await response.json();

        // Remove loading message
        removeMessage(loadingMessageId);

        if (data.success) {
            addMessage('assistant', data.response, data.usage);
            updateSessionStats(data.usage);
            loadCredits(); // Refresh balance
        } else {
            showToast('Error: ' + data.error, 'error');
            addMessage('assistant', '❌ Error: ' + data.error);
        }
    } catch (error) {
        removeMessage(loadingMessageId);
        throw error;
    }
}

// Handle streaming chat
async function handleStreamingChat(message) {
    const messageId = addMessage('assistant', '', null, true);
    let fullResponse = '';
    let usage = null;

    try {
        const response = await fetch('api/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: message,
                model: state.currentModel,
                streaming: true,
                temperature: state.temperature,
                max_tokens: state.maxTokens,
                csrf_token: elements.csrfToken.value
            })
        });

        if (!response.ok) {
            const data = await response.json();
            removeMessage(messageId);
            throw new Error(data.error || 'Request failed');
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        while (true) {
            const {value, done} = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value);
            const lines = chunk.split('\n');

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    const jsonStr = line.substring(6);
                    try {
                        const data = JSON.parse(jsonStr);

                        if (data.type === 'content') {
                            fullResponse += data.content;
                            updateMessage(messageId, fullResponse);
                        } else if (data.type === 'usage') {
                            usage = data.usage;
                        } else if (data.type === 'done') {
                            updateMessage(messageId, fullResponse, usage, false);
                            updateSessionStats(usage);
                            loadCredits();
                            return;
                        }
                    } catch (e) {
                        // Ignore parse errors
                    }
                }
            }
        }
    } catch (error) {
        removeMessage(messageId);
        showToast('Streaming error: ' + error.message, 'error');
        throw error;
    }
}

// Add message to chat
function addMessage(role, content, usage = null, isLoading = false) {
    const messageId = 'msg-' + Date.now() + '-' + Math.random();
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${role} ${isLoading ? 'loading' : ''}`;
    messageDiv.id = messageId;

    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.textContent = role === 'user' ? '👤' : '🤖';

    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';

    const textDiv = document.createElement('div');
    textDiv.className = 'message-text';
    textDiv.textContent = content;

    contentDiv.appendChild(textDiv);

    // Add usage metadata for assistant messages
    if (usage && role === 'assistant') {
        const metaDiv = document.createElement('div');
        metaDiv.className = 'message-meta';
        metaDiv.innerHTML = `
            <span>Tokens: ${usage.totalTokens} | Cost: $${usage.cost.toFixed(6)}</span>
            <div class="message-actions">
                <button onclick="copyMessage('${messageId}')" title="Copy">📋</button>
            </div>
        `;
        contentDiv.appendChild(metaDiv);
    }

    messageDiv.appendChild(avatar);
    messageDiv.appendChild(contentDiv);

    // Remove welcome message if exists
    const welcomeMsg = elements.chatMessages.querySelector('.welcome-message');
    if (welcomeMsg) {
        welcomeMsg.remove();
    }

    elements.chatMessages.appendChild(messageDiv);
    elements.chatMessages.scrollTop = elements.chatMessages.scrollHeight;

    return messageId;
}

// Update existing message
function updateMessage(messageId, content, usage = null, isLoading = true) {
    const messageDiv = document.getElementById(messageId);
    if (!messageDiv) return;

    if (!isLoading) {
        messageDiv.classList.remove('loading');
    }

    const textDiv = messageDiv.querySelector('.message-text');
    if (textDiv) {
        textDiv.textContent = content;
    }

    // Add/update usage metadata
    if (usage) {
        let metaDiv = messageDiv.querySelector('.message-meta');
        if (!metaDiv) {
            metaDiv = document.createElement('div');
            metaDiv.className = 'message-meta';
            messageDiv.querySelector('.message-content').appendChild(metaDiv);
        }
        metaDiv.innerHTML = `
            <span>Tokens: ${usage.totalTokens} | Cost: $${usage.cost.toFixed(6)}</span>
            <div class="message-actions">
                <button onclick="copyMessage('${messageId}')" title="Copy">📋</button>
            </div>
        `;
    }

    elements.chatMessages.scrollTop = elements.chatMessages.scrollHeight;
}

// Remove message
function removeMessage(messageId) {
    const messageDiv = document.getElementById(messageId);
    if (messageDiv) {
        messageDiv.remove();
    }
}

// Copy message content
window.copyMessage = function(messageId) {
    const messageDiv = document.getElementById(messageId);
    if (!messageDiv) return;

    const textDiv = messageDiv.querySelector('.message-text');
    if (!textDiv) return;

    navigator.clipboard.writeText(textDiv.textContent).then(() => {
        showToast('Message copied to clipboard', 'success');
    }).catch(() => {
        showToast('Failed to copy message', 'error');
    });
};

// Update session statistics
function updateSessionStats(usage) {
    if (!usage) return;

    state.sessionStats.messageCount++;
    state.sessionStats.totalTokens += usage.totalTokens || 0;
    state.sessionStats.totalCost += usage.cost || 0;

    elements.messageCount.textContent = state.sessionStats.messageCount;
    elements.totalTokens.textContent = state.sessionStats.totalTokens.toLocaleString();
    elements.totalCost.textContent = '$' + state.sessionStats.totalCost.toFixed(6);
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;

    const container = document.getElementById('toast-container');
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Auto-resize textarea
function autoResizeTextarea() {
    const textarea = elements.messageInput;
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
}
