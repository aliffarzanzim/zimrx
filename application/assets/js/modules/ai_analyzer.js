// Clinical AI assistant: extracts active consultation details, formats clinical prompts, and queries LLM endpoints.
(function() {
    // AI provider configuration and credential settings
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
        const isLocal = finalBaseUrl.includes('localhost') || finalBaseUrl.includes('127.0.0.1');

        if (!finalBaseUrl || (!apiKey && !isLocal)) {
            alert('Please select a Provider (and enter an API Key for cloud providers) to fetch models.');
            return;
        }

        fetchModelsBtn.disabled = true;
        fetchModelsBtn.style.opacity = '0.5';

        try {
            const reqHeaders = { "Content-Type": "application/json" };
            if (apiKey) {
                reqHeaders["Authorization"] = `Bearer ${apiKey}`;
            }
            const response = await fetch(`${finalBaseUrl.replace(/\/$/, '')}/models`, {
                method: "GET",
                headers: reqHeaders
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
        const apiKey = apiKeyInput.value.trim();
        const finalBaseUrl = (providerSelect.value === 'custom') ? baseUrlInput.value.trim() : providerSelect.value;
        const modelName = modelNameInput.value.trim();
        const isLocal = finalBaseUrl.includes('localhost') || finalBaseUrl.includes('127.0.0.1');

        if (!finalBaseUrl || !modelName || (!apiKey && !isLocal)) {
            alert('Please configure Provider and Model Name in the settings. (Cloud providers also require an API Key)');
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
            const reqHeaders = { "Content-Type": "application/json" };
            if (apiKey) {
                reqHeaders["Authorization"] = `Bearer ${apiKey}`;
            }
            const response = await fetch(`${finalBaseUrl.replace(/\/$/, '')}/chat/completions`, {
                method: "POST",
                headers: reqHeaders,
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
