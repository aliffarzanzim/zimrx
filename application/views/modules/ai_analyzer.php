<?php
declare(strict_types=1);

// AI Analyzer widget: local LLM (Ollama/LM Studio) or cloud provider clinical decision support.
?>
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
                <label class="ai-label">Local Provider</label>
                <select id="ai-provider" class="ai-inp">
                    <option value="http://127.0.0.1:11434/v1">Local Ollama (127.0.0.1:11434)</option>
                    <option value="http://127.0.0.1:1234/v1">LM Studio (127.0.0.1:1234)</option>
                    <option value="custom">Custom Local Endpoint</option>
                </select>
            </div>
            <div>
                <label class="ai-label">Model Name</label>
                <div style="display: flex; gap: 6px;">
                    <input type="text" id="ai-model-name" class="ai-inp" list="ai-model-list" placeholder="e.g. llama3.2, meditron..." autocomplete="off">
                    <datalist id="ai-model-list"></datalist>
                    <button type="button" id="ai-fetch-models" class="ai-btn ai-btn-outline" style="flex: 0 0 36px; height: 32px; padding: 0;" title="Fetch Available Local Models">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.92-10.24l5.58 5.58"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <div id="custom-url-group" style="display: none; margin-bottom: 8px;">
            <label class="ai-label">Custom Base URL</label>
            <input type="text" id="ai-base-url" class="ai-inp" placeholder="http://127.0.0.1:11434/v1">
        </div>

        <div style="font-size: 0.72rem; color: var(--zrx-slate-600); background: var(--zrx-bg-canvas); border: 1px solid var(--zrx-border); border-radius: 4px; padding: 6px 10px; margin-bottom: 8px; line-height: 1.4;">
            <strong>Offline Inference:</strong> Clinical queries are processed entirely on your local machine with zero external network transmission.
        </div>

        <div style="display: flex; justify-content: flex-end;">
            <button type="button" id="ai-save-settings" class="ai-btn ai-btn-primary" style="height: 32px; padding: 0 15px; flex: none;">Save Settings</button>
        </div>
    </div>

    <!-- Chat Box -->
    <div id="ai-chat-box" class="ai-chat-area">
        <div style="text-align: center; color: var(--zrx-border-divider); font-size: 0.85rem; margin-top: auto; margin-bottom: auto;">
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

<script src="assets/js/modules/ai_analyzer.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/ai_analyzer.js') ?>"></script>
