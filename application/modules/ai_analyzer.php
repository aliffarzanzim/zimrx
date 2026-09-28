<div class="ai-wrapper">
    <!-- Header -->
    <div class="ai-header">
        <span>AI Analyzer</span>
        <button type="button" class="ai-settings-btn" id="ai-settings-toggle" title="AI Settings">
            <?= zrx_icon('sliders', 14) ?>
        </button>
    </div>

    <!-- Settings Panel -->
    <div id="ai-settings-panel" class="ai-settings-panel">
        <div class="ai-settings-grid">
            <div>
                <label class="ai-label">Provider</label>
                <select id="ai-provider" class="ai-inp">
                    <option value="http://localhost:11434/v1">Local Ollama (100% Offline & Private)</option>
                    <option value="http://localhost:1234/v1">LM Studio (Local Offline)</option>
                    <option value="https://api.openai.com/v1">OpenAI (Cloud)</option>
                    <option value="https://generativelanguage.googleapis.com/v1beta/openai">Google Gemini (Cloud)</option>
                    <option value="https://api.x.ai/v1">xAI Grok (Cloud)</option>
                    <option value="https://api.deepseek.com/v1">DeepSeek (Cloud)</option>
                    <option value="custom">Custom Endpoint</option>
                </select>
            </div>
            <div id="custom-url-group" style="display: none;">
                <label class="ai-label">Custom Base URL</label>
                <input type="text" id="ai-base-url" class="ai-inp" placeholder="http://localhost:11434/v1">
            </div>
        </div>

        <div style="font-size: 0.72rem; color: #475569; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px; margin-bottom: 8px; line-height: 1.4;">
            <strong>Privacy &amp; Data Sovereignty:</strong> Local Ollama/LM Studio processes clinical queries entirely offline with <em>zero external network transmission</em>. Cloud providers transmit de-identified prompts (patient names and contacts are never sent) over the internet.
        </div>

        <div class="ai-settings-grid">
            <div>
                <label class="ai-label">API Key</label>
                <input type="password" id="ai-api-key" class="ai-inp" placeholder="Enter API Key">
            </div>
            <div>
                <label class="ai-label">Model Name</label>
                <div style="display: flex; gap: 6px;">
                    <input type="text" id="ai-model-name" class="ai-inp" list="ai-model-list" placeholder="Select or type model..." autocomplete="off">
                    <datalist id="ai-model-list"></datalist>
                    <button type="button" id="ai-fetch-models" class="ai-btn ai-btn-outline" style="flex: 0 0 36px; height: 32px; padding: 0;" title="Fetch Available Models">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.92-10.24l5.58 5.58"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end;">
            <button type="button" id="ai-save-settings" class="ai-btn ai-btn-primary" style="height: 32px; padding: 0 15px; flex: none;">Save Settings</button>
        </div>
    </div>

    <!-- Chat Box -->
    <div id="ai-chat-box" class="ai-chat-area">
        <div style="text-align: center; color: #94a3b8; font-size: 0.85rem; margin-top: auto; margin-bottom: auto;">
            AI analysis will appear here.<br>Click "Start Analysis" below.
        </div>
    </div>

    <!-- Footer Actions (Like Chat Send Box) -->
    <div class="ai-footer">
        <button type="button" id="ai-copy-btn" class="ai-btn ai-btn-outline">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            Copy For Chatbot
        </button>
        <button type="button" id="ai-start-btn" class="ai-btn ai-btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            Start Analysis
        </button>
    </div>
</div>

<script src="assets/js/layout/ai_analyzer.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/layout/ai_analyzer.js') ?>"></script>
