<style>
    .ai-wrapper {
        display: flex;
        flex-direction: column;
        height: 100%;
        background: transparent;
        border: none;
        border-radius: 0;
        overflow: visible;
        margin: -2px;
        width: calc(100% + 4px);
    }

    .ai-header {
        background: #cbd5e1;
        color: #0f172a;
        font-weight: 600;
        font-size: 0.75rem;
        height: 32px;
        box-sizing: border-box;
        padding: 0 0.5rem;
        border-bottom: 1px solid #94a3b8;
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .ai-settings-btn {
        appearance: none;
        -webkit-appearance: none;
        background: transparent;
        border: none;
        border-radius: 3px;
        color: #64748b;
        opacity: 0.75;
        width: 20px;
        height: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        box-shadow: none;
        outline: none;
        transition: color 0.15s ease, opacity 0.15s ease, background-color 0.15s ease;
    }

    .ai-settings-btn:hover {
        background: rgba(0, 0, 0, 0.05);
        color: #0f172a;
        opacity: 1;
        transform: none;
    }

    .ai-settings-panel {
        display: none;
        background: #f8fafc;
        border-bottom: 1px solid #cbd5e1;
        padding: 15px;
        box-shadow: inset 0 -2px 5px rgba(0,0,0,0.02);
    }

    .ai-settings-panel.active {
        display: block;
    }

    .ai-inp {
        width: 100%;
        height: 32px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 0 10px;
        font-family: inherit;
        font-size: 0.85rem;
        color: #0f172a;
        background: #ffffff;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }

    .ai-inp:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }

    .ai-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 4px;
        display: block;
    }

    .ai-settings-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 12px;
    }

    .ai-chat-area {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
        background: #ffffff;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .ai-msg {
        max-width: 88%;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 0.9rem;
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .ai-msg-user {
        align-self: flex-end;
        background: #eff6ff;
        color: #1e3a8a;
        border: 1px solid #bfdbfe;
        border-bottom-right-radius: 2px;
    }

    .ai-msg-ai {
        align-self: flex-start;
        background: #f8fafc;
        color: #0f172a;
        border: 1px solid #cbd5e1;
        border-bottom-left-radius: 2px;
    }

    .ai-footer {
        background: #f1f5f9;
        border-top: 1px solid #cbd5e1;
        padding: 12px 15px;
        display: flex;
        gap: 10px;
    }

    .ai-btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 38px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }

    .ai-btn-outline {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
    }

    .ai-btn-outline:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .ai-btn-primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 2px 4px rgba(37,99,235,0.2);
    }

    .ai-btn-primary:hover {
        background: #1d4ed8;
    }

    .ai-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .ai-typing-indicator {
        display: flex;
        gap: 4px;
        padding: 4px 0;
    }

    .ai-typing-indicator span {
        width: 6px;
        height: 6px;
        background: #94a3b8;
        border-radius: 50%;
        animation: ai-bounce 1.4s infinite ease-in-out both;
    }

    .ai-typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
    .ai-typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

    @keyframes ai-bounce {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1); }
    }
</style>

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
                    <option value="https://api.openai.com/v1">OpenAI</option>
                    <option value="https://generativelanguage.googleapis.com/v1beta/openai">Google Gemini</option>
                    <option value="https://api.x.ai/v1">xAI (Grok)</option>
                    <option value="https://api.deepseek.com/v1">DeepSeek</option>
                    <option value="custom">Custom URL</option>
                </select>
            </div>
            <div id="custom-url-group" style="display: none;">
                <label class="ai-label">Custom Base URL</label>
                <input type="text" id="ai-base-url" class="ai-inp" placeholder="https://...">
            </div>
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

<script>
(function() {
    // --- 1. Settings & UI Elements ---
    const settingsToggle = document.getElementById('ai-settings-toggle');
    const settingsPanel = document.getElementById('ai-settings-panel');
    const providerSelect = document.getElementById('ai-provider');
    const customUrlGroup = document.getElementById('custom-url-group');
    const baseUrlInput = document.getElementById('ai-base-url');
    const modelNameInput = document.getElementById('ai-model-name');
    const apiKeyInput = document.getElementById('ai-api-key');
    const saveBtn = document.getElementById('ai-save-settings');
    const fetchModelsBtn = document.getElementById('ai-fetch-models');
    const modelDataList = document.getElementById('ai-model-list');

    // Toggle Settings Panel
    settingsToggle.addEventListener('click', () => {
        settingsPanel.classList.toggle('active');
    });

    // Provider Dropdown Logic
    providerSelect.addEventListener('change', () => {
        if (providerSelect.value === 'custom') {
            customUrlGroup.style.display = 'block';
            baseUrlInput.value = localStorage.getItem('ZIMRX_AI_BASE_URL') || '';
        } else {
            customUrlGroup.style.display = 'none';
            baseUrlInput.value = providerSelect.value;
        }
    });

    // Load Saved Settings
    const savedBaseUrl = localStorage.getItem('ZIMRX_AI_BASE_URL') || '';
    if (savedBaseUrl) {
        let found = false;
        Array.from(providerSelect.options).forEach(opt => {
            if (opt.value === savedBaseUrl) {
                opt.selected = true;
                found = true;
            }
        });
        if (!found) {
            providerSelect.value = 'custom';
            customUrlGroup.style.display = 'block';
            baseUrlInput.value = savedBaseUrl;
        } else {
            baseUrlInput.value = savedBaseUrl;
        }
    } else {
        baseUrlInput.value = providerSelect.value; // default
    }

    modelNameInput.value = localStorage.getItem('ZIMRX_AI_MODEL_NAME') || '';
    apiKeyInput.value = localStorage.getItem('ZIMRX_AI_API_KEY') || '';

    // Save Settings
    saveBtn.addEventListener('click', () => {
        const finalBaseUrl = (providerSelect.value === 'custom') ? baseUrlInput.value.trim() : providerSelect.value;
        localStorage.setItem('ZIMRX_AI_BASE_URL', finalBaseUrl);
        localStorage.setItem('ZIMRX_AI_MODEL_NAME', modelNameInput.value.trim());
        localStorage.setItem('ZIMRX_AI_API_KEY', apiKeyInput.value.trim());

        const originalText = saveBtn.textContent;
        saveBtn.textContent = 'Saved!';
        setTimeout(() => {
            saveBtn.textContent = originalText;
            settingsPanel.classList.remove('active');
        }, 1000);
    });

    // Fetch Models Dynamically
    fetchModelsBtn.addEventListener('click', async () => {
        const apiKey = apiKeyInput.value.trim();
        const finalBaseUrl = (providerSelect.value === 'custom') ? baseUrlInput.value.trim() : providerSelect.value;

        if (!apiKey || !finalBaseUrl) {
            alert('Please enter an API Key and select a Provider to fetch models.');
            return;
        }

        fetchModelsBtn.disabled = true;
        fetchModelsBtn.style.opacity = '0.5';

        try {
            const response = await fetch(`${finalBaseUrl.replace(/\/$/, '')}/models`, {
                method: "GET",
                headers: {
                    "Authorization": `Bearer ${apiKey}`,
                    "Content-Type": "application/json"
                }
            });

            if (!response.ok) throw new Error("Failed to fetch models (Check API Key or CORS restrictions)");

            const data = await response.json();
            const modelsArray = data.data || data.models || [];

            if (modelsArray.length === 0) {
                alert('No models returned by the provider.');
            } else {
                // Populate Datalist
                modelDataList.innerHTML = '';
                modelsArray.forEach(model => {
                    const opt = document.createElement('option');
                    opt.value = model.id || model.name;
                    modelDataList.appendChild(opt);
                });

                // Alert success and focus input so they can see dropdown
                modelNameInput.focus();
                // Optionally clear and prompt to select
                if(!modelNameInput.value) {
                    modelNameInput.placeholder = "Select from list...";
                }
            }
        } catch (error) {
            alert(`Could not fetch models automatically: ${error.message}\n\nYou can still type the model name manually (e.g. gpt-4o-mini).`);
        } finally {
            fetchModelsBtn.disabled = false;
            fetchModelsBtn.style.opacity = '1';
        }
    });


    // --- 2. Summary Generation Logic ---
    function generateClinicalSummary() {
        let age = document.getElementById('patient-age')?.value || '[Age]';
        let gender = document.getElementById('patient-gender')?.value || '[Gender]';
        if (gender === '' || gender === '--') gender = 'patient';

        let summary = `${age}-year-old ${gender.toLowerCase()} complaints of -\n`;

        // P/C
        let pcRows = document.querySelectorAll('#pc-tbody .pc-row');
        let pcCount = 0;
        pcRows.forEach((row) => {
            let complaint = row.querySelector('.pc-complaint-input')?.value.trim();
            let duration = row.querySelector('.pc-duration-input')?.value.trim();
            let unit = row.querySelector('.pc-unit-input')?.value.trim();
            if(complaint) {
                pcCount++;
                summary += `${pcCount}. ${complaint}`;
                if (duration) summary += ` ${duration}`;
                if (unit) summary += ` ${unit}`;
                summary += `\n`;
            }
        });

        // Physical Examination
        let peRows = document.querySelectorAll('#pe-tbody .pc-row');
        let peData = [];
        peRows.forEach(row => {
            let name = row.querySelector('textarea.pe-input')?.value.trim();
            let inputs = Array.from(row.querySelectorAll('input[type="text"].pe-input'));
            let vals = inputs.map(i => i.value.trim()).filter(v => v !== '');
            if(name && vals.length > 0) {
                peData.push(`${name}: ${vals.join(' ')}`);
            }
        });

        if (peData.length > 0) {
            summary += `\nPhysical examination findings:\n` + peData.join('\n') + `\n`;
        }

        // Reports
        let repRows = document.querySelectorAll('#reports-tbody .pc-row');
        let repData = [];
        repRows.forEach(row => {
            let name = row.querySelector('.rep-name-input')?.value.trim();
            let res = row.querySelector('.rep-result-input')?.value.trim();
            let unit = row.querySelector('.rep-unit-input')?.value.trim();
            if(name && res) {
                repData.push(`${name}: ${res} ${unit}`);
            }
        });

        if (repData.length > 0) {
            summary += `\nReports include:\n` + repData.join('\n') + `\n`;
        }

        // Dx
        let dxRows = document.querySelectorAll('#dx-tbody .pc-row');
        let dxs = [];
        dxRows.forEach(row => {
            let dx = row.querySelector('.dx-input')?.value.trim();
            if(dx) dxs.push(dx);
        });

        if(dxs.length > 0) {
            summary += `\nMy probable diagnosis is ${dxs.join(', ')}.\n`;
            summary += `Justify it or tell me the provisional diagnosis and tell me DD.`;
        } else {
            summary += `\nTell me the provisional diagnosis and tell me DD.`;
        }

        return summary;
    }

    // --- 3. Chat Actions ---
    const copyBtn = document.getElementById('ai-copy-btn');
    const startBtn = document.getElementById('ai-start-btn');
    const chatBox = document.getElementById('ai-chat-box');

    function appendMessage(role, text) {
        if (chatBox.children.length === 1 && chatBox.children[0].tagName === 'DIV' && chatBox.children[0].style.textAlign === 'center') {
            chatBox.innerHTML = '';
        }
        const msgDiv = document.createElement('div');
        msgDiv.className = `ai-msg ${role === 'user' ? 'ai-msg-user' : 'ai-msg-ai'}`;
        msgDiv.textContent = text;
        chatBox.appendChild(msgDiv);
        chatBox.scrollTop = chatBox.scrollHeight;
        return msgDiv;
    }

    function showTyping() {
        if (chatBox.children.length === 1 && chatBox.children[0].tagName === 'DIV' && chatBox.children[0].style.textAlign === 'center') {
            chatBox.innerHTML = '';
        }
        const typingDiv = document.createElement('div');
        typingDiv.className = 'ai-msg ai-msg-ai';
        typingDiv.id = 'ai-typing';
        typingDiv.innerHTML = `<div class="ai-typing-indicator"><span></span><span></span><span></span></div>`;
        chatBox.appendChild(typingDiv);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function hideTyping() {
        const typingDiv = document.getElementById('ai-typing');
        if (typingDiv) typingDiv.remove();
    }

    // Copy Action
    copyBtn.addEventListener('click', () => {
        const summary = generateClinicalSummary();
        navigator.clipboard.writeText(summary).then(() => {
            const originalText = copyBtn.innerHTML;
            copyBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!`;
            setTimeout(() => { copyBtn.innerHTML = originalText; }, 2000);
        }).catch(err => alert('Failed to copy text: ' + err));
    });

    // Start Analysis Action
    startBtn.addEventListener('click', async () => {
        const apiKey = apiKeyInput.value.trim();
        const finalBaseUrl = (providerSelect.value === 'custom') ? baseUrlInput.value.trim() : providerSelect.value;
        const modelName = modelNameInput.value.trim();

        if (!apiKey || !finalBaseUrl || !modelName) {
            alert('Please configure Provider, Model Name, and API Key in the settings first.');
            settingsPanel.classList.add('active');
            return;
        }

        const summary = generateClinicalSummary();
        appendMessage('user', summary);
        showTyping();
        startBtn.disabled = true;

        // Universal AI Payload
        const payload = {
            model: modelName,
            messages: [
                {
                    role: "user",
                    content: [
                        { type: "text", text: summary }
                    ]
                }
            ],
            temperature: 0.2
        };

        try {
            const response = await fetch(`${finalBaseUrl.replace(/\/$/, '')}/chat/completions`, {
                method: "POST",
                headers: {
                    "Authorization": `Bearer ${apiKey}`,
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.error?.message || "API Connection Failed");
            }

            const data = await response.json();
            const resultText = data.choices[0].message.content;

            hideTyping();
            appendMessage('ai', resultText);

        } catch (error) {
            console.error("Universal AI Error:", error.message);
            hideTyping();
            appendMessage('ai', `❌ Error: ${error.message}\n\nPlease check your API keys and Provider settings.`);
        } finally {
            startBtn.disabled = false;
        }
    });

})();
</script>
