<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $db = DB::connect();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full first-color text-slate-800">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - دلال Map العراق' : 'دلال Map - منصة نظم المعلومات الجغرافية العقارية في العراق'; ?></title>

    <!-- Meta tags for PWA -->
    <meta name="theme-color" content="#F8FAFC">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="https://ui-avatars.com/api/?name=Tabu+Map&background=10B981&color=ffffff&size=192">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Cairo', 'Inter', 'sans-serif'],
                        display: ['Cairo', 'Outfit', 'sans-serif'],
                    },
                    colors: {
                        iraq: {
                            gold: '#D4AF37',
                            red: '#CE1126',
                            green: '#007A3D',
                        },
                        theme: {
                            first: '#feffdf',
                            second: '#ffe79a',
                            third: '#ffa952',
                            fourth: '#ef5a5a',
                        }
                    }
                }
            }
        }
    </script>

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Leaflet MarkerCluster -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

    <!-- Leaflet-Geoman Drawing Tools -->
    <link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.css" />
    <script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.min.js"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Custom CSS -->
    <style>
        .first-color { background-color: #feffdf !important; }
        .second-color { background-color: #ffe79a !important; }
        .third-color { background-color: #ffa952 !important; }
        .fourth-color { background-color: #ef5a5a !important; }

        [x-cloak] { display: none !important; }
        .glassmorphism {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.03);
        }
        .leaflet-popup-content-wrapper {
            background: #ffffff !important;
            color: #1e293b !important;
            border-radius: 1rem !important;
            border: 1px solid rgba(0, 0, 0, 0.06) !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        }
        .leaflet-popup-tip {
            background: #ffffff !important;
        }
        .leaflet-popup-close-button {
            color: #64748b !important;
            font-size: 1.25rem !important;
            padding: 8px !important;
        }
        .leaflet-bar {
            border: 1px solid rgba(0, 0, 0, 0.06) !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
        }
        .leaflet-bar a {
            background-color: #ffffff !important;
            color: #334155 !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06) !important;
        }
        .leaflet-bar a:hover {
            background-color: #f8fafc !important;
            color: #10B981 !important;
        }
        .leaflet-container {
            background: #f8fafc !important;
        }
        .marker-cluster-small {
            background-color: rgba(16, 185, 129, 0.2) !important;
        }
        .marker-cluster-small div {
            background-color: rgba(16, 185, 129, 0.9) !important;
            color: white !important;
            font-weight: 700 !important;
        }
        .marker-cluster-medium {
            background-color: rgba(245, 158, 11, 0.2) !important;
        }
        .marker-cluster-medium div {
            background-color: rgba(245, 158, 11, 0.9) !important;
            color: white !important;
            font-weight: 700 !important;
        }
        .marker-cluster-large {
            background-color: rgba(239, 68, 68, 0.2) !important;
        }
        .marker-cluster-large div {
            background-color: rgba(239, 68, 68, 0.9) !important;
            color: white !important;
            font-weight: 700 !important;
        }
    </style>
    <script>
        window.deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            window.deferredPrompt = e;
            window.dispatchEvent(new CustomEvent('pwa-install-ready'));
        });

        // Automatic background login if session expired but user is registered locally in the app
        document.addEventListener("DOMContentLoaded", function() {
            const serverLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
            const localUserData = localStorage.getItem('dallamap_user');
            
            if (!serverLoggedIn && localUserData) {
                if (!sessionStorage.getItem('autologin_attempted')) {
                    sessionStorage.setItem('autologin_attempted', 'true');
                    try {
                        const user = JSON.parse(localUserData);
                        fetch('api_register.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(user)
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.location.reload();
                            }
                        })
                        .catch(err => console.error('Auto login failed:', err));
                    } catch (e) {
                        console.error('Parsing local user data failed:', e);
                    }
                }
            }
        });
    </script>
</head>
<body class="h-full flex flex-col overflow-hidden first-color text-slate-800">
    <!-- Desktop Header (Hidden on Mobile) -->
    <header class="hidden md:flex glassmorphism sticky top-0 z-50 px-4 py-3 items-center justify-between shadow-md shrink-0">
        <div class="flex items-center space-x-3">
            <a href="index.php" class="flex items-center space-x-2">
                <img src="images/facelogo.png" alt="Dallal Map Logo" class="w-[72px] h-[72px] rounded-2xl object-contain shadow-sm bg-white p-1 border border-slate-100">
                <div>
                    <span class="font-display font-bold text-xl tracking-tight text-slate-800">دلال <span class="text-theme-fourth font-extrabold">Map</span></span>
                </div>
            </a>
        </div>

        <nav class="flex items-center space-x-2 sm:space-x-4">
            <a href="index.php" class="text-xs sm:text-sm font-semibold text-slate-600 hover:text-emerald-600 transition-colors">
                <i class="fa-solid fa-earth-asia ml-1 text-emerald-500"></i> <span>استكشاف الخارطة</span>
            </a>

            <!-- Abu Abd AI Button -->
            <button x-data @click="$dispatch('open-abu-abd')" class="text-xs sm:text-sm font-semibold text-slate-600 hover:text-amber-600 transition-colors flex items-center">
                <img src="images/facelogo.png" class="w-5 h-5 sm:w-6 sm:h-6 ml-1.5 object-contain rounded-full border border-slate-200 shadow-sm" alt="Abu Abd"> <span>أسأل ابو عبد</span>
            </button>
            
            <?php if ($currentUser): ?>
                <a href="create.php" class="text-xs sm:text-sm font-semibold text-slate-600 hover:text-emerald-600 transition-colors">
                    <i class="fa-solid fa-plus-circle ml-1 text-emerald-500"></i> <span>إضافة عقار</span>
                </a>
                <a href="dashboard.php" class="text-xs sm:text-sm font-semibold text-slate-600 hover:text-emerald-600 transition-colors">
                    <i class="fa-solid fa-folder-open ml-1 text-emerald-500"></i> <span>إعلاناتي</span>
                </a>

                <!-- User Dropdown Profile -->
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none bg-slate-100 rounded-full p-1 border border-slate-200 hover:border-emerald-500 transition-all">
                        <img class="h-7 w-7 rounded-full object-cover shadow-sm" src="<?php echo htmlspecialchars($currentUser['avatar'] ?? 'https://ui-avatars.com/api/?name='.urlencode($currentUser['name'])); ?>" alt="<?php echo htmlspecialchars($currentUser['name']); ?>">
                        <span class="hidden md:block text-xs font-bold text-slate-700 pr-2 pr-1"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute left-0 mt-2 w-48 rounded-2xl shadow-xl py-2 bg-white border border-slate-200 ring-1 ring-black ring-opacity-5 focus:outline-none text-slate-700 z-50">
                        <div class="px-4 py-2 border-b border-slate-100">
                            <p class="text-[10px] text-slate-400">تم تسجيل الدخول بصفتك</p>
                            <p class="text-xs font-extrabold truncate text-slate-800"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                            <span class="inline-block mt-1 text-[10px] uppercase font-bold tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full">
                                <?php echo $currentUser['user_type'] === 'broker' ? 'دلال / مكتب' : ($currentUser['user_type'] === 'company' ? 'شركة عقارية' : 'شخصي'); ?>
                            </span>
                        </div>
                        <a href="dashboard.php" class="block px-4 py-2 text-xs font-semibold hover:bg-slate-50 hover:text-slate-900 transition-colors text-right">
                            <i class="fa-solid fa-circle-user ml-2 text-slate-400"></i> لوحة التحكم
                        </a>
                        <a href="logout.php" class="w-full text-right block px-4 py-2 text-xs font-semibold hover:bg-red-50 hover:text-red-600 transition-colors">
                            <i class="fa-solid fa-sign-out-alt ml-2 text-red-500/80"></i> تسجيل الخروج
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-bold px-4 py-2 rounded-xl text-xs sm:text-sm flex items-center space-x-2 transition-all transform hover:scale-105 shadow-md shadow-emerald-500/10">
                    <i class="fa-solid fa-sign-in-alt"></i>
                    <span>تسجيل دخول سريع</span>
                </a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Mobile App Header (Visible only on screens < md) -->
    <header class="md:hidden glassmorphism sticky top-0 z-50 px-4 py-2 flex items-center justify-between shadow-sm shrink-0">
        <div class="flex items-center space-x-2">
            <a href="index.php" class="flex items-center space-x-1.5">
                <img src="images/facelogo.png" alt="Dallal Map Logo" class="w-10 h-10 rounded-xl object-contain shadow-sm bg-white p-0.5 border border-slate-100">
                <span class="font-display font-bold text-base tracking-tight text-slate-800">دلال <span class="text-theme-fourth font-extrabold">Map</span></span>
            </a>
        </div>
        
        <div class="flex items-center space-x-2">
            <!-- PWA Install Button -->
            <button x-data="{ canInstall: false }" 
                    x-show="canInstall"
                    @pwa-install-ready.window="canInstall = true"
                    @click="if (window.deferredPrompt) { window.deferredPrompt.prompt(); window.deferredPrompt = null; canInstall = false; }"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold px-2.5 py-1.5 rounded-lg shadow-sm flex items-center space-x-1 space-x-reverse"
                    x-cloak>
                <i class="fa-solid fa-download"></i>
                <span>تثبيت</span>
            </button>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-h-0 relative pb-[68px] md:pb-0">
