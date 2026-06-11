(function () {
    const bootstrap = window.CHAT_BOOTSTRAP || {};
    const currentUserId = Number(bootstrap.currentUserId || 0);
    const roomList = document.getElementById('chat-room-list');
    const openOrderForm = document.getElementById('chat-open-order-form');
    const messagesBox = document.getElementById('chat-messages');
    const sendForm = document.getElementById('chat-send-form');
    const title = document.getElementById('chat-room-title');
    let activeRoomId = Number(bootstrap.roomId || 0);
    let lastMessageId = 0;

    async function api(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
        });
        const payload = await response.json();

        if (!payload.success) {
            throw new Error(payload.message || 'Lỗi hệ thống.');
        }

        return payload.data ?? payload;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderRooms(rooms) {
        roomList.innerHTML = rooms.map((room) => {
            const isActive = Number(room.id) === activeRoomId;
            const store = room.store_name || room.store_email || 'Shop';
            const unreadCount = Number(room.unread_count || 0);

            const activeClasses = isActive ? 'bg-primary/10 border border-primary/20 shadow-sm' : 'bg-transparent border border-transparent hover:bg-white hover:shadow-sm hover:border-border-subtle';
            const avatarClasses = isActive ? 'bg-primary text-white shadow-md' : 'bg-surface-container-high text-on-surface-variant group-hover:bg-primary/5 group-hover:text-primary transition-colors';
            const textClasses = isActive ? 'text-primary' : 'text-on-surface group-hover:text-primary transition-colors';
            const subtextClasses = isActive ? 'text-primary/70' : 'text-on-surface-variant';

            return `
                <button type="button" data-room-id="${Number(room.id)}" class="w-full text-left p-2.5 rounded-xl transition-all duration-200 flex items-center gap-2.5 group relative ${activeClasses}">
                    <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center ${avatarClasses}">
                        <span class="material-symbols-outlined text-[18px]">storefront</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <strong class="text-xs font-bold truncate block ${textClasses}">
                            ${escapeHtml(store)}
                        </strong>
                        <span class="text-[11px] truncate block flex items-center gap-1 font-medium ${subtextClasses}">
                            <span class="inline-block w-1 h-1 rounded-full ${isActive ? 'bg-primary' : 'bg-outline-variant'}"></span>
                            Đơn #${escapeHtml(room.order_code)}
                        </span>
                    </div>
                    ${unreadCount > 0 ? `<span class="flex-shrink-0 bg-error text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow-sm">${unreadCount}</span>` : ''}
                </button>
            `;
        }).join('');
    }

    function appendMessages(messages) {
        for (const message of messages) {
            const id = Number(message.id);

            if (id <= lastMessageId) {
                continue;
            }

            const mine = Number(message.sender_id) === currentUserId;
            const isSystem = message.sender_type === 'system' || message.sender_type === 'admin' || !message.sender_id;
            
            const node = document.createElement('article');
            
            let contentHtml = '';
            if (message.message_type === 'image') {
                contentHtml = `<img src="${escapeHtml(message.content)}" alt="Hình ảnh" class="max-w-[200px] rounded-lg cursor-zoom-in mt-1 border border-black/10 transition-transform hover:scale-[1.02]" onclick="if(window.openImageModal) window.openImageModal(this.src)">`;
            } else {
                contentHtml = `<p class="m-0">${escapeHtml(message.content)}</p>`;
            }

            if (isSystem) {
                node.className = 'max-w-[80%] md:max-w-[70%] px-3 py-2 rounded-[16px] text-[13px] leading-relaxed self-start bg-amber-50 text-on-surface rounded-bl-[3px] shadow-sm border border-amber-200/50 relative';
                node.innerHTML = `
                    <strong class="text-[11px] text-amber-600 block mb-0.5 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-[12px]">admin_panel_settings</span>
                        ${escapeHtml(message.sender_name || 'Hệ thống')}
                    </strong>
                    <div class="whitespace-pre-wrap">${contentHtml}</div>
                    <time class="block text-[10px] mt-1 font-medium text-on-surface-variant text-right">${escapeHtml(message.created_at || '')}</time>
                `;
            } else {
                node.className = mine
                    ? 'max-w-[80%] md:max-w-[70%] px-3 py-2 rounded-[16px] text-[13px] leading-relaxed self-end bg-gradient-to-br from-primary to-blue-600 text-white rounded-br-[3px] shadow-md relative'
                    : 'max-w-[80%] md:max-w-[70%] px-3 py-2 rounded-[16px] text-[13px] leading-relaxed self-start bg-white text-on-surface rounded-bl-[3px] shadow-sm border border-border-subtle relative';

                node.innerHTML = `
                    ${!mine ? `<strong class="text-[11px] text-primary block mb-0.5 font-bold">${escapeHtml(message.sender_name || 'Cửa hàng')}</strong>` : ''}
                    <div class="whitespace-pre-wrap">${contentHtml}</div>
                    <time class="block text-[10px] mt-1 font-medium ${mine ? 'text-white/70 text-right' : 'text-on-surface-variant text-right'}">${escapeHtml(message.created_at || '')}</time>
                `;
            }
            
            messagesBox.appendChild(node);
            lastMessageId = id;
        }

        if (messages.length > 0) {
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }
    }

    async function loadRooms() {
        const rooms = await api('/api/chat.php?action=rooms');
        renderRooms(rooms);

        if (!activeRoomId && rooms[0]) {
            selectRoom(Number(rooms[0].id));
        }
    }

    async function selectRoom(roomId) {
        activeRoomId = roomId;
        lastMessageId = 0;
        messagesBox.innerHTML = '';
        title.textContent = 'Room #' + roomId;
        await loadRooms();
        await loadMessages();
    }

    async function loadMessages() {
        if (!activeRoomId) {
            return;
        }

        const messages = await api(`/api/chat.php?action=messages&room_id=${activeRoomId}&after_id=${lastMessageId}`);
        appendMessages(messages);
    }

    roomList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-room-id]');

        if (!button) {
            return;
        }

        selectRoom(Number(button.dataset.roomId)).catch(showError);
    });

    openOrderForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const orderId = Number(new FormData(openOrderForm).get('order_id'));

        if (!orderId) {
            return;
        }

        try {
            const room = await api('/api/chat.php?action=room', {
                method: 'POST',
                body: JSON.stringify({ order_id: orderId }),
            });
            await selectRoom(Number(room.id));
        } catch (error) {
            showError(error);
        }
    });

    sendForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!activeRoomId) {
            showError(new Error('Vui lòng chon room chat.'));
            return;
        }

        const textarea = sendForm.querySelector('textarea[name="content"]');
        const fileInput = document.getElementById('chat-image-input');
        const content = textarea.value.trim();
        const file = fileInput.files[0];

        if (!content && !file) {
            return;
        }

        try {
            // Disable form during upload
            const submitBtn = sendForm.querySelector('button[type="submit"]');
            const submitBtnHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;

            if (file) {
                // Upload image
                const formData = new FormData();
                formData.append('image', file);
                
                await fetch(`/api/chat.php?action=upload_image&room_id=${activeRoomId}`, {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(payload => {
                    if (!payload.success) throw new Error(payload.message || 'Lỗi upload ảnh.');
                });
                
                // Clear file input
                fileInput.value = '';
            }

            if (content) {
                // Send text
                await api(`/api/chat.php?action=send&room_id=${activeRoomId}`, {
                    method: 'POST',
                    body: JSON.stringify({ content }),
                });
                textarea.value = '';
            }

            await loadMessages();
        } catch (error) {
            showError(error);
        } finally {
            const submitBtn = sendForm.querySelector('button[type="submit"]');
            submitBtn.disabled = false;
        }
    });

    // Make textarea not required if image is selected
    const fileInput = document.getElementById('chat-image-input');
    const textarea = sendForm.querySelector('textarea[name="content"]');
    if (fileInput && textarea) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                textarea.removeAttribute('required');
                textarea.placeholder = `Đã đính kèm: ${fileInput.files[0].name}`;
            } else {
                textarea.setAttribute('required', 'required');
                textarea.placeholder = 'Nhập tin nhắn...';
            }
        });
    }

    function showError(error) {
        title.textContent = error.message || 'Lỗi hệ thống.';
    }

    loadRooms().catch(showError);
    window.setInterval(() => loadRooms().catch(() => {}), 10000);
    window.setInterval(() => loadMessages().catch(() => {}), 3000);
})();
