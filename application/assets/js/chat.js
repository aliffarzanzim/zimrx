/**
 * ZimRx Clinical Internal Messaging Client
 * Features:
 * - Real-time Smart Polling
 * - Delivery & Seen Statuses (✓ Sent, ✓✓ Delivered, ✓✓ Seen)
 * - File / Media Attachments (Photos, Scans, Lab PDFs)
 * - Dropdown Attachment Menu
 * - Customizable Quick Messages with Settings Modal
 * - Message Deletion and Hiding
 */

(function () {
    'use strict';

    let isWidgetOpen = false;
    let activeConversationId = null;
    let conversations = [];
    let availableUsers = [];
    let quickMessages = [];
    let currentServerTick = 0;
    let lastMessageId = 0;
    let pollIntervalId = null;
    let audioCtx = null;
    let pendingAttachmentFile = null;

    // DOM Elements
    let widgetEl = null;
    let headerBadgeEl = null;
    let convListEl = null;
    let messagesContainerEl = null;
    let activeTitleEl = null;
    let activeSubEl = null;
    let chatTextareaEl = null;
    let btnSendEl = null;
    let viewListEl = null;
    let viewMessagesEl = null;
    let colleagueModalEl = null;
    let quickSettingsModalEl = null;
    let quickChipsContainerEl = null;
    let attachDropdownEl = null;
    let pendingAttachmentStripEl = null;
    let pendingThumbImgEl = null;
    let pendingFileNameEl = null;
    let fileInputPhoto = null;
    let fileInputDoc = null;

    function playNotificationChime() {
        try {
            if (!audioCtx) {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.12);
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.28);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.3);
        } catch (e) {
            // Non-blocking audio policy
        }
    }

    function initChatWidget() {
        if (document.getElementById('zimrx-chat-widget')) return;

        const widgetHtml = `
            <div id="zimrx-chat-widget" class="zimrx-chat-widget hidden">
                <!-- Header -->
                <div class="chat-widget-header" id="chat-header-bar">
                    <div class="chat-header-title-wrap">
                        <div class="chat-header-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="chat-header-title">Team Chat</div>
                            <div class="chat-header-sub">
                                <span class="chat-status-pulse"></span>
                                <span>Connected</span>
                            </div>
                        </div>
                    </div>
                    <div class="chat-header-actions">
                        <button type="button" class="chat-header-btn" id="btn-chat-minimize" title="Minimize">−</button>
                        <button type="button" class="chat-header-btn" id="btn-chat-close" title="Close">✕</button>
                    </div>
                </div>

                <div class="chat-body-wrapper">
                    <!-- View 1: Conversations List -->
                    <div class="chat-view-list" id="chat-view-list">
                        <div class="chat-search-bar">
                            <input type="text" class="chat-search-input" id="chat-search-input" placeholder="Search conversations...">
                            <button type="button" class="chat-btn-new" id="btn-new-chat">+ New</button>
                        </div>
                        <div class="chat-conversations-scroll" id="chat-conversations-scroll">
                            <div style="padding: 1.5rem; text-align: center; color: #94a3b8; font-size: 0.82rem;">Loading conversations...</div>
                        </div>
                    </div>

                    <!-- View 2: Active Messages View -->
                    <div class="chat-view-messages" id="chat-view-messages">
                        <div class="chat-active-header">
                            <button type="button" class="btn-chat-back" id="btn-chat-back" title="Back to list">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="19" y1="12" x2="5" y2="12"></line>
                                    <polyline points="12 19 5 12 12 5"></polyline>
                                </svg>
                            </button>
                            <div class="chat-active-info">
                                <div class="chat-active-title" id="chat-active-title">Conversation</div>
                                <div class="chat-active-subtitle" id="chat-active-sub">Direct Chat</div>
                            </div>
                        </div>

                        <!-- Quick Clinical Action Chips Bar -->
                        <div class="chat-quick-actions-bar">
                            <div class="chat-quick-chips-scroll" id="chat-quick-chips-scroll"></div>
                            <button type="button" class="btn-quick-settings" id="btn-quick-settings" title="Customise Quick Messages">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Message Stream -->
                        <div class="chat-messages-container" id="chat-messages-container"></div>

                        <!-- Pending Attachment Strip -->
                        <div class="chat-pending-attachment-strip" id="chat-pending-attachment-strip">
                            <div class="chat-pending-thumb-wrap">
                                <img id="chat-pending-thumb" class="chat-pending-thumb" src="" alt="Thumbnail" style="display: none;">
                                <span id="chat-pending-filename" class="chat-pending-name">filename.pdf</span>
                            </div>
                            <button type="button" class="btn-remove-attachment" id="btn-remove-attachment" title="Remove attachment">&times;</button>
                        </div>

                        <!-- Input Bar with Attachment Dropdown -->
                        <div class="chat-input-bar">
                            <input type="file" id="chat-file-photo" accept="image/*" style="display: none;">
                            <input type="file" id="chat-file-doc" accept=".pdf,image/*" style="display: none;">

                            <!-- Attachment Trigger Button -->
                            <button type="button" class="btn-chat-attach-trigger" id="btn-chat-attach-trigger" title="Attach Patient, Image, or PDF">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                            </button>

                            <!-- Attachment Dropdown Menu -->
                            <div class="chat-attach-dropdown" id="chat-attach-dropdown">
                                <div class="chat-attach-menu-item" id="attach-item-patient">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="16" y1="13" x2="8" y2="13"></line>
                                        <line x1="16" y1="17" x2="8" y2="17"></line>
                                    </svg>
                                    <span>Attach Active Patient</span>
                                </div>
                                <div class="chat-attach-menu-item" id="attach-item-photo">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                    <span>Send Photo / Scan</span>
                                </div>
                                <div class="chat-attach-menu-item" id="attach-item-doc">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                    </svg>
                                    <span>Attach Document / PDF</span>
                                </div>
                            </div>

                            <textarea class="chat-textarea" id="chat-textarea" placeholder="Type a message... (Enter to send)" rows="1"></textarea>
                            
                            <button type="button" class="chat-btn-send" id="btn-chat-send" title="Send Message">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colleague Selector Modal -->
            <div id="chat-colleague-modal" class="chat-modal-backdrop" style="display: none;">
                <div class="chat-modal-card">
                    <div class="chat-modal-header">
                        <div class="chat-modal-title">Select Colleague to Message</div>
                        <button type="button" class="chat-header-btn" id="btn-close-colleague-modal" style="color: #64748b;">✕</button>
                    </div>
                    <div class="chat-colleague-list" id="chat-colleague-list"></div>
                </div>
            </div>

            <!-- Quick Messages Customizer Modal -->
            <div id="chat-quick-settings-modal" class="chat-modal-backdrop" style="display: none;">
                <div class="chat-modal-card">
                    <div class="chat-modal-header">
                        <div class="chat-modal-title">Customise Quick Action Messages</div>
                        <button type="button" class="chat-header-btn" id="btn-close-quick-settings" style="color: #64748b;">✕</button>
                    </div>
                    <div class="chat-modal-body">
                        <div id="quick-settings-list"></div>
                        <div class="quick-add-form">
                            <div style="font-size: 0.80rem; font-weight: 700; color: #0f172a;">Add New Quick Message</div>
                            <input type="text" id="quick-input-title" class="quick-form-input" placeholder="Button Title (e.g. Next Patient)" autocomplete="off">
                            <input type="text" id="quick-input-msg" class="quick-form-input" placeholder="Full Message (e.g. Please send next patient inside)" autocomplete="off">
                            <button type="button" id="btn-submit-quick-msg" class="btn-add-quick-msg">+ Save Quick Message</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', widgetHtml);

        // Bind Elements
        widgetEl = document.getElementById('zimrx-chat-widget');
        headerBadgeEl = document.getElementById('header-chat-unread-badge');
        convListEl = document.getElementById('chat-conversations-scroll');
        messagesContainerEl = document.getElementById('chat-messages-container');
        activeTitleEl = document.getElementById('chat-active-title');
        activeSubEl = document.getElementById('chat-active-sub');
        chatTextareaEl = document.getElementById('chat-textarea');
        btnSendEl = document.getElementById('btn-chat-send');
        viewListEl = document.getElementById('chat-view-list');
        viewMessagesEl = document.getElementById('chat-view-messages');
        colleagueModalEl = document.getElementById('chat-colleague-modal');
        quickSettingsModalEl = document.getElementById('chat-quick-settings-modal');
        quickChipsContainerEl = document.getElementById('chat-quick-chips-scroll');
        attachDropdownEl = document.getElementById('chat-attach-dropdown');
        pendingAttachmentStripEl = document.getElementById('chat-pending-attachment-strip');
        pendingThumbImgEl = document.getElementById('chat-pending-thumb');
        pendingFileNameEl = document.getElementById('chat-pending-filename');
        fileInputPhoto = document.getElementById('chat-file-photo');
        fileInputDoc = document.getElementById('chat-file-doc');

        // Events
        document.getElementById('btn-header-chat')?.addEventListener('click', toggleChatWidget);
        document.getElementById('btn-chat-close')?.addEventListener('click', closeChatWidget);
        document.getElementById('btn-chat-minimize')?.addEventListener('click', () => widgetEl.classList.toggle('collapsed'));
        document.getElementById('btn-chat-back')?.addEventListener('click', backToConversations);
        document.getElementById('btn-new-chat')?.addEventListener('click', openColleagueModal);
        document.getElementById('btn-close-colleague-modal')?.addEventListener('click', closeColleagueModal);
        document.getElementById('btn-quick-settings')?.addEventListener('click', openQuickSettingsModal);
        document.getElementById('btn-close-quick-settings')?.addEventListener('click', closeQuickSettingsModal);
        document.getElementById('btn-submit-quick-msg')?.addEventListener('click', saveNewQuickMessage);

        btnSendEl?.addEventListener('click', sendMessage);
        chatTextareaEl?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Attachment Dropdown Toggle & Items
        document.getElementById('btn-chat-attach-trigger')?.addEventListener('click', (e) => {
            e.stopPropagation();
            if (attachDropdownEl) {
                attachDropdownEl.style.display = (attachDropdownEl.style.display === 'block') ? 'none' : 'block';
            }
        });

        document.addEventListener('click', () => {
            if (attachDropdownEl) attachDropdownEl.style.display = 'none';
        });

        document.getElementById('attach-item-patient')?.addEventListener('click', () => {
            if (attachDropdownEl) attachDropdownEl.style.display = 'none';
            sendPatientCard();
        });

        document.getElementById('attach-item-photo')?.addEventListener('click', () => {
            if (attachDropdownEl) attachDropdownEl.style.display = 'none';
            fileInputPhoto?.click();
        });

        document.getElementById('attach-item-doc')?.addEventListener('click', () => {
            if (attachDropdownEl) attachDropdownEl.style.display = 'none';
            fileInputDoc?.click();
        });

        fileInputPhoto?.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                handleAttachmentSelected(e.target.files[0]);
            }
        });

        fileInputDoc?.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                handleAttachmentSelected(e.target.files[0]);
            }
        });

        document.getElementById('btn-remove-attachment')?.addEventListener('click', removePendingAttachment);

        // Search filter
        document.getElementById('chat-search-input')?.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.chat-item').forEach(item => {
                const name = item.dataset.title?.toLowerCase() || '';
                item.style.display = name.includes(query) ? 'flex' : 'none';
            });
        });

        fetchConversations();
        fetchQuickMessages();
        startSmartPolling();
    }

    function toggleChatWidget() {
        if (isWidgetOpen) closeChatWidget();
        else openChatWidget();
    }

    function openChatWidget() {
        if (!widgetEl) return;
        isWidgetOpen = true;
        widgetEl.classList.remove('hidden');
        widgetEl.classList.remove('collapsed');
        fetchConversations();
    }

    function closeChatWidget() {
        if (!widgetEl) return;
        isWidgetOpen = false;
        widgetEl.classList.add('hidden');
    }

    function backToConversations() {
        activeConversationId = null;
        lastMessageId = 0;
        viewMessagesEl?.classList.remove('open');
        fetchConversations();
    }

    // -------------------------------------------------------------------------
    // Attachment Handling
    // -------------------------------------------------------------------------
    function handleAttachmentSelected(file) {
        if (!file) return;
        pendingAttachmentFile = file;

        if (pendingFileNameEl) pendingFileNameEl.textContent = file.name;
        if (pendingAttachmentStripEl) pendingAttachmentStripEl.style.display = 'flex';

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                if (pendingThumbImgEl) {
                    pendingThumbImgEl.src = e.target.result;
                    pendingThumbImgEl.style.display = 'block';
                }
            };
            reader.readAsDataURL(file);
        } else {
            if (pendingThumbImgEl) pendingThumbImgEl.style.display = 'none';
        }

        if (chatTextareaEl) chatTextareaEl.focus();
    }

    function removePendingAttachment() {
        pendingAttachmentFile = null;
        if (fileInputPhoto) fileInputPhoto.value = '';
        if (fileInputDoc) fileInputDoc.value = '';
        if (pendingAttachmentStripEl) pendingAttachmentStripEl.style.display = 'none';
    }

    // -------------------------------------------------------------------------
    // Conversations & Messages
    // -------------------------------------------------------------------------
    async function fetchConversations() {
        try {
            const res = await fetch('api/chat.php?action=list_conversations');
            const data = await res.json();
            if (data.ok) {
                conversations = data.conversations || [];
                availableUsers = data.available_users || [];
                currentServerTick = parseFloat(data.tick || 0);
                updateHeaderBadge(data.total_unread || 0);
                renderConversationList();
            }
        } catch (e) {}
    }

    function updateHeaderBadge(count) {
        if (!headerBadgeEl) return;
        if (count > 0) {
            headerBadgeEl.textContent = count > 99 ? '99+' : count;
            headerBadgeEl.style.display = 'flex';
        } else {
            headerBadgeEl.style.display = 'none';
        }
    }

    function renderConversationList() {
        if (!convListEl) return;
        if (!conversations.length) {
            convListEl.innerHTML = `
                <div style="padding: 2rem 1rem; text-align: center; color: #94a3b8; font-size: 0.84rem;">
                    No messages yet.<br>Click <strong>+ New</strong> to chat with a colleague.
                </div>
            `;
            return;
        }

        let html = '';
        conversations.forEach(c => {
            const isNotice = (c.title === 'Notice Channel' || c.title === 'Notice Board');
            const isChannel = c.type === 'channel';
            const isGroup = c.type === 'group';
            let avatarClass = 'chat-avatar';
            let initial = '#';

            if (isNotice) {
                avatarClass += ' notice-avatar';
                initial = '📢';
            } else if (isChannel || isGroup) {
                avatarClass += ' channel-avatar';
                initial = isChannel ? '#' : '👥';
            } else {
                const role = c.other_user?.role || '';
                if (role === 'doctor') avatarClass += ' doctor-avatar';
                else if (role === 'assistant') avatarClass += ' assistant-avatar';
                initial = (c.title || 'U').charAt(0).toUpperCase();
            }

            const unreadBadge = c.unread_count > 0 
                ? `<span class="chat-item-unread">${c.unread_count}</span>` 
                : '';

            const isPinned = (c.is_pinned === 1);
            const timeStr = c.last_message_at ? formatTime(c.last_message_at) : '';
            const pinIconSvg = `
                <button type="button" class="btn-toggle-pin ${isPinned ? 'pinned' : ''}" data-id="${c.id}" title="${isPinned ? 'Unpin from top' : 'Pin to top'}">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="${isPinned ? '#eab308' : 'none'}" stroke="${isPinned ? '#ca8a04' : '#94a3b8'}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="17" x2="12" y2="22"></line>
                        <path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.89A2 2 0 0 1 15 10.77V6h1a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h1v4.77a2 2 0 0 1-1.11 1.79l-1.78.89A2 2 0 0 0 5 15.24Z"></path>
                    </svg>
                </button>
            `;

            html += `
                <div class="chat-item ${c.id === activeConversationId ? 'active' : ''}" data-id="${c.id}" data-title="${escapeHtml(c.title || '')}">
                    <div class="${avatarClass}">${initial}</div>
                    <div class="chat-item-content">
                        <div class="chat-item-top">
                            <div class="chat-item-name-wrap">
                                <span class="chat-item-name">${escapeHtml(c.title || 'Chat')}</span>
                            </div>
                            <div class="chat-item-actions">
                                ${pinIconSvg}
                                <span class="chat-item-time">${timeStr}</span>
                            </div>
                        </div>
                        <div class="chat-item-bottom">
                            <span class="chat-item-preview">${escapeHtml(c.last_message_preview || 'No messages')}</span>
                            ${unreadBadge}
                        </div>
                    </div>
                </div>
            `;
        });

        convListEl.innerHTML = html;

        convListEl.querySelectorAll('.btn-toggle-pin').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = parseInt(btn.dataset.id, 10);
                await togglePinConversation(id);
            });
        });

        convListEl.querySelectorAll('.chat-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = parseInt(item.dataset.id, 10);
                openConversation(id);
            });
        });
    }

    async function togglePinConversation(convId) {
        const formData = new FormData();
        formData.append('action', 'toggle_pin');
        formData.append('conversation_id', convId);

        try {
            const res = await fetch('api/chat.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok) {
                await fetchConversations();
            }
        } catch (e) {}
    }

    async function openConversation(convId) {
        activeConversationId = convId;
        const conv = conversations.find(c => c.id === convId);
        if (conv) {
            if (activeTitleEl) activeTitleEl.textContent = conv.title || 'Chat';
            if (activeSubEl) {
                if (conv.title === 'Notice Channel' || conv.title === 'Notice Board') {
                    activeSubEl.textContent = '📢 Official Clinic Notices & Circulars';
                } else if (conv.type === 'channel') {
                    activeSubEl.textContent = 'General Clinic Team Channel';
                } else {
                    activeSubEl.textContent = (conv.other_user?.role ? `Colleague • ${conv.other_user.role}` : 'Direct Conversation');
                }
            }
        }

        viewMessagesEl?.classList.add('open');
        messagesContainerEl.innerHTML = '<div style="padding: 2rem; text-align: center; color: #94a3b8; font-size: 0.82rem;">Loading messages...</div>';

        try {
            const res = await fetch(`api/chat.php?action=get_messages&conversation_id=${convId}&limit=50`);
            const data = await res.json();
            if (data.ok) {
                lastMessageId = data.last_read_id || 0;
                renderMessages(data.messages || [], false);
                scrollToBottom();
                if (chatTextareaEl) chatTextareaEl.focus();
            }
        } catch (e) {
            messagesContainerEl.innerHTML = '<div style="padding: 1rem; color: #ef4444; font-size: 0.82rem;">Failed to load messages.</div>';
        }
    }

    function renderStatusIcon(status) {
        if (status === 'seen') {
            return '<span class="chat-status-icon seen" title="Seen">✓✓</span>';
        } else if (status === 'delivered') {
            return '<span class="chat-status-icon delivered" title="Delivered">✓✓</span>';
        }
        return '<span class="chat-status-icon sent" title="Sent">✓</span>';
    }

    function renderMessages(msgList, appendOnly = false) {
        if (!messagesContainerEl) return;
        if (!appendOnly) messagesContainerEl.innerHTML = '';

        msgList.forEach(m => {
            const isMine = m.is_mine;
            const isDeleted = (m.is_deleted === 1);
            const timeStr = m.created_at ? formatTime(m.created_at) : '';
            const statusIcon = isMine ? renderStatusIcon(m.status || 'sent') : '';

            let contentHtml = '';

            if (isDeleted) {
                contentHtml = `<div class="chat-msg-bubble chat-msg-deleted">🚫 This message was deleted</div>`;
            } else if (m.message_type === 'patient_card' && m.metadata) {
                const meta = m.metadata;
                contentHtml = `
                    <div class="chat-msg-bubble chat-patient-card-bubble">
                        <div class="chat-pcard-header">
                            <span class="chat-pcard-tag">📋 Patient Context</span>
                            ${meta.urgency === 'high' ? '<span style="color:#ef4444;font-size:0.72rem;font-weight:700;">⚠️ Urgent</span>' : ''}
                        </div>
                        <div class="chat-pcard-name">${escapeHtml(meta.patient_name || 'Patient')}</div>
                        <div class="chat-pcard-meta">
                            ${meta.patient_reg ? `Reg: <strong>${escapeHtml(meta.patient_reg)}</strong> • ` : ''}
                            ${meta.patient_age ? `Age: ${escapeHtml(meta.patient_age)} • ` : ''}
                            ${meta.patient_gender ? `${escapeHtml(meta.patient_gender)}` : ''}
                        </div>
                        ${m.message ? `<div class="chat-pcard-msg">${escapeHtml(m.message)}</div>` : ''}
                    </div>
                `;
            } else {
                let attachmentHtml = '';
                if (m.file_path) {
                    if (m.file_type === 'image') {
                        attachmentHtml = `
                            <div class="chat-attachment-img-wrap" onclick="window.open('${escapeHtml(m.file_path)}', '_blank')">
                                <img src="${escapeHtml(m.file_path)}" alt="Photo Attachment">
                            </div>
                        `;
                    } else {
                        attachmentHtml = `
                            <a href="${escapeHtml(m.file_path)}" target="_blank" class="chat-attachment-doc-card">
                                <div class="chat-doc-icon">PDF</div>
                                <div class="chat-doc-details">
                                    <div class="chat-doc-name">${escapeHtml(m.file_name || 'document.pdf')}</div>
                                    <div class="chat-doc-size">${formatFileSize(m.file_size)}</div>
                                </div>
                            </a>
                        `;
                    }
                }

                contentHtml = `
                    <div class="chat-msg-bubble">
                        ${attachmentHtml}
                        ${m.message ? `<div>${escapeHtml(m.message)}</div>` : ''}
                    </div>
                `;
            }

            const row = document.createElement('div');
            row.className = `chat-msg-row ${isMine ? 'mine' : 'theirs'}`;
            row.dataset.msgId = m.id;

            row.innerHTML = `
                ${!isMine ? `<span class="chat-msg-sender">${escapeHtml(m.sender_name)} <span style="font-weight:400;color:#94a3b8;font-size:0.64rem;">(${escapeHtml(m.sender_role)})</span></span>` : ''}
                <div class="chat-msg-bubble-wrap">
                    ${contentHtml}
                    ${!isDeleted ? `
                        <button type="button" class="chat-msg-menu-btn" title="Message actions" data-id="${m.id}" data-mine="${isMine ? '1' : '0'}">⋯</button>
                    ` : ''}
                </div>
                <div class="chat-msg-footer">
                    <span>${timeStr}</span>
                    ${statusIcon}
                </div>
            `;

            messagesContainerEl.appendChild(row);
            if (m.id > lastMessageId) {
                lastMessageId = m.id;
            }
        });

        bindMessageContextMenus();
    }

    function bindMessageContextMenus() {
        document.querySelectorAll('.chat-msg-menu-btn').forEach(btn => {
            btn.onclick = (e) => {
                e.stopPropagation();
                const msgId = parseInt(btn.dataset.id, 10);
                const isMine = btn.dataset.mine === '1';

                const choice = confirm(isMine ? 'Do you want to DELETE this message for everyone?' : 'Do you want to HIDE this message?');
                if (!choice) return;

                if (isMine) {
                    deleteMessage(msgId);
                } else {
                    hideMessage(msgId);
                }
            };
        });
    }

    async function deleteMessage(msgId) {
        const formData = new FormData();
        formData.append('action', 'delete_message');
        formData.append('message_id', msgId);

        try {
            const res = await fetch('api/chat.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok) {
                const row = document.querySelector(`.chat-msg-row[data-msg-id="${msgId}"]`);
                if (row) {
                    const bubble = row.querySelector('.chat-msg-bubble');
                    if (bubble) {
                        bubble.className = 'chat-msg-bubble chat-msg-deleted';
                        bubble.textContent = '🚫 This message was deleted';
                    }
                    row.querySelector('.chat-msg-menu-btn')?.remove();
                }
            }
        } catch (e) {}
    }

    async function hideMessage(msgId) {
        const formData = new FormData();
        formData.append('action', 'hide_message');
        formData.append('message_id', msgId);

        try {
            const res = await fetch('api/chat.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok) {
                document.querySelector(`.chat-msg-row[data-msg-id="${msgId}"]`)?.remove();
            }
        } catch (e) {}
    }

    async function sendMessage() {
        if (!activeConversationId) return;
        const text = chatTextareaEl?.value.trim() || '';

        if (!text && !pendingAttachmentFile) return;

        if (chatTextareaEl) chatTextareaEl.value = '';
        btnSendEl.disabled = true;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('conversation_id', activeConversationId);
        formData.append('message', text);
        formData.append('message_type', 'text');

        if (pendingAttachmentFile) {
            formData.append('attachment', pendingAttachmentFile);
        }

        try {
            const res = await fetch('api/chat.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok && data.message) {
                renderMessages([data.message], true);
                scrollToBottom();
                removePendingAttachment();
            } else {
                alert('Failed to send: ' + (data.error || 'Unknown error'));
            }
        } catch (e) {
            alert('Send error: ' + e.message);
        } finally {
            btnSendEl.disabled = false;
        }
    }

    async function sendPatientCard() {
        if (!activeConversationId) return;

        const pName = document.getElementById('patient-name')?.value.trim() || 'Walk-in Patient';
        const pReg = document.getElementById('patient-reg-no')?.value.trim() || '';
        const pAge = document.getElementById('patient-age')?.value.trim() || '';
        const pGender = document.getElementById('patient-gender')?.value.trim() || '';

        const note = prompt(`Send patient card for "${pName}" to chat with a message:`, 'Please review the patient');
        if (note === null) return;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('conversation_id', activeConversationId);
        formData.append('message', note.trim() || 'Please review the patient');
        formData.append('message_type', 'patient_card');
        formData.append('metadata', JSON.stringify({
            patient_name: pName,
            patient_reg: pReg,
            patient_age: pAge,
            patient_gender: pGender,
            urgency: 'normal'
        }));

        try {
            const res = await fetch('api/chat.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok && data.message) {
                renderMessages([data.message], true);
                scrollToBottom();
            }
        } catch (e) {
            alert('Error sending patient card: ' + e.message);
        }
    }

    // -------------------------------------------------------------------------
    // Quick Messages & Customizer
    // -------------------------------------------------------------------------
    async function fetchQuickMessages() {
        try {
            const res = await fetch('api/chat.php?action=get_quick_messages');
            const data = await res.json();
            if (data.ok && Array.isArray(data.items)) {
                quickMessages = data.items;
                renderQuickChips();
            }
        } catch (e) {}
    }

    function renderQuickChips() {
        if (!quickChipsContainerEl) return;
        let html = '';
        quickMessages.filter(m => m.is_active === 1).forEach(m => {
            html += `<span class="chat-action-chip" data-msg="${escapeHtml(m.message)}">${escapeHtml(m.title)}</span>`;
        });
        quickChipsContainerEl.innerHTML = html;

        quickChipsContainerEl.querySelectorAll('.chat-action-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                if (chatTextareaEl) {
                    chatTextareaEl.value = chip.dataset.msg;
                    sendMessage();
                }
            });
        });
    }

    function openQuickSettingsModal() {
        if (!quickSettingsModalEl) return;
        renderQuickSettingsList();
        quickSettingsModalEl.style.display = 'flex';
    }

    function closeQuickSettingsModal() {
        if (quickSettingsModalEl) quickSettingsModalEl.style.display = 'none';
    }

    function renderQuickSettingsList() {
        const listEl = document.getElementById('quick-settings-list');
        if (!listEl) return;

        let html = '';
        quickMessages.forEach(m => {
            html += `
                <div class="quick-setting-item" data-id="${m.id}">
                    <div class="quick-setting-info">
                        <div class="quick-setting-title">${escapeHtml(m.title)}</div>
                        <div class="quick-setting-msg">${escapeHtml(m.message)}</div>
                    </div>
                    <div class="quick-setting-actions">
                        ${m.can_edit ? `<button type="button" class="btn-quick-del" onclick="window.zimrxDeleteQuickMessage(${m.id})">Delete</button>` : '<span style="font-size:0.7rem;color:#94a3b8;">Default</span>'}
                    </div>
                </div>
            `;
        });
        listEl.innerHTML = html || '<div style="color:#94a3b8;font-size:0.8rem;padding:1rem;">No quick messages configured.</div>';
    }

    async function saveNewQuickMessage() {
        const titleInput = document.getElementById('quick-input-title');
        const msgInput = document.getElementById('quick-input-msg');
        const title = titleInput?.value.trim();
        const msg = msgInput?.value.trim();

        if (!title || !msg) {
            alert('Please provide both button title and message content.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'save_quick_message');
        formData.append('title', title);
        formData.append('message', msg);

        try {
            const res = await fetch('api/chat.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok) {
                if (titleInput) titleInput.value = '';
                if (msgInput) msgInput.value = '';
                await fetchQuickMessages();
                renderQuickSettingsList();
            } else {
                alert('Save failed: ' + (data.error || 'Unknown error'));
            }
        } catch (e) {
            alert('Error saving quick message: ' + e.message);
        }
    }

    window.zimrxDeleteQuickMessage = async function (id) {
        if (!confirm('Are you sure you want to delete this quick message?')) return;
        const formData = new FormData();
        formData.append('action', 'delete_quick_message');
        formData.append('id', id);

        try {
            const res = await fetch('api/chat.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok) {
                await fetchQuickMessages();
                renderQuickSettingsList();
            }
        } catch (e) {}
    };

    // -------------------------------------------------------------------------
    // Colleague Modal
    // -------------------------------------------------------------------------
    function openColleagueModal() {
        if (!colleagueModalEl) return;
        const listEl = document.getElementById('chat-colleague-list');
        if (!availableUsers.length) {
            listEl.innerHTML = '<div style="padding:1.5rem;text-align:center;color:#94a3b8;">No colleagues found.</div>';
        } else {
            let html = '';
            availableUsers.forEach(u => {
                html += `
                    <div class="chat-colleague-item" data-user-id="${u.id}">
                        <div>
                            <div style="font-weight:700;color:#0f172a;font-size:0.86rem;">${escapeHtml(u.display_name || u.username)}</div>
                            <div style="font-size:0.72rem;color:#64748b;">@${escapeHtml(u.username)}</div>
                        </div>
                        <span class="chat-colleague-role role-${escapeHtml(u.role)}">${escapeHtml(u.role)}</span>
                    </div>
                `;
            });
            listEl.innerHTML = html;

            listEl.querySelectorAll('.chat-colleague-item').forEach(item => {
                item.addEventListener('click', async () => {
                    const uid = parseInt(item.dataset.userId, 10);
                    closeColleagueModal();
                    await startDirectChat(uid);
                });
            });
        }
        colleagueModalEl.style.display = 'flex';
    }

    function closeColleagueModal() {
        if (colleagueModalEl) colleagueModalEl.style.display = 'none';
    }

    async function startDirectChat(targetUserId) {
        const formData = new FormData();
        formData.append('action', 'start_direct');
        formData.append('target_user_id', targetUserId);

        try {
            const res = await fetch('api/chat.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok && data.conversation_id) {
                await fetchConversations();
                openConversation(data.conversation_id);
            }
        } catch (e) {}
    }

    // -------------------------------------------------------------------------
    // Smart Polling
    // -------------------------------------------------------------------------
    function startSmartPolling() {
        if (pollIntervalId) clearInterval(pollIntervalId);

        const pollTick = async () => {
            try {
                const params = new URLSearchParams({
                    action: 'poll',
                    since_tick: currentServerTick,
                    active_conversation_id: activeConversationId || 0,
                    last_msg_id: lastMessageId || 0
                });

                const res = await fetch(`api/chat.php?${params.toString()}`);
                const data = await res.json();

                if (data.ok) {
                    if (data.changed) {
                        currentServerTick = parseFloat(data.tick || currentServerTick);
                        updateHeaderBadge(data.total_unread || 0);

                        // If status changed (seen / delivered updates)
                        if (data.other_read_id || data.other_delivered_id) {
                            updateStatusesInDOM(data.other_read_id, data.other_delivered_id);
                        }

                        if (Array.isArray(data.new_messages) && data.new_messages.length > 0) {
                            renderMessages(data.new_messages, true);
                            scrollToBottom();
                            playNotificationChime();
                        } else if (data.total_unread > 0) {
                            playNotificationChime();
                        }

                        if (!activeConversationId) {
                            fetchConversations();
                        }
                    }
                }
            } catch (e) {}
        };

        let pollTimer = setInterval(pollTick, 2500);

        document.addEventListener('visibilitychange', () => {
            clearInterval(pollTimer);
            if (document.hidden) {
                pollTimer = setInterval(pollTick, 18000);
            } else {
                pollTick();
                pollTimer = setInterval(pollTick, 2500);
            }
        });
    }

    function updateStatusesInDOM(readId, deliveredId) {
        document.querySelectorAll('.chat-msg-row.mine').forEach(row => {
            const id = parseInt(row.dataset.msgId, 10);
            const footer = row.querySelector('.chat-msg-footer');
            if (!footer) return;

            let iconEl = footer.querySelector('.chat-status-icon');
            let status = 'sent';

            if (readId >= id) {
                status = 'seen';
            } else if (deliveredId >= id) {
                status = 'delivered';
            }

            if (iconEl) {
                iconEl.className = `chat-status-icon ${status}`;
                iconEl.textContent = (status === 'seen' || status === 'delivered') ? '✓✓' : '✓';
                iconEl.title = status.charAt(0).toUpperCase() + status.slice(1);
            }
        });
    }

    function scrollToBottom() {
        if (messagesContainerEl) {
            messagesContainerEl.scrollTop = messagesContainerEl.scrollHeight;
        }
    }

    function formatTime(str) {
        if (!str) return '';
        try {
            const parts = str.split(' ');
            if (parts.length >= 2) {
                const t = parts[1].split(':');
                let h = parseInt(t[0], 10);
                const m = t[1];
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                return `${h}:${m} ${ampm}`;
            }
        } catch (e) {}
        return '';
    }

    function formatFileSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, function (m) {
            switch (m) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case "'": return '&#39;';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChatWidget);
    } else {
        initChatWidget();
    }
})();
