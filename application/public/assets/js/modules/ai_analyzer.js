// Clinical AI assistant: extracts active consultation details, formats clinical prompts, and queries local LLM endpoints.
(function() {
    // Purge any legacy API keys from browser storage
    try { localStorage.removeItem('ZIMRX_AI_API_KEY'); } catch (e) {}

    const settingsToggle = document.getElementById('ai-settings-toggle');
    const settingsPanel = document.getElementById('ai-settings-panel');
    const providerSelect = document.getElementById('ai-provider');
    const customUrlGroup = document.getElementById('custom-url-group');
    const baseUrlInput = document.getElementById('ai-base-url');
    const modelNameInput = document.getElementById('ai-model-name');
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
        baseUrlInput.value = providerSelect.value;
    }

    modelNameInput.value = localStorage.getItem('ZIMRX_AI_MODEL_NAME') || '';

    function isLoopbackUrl(urlStr) {
        try {
            const parsed = new URL(urlStr);
            const host = parsed.hostname.toLowerCase();
            return host === 'localhost' || host === '127.0.0.1' || host === '::1' || host === '[::1]';
        } catch (e) {
            return false;
        }
    }

    // Save Settings
    saveBtn.addEventListener('click', () => {
        const finalBaseUrl = (providerSelect.value === 'custom') ? baseUrlInput.value.trim() : providerSelect.value;
        if (providerSelect.value === 'custom' && !isLoopbackUrl(finalBaseUrl)) {
            alert('Security restriction: AI Analyzer operates strictly offline on loopback (127.0.0.1 or localhost). External URLs are blocked to protect patient privacy.');
            return;
        }
        localStorage.setItem('ZIMRX_AI_BASE_URL', finalBaseUrl);
        localStorage.setItem('ZIMRX_AI_MODEL_NAME', modelNameInput.value.trim());

        const originalText = saveBtn.textContent;
        saveBtn.textContent = 'Saved!';
        setTimeout(() => {
            saveBtn.textContent = originalText;
            settingsPanel.classList.remove('active');
        }, 1000);
    });

    // Fetch Available Local Models
    fetchModelsBtn.addEventListener('click', async () => {
        const finalBaseUrl = (providerSelect.value === 'custom') ? baseUrlInput.value.trim() : providerSelect.value;

        if (!finalBaseUrl) {
            alert('Please configure a local provider endpoint first.');
            return;
        }
        if (providerSelect.value === 'custom' && !isLoopbackUrl(finalBaseUrl)) {
            alert('Security restriction: Custom endpoint must be a local loopback address (127.0.0.1 or localhost).');
            return;
        }

        fetchModelsBtn.disabled = true;
        fetchModelsBtn.style.opacity = '0.5';

        try {
            const response = await fetch(`${finalBaseUrl.replace(/\/$/, '')}/models`, {
                method: "GET",
                headers: { "Content-Type": "application/json" }
            });

            if (!response.ok) throw new Error("Could not connect to local endpoint (ensure Ollama/LM Studio is running)");

            const data = await response.json();
            const modelsArray = data.data || data.models || [];

            if (modelsArray.length === 0) {
                alert('No models found on local endpoint.');
            } else {
                modelDataList.innerHTML = '';
                modelsArray.forEach(model => {
                    const opt = document.createElement('option');
                    opt.value = model.id || model.name;
                    modelDataList.appendChild(opt);
                });

                modelNameInput.focus();
                if (!modelNameInput.value) {
                    modelNameInput.placeholder = "Select from list...";
                }
            }
        } catch (error) {
            alert(`Could not fetch models: ${error.message}\nYou can still type the model name manually (e.g. llama3.2).`);
        } finally {
            fetchModelsBtn.disabled = false;
            fetchModelsBtn.style.opacity = '1';
        }
    });


    // Clinical summary prompt generation
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

    // Chat UI actions and message dispatching
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
        const finalBaseUrl = (providerSelect.value === 'custom') ? baseUrlInput.value.trim() : providerSelect.value;
        const modelName = modelNameInput.value.trim();

        if (!finalBaseUrl || !modelName) {
            alert('Please configure local Provider and Model Name in the settings.');
            settingsPanel.classList.add('active');
            return;
        }

        if (providerSelect.value === 'custom' && !isLoopbackUrl(finalBaseUrl)) {
            alert('Security restriction: AI Analyzer operates strictly offline on loopback (127.0.0.1 or localhost). External URLs are blocked.');
            settingsPanel.classList.add('active');
            return;
        }

        const summary = generateClinicalSummary();
        appendMessage('user', summary);
        showTyping();
        startBtn.disabled = true;

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
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errData = await response.json().catch(() => ({}));
                throw new Error(errData.error?.message || `HTTP ${response.status}`);
            }

            const data = await response.json();
            const resultText = data.choices?.[0]?.message?.content || "No response received.";

            hideTyping();
            appendMessage('ai', resultText);

        } catch (error) {
            console.error("Local AI Error:", error.message);
            hideTyping();
            appendMessage('ai', `Connection failed: ${error.message}\n\nPlease ensure Ollama (ollama serve) or LM Studio is running locally on ${finalBaseUrl}.`);
        } finally {
            startBtn.disabled = false;
        }
    });

})();
