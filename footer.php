    </main>

    <!-- Mobile Bottom Navigation Bar (Hidden on Desktop) -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 backdrop-blur-md border-t border-slate-200/80 shadow-[0_-4px_12px_rgba(0,0,0,0.05)] flex justify-around items-center z-40 px-2 pb-safe">
        
        <!-- Tab 1: Map (الخريطة) -->
        <button onclick="if (window.location.pathname.endsWith('index.php') || window.location.pathname.endsWith('/') || window.location.pathname.split('/').pop() === '') { window.dispatchEvent(new CustomEvent('show-map')); } else { window.location.href = 'index.php'; }"
                class="flex flex-col items-center justify-center text-slate-500 hover:text-emerald-600 active:text-emerald-650 transition-colors flex-1 py-1 focus:outline-none">
            <i class="fa-solid fa-map-location-dot text-lg"></i>
            <span class="text-[9px] font-bold mt-1">الخارطة</span>
        </button>

        <!-- Tab 2: List (العقارات) -->
        <button onclick="if (window.location.pathname.endsWith('index.php') || window.location.pathname.endsWith('/') || window.location.pathname.split('/').pop() === '') { window.dispatchEvent(new CustomEvent('show-list')); } else { window.location.href = 'index.php?view=list'; }"
                class="flex flex-col items-center justify-center text-slate-500 hover:text-emerald-600 active:text-emerald-650 transition-colors flex-1 py-1 focus:outline-none">
            <i class="fa-solid fa-list-ul text-lg"></i>
            <span class="text-[9px] font-bold mt-1">العقارات</span>
        </button>

        <!-- Tab 3: Add Property (إضافة عقار) -->
        <a href="create.php" 
           class="flex flex-col items-center justify-center text-slate-500 hover:text-emerald-600 active:text-emerald-650 transition-colors flex-1 py-1">
            <i class="fa-solid fa-circle-plus text-lg"></i>
            <span class="text-[9px] font-bold mt-1">إضافة</span>
        </a>

        <!-- Tab 4: Ask Abu Abd (اسأل ابو عبد) -->
        <button onclick="if (window.location.pathname.endsWith('index.php') || window.location.pathname.endsWith('/') || window.location.pathname.split('/').pop() === '') { window.dispatchEvent(new CustomEvent('open-abu-abd')); } else { window.location.href = 'index.php?open_chat=true'; }"
                class="flex flex-col items-center justify-center text-slate-500 hover:text-amber-500 active:text-amber-650 transition-colors flex-1 py-1 focus:outline-none">
            <i class="fa-solid fa-comment-dots text-xl text-amber-500"></i>
            <span class="text-[9px] font-bold mt-1">أبو عبد AI</span>
        </button>

        <!-- Tab 5: Filter (تصفية) -->
        <button onclick="if (window.location.pathname.endsWith('index.php') || window.location.pathname.endsWith('/') || window.location.pathname.split('/').pop() === '') { window.dispatchEvent(new CustomEvent('open-filters')); } else { window.location.href = 'index.php?show_filters=true'; }"
                class="flex flex-col items-center justify-center text-slate-500 hover:text-emerald-600 active:text-emerald-650 transition-colors flex-1 py-1 focus:outline-none">
            <i class="fa-solid fa-sliders text-lg"></i>
            <span class="text-[9px] font-bold mt-1">تصفية</span>
        </button>

        <!-- Tab 6: Profile / Login (حسابي) -->
        <?php if ($currentUser): ?>
            <a href="dashboard.php" 
               class="flex flex-col items-center justify-center text-slate-500 hover:text-emerald-600 active:text-emerald-650 transition-colors flex-1 py-1">
                <img class="h-6 w-6 rounded-full object-cover shadow-sm border border-slate-200" src="<?php echo htmlspecialchars($currentUser['avatar'] ?? 'https://ui-avatars.com/api/?name='.urlencode($currentUser['name'])); ?>" alt="Avatar">
                <span class="text-[9px] font-bold mt-1">حسابي</span>
            </a>
        <?php else: ?>
            <a href="login.php" 
               class="flex flex-col items-center justify-center text-slate-500 hover:text-emerald-600 active:text-emerald-650 transition-colors flex-1 py-1">
                <i class="fa-solid fa-circle-user text-lg"></i>
                <span class="text-[9px] font-bold mt-1">دخول</span>
            </a>
        <?php endif; ?>

    <!-- Notification Toast Messages (Alpine-driven) -->
    <div x-data="{ 
            messages: [],
            add(text, type = 'success') {
                const id = Date.now();
                this.messages.push({ id, text, type });
                setTimeout(() => this.remove(id), 5000);
            },
            remove(id) {
                this.messages = this.messages.filter(m => m.id !== id);
            }
         }"
         @notify.window="add($event.detail.text, $event.detail.type)"
         class="fixed bottom-4 right-4 z-50 flex flex-col space-y-2 w-full max-w-sm px-4">
        
        <!-- Preload server flashes -->
        <?php if(isset($_SESSION['success'])): ?>
            <div x-init="$nextTick(() => add(<?php echo json_encode($_SESSION['success']); ?>, 'success'))"></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div x-init="$nextTick(() => add(<?php echo json_encode($_SESSION['error']); ?>, 'error'))"></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <template x-for="msg in messages" :key="msg.id">
            <div x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="transform translate-y-2 opacity-0"
                 x-transition:enter-end="transform translate-y-0 opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 :class="{
                    'bg-emerald-50 border-emerald-200 text-emerald-800': msg.type === 'success',
                    'bg-red-50 border-red-200 text-red-800': msg.type === 'error',
                    'bg-white border-slate-200 text-slate-700': msg.type === 'info'
                 }"
                 class="flex items-center justify-between p-4 rounded-xl border shadow-lg bg-white border-slate-100">
                <div class="flex items-center space-x-3 space-x-reverse">
                    <template x-if="msg.type === 'success'">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                    </template>
                    <template x-if="msg.type === 'error'">
                        <i class="fa-solid fa-circle-exclamation text-red-600 text-lg"></i>
                    </template>
                    <span class="text-xs font-semibold leading-relaxed" x-text="msg.text"></span>
                </div>
                <button @click="remove(msg.id)" class="text-slate-400 hover:text-slate-700 focus:outline-none ml-4">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
        </template>
    </div>

    <!-- Service Worker registration for PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('Service worker registered successfully.'))
                    .catch(err => console.log('Service worker registration failed:', err));
            });
        }
    </script>
</body>
</html>
