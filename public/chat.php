<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/user/_buyer_ui.php';

$user = PermissionMiddleware::requireModule(MODULE_CHAT);
$selectedRoomId = (int) ($_GET['room_id'] ?? 0);
$orders = ChatModel::ordersForActor($user, '', 80);
$rooms = ChatModel::roomsForActor($user);
$homeUrl = match ($user['user_type']) {
    USER_TYPE_ADMIN, USER_TYPE_SUB_ADMIN_ACTIVE => '/admin/dashboard.php',
    USER_TYPE_STORE_APPROVED, USER_TYPE_STORE_EMPLOYEE => '/store/dashboard.php',
    default => '/user/home.php',
};
$isAdminPortal = in_array($user['user_type'], [USER_TYPE_ADMIN, USER_TYPE_SUB_ADMIN_ACTIVE], true);
$isStorePortal = in_array($user['user_type'], [USER_TYPE_STORE_APPROVED, USER_TYPE_STORE_EMPLOYEE], true);
$isBuyer = !$isAdminPortal && !$isStorePortal;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tin nhắn - OmniSales</title>
    <?php include __DIR__ . '/user/_tailwind_head.php'; ?>
    <?php if ($isStorePortal || $isAdminPortal): ?>
        <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
        <?php if ($isStorePortal): ?>
            <link rel="stylesheet" href="/assets/css/store-buyer.css?v=20260609-12">
        <?php endif; ?>
    <?php endif; ?>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.35); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.6); }
        .chat-bg {
            background-color: #f8fafc;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23cbd5e1' fill-opacity='0.12'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        <?php if ($isStorePortal || $isAdminPortal): ?>
        /* Store/Admin: override shell so chat fills the page next to sidebar nav */
        .portal-page .portal-shell.chat-shell {
            padding-top: 12px !important;
            padding-bottom: 12px !important;
        }
        <?php endif; ?>
    </style>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-surface-container-lowest text-on-background <?= $isStorePortal ? 'portal-page store-page' : ($isAdminPortal ? 'portal-page' : 'pt-[64px]') ?> pb-0">

    <?php if ($isBuyer): ?>
        <?php include __DIR__ . '/user/_tailwind_header.php'; ?>
    <?php elseif ($isAdminPortal): ?>
        <?php include __DIR__ . '/admin/_admin_nav.php'; ?>
    <?php elseif ($isStorePortal): ?>
        <?php include __DIR__ . '/store/_store_nav.php'; ?>
    <?php endif; ?>

    <main class="<?= ($isStorePortal || $isAdminPortal) ? 'portal-shell portal-wide chat-shell' : 'max-w-[1400px] mx-auto w-full px-0 sm:px-4 lg:px-6 sm:mt-4 flex-grow flex flex-col' ?>" style="height: <?= $isBuyer ? 'calc(100vh - 64px)' : 'auto' ?>;">

        <!-- ===== SHARED PREMIUM CHAT UI ===== -->
        <div class="bg-white sm:rounded-2xl sm:border border-border-subtle shadow-lg flex-grow flex overflow-hidden min-h-0 relative <?= ($isStorePortal || $isAdminPortal) ? 'rounded-xl' : '' ?>" style="<?= ($isStorePortal || $isAdminPortal) ? 'height: calc(100vh - 60px);' : '' ?>">

            <!-- Sidebar -->
            <aside class="w-[280px] lg:w-[320px] flex-shrink-0 border-r border-border-subtle flex flex-col bg-surface-container-lowest hidden md:flex z-20" id="chat-sidebar">
                <!-- Header -->
                <div class="p-3.5 border-b border-border-subtle bg-white flex items-center justify-between z-10 shadow-sm">
                    <h1 class="text-base font-extrabold text-on-surface flex items-center gap-2 tracking-tight">
                        <span class="material-symbols-outlined text-[20px] text-primary">chat</span>
                        Tin nhắn
                    </h1>
                </div>

                <!-- Chọn đơn hàng -->
                <div class="p-2.5 bg-surface-container-lowest z-10 border-b border-border-subtle">
                    <form id="chat-open-order-form" class="relative group">
                        <select name="order_id" required class="w-full appearance-none bg-white border border-border-subtle rounded-xl py-2 pl-3.5 pr-9 text-xs font-medium focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none shadow-sm text-on-surface">
                            <option value="">Tạo phiên hỗ trợ cho đơn hàng...</option>
                            <?php foreach ($orders as $order): ?>
                                <option value="<?= (int) $order['id'] ?>">
                                    Đơn #<?= htmlspecialchars($order['order_code']) ?> — <?= htmlspecialchars(UiHelper::statusLabel($order['status'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-primary">
                            <span class="material-symbols-outlined text-[16px] bg-primary/10 rounded-md p-0.5">add</span>
                        </div>
                        <script>
                            document.querySelector('#chat-open-order-form select').addEventListener('change', function() {
                                if(this.value) {
                                    this.form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));
                                }
                            });
                        </script>
                    </form>
                </div>

                <!-- Danh sách phòng chat -->
                <div id="chat-room-list" class="flex-1 overflow-y-auto overflow-x-hidden p-1.5 space-y-0.5 custom-scrollbar bg-surface-container-lowest">
                    <?php if (!$rooms): ?>
                        <div class="text-center py-10 px-5">
                            <div class="w-14 h-14 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                                <span class="material-symbols-outlined text-[28px] text-primary">forum</span>
                            </div>
                            <p class="text-sm font-medium text-on-surface mb-1">Chưa có tin nhắn</p>
                            <p class="text-xs text-on-surface-variant">Chọn đơn hàng ở trên để bắt đầu trò chuyện.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($rooms as $room): ?>
                        <?php $isActive = (int) $room['id'] === $selectedRoomId; ?>
                        <button type="button" data-room-id="<?= (int) $room['id'] ?>" class="chat-room-button w-full text-left p-2.5 rounded-xl transition-all duration-200 flex items-center gap-2.5 group relative <?= $isActive ? 'bg-primary/10 border border-primary/20 shadow-sm' : 'bg-transparent border border-transparent hover:bg-white hover:shadow-sm hover:border-border-subtle' ?>">

                            <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center <?= $isActive ? 'bg-primary text-white shadow-md' : 'bg-surface-container-high text-on-surface-variant group-hover:bg-primary/5 group-hover:text-primary transition-colors' ?>">
                                <span class="material-symbols-outlined text-[18px]">storefront</span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <strong class="text-xs font-bold truncate block <?= $isActive ? 'text-primary' : 'text-on-surface group-hover:text-primary transition-colors' ?>">
                                    <?= htmlspecialchars((string) ($room['store_name'] ?: $room['store_email'])) ?>
                                </strong>
                                <span class="text-[11px] truncate block flex items-center gap-1 font-medium <?= $isActive ? 'text-primary/70' : 'text-on-surface-variant' ?>">
                                    <span class="inline-block w-1 h-1 rounded-full <?= $isActive ? 'bg-primary' : 'bg-outline-variant' ?>"></span>
                                    Đơn #<?= htmlspecialchars($room['order_code']) ?>
                                </span>
                            </div>
                            <?php if ((int) ($room['unread_count'] ?? 0) > 0): ?>
                                <span class="flex-shrink-0 bg-error text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow-sm"><?= (int) $room['unread_count'] ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- Khung chat chính -->
            <section class="flex-1 flex flex-col min-w-0 relative bg-white z-10">
                <!-- Header -->
                <div id="chat-room-title" class="px-4 py-2.5 border-b border-border-subtle bg-white/90 backdrop-blur-md flex items-center gap-3 z-20 shadow-sm">
                    <button class="md:hidden w-8 h-8 flex items-center justify-center rounded-full hover:bg-surface-container-high transition-colors text-on-surface-variant bg-surface-container-low" onclick="document.getElementById('chat-sidebar').classList.toggle('hidden'); document.getElementById('chat-sidebar').classList.toggle('absolute'); document.getElementById('chat-sidebar').classList.toggle('z-30'); document.getElementById('chat-sidebar').classList.toggle('h-full'); document.getElementById('chat-sidebar').classList.toggle('w-full');">
                        <span class="material-symbols-outlined text-[20px]">menu</span>
                    </button>

                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary hidden md:flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">chat_bubble</span>
                    </div>

                    <div class="flex flex-col min-w-0 flex-1">
                        <span class="font-bold text-sm text-on-surface tracking-tight truncate">Tin nhắn OmniSales</span>
                        <span class="text-[11px] text-on-surface-variant font-medium">Bảo mật · An toàn</span>
                    </div>
                </div>

                <!-- Vùng tin nhắn -->
                <div id="chat-messages" class="flex-1 overflow-y-auto p-4 flex flex-col gap-2.5 chat-bg custom-scrollbar relative">
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-6">
                        <div class="w-16 h-16 bg-white rounded-full shadow-md flex items-center justify-center mb-4 border border-border-subtle">
                            <span class="material-symbols-outlined text-[32px] text-primary/40">chat</span>
                        </div>
                        <h2 class="text-base font-bold text-on-surface mb-1">Xin chào!</h2>
                        <p class="text-xs font-medium text-on-surface-variant max-w-xs">Chọn một đoạn hội thoại hoặc đơn hàng từ danh sách bên trái để bắt đầu nhắn tin.</p>
                    </div>
                </div>

                <!-- Ô nhập tin nhắn -->
                <form id="chat-send-form" class="p-3 bg-white border-t border-border-subtle flex items-end gap-2.5 relative z-20">
                    <div class="flex-1 min-w-0 relative bg-surface-container-low rounded-2xl focus-within:bg-white focus-within:shadow-md focus-within:ring-2 focus-within:ring-primary/20 transition-all duration-300 flex items-end border border-transparent focus-within:border-primary/30">

                        <textarea name="content" rows="1" placeholder="Nhập tin nhắn..." required class="w-full bg-transparent border-none focus:ring-0 resize-none py-2.5 px-4 text-[13px] max-h-[120px] outline-none custom-scrollbar m-0 text-on-surface placeholder:text-outline" oninput="this.style.height = ''; this.style.height = Math.min(this.scrollHeight, 120) + 'px'"></textarea>

                        <div class="flex-shrink-0 flex p-1.5 gap-0.5 self-end">
                            <label title="Gửi hình ảnh" class="w-8 h-8 flex items-center justify-center text-primary/70 hover:text-primary hover:bg-primary/10 rounded-lg cursor-pointer transition-colors m-0">
                                <input type="file" name="image" id="chat-image-input" accept="image/png,image/jpeg,image/webp" class="hidden">
                                <span class="material-symbols-outlined text-[20px]">image</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="flex-shrink-0 w-[40px] h-[40px] bg-gradient-to-r from-primary to-blue-500 text-white rounded-xl flex items-center justify-center hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0">
                        <span class="material-symbols-outlined text-[20px] ml-0.5">send</span>
                    </button>
                </form>
            </section>

        </div>
        <!-- ===== END SHARED CHAT UI ===== -->

    </main>

    <?php if ($isBuyer): ?>
        <!-- Mobile Bottom Nav -->
        <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-border-subtle z-40 safe-bottom md:hidden">
            <div class="flex items-center justify-around h-14 max-w-lg mx-auto">
                <a href="/user/home.php" class="flex flex-col items-center gap-0.5 text-on-surface-variant hover:text-primary transition-colors py-1 px-3">
                    <span class="material-symbols-outlined text-[20px]">home</span>
                    <span class="text-[10px] font-semibold">Trang chủ</span>
                </a>
                <a href="/user/products.php" class="flex flex-col items-center gap-0.5 text-on-surface-variant hover:text-primary transition-colors py-1 px-3">
                    <span class="material-symbols-outlined text-[20px]">grid_view</span>
                    <span class="text-[10px] font-semibold">Sản phẩm</span>
                </a>
                <a href="/chat.php" class="flex flex-col items-center gap-0.5 text-primary transition-colors py-1 px-3">
                    <span class="material-symbols-outlined text-[20px]">chat</span>
                    <span class="text-[10px] font-semibold">Tin nhắn</span>
                </a>
                <a href="/profile.php" class="flex flex-col items-center gap-0.5 text-on-surface-variant hover:text-primary transition-colors py-1 px-3">
                    <span class="material-symbols-outlined text-[20px]">person</span>
                    <span class="text-[10px] font-semibold">Tài khoản</span>
                </a>
            </div>
        </nav>
    <?php endif; ?>

    <!-- Modal xem ảnh -->
    <div id="image-modal" class="hidden fixed inset-0 bg-black/90 z-[1000] items-center justify-center cursor-zoom-out flex-col backdrop-blur-sm" onclick="this.classList.add('hidden'); this.classList.remove('flex')">
        <img id="image-modal-img" src="" class="max-w-[95vw] max-h-[90vh] rounded-2xl shadow-2xl object-contain border border-white/10">
        <div class="text-white/80 mt-4 text-xs font-medium bg-black/50 px-4 py-1.5 rounded-full border border-white/10 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[14px]">close</span>Nhấn bất kỳ đâu để đóng
        </div>
    </div>

    <script>
        function openImageModal(src) {
            document.getElementById('image-modal-img').src = src;
            const modal = document.getElementById('image-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        window.CHAT_BOOTSTRAP = {
            roomId: <?= $selectedRoomId ?>,
            currentUserId: <?= (int) $user['id'] ?>,
            isBuyer: true
        };
    </script>
    <script src="/assets/js/chat.js?v=<?= time() ?>"></script>
    <script src="/assets/js/global.js?v=<?= time() ?>"></script>
</body>
</html>
