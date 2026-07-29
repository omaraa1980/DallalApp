<?php
$pageTitle = 'استكشاف الخارطة';
require_once __DIR__ . '/header.php';
?>

<div id="home-root" x-data="{ 
    surveyLoading: false,
    surveyResults: null,
    surveyCollapsed: false,
    activeSurveyTab: 'schools',
    desktopSidebarOpen: true,
    sheetState: 'collapsed', // collapsed, half, full
    filterModalOpen: false,
    selectedProperty: null,
    activeImage: null,
    search: '',
    propertyType: '',
    status: '',
    governorate: '',
    priceMin: '',
    priceMax: '',
    properties: [],
    loading: false,
    isLoggedIn: <?php echo $currentUser ? 'true' : 'false'; ?>,
    activeLayer: 'osm',
    shouldFitBounds: false,
    chatOpen: false,
    chatMessage: '',
    chatHistory: [{ role: 'assistant', content: 'مرحباً! أنا ابو عبد، دلالك العقاري العراقي. شلون أگدر أساعدك اليوم؟ اسألني عن أسعار أو أماكن العقارات.' }],
    chatLoading: false,
    init() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('show_filters')) {
            this.filterModalOpen = true;
        }
        if (urlParams.has('open_chat')) {
            this.chatOpen = true;
        }
        if (urlParams.get('view') === 'list') {
            this.sheetState = 'half';
        }
    },

    sendChatMessage() {
        if (!this.chatMessage.trim() || this.chatLoading) return;
        this.chatHistory.push({ role: 'user', content: this.chatMessage });
        const currentMessage = this.chatMessage;
        this.chatMessage = '';
        this.chatLoading = true;
        
        setTimeout(() => {
            const chatBox = document.getElementById('chat-history-box');
            if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
        }, 100);

        fetch('api_chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
            },
            body: JSON.stringify({ message: currentMessage })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                this.chatHistory.push({ role: 'assistant', content: res.reply });
                if (res.properties && res.properties.length > 0) {
                    this.properties = res.properties;
                    if(typeof renderMapLayers === 'function') {
                        renderMapLayers(res.properties);
                    }
                    
                    let tempGroup = L.featureGroup();
                    res.properties.forEach(prop => {
                        if (prop.geometry) {
                            if (prop.geometry.type === 'Point') {
                                tempGroup.addLayer(L.marker([prop.geometry.coordinates[1], prop.geometry.coordinates[0]]));
                            } else if (prop.geometry.type === 'Polygon') {
                                tempGroup.addLayer(L.geoJSON(prop.geometry));
                            }
                        }
                    });
                    if (tempGroup.getLayers().length > 0 && map) {
                        map.fitBounds(tempGroup.getBounds(), { padding: [50, 50], maxZoom: 14 });
                    }
                }
            } else {
                this.chatHistory.push({ role: 'assistant', content: 'المعذرة، صار خطأ بالاتصال.' });
            }
        })
        .catch(err => {
            this.chatHistory.push({ role: 'assistant', content: 'عفواً، واجهتني مشكلة تقنية.' });
        })
        .finally(() => {
            this.chatLoading = false;
            setTimeout(() => {
                const chatBox = document.getElementById('chat-history-box');
                if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
            }, 100);
        });
    },

    toggleSheet() {
        if (this.sheetState === 'collapsed') {
            this.sheetState = 'half';
        } else if (this.sheetState === 'half') {
            this.sheetState = 'full';
        } else {
            this.sheetState = 'collapsed';
        }
    },
    expandSheet() {
        this.sheetState = 'half';
    },
    collapseSheet() {
        this.sheetState = 'collapsed';
    },
    formatPrice(price) {
        const num = parseFloat(price);
        if (num >= 1000000000) {
            return (num / 1000000000).toFixed(1) + ' B IQD';
        }
        if (num >= 1000000) {
            return (num / 1000000).toFixed(0) + ' M IQD';
        }
        return num.toLocaleString() + ' IQD';
    },
    formatPriceArabic(price) {
        const num = parseFloat(price);
        if (num >= 1000000000) {
            return (num / 1000000000).toFixed(1) + ' مليار د.ع';
        }
        if (num >= 1000000) {
            return (num / 1000000).toFixed(0) + ' مليون د.ع';
        }
        return num.toLocaleString() + ' د.ع';
    },
    formatPriceInput(val) {
        if (!val) return '';
        let clean = String(val).replace(/[^\d]/g, '');
        if (!clean) return '';
        return Number(clean).toLocaleString('en-US');
    }
}" @open-abu-abd.window="chatOpen = true" @open-filters.window="filterModalOpen = true" @show-map.window="sheetState = 'collapsed'; chatOpen = false; filterModalOpen = false;" @show-list.window="sheetState = 'half'; chatOpen = false; filterModalOpen = false;" class="flex-1 flex flex-col md:flex-row min-h-0 relative first-color overflow-hidden">

    <!-- Desktop Sidebar -->
    <aside :class="desktopSidebarOpen ? 'translate-x-0' : 'translate-x-full md:w-0 border-l-0 overflow-hidden'"
           class="hidden md:flex md:w-[450px] lg:w-[500px] flex-col bg-white border-l border-slate-200 shrink-0 h-full transition-all duration-300 ease-in-out relative z-30">
        
        <!-- Toggle Sidebar Button (Floating on the edge) -->
        <button @click="desktopSidebarOpen = !desktopSidebarOpen" 
                title="اخفاء / إظهار القائمة"
                class="hidden md:flex absolute top-1/2 -translate-y-1/2 -left-4 z-40 w-4 h-14 bg-white border border-slate-200 hover:border-emerald-500 rounded-l-xl items-center justify-center text-slate-500 hover:text-emerald-600 shadow-md transition-colors focus:outline-none">
            <i class="fa-solid text-[9px]" :class="desktopSidebarOpen ? 'fa-chevron-right' : 'fa-chevron-left'"></i>
        </button>
        
        <!-- Search & Filter Header -->
        <div class="p-4 border-b border-slate-200 bg-theme-first/80 space-y-3">
            <h2 class="font-display font-bold text-lg text-slate-800">ابحث عن عقارات في العراق</h2>
            
            <div class="relative">
                <input type="text" x-model="search" @input.debounce.500ms="shouldFitBounds = true; fetchPropertiesInViewport()" 
                       placeholder="ابحث بالاسم، التفاصيل..." 
                       class="w-full bg-white border border-slate-200 rounded-xl pr-10 pl-4 py-2.5 text-sm text-slate-800 placeholder-slate-450 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors text-right shadow-sm">
                <i class="fa-solid fa-magnifying-glass absolute right-3.5 top-3.5 text-slate-400 text-sm"></i>
            </div>

            <!-- Fast filters grid -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <select x-model="propertyType" @change="shouldFitBounds = true; fetchPropertiesInViewport()" 
                            class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm">
                        <option value="">كل أنواع العقارات</option>
                        <option value="house">بيت</option>
                        <option value="apartment">شقة</option>
                        <option value="land">أرض</option>
                        <option value="commercial">تجاري</option>
                    </select>
                </div>
                <div>
                    <select x-model="status" @change="shouldFitBounds = true; fetchPropertiesInViewport()" 
                            class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm">
                        <option value="">كل المعاملات</option>
                        <option value="sale">للبيع</option>
                        <option value="rent">للإيجار</option>
                    </select>
                </div>
            </div>

            <!-- Governorate filter -->
            <div>
                <select x-model="governorate" @change="shouldFitBounds = true; fetchPropertiesInViewport()" 
                        class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-750 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm text-right">
                    <option value="">كل المحافظات</option>
                    <option value="بغداد">بغداد</option>
                    <option value="نينوى">نينوى</option>
                    <option value="البصرة">البصرة</option>
                    <option value="صلاح الدين">صلاح الدين</option>
                    <option value="دهوك">دهوك</option>
                    <option value="أربيل">أربيل</option>
                    <option value="السليمانية">السليمانية</option>
                    <option value="كركوك">كركوك</option>
                    <option value="ديالى">ديالى</option>
                    <option value="الأنبار">الأنبار</option>
                    <option value="بابل">بابل</option>
                    <option value="كربلاء">كربلاء</option>
                    <option value="النجف">النجف</option>
                    <option value="واسط">واسط</option>
                    <option value="القادسية">القادسية</option>
                    <option value="ميسان">ميسان</option>
                    <option value="ذي قار">ذي قار</option>
                    <option value="المثنى">المثنى</option>
                </select>
            </div>

            <!-- Price filters row -->
            <div class="flex items-center space-x-2 space-x-reverse pt-1">
                <input type="text" :value="formatPriceInput(priceMin)" 
                       @input="priceMin = $event.target.value.replace(/[^\d]/g, '')" 
                       @input.debounce.500ms="shouldFitBounds = true; fetchPropertiesInViewport()" 
                       placeholder="الحد الأدنى د.ع" 
                       class="w-1/2 bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-700 focus:outline-none focus:border-[#247291] focus:ring-1 focus:ring-[#247291] text-right shadow-sm">
                <span class="text-slate-400 text-xs">-</span>
                <input type="text" :value="formatPriceInput(priceMax)" 
                       @input="priceMax = $event.target.value.replace(/[^\d]/g, '')" 
                       @input.debounce.500ms="shouldFitBounds = true; fetchPropertiesInViewport()" 
                       placeholder="الحد الأقصى د.ع" 
                       class="w-1/2 bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-700 focus:outline-none focus:border-[#247291] focus:ring-1 focus:ring-[#247291] text-right shadow-sm">
            </div>
        </div>

        <!-- Sidebar Listings Content -->
        <div class="flex-1 overflow-y-auto p-4 space-y-4">
            <div class="flex justify-between items-center text-xs text-slate-500 mb-2">
                <span x-text="properties.length + ' إعلانات معروضة في النطاق'"></span>
                <button @click="resetFilters()" class="text-emerald-600 font-bold hover:underline">إعادة ضبط التصفية</button>
            </div>

            <!-- Loading overlay -->
            <div x-show="loading" class="flex flex-col items-center justify-center py-20 space-y-3">
                <i class="fa-solid fa-spinner animate-spin text-emerald-500 text-3xl"></i>
                <span class="text-xs text-slate-500 font-bold">جاري تحميل البيانات الجغرافية...</span>
            </div>

            <!-- Empty State -->
            <div x-show="!loading && properties.length === 0" class="text-center py-20 first-color rounded-2xl p-6 border border-slate-100 shadow-inner">
                <i class="fa-solid fa-map-location text-slate-400 text-4xl mb-3"></i>
                <p class="text-sm font-semibold text-slate-700">لا توجد عقارات معروضة هنا</p>
                <p class="text-xs text-slate-400 mt-1">حرك الخريطة أو قم بالتصغير لرؤية عقارات أخرى في العراق.</p>
            </div>

            <!-- Property Card Iteration -->
            <div x-show="!loading" class="space-y-3">
                <template x-for="prop in properties" :key="prop.id">
                    <div @click="zoomToProperty(prop)" 
                          class="group p-4 bg-white hover:bg-slate-50 border border-slate-100 hover:border-emerald-300 rounded-2xl cursor-pointer transition-all duration-200 shadow-sm hover:shadow-md flex space-x-4 space-x-reverse">
                        
                        <!-- Mini visual preview -->
                        <div class="w-16 h-16 rounded-xl flex flex-col items-center justify-center shrink-0 border border-slate-100 relative overflow-hidden bg-slate-50">
                            <template x-if="prop.images && prop.images.length > 0">
                                <img :src="prop.images[0]" class="w-full h-full object-cover" alt="Property thumbnail">
                            </template>
                            <template x-if="!prop.images || prop.images.length === 0">
                                <div :class="{
                                        'text-emerald-600': prop.property_type === 'house',
                                        'text-sky-600': prop.property_type === 'apartment',
                                        'text-amber-600': prop.property_type === 'land',
                                        'text-purple-600': prop.property_type === 'commercial',
                                     }"
                                     class="w-full h-full flex flex-col items-center justify-center">
                                    <template x-if="prop.property_type === 'house'"><i class="fa-solid fa-house text-2xl"></i></template>
                                    <template x-if="prop.property_type === 'apartment'"><i class="fa-solid fa-building text-2xl"></i></template>
                                    <template x-if="prop.property_type === 'land'"><i class="fa-solid fa-mountain text-2xl"></i></template>
                                    <template x-if="prop.property_type === 'commercial'"><i class="fa-solid fa-store text-2xl"></i></template>
                                </div>
                            </template>
                            
                            <span :class="prop.status === 'sale' ? 'bg-emerald-500 text-white shadow-sm' : 'bg-amber-500 text-white shadow-sm'"
                                  class="absolute -top-1.5 -right-1.5 text-[8px] font-extrabold px-1.5 py-0.5 rounded-full"
                                  x-text="prop.status === 'sale' ? 'بيع' : 'إيجار'">
                            </span>
                        </div>

                        <!-- Card Details -->
                        <div class="flex-1 min-w-0 flex flex-col justify-between">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-sm group-hover:text-emerald-600 transition-colors truncate text-right" x-text="prop.title"></h3>
                                <p class="text-xs text-slate-500 line-clamp-1 mt-0.5 text-right" x-text="prop.description"></p>
                            </div>
                            <div class="flex justify-between items-end mt-2">
                                <span class="text-emerald-600 font-display font-extrabold text-sm" x-text="formatPriceArabic(prop.price)"></span>
                                <span class="text-[10px] text-slate-400 flex items-center font-semibold">
                                    <i class="fa-solid fa-clock ml-1"></i> <span x-text="prop.created_at_human"></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </aside>

    <!-- Interactive Map Section -->
    <div class="flex-1 h-full relative flex flex-col">
        <!-- Leaflet map container -->
        <div id="map" class="w-full flex-1 z-10"></div>

        <!-- Floating Geolocation Survey Panel -->
        <div x-show="surveyResults && !surveyCollapsed" 
             x-transition
             class="absolute top-4 left-4 z-20 w-80 max-w-[calc(100vw-2rem)] bg-white/95 backdrop-blur-md border border-slate-200 rounded-2xl shadow-2xl p-4 flex flex-col max-h-[70%] text-right font-sans animate-fade-in" x-cloak>
            <div class="flex justify-between items-center border-b border-slate-100 pb-2 mb-3">
                <div class="flex items-center space-x-1">
                    <button @click="clearSurvey()" title="إلغاء الاستطلاع" class="text-slate-400 hover:text-red-500 transition-colors p-1.5 bg-slate-50 hover:bg-red-50 rounded-lg">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                    <button @click="surveyCollapsed = true" title="اخفاء القائمة" class="text-[10px] font-bold text-slate-550 hover:text-slate-800 transition-colors px-2 py-1 bg-slate-50 hover:bg-slate-100 rounded-lg flex items-center space-x-1 space-x-reverse border border-slate-200">
                        <i class="fa-solid fa-eye-slash"></i>
                        <span>اخفاء القائمة</span>
                    </button>
                </div>
                <div class="flex items-center space-x-2 space-x-reverse">
                    <i class="fa-solid fa-map-location-dot text-emerald-600 text-lg animate-pulse"></i>
                    <h3 class="font-extrabold text-sm text-slate-800">استطلاع المنطقة (١ كم)</h3>
                </div>
            </div>

            <!-- Tabs -->
            <div class="grid grid-cols-4 gap-1 bg-slate-100 p-1 rounded-xl mb-3 text-center text-[10px] font-bold">
                <button @click="activeSurveyTab = 'schools'" :class="activeSurveyTab === 'schools' ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-500'" class="py-1.5 rounded-lg transition-all">
                    مدارس (<span x-text="surveyResults?.schools?.length || 0"></span>)
                </button>
                <button @click="activeSurveyTab = 'markets'" :class="activeSurveyTab === 'markets' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500'" class="py-1.5 rounded-lg transition-all">
                    أسواق (<span x-text="surveyResults?.markets?.length || 0"></span>)
                </button>
                <button @click="activeSurveyTab = 'mosques'" :class="activeSurveyTab === 'mosques' ? 'bg-white text-amber-600 shadow-sm' : 'text-slate-500'" class="py-1.5 rounded-lg transition-all">
                    مساجد (<span x-text="surveyResults?.mosques?.length || 0"></span>)
                </button>
                <button @click="activeSurveyTab = 'hospitals'" :class="activeSurveyTab === 'hospitals' ? 'bg-white text-rose-600 shadow-sm' : 'text-slate-500'" class="py-1.5 rounded-lg transition-all">
                    مستشفيات (<span x-text="surveyResults?.hospitals?.length || 0"></span>)
                </button>
            </div>

            <!-- List items -->
            <div class="flex-1 overflow-y-auto space-y-2 scrollbar-thin">
                <template x-for="item in (surveyResults ? surveyResults[activeSurveyTab] : [])" :key="item.id">
                    <div @click="focusSurveyItem(item)" 
                         :class="{
                            'hover:bg-emerald-50/50 hover:border-emerald-250': activeSurveyTab === 'schools',
                            'hover:bg-blue-50/50 hover:border-blue-250': activeSurveyTab === 'markets',
                            'hover:bg-amber-50/50 hover:border-amber-250': activeSurveyTab === 'mosques',
                            'hover:bg-rose-50/50 hover:border-rose-250': activeSurveyTab === 'hospitals',
                         }"
                         class="p-2.5 bg-slate-50 border border-slate-150 rounded-xl cursor-pointer transition-all flex justify-between items-center">
                        <span :class="{
                                'bg-emerald-100 text-emerald-700': activeSurveyTab === 'schools',
                                'bg-blue-100 text-blue-700': activeSurveyTab === 'markets',
                                'bg-amber-100 text-amber-700': activeSurveyTab === 'mosques',
                                'bg-rose-100 text-rose-700': activeSurveyTab === 'hospitals',
                              }" 
                              class="text-[10px] px-2 py-0.5 rounded-full font-bold" 
                              x-text="item.distanceText"></span>
                        <div class="text-right min-w-0 flex-1 ml-2">
                            <span class="text-xs font-bold text-slate-800 block truncate" x-text="item.name"></span>
                            <span class="text-[9px] text-slate-450" x-text="item.typeText"></span>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div x-show="surveyResults && surveyResults[activeSurveyTab]?.length === 0" class="text-center py-6 text-slate-400 text-xs">
                    <i class="fa-solid fa-circle-info mb-1.5 text-slate-350 text-lg"></i>
                    <p>لا يوجد في النطاق المحدد (١ كم)</p>
                </div>
            </div>
        </div>

        <!-- Minimized Floating Geolocation Survey Pill -->
        <button x-show="surveyResults && surveyCollapsed"
                @click="surveyCollapsed = false"
                class="absolute top-4 left-4 z-20 px-3 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-lg flex items-center space-x-2 space-x-reverse font-sans text-xs transition-all animate-fade-in" x-cloak>
            <i class="fa-solid fa-map-location-dot text-sm animate-pulse"></i>
            <span>نتائج الاستطلاع (١ كم)</span>
            <i class="fa-solid fa-chevron-down text-[10px] mr-1"></i>
        </button>

        <!-- Survey Loading Overlay -->
        <div x-show="surveyLoading" 
             class="absolute inset-0 bg-slate-950/20 backdrop-blur-[1px] z-20 flex flex-col items-center justify-center space-y-3 font-sans" x-cloak>
            <div class="bg-white px-5 py-4 rounded-2xl shadow-2xl border border-slate-100 flex items-center space-x-3 space-x-reverse">
                <i class="fa-solid fa-spinner animate-spin text-emerald-600 text-2xl"></i>
                <div class="text-right">
                    <h4 class="font-extrabold text-sm text-slate-800">جاري استطلاع المنطقة...</h4>
                    <p class="text-[10px] text-slate-400 mt-0.5">البحث عن المدارس والأسواق والمساجد والمستشفيات</p>
                </div>
            </div>
        </div>

        <!-- Floating UI controls overlay for Mobile -->
        <div class="absolute top-4 right-4 z-20 flex flex-col space-y-2 md:hidden">
            <!-- Mobile Filters Toggle -->
            <button @click="filterModalOpen = true" 
                    class="w-10 h-10 rounded-xl bg-white/90 text-slate-700 flex items-center justify-center border border-slate-200 shadow-lg focus:outline-none hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-filter text-sm text-emerald-500"></i>
            </button>
            <!-- Mobile Layer Switcher -->
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button @click="open = !open" 
                        class="w-10 h-10 rounded-xl bg-white/90 text-slate-700 flex items-center justify-center border border-slate-200 shadow-lg focus:outline-none hover:bg-slate-50 transition-colors">
                    <i class="fa-solid fa-layer-group text-sm text-emerald-500"></i>
                </button>
                <div x-show="open" 
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95 translate-y-2"
                     x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="transform opacity-0 scale-95 translate-y-2"
                     class="absolute top-full right-0 mt-2 w-40 bg-white border border-slate-200 rounded-2xl p-2 shadow-2xl space-y-1 z-30">
                    <button @click="activeLayer = 'osm'; setMapLayer('osm'); open = false;"
                            :class="activeLayer === 'osm' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'text-slate-600 hover:bg-slate-50 border-transparent'"
                            class="w-full text-right px-2.5 py-1.5 rounded-xl text-[11px] font-semibold border flex items-center space-x-2 space-x-reverse transition-all">
                        <i class="fa-solid fa-road"></i>
                        <span>خريطة الشارع</span>
                    </button>
                    <button @click="activeLayer = 'satellite'; setMapLayer('satellite'); open = false;"
                            :class="activeLayer === 'satellite' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'text-slate-600 hover:bg-slate-50 border-transparent'"
                            class="w-full text-right px-2.5 py-1.5 rounded-xl text-[11px] font-semibold border flex items-center space-x-2 space-x-reverse transition-all">
                        <i class="fa-solid fa-globe"></i>
                        <span>الأقمار الصناعية</span>
                    </button>
                </div>
            </div>
            <!-- Geolocation trigger -->
            <button @click="locateUser()" 
                    class="w-10 h-10 rounded-xl bg-white/90 text-slate-700 flex items-center justify-center border border-slate-200 shadow-lg focus:outline-none hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-location-crosshairs text-sm text-emerald-500"></i>
            </button>
        </div>

        <!-- Desktop Map Floating tools -->
        <div class="hidden md:flex absolute bottom-6 right-6 z-20 items-center space-x-2 space-x-reverse">
            <!-- Custom Layer Switcher -->
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button @click="open = !open" 
                        class="px-4 py-2.5 rounded-xl bg-white/90 text-slate-700 border border-slate-200 hover:border-emerald-500/50 flex items-center space-x-2 space-x-reverse shadow-lg hover:bg-slate-50 transition-all">
                    <i class="fa-solid fa-layer-group text-emerald-500"></i>
                    <span class="text-xs font-bold">طبقات الخريطة</span>
                </button>
                <div x-show="open" 
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95 -translate-y-2"
                     x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="transform opacity-0 scale-95 -translate-y-2"
                     class="absolute bottom-full left-0 mb-2 w-48 bg-white border border-slate-200 rounded-2xl p-2 shadow-2xl space-y-1 z-30">
                    <button @click="activeLayer = 'osm'; setMapLayer('osm'); open = false;"
                            :class="activeLayer === 'osm' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'text-slate-600 hover:bg-slate-50 border-transparent'"
                            class="w-full text-right px-3 py-2 rounded-xl text-xs font-semibold border flex items-center space-x-2 space-x-reverse transition-all">
                        <i class="fa-solid fa-road"></i>
                        <span>خريطة الشارع (OSM)</span>
                    </button>
                    <button @click="activeLayer = 'satellite'; setMapLayer('satellite'); open = false;"
                            :class="activeLayer === 'satellite' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'text-slate-600 hover:bg-slate-50 border-transparent'"
                            class="w-full text-right px-3 py-2 rounded-xl text-xs font-semibold border flex items-center space-x-2 space-x-reverse transition-all">
                        <i class="fa-solid fa-globe"></i>
                        <span>الأقمار الصناعية</span>
                    </button>
                </div>
            </div>

            <button @click="locateUser()" 
                       class="px-4 py-2.5 rounded-xl bg-white/90 text-slate-700 border border-slate-200 hover:border-emerald-500/50 flex items-center space-x-2 space-x-reverse shadow-lg hover:bg-slate-50 transition-all">
                <i class="fa-solid fa-location-crosshairs text-emerald-500"></i>
                <span class="text-xs font-bold">تحديد موقعي</span>
            </button>
        </div>

        <!-- Mobile Bottom Drawer Sheet -->
        <div class="md:hidden absolute bottom-[68px] left-0 right-0 z-30 transition-all duration-300 flex flex-col bg-white border-t border-slate-200 rounded-t-3xl shadow-2xl"
             :style="sheetState === 'collapsed' ? 'height: 75px;' : (sheetState === 'half' ? 'height: 50%;' : 'height: 85%;')">
            
            <div @click="toggleSheet()" class="w-full py-3 flex flex-col items-center justify-center cursor-pointer shrink-0">
                <div class="w-12 h-1.5 rounded-full bg-slate-200"></div>
                <div class="flex justify-between w-full px-6 items-center mt-1">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider" 
                          x-text="properties.length + ' عقاراً قريباً'"></span>
                    <i class="fa-solid" :class="sheetState === 'collapsed' ? 'fa-chevron-up text-emerald-500' : 'fa-chevron-down text-slate-400'"></i>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-4 pb-6 space-y-4">
                <div x-show="properties.length === 0" class="text-center py-10">
                    <p class="text-sm font-semibold text-slate-600">لا توجد عقارات معروضة</p>
                    <p class="text-xs text-slate-400 mt-1">قم بتكبير أو تصغير أو تحريك الخريطة للبحث في مدن عراقية أخرى.</p>
                </div>
                
                <div class="space-y-3">
                    <template x-for="prop in properties" :key="prop.id">
                        <div @click="zoomToProperty(prop); collapseSheet();" 
                             class="group p-4 bg-slate-50 border border-slate-100 rounded-2xl flex space-x-4 space-x-reverse hover:bg-slate-100/55 transition-colors">
                            <div class="w-14 h-14 rounded-xl flex items-center justify-center shrink-0 border border-slate-200 relative overflow-hidden bg-slate-50">
                                <template x-if="prop.images && prop.images.length > 0">
                                    <img :src="prop.images[0]" class="w-full h-full object-cover" alt="Property thumbnail">
                                </template>
                                <template x-if="!prop.images || prop.images.length === 0">
                                    <div :class="{
                                            'text-emerald-600': prop.property_type === 'house',
                                            'text-sky-600': prop.property_type === 'apartment',
                                            'text-amber-600': prop.property_type === 'land',
                                            'text-purple-600': prop.property_type === 'commercial',
                                         }"
                                         class="w-full h-full flex flex-col items-center justify-center">
                                        <template x-if="prop.property_type === 'house'"><i class="fa-solid fa-house text-xl"></i></template>
                                        <template x-if="prop.property_type === 'apartment'"><i class="fa-solid fa-building text-xl"></i></template>
                                        <template x-if="prop.property_type === 'land'"><i class="fa-solid fa-mountain text-xl"></i></template>
                                        <template x-if="prop.property_type === 'commercial'"><i class="fa-solid fa-store text-xl"></i></template>
                                    </div>
                                </template>
                                <span :class="prop.status === 'sale' ? 'bg-emerald-500 text-white shadow-sm' : 'bg-amber-500 text-white shadow-sm'"
                                      class="absolute -top-1 -right-1 text-[7px] font-extrabold px-1 rounded"
                                      x-text="prop.status === 'sale' ? 'بيع' : 'إيجار'">
                                </span>
                            </div>
                            <div class="flex-1 min-w-0 flex flex-col justify-between">
                                <div>
                                    <h3 class="font-bold text-slate-800 text-xs truncate text-right" x-text="prop.title"></h3>
                                    <p class="text-[10px] text-slate-500 line-clamp-1 mt-0.5 text-right" x-text="prop.description"></p>
                                </div>
                                <div class="flex justify-between items-end mt-1">
                                    <span class="text-emerald-600 font-display font-extrabold text-xs" x-text="formatPriceArabic(prop.price)"></span>
                                    <span class="text-[9px] text-slate-400" x-text="prop.created_at_human"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Details Dialog Modal -->
        <div x-show="selectedProperty" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div @click="selectedProperty = null" class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm"></div>

                <div x-show="selectedProperty" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="transform opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="transform opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="transform opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="transform opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block w-full max-w-lg overflow-hidden text-right align-middle transition-all bg-white border border-slate-200 rounded-3xl shadow-2xl relative z-50">
                    
                    <template x-if="selectedProperty?.images && selectedProperty?.images.length > 0">
                        <div class="relative h-64 bg-slate-900 overflow-hidden border-b border-slate-100">
                            <img :src="activeImage" class="w-full h-full object-cover transition-all duration-300" alt="Property Image">
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-transparent to-slate-950/30 pointer-events-none"></div>
                            
                            <div class="absolute top-4 right-4 flex items-center space-x-2 space-x-reverse">
                                <span :class="selectedProperty?.status === 'sale' ? 'bg-emerald-500 text-white shadow-md' : 'bg-amber-500 text-white shadow-md'"
                                      class="text-[10px] font-extrabold uppercase tracking-widest px-3 py-1 rounded-full"
                                      x-text="selectedProperty?.status === 'sale' ? 'للبيع' : 'للإيجار'">
                                </span>
                            </div>

                            <template x-if="selectedProperty?.images.length > 1">
                                <div>
                                    <button @click="let idx = selectedProperty.images.indexOf(activeImage); activeImage = selectedProperty.images[idx === 0 ? selectedProperty.images.length - 1 : idx - 1]"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/25 hover:bg-white/45 text-white flex items-center justify-center transition-all focus:outline-none shadow-md">
                                        <i class="fa-solid fa-chevron-right text-xs"></i>
                                    </button>
                                    <button @click="let idx = selectedProperty.images.indexOf(activeImage); activeImage = selectedProperty.images[idx === selectedProperty.images.length - 1 ? 0 : idx + 1]"
                                            class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/25 hover:bg-white/45 text-white flex items-center justify-center transition-all focus:outline-none shadow-md">
                                        <i class="fa-solid fa-chevron-left text-xs"></i>
                                    </button>
                                </div>
                            </template>

                            <div class="absolute bottom-4 right-4 left-4 flex justify-between items-end">
                                <h2 class="font-display font-extrabold text-lg text-white drop-shadow-md truncate max-w-[70%] text-right" x-text="selectedProperty?.title"></h2>
                                <span class="bg-black/55 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full shadow-sm"
                                      x-text="(selectedProperty.images.indexOf(activeImage) + 1) + ' / ' + selectedProperty.images.length">
                                </span>
                            </div>
                        </div>
                    </template>

                    <template x-if="!selectedProperty?.images || selectedProperty?.images.length === 0">
                        <div class="h-44 bg-gradient-to-r from-emerald-50 to-teal-50 relative flex items-center justify-center p-6 border-b border-slate-100 overflow-hidden">
                            <div class="absolute -right-10 -bottom-10 opacity-5">
                                <i class="fa-solid fa-map-location-dot text-[200px] text-slate-800"></i>
                            </div>
                            <div class="text-center">
                                <span :class="selectedProperty?.status === 'sale' ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-white'"
                                      class="inline-block text-[10px] font-extrabold uppercase tracking-widest px-3 py-1 rounded-full mb-3 shadow-sm"
                                      x-text="selectedProperty?.status === 'sale' ? 'للبيع' : 'للإيجار'">
                                </span>
                                <h2 class="font-display font-extrabold text-2xl text-slate-800 tracking-tight" x-text="selectedProperty?.title"></h2>
                            </div>
                        </div>
                    </template>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-5">
                        <template x-if="selectedProperty?.images && selectedProperty?.images.length > 1">
                            <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-thin">
                                <template x-for="(img, idx) in selectedProperty.images" :key="idx">
                                    <button @click="activeImage = img" 
                                            :class="activeImage === img ? 'border-emerald-500 ring-2 ring-emerald-500/20 scale-95' : 'border-slate-200 hover:border-slate-350'"
                                            class="w-14 h-14 rounded-lg overflow-hidden border-2 shrink-0 transition-all focus:outline-none">
                                        <img :src="img" class="w-full h-full object-cover" />
                                    </button>
                                </template>
                            </div>
                        </template>

                        <div class="grid grid-cols-3 gap-2 bg-slate-50 p-4 rounded-2xl border border-slate-200 text-center">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-450 block">السعر</span>
                                <span class="text-emerald-600 font-display font-extrabold text-sm block mt-0.5 whitespace-nowrap" x-text="selectedProperty ? formatPriceArabic(selectedProperty.price) : ''"></span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-450 block">المحافظة</span>
                                <span class="text-slate-750 text-xs font-extrabold block mt-0.5" x-text="selectedProperty?.governorate || 'غير محدد'"></span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-450 block">نوع العقار</span>
                                <span class="text-slate-750 text-xs font-bold capitalize flex items-center justify-center mt-0.5 whitespace-nowrap">
                                    <template x-if="selectedProperty?.property_type === 'house'"><i class="fa-solid fa-house ml-1 text-emerald-500"></i></template>
                                    <template x-if="selectedProperty?.property_type === 'apartment'"><i class="fa-solid fa-building ml-1 text-sky-500"></i></template>
                                    <template x-if="selectedProperty?.property_type === 'land'"><i class="fa-solid fa-mountain ml-1 text-amber-500"></i></template>
                                    <template x-if="selectedProperty?.property_type === 'commercial'"><i class="fa-solid fa-store ml-1 text-purple-500"></i></template>
                                    <span x-text="selectedProperty?.property_type === 'house' ? 'بيت' : (selectedProperty?.property_type === 'apartment' ? 'شقة' : (selectedProperty?.property_type === 'land' ? 'أرض' : 'تجاري'))"></span>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <h4 class="text-xs uppercase font-extrabold text-slate-500 tracking-wider text-right">الوصف والتفاصيل</h4>
                            <p class="text-slate-650 text-sm leading-relaxed whitespace-pre-wrap text-right font-semibold" x-text="selectedProperty?.description"></p>
                        </div>

                        <div class="flex items-center space-x-3 space-x-reverse p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <img class="w-10 h-10 rounded-full object-cover border border-slate-200" :src="selectedProperty?.user_avatar" alt="">
                            <div>
                                <h5 class="text-xs font-bold text-slate-800 text-right" x-text="selectedProperty?.user_name"></h5>
                                <span class="text-[9px] uppercase tracking-wider text-slate-450 text-right block" x-text="(selectedProperty?.user_type === 'broker' ? 'دلال / مكتب' : (selectedProperty?.user_type === 'company' ? 'شركة عقارية' : 'شخصي')) + ' (الناشر)'"></span>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-5">
                            <div class="mb-4">
                                <button @click="window.surveyArea(selectedProperty.id)" 
                                        class="w-full py-3.5 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm flex items-center justify-center space-x-2 space-x-reverse transition-all shadow-lg shadow-blue-500/10">
                                    <i class="fa-solid fa-map-location-dot"></i>
                                    <span>اسأل عن المنطقة (المدارس والخدمات)</span>
                                </button>
                            </div>
                            <template x-if="isLoggedIn">
                                <div class="space-y-3">
                                    <h4 class="text-xs uppercase font-extrabold text-emerald-600 tracking-wider text-right">
                                        <i class="fa-solid fa-phone ml-1"></i> الاتصال بالبائع (رقم الهاتف)
                                    </h4>
                                    <a :href="'tel:' + selectedProperty?.phone_number" 
                                       class="w-full py-3.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm flex items-center justify-center space-x-2 transition-all shadow-lg shadow-emerald-500/10">
                                        <i class="fa-solid fa-phone"></i>
                                        <span x-text="selectedProperty?.phone_number"></span>
                                    </a>
                                </div>
                            </template>
                            <template x-if="!isLoggedIn">
                                <div class="bg-slate-50 p-4 rounded-2xl border border-dashed border-slate-350 text-center space-y-4">
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-700">تفاصيل الاتصال مغلقة</h4>
                                        <p class="text-[10px] text-slate-450 mt-1">يرجى تسجيل الدخول مجاناً لعرض رقم هاتف البائع.</p>
                                    </div>
                                    <a href="login.php" 
                                       class="inline-flex py-2.5 px-5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-bold text-xs items-center space-x-2 transition-all shadow-md">
                                        <i class="fa-solid fa-sign-in-alt"></i>
                                        <span>تسجيل دخول سريع ومجاني</span>
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>

                    <button @click="selectedProperty = null" class="absolute top-4 left-4 z-[100] text-slate-600 hover:text-red-500 transition-colors bg-white shadow-lg p-2 w-10 h-10 flex items-center justify-center rounded-full border-2 border-slate-200">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Filters Dialog Modal -->
    <div x-show="filterModalOpen" class="fixed inset-0 z-50 overflow-y-auto md:hidden" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="filterModalOpen = false" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
            
            <div x-show="filterModalOpen"
                 x-transition
                 class="w-full max-w-md bg-white border border-slate-200 rounded-3xl p-6 relative z-50 shadow-2xl flex flex-col space-y-4 text-right">
                
                <div class="flex justify-between items-center border-b border-slate-150 pb-3">
                    <button @click="filterModalOpen = false" class="text-slate-450 hover:text-slate-700 bg-slate-100 p-2 rounded-full border border-slate-200">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                    <h3 class="font-display font-extrabold text-lg text-slate-800">تصفية العقارات</h3>
                </div>

                <!-- Search Input -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-500">بحث بالاسم أو التفاصيل</label>
                    <div class="relative">
                        <input type="text" x-model="search" @input.debounce.500ms="shouldFitBounds = true; fetchPropertiesInViewport()" 
                               placeholder="ابحث بالاسم، التفاصيل..." 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl pr-10 pl-4 py-2.5 text-sm text-slate-800 placeholder-slate-450 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors text-right shadow-inner">
                        <i class="fa-solid fa-magnifying-glass absolute right-3.5 top-3.5 text-slate-400 text-sm"></i>
                    </div>
                </div>

                <!-- Fast Filters Grid -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500">نوع العقار</label>
                        <select x-model="propertyType" @change="shouldFitBounds = true; fetchPropertiesInViewport()" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm text-right">
                            <option value="">كل أنواع العقارات</option>
                            <option value="house">بيت</option>
                            <option value="apartment">شقة</option>
                            <option value="land">أرض</option>
                            <option value="commercial">تجاري</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500">نوع المعاملة</label>
                        <select x-model="status" @change="shouldFitBounds = true; fetchPropertiesInViewport()" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm text-right">
                            <option value="">كل المعاملات</option>
                            <option value="sale">للبيع</option>
                            <option value="rent">للإيجار</option>
                        </select>
                    </div>
                </div>

                <!-- Governorate Filter -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-500">المحافظة</label>
                    <select x-model="governorate" @change="shouldFitBounds = true; fetchPropertiesInViewport()" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm text-right">
                        <option value="">كل المحافظات</option>
                        <option value="بغداد">بغداد</option>
                        <option value="نينوى">نينوى</option>
                        <option value="البصرة">البصرة</option>
                        <option value="صلاح الدين">صلاح الدين</option>
                        <option value="دهوك">دهوك</option>
                        <option value="أربيل">أربيل</option>
                        <option value="السليمانية">السليمانية</option>
                        <option value="كركوك">كركوك</option>
                        <option value="ديالى">ديالى</option>
                        <option value="الأنبار">الأنبار</option>
                        <option value="بابل">بابل</option>
                        <option value="كربلاء">كربلاء</option>
                        <option value="النجف">النجف</option>
                        <option value="واسط">واسط</option>
                        <option value="القادسية">القادسية</option>
                        <option value="ميسان">ميسان</option>
                        <option value="ذي قار">ذي قار</option>
                        <option value="المثنى">المثنى</option>
                    </select>
                </div>

                <!-- Price Filters Row -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-500">نطاق السعر (د.ع)</label>
                    <div class="flex items-center space-x-2 space-x-reverse">
                        <input type="text" :value="formatPriceInput(priceMin)" 
                               @input="priceMin = $event.target.value.replace(/[^\d]/g, '')" 
                               @input.debounce.500ms="shouldFitBounds = true; fetchPropertiesInViewport()" 
                               placeholder="الحد الأدنى د.ع" 
                               class="w-1/2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:border-[#247291] focus:ring-1 focus:ring-[#247291] text-right shadow-inner">
                        <span class="text-slate-400 text-xs">-</span>
                        <input type="text" :value="formatPriceInput(priceMax)" 
                               @input="priceMax = $event.target.value.replace(/[^\d]/g, '')" 
                               @input.debounce.500ms="shouldFitBounds = true; fetchPropertiesInViewport()" 
                               placeholder="الحد الأقصى د.ع" 
                               class="w-1/2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:border-[#247291] focus:ring-1 focus:ring-[#247291] text-right shadow-inner">
                    </div>
                </div>

                <!-- Reset and Show buttons -->
                <div class="pt-4 border-t border-slate-150 flex gap-2">
                    <button @click="resetFilters(); filterModalOpen = false;" 
                            class="w-1/2 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                        إعادة تعيين
                    </button>
                    <button @click="filterModalOpen = false" 
                            class="w-1/2 py-2.5 rounded-xl bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700 transition-colors shadow-md">
                        عرض النتائج (<span x-text="properties.length"></span>)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Abu Abd Chat Modal -->
    <div x-show="chatOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="chatOpen = false" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
            
            <div class="w-full max-w-md bg-white border border-slate-200 rounded-3xl p-6 relative z-50 shadow-2xl flex flex-col h-[500px]">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                    <div class="flex items-center space-x-2 space-x-reverse">
                        <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-md border-2 border-emerald-500 overflow-hidden">
                            <img src="images/facelogo.png" class="w-full h-full object-contain bg-white" alt="Abu Abd">
                        </div>
                        <div>
                            <h3 class="font-display font-extrabold text-lg text-slate-800">ابو عبد <span class="text-[10px] text-emerald-500 bg-emerald-50 px-2 py-0.5 rounded-full ml-1 font-bold">متاح</span></h3>
                            <p class="text-[10px] text-slate-400">مساعدك العقاري الذكي</p>
                        </div>
                    </div>
                    <button @click="chatOpen = false" class="text-slate-400 hover:text-slate-700 bg-slate-100 p-2 rounded-full border border-slate-200"><i class="fa-solid fa-xmark text-sm"></i></button>
                </div>

                <!-- Chat History -->
                <div id="chat-history-box" class="flex-1 overflow-y-auto pr-2 space-y-4 mb-4 scrollbar-thin">
                    <template x-for="(msg, index) in chatHistory" :key="index">
                        <div :class="msg.role === 'user' ? 'flex justify-start' : 'flex justify-end'">
                            <div :class="msg.role === 'user' ? 'bg-emerald-600 text-white rounded-br-none' : 'bg-slate-100 text-slate-800 rounded-bl-none'"
                                 class="max-w-[85%] p-3 rounded-2xl text-sm font-semibold shadow-sm leading-relaxed whitespace-pre-wrap text-right">
                                <span x-text="msg.content"></span>
                            </div>
                        </div>
                    </template>
                    <div x-show="chatLoading" class="flex justify-end">
                        <div class="bg-slate-100 text-slate-500 p-3 rounded-2xl rounded-bl-none text-xs flex space-x-1 space-x-reverse items-center shadow-sm">
                            <i class="fa-solid fa-circle text-[8px] animate-bounce"></i>
                            <i class="fa-solid fa-circle text-[8px] animate-bounce" style="animation-delay: 100ms;"></i>
                            <i class="fa-solid fa-circle text-[8px] animate-bounce" style="animation-delay: 200ms;"></i>
                        </div>
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="relative mt-auto border-t border-slate-100 pt-4 shrink-0">
                    <input type="text" x-model="chatMessage" @keydown.enter="sendChatMessage()"
                           placeholder="اسأل ابو عبد (مثال: اسعار البيوت في بغداد)" 
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pr-12 pl-4 py-3 text-sm text-slate-800 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-right shadow-inner">
                    <button @click="sendChatMessage()" :disabled="chatLoading"
                            class="absolute left-2 top-6 w-9 h-9 rounded-lg bg-amber-500 hover:bg-amber-600 text-white flex items-center justify-center transition-colors shadow-md disabled:opacity-50">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let map;
    let osmLayer;
    let satelliteLayer;
    let markersCluster;
    let polygonsLayerGroup;
    let surveyLayerGroup;

    function getAlpineState() {
        return Alpine.$data(document.getElementById('home-root'));
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Map centered on Iraq/Baghdad
        map = L.map('map', {
            zoomControl: false
        }).setView([33.3152, 44.3661], 12);
        window.map = map;

        // Custom Zoom Control at bottom-left
        L.control.zoom({
            position: 'bottomleft'
        }).addTo(map);

        // Map Layers
        osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        });

        satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri',
            maxZoom: 19
        });

        osmLayer.addTo(map);

        // Cluster and polygon group layers
        markersCluster = L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 40
        });
        map.addLayer(markersCluster);

        polygonsLayerGroup = L.layerGroup();
        map.addLayer(polygonsLayerGroup);

        surveyLayerGroup = L.layerGroup().addTo(map);

        map.on('moveend', function () {
            fetchPropertiesInViewport();
        });

        // Initial Load
        fetchPropertiesInViewport();
    });

    function resetFilters() {
        const state = getAlpineState();
        state.search = '';
        state.propertyType = '';
        state.status = '';
        state.governorate = '';
        state.priceMin = '';
        state.priceMax = '';
        fetchPropertiesInViewport();
    }

    function zoomToProperty(property) {
        if (!property.geometry) return;

        const state = getAlpineState();
        state.selectedProperty = property;
        state.activeImage = property.images && property.images.length > 0 ? property.images[0] : null;

        if (property.geometry.type === 'Point') {
            const coords = property.geometry.coordinates;
            map.setView([coords[1], coords[0]], 16);
        } else if (property.geometry.type === 'Polygon') {
            const geojsonLayer = L.geoJSON(property.geometry);
            map.fitBounds(geojsonLayer.getBounds(), { padding: [50, 50] });
        }
    }

    function setMapLayer(layerName) {
        if (!map) return;

        if (map.hasLayer(osmLayer)) map.removeLayer(osmLayer);
        if (map.hasLayer(satelliteLayer)) map.removeLayer(satelliteLayer);

        if (layerName === 'osm') {
            osmLayer.addTo(map);
        } else if (layerName === 'satellite') {
            satelliteLayer.addTo(map);
        }
    }

    function locateUser() {
        if (!navigator.geolocation) {
            alert('متصفحك لا يدعم تحديد الموقع الجغرافي.');
            return;
        }

        map.locate({ setView: true, maxZoom: 16 });
        
        map.on('locationfound', function(e) {
            const userIcon = L.divIcon({
                html: `<div class="relative flex items-center justify-center h-4 w-4">
                         <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                         <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500 border-2 border-slate-950"></span>
                       </div>`,
                className: 'user-location-marker',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });
            
            if (window.userLocMarker) {
                map.removeLayer(window.userLocMarker);
            }
            
            window.userLocMarker = L.marker(e.latlng, { icon: userIcon }).addTo(map);
        });

        map.on('locationerror', function() {
            alert('تم رفض الوصول للموقع. يرجى تفعيل أذونات الـ GPS على جهازك.');
        });
    }

    function fetchPropertiesInViewport() {
        const state = getAlpineState();
        if (!map) return;

        state.loading = true;

        const bounds = map.getBounds();
        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();

        let url = `api_properties.php?sw_lat=${sw.lat}&sw_lng=${sw.lng}&ne_lat=${ne.lat}&ne_lng=${ne.lng}`;

        if (state.search) url += `&search=${encodeURIComponent(state.search)}`;
        if (state.propertyType) url += `&property_type=${state.propertyType}`;
        if (state.status) url += `&status=${state.status}`;
        if (state.governorate) url += `&governorate=${encodeURIComponent(state.governorate)}`;
        if (state.priceMin) url += `&price_min=${state.priceMin}`;
        if (state.priceMax) url += `&price_max=${state.priceMax}`;

        const GOVERNORATE_COORDINATES = {
            'بغداد': [33.3152, 44.3661],
            'نينوى': [36.3400, 43.1300],
            'البصرة': [30.5081, 47.7835],
            'صلاح الدين': [34.6000, 43.6800],
            'دهوك': [36.8683, 42.9972],
            'أربيل': [36.1911, 44.0092],
            'السليمانية': [35.5618, 45.4385],
            'كركوك': [35.4681, 44.3922],
            'ديالى': [34.0000, 45.0000],
            'الأنبار': [33.5000, 41.5000],
            'بابل': [32.4842, 44.4305],
            'كربلاء': [32.6160, 44.0249],
            'النجف': [32.0259, 44.3462],
            'واسط': [32.5000, 45.8300],
            'القادسية': [32.0000, 45.0000],
            'ميسان': [31.9000, 47.1000],
            'ذي قار': [31.0583, 46.2575],
            'المثنى': [30.3000, 45.2000]
        };

        fetch(url)
            .then(res => res.json())
            .then(res => {
                state.properties = res.data;
                renderMapLayers(res.data);

                if (state.shouldFitBounds) {
                    state.shouldFitBounds = false;
                    
                    if (res.data && res.data.length > 0) {
                        let tempGroup = L.featureGroup();
                        res.data.forEach(prop => {
                            if (prop.geometry) {
                                if (prop.geometry.type === 'Point') {
                                    const coords = prop.geometry.coordinates;
                                    tempGroup.addLayer(L.marker([coords[1], coords[0]]));
                                } else if (prop.geometry.type === 'Polygon') {
                                    tempGroup.addLayer(L.geoJSON(prop.geometry));
                                }
                            }
                        });
                        if (tempGroup.getLayers().length > 0) {
                            map.fitBounds(tempGroup.getBounds(), { padding: [50, 50], maxZoom: 14 });
                        }
                    } else if (state.governorate && GOVERNORATE_COORDINATES[state.governorate]) {
                        map.setView(GOVERNORATE_COORDINATES[state.governorate], 11);
                    }
                }
            })
            .catch(err => {
                console.error('Error fetching map spatial nodes:', err);
            })
            .finally(() => {
                state.loading = false;
            });
    }

    function renderMapLayers(data) {
        markersCluster.clearLayers();
        polygonsLayerGroup.clearLayers();

        const state = getAlpineState();

        data.forEach(property => {
            if (!property.geometry) return;

            if (property.geometry.type === 'Point') {
                const coords = property.geometry.coordinates;
                const priceFormatted = state.formatPriceArabic(property.price);
                const markerIcon = L.divIcon({
                    html: `<div class="relative flex flex-col items-center group select-none">
                             <div class="shadow-md border border-emerald-500/50 rounded-lg px-2 py-0.5 text-[10px] font-extrabold bg-white text-slate-800 transition-transform whitespace-nowrap z-20">
                               ${priceFormatted}
                             </div>
                             <div class="w-12 h-12 rounded-full border-2 border-emerald-500 shadow-md bg-white overflow-hidden flex items-center justify-center -mt-1 z-10">
                               <img src="images/facelogo.png" class="w-full h-full object-contain" alt="">
                             </div>
                             <div class="w-4 h-4 bg-emerald-500 rotate-45 transform -translate-y-2 shadow-sm z-0"></div>
                            </div>`,
                    className: 'custom-property-logo-marker',
                    iconSize: [75, 90],
                    iconAnchor: [37.5, 78]
                });

                const marker = L.marker([coords[1], coords[0]], { icon: markerIcon });
                const popupContent = buildPopupHtml(property, state);
                marker.bindPopup(popupContent);
                markersCluster.addLayer(marker);

            } else if (property.geometry.type === 'Polygon') {
                const polygon = L.geoJSON(property.geometry, {
                    style: {
                        color: '#2563EB',
                        weight: 3,
                        opacity: 0.85,
                        fillColor: '#3B82F6',
                        fillOpacity: 0.2
                    }
                });

                const popupContent = buildPopupHtml(property, state);
                polygon.bindPopup(popupContent);

                polygon.on('mouseover', function () {
                    this.setStyle({
                        fillOpacity: 0.4,
                        weight: 4
                    });
                });
                polygon.on('mouseout', function () {
                    this.setStyle({
                        fillOpacity: 0.2,
                        weight: 3
                    });
                });

                polygonsLayerGroup.addLayer(polygon);
            }
        });
    }

    function buildPopupHtml(property, state) {
        const priceStr = state.formatPriceArabic(property.price);
        
        let typeName = 'بيت';
        let iconHtml = '<i class="fa-solid fa-house text-emerald-500"></i>';
        if (property.property_type === 'house') {
            typeName = 'بيت';
            iconHtml = '<i class="fa-solid fa-house text-emerald-500"></i>';
        } else if (property.property_type === 'apartment') {
            typeName = 'شقة';
            iconHtml = '<i class="fa-solid fa-building text-sky-500"></i>';
        } else if (property.property_type === 'land') {
            typeName = 'أرض';
            iconHtml = '<i class="fa-solid fa-mountain text-amber-500"></i>';
        } else if (property.property_type === 'commercial') {
            typeName = 'تجاري';
            iconHtml = '<i class="fa-solid fa-store text-purple-500"></i>';
        }

        const statusLabel = property.status === 'sale' ? 
            '<span class="bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded text-[8px] font-extrabold border border-emerald-100">للبيع</span>' :
            '<span class="bg-amber-50 text-amber-600 px-2 py-0.5 rounded text-[8px] font-extrabold border border-amber-100">للإيجار</span>';

        const govLabel = property.governorate ? 
            `<span class="bg-slate-150 text-slate-700 px-2 py-0.5 rounded text-[8px] font-extrabold border border-slate-200/60">${property.governorate}</span>` : 
            '';

        let imagesHtml = '';
        if (property.images && property.images.length > 0) {
            imagesHtml = `
                <div class="mt-2 grid grid-cols-3 gap-1">
                    ${property.images.slice(0, 3).map(img => `<img src="${img}" class="w-full h-12 object-cover rounded border border-slate-100" />`).join('')}
                </div>
            `;
        }

        return `
            <div class="p-2 space-y-3 text-slate-800 font-sans w-52 text-right">
                <div class="flex justify-between items-start">
                    <span class="capitalize text-[10px] text-slate-400 flex items-center font-semibold font-sans">
                        ${iconHtml} <span class="mr-1.5">${typeName}</span>
                    </span>
                    <div class="flex items-center space-x-1 space-x-reverse">
                        ${govLabel}
                        ${statusLabel}
                    </div>
                </div>
                <div>
                    <h4 class="font-extrabold text-xs leading-snug line-clamp-2 text-right text-slate-800">${property.title}</h4>
                    <span class="text-emerald-600 font-display font-extrabold text-sm mt-1.5 block text-right">${priceStr}</span>
                </div>
                ${imagesHtml}
                <div class="border-t border-slate-200 pt-2 flex justify-between items-center space-x-2 space-x-reverse">
                    <span class="text-[9px] text-slate-400 font-semibold">${property.created_at_human}</span>
                    <div class="flex items-center space-x-1.5 space-x-reverse">
                        <button onclick="window.surveyArea(${property.id})" 
                                class="bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg transition-all shadow-sm">
                            اسأل عن المنطقة
                        </button>
                        <button onclick="window.viewPropertyDetails(${property.id})" 
                                class="bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg transition-all shadow-sm">
                            التفاصيل
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    window.viewPropertyDetails = function (propertyId) {
        const state = getAlpineState();
        const prop = state.properties.find(p => p.id === propertyId);
        if (prop) {
            state.selectedProperty = prop;
            state.activeImage = prop.images && prop.images.length > 0 ? prop.images[0] : null;
        }
    };

    window.clearSurvey = function () {
        const state = getAlpineState();
        state.surveyResults = null;
        if (surveyLayerGroup) {
            surveyLayerGroup.clearLayers();
        }
    };

    window.focusSurveyItem = function (item) {
        if (!map || !item) return;

        // Reset all polyline weights
        const state = getAlpineState();
        const results = state.surveyResults;
        if (results) {
            Object.values(results).forEach(group => {
                group.forEach(el => {
                    if (el.mapPolyline) {
                        el.mapPolyline.setStyle({ weight: 8, opacity: 0.9 });
                    }
                });
            });
        }

        // Highlight selected polyline
        if (item.mapPolyline) {
            item.mapPolyline.setStyle({ weight: 12, opacity: 1.0 });
        }

        // Pan to item and open marker popup
        map.setView([item.lat, item.lng], 16);
        if (item.mapMarker) {
            item.mapMarker.openPopup();
        }
    };

    window.drawSurveyFeatures = function () {
        if (!surveyLayerGroup) return;

        const state = getAlpineState();
        const results = state.surveyResults;
        if (!results) return;

        const configs = {
            schools: { color: '#10B981', iconClass: 'fa-graduation-cap', bgClass: 'bg-emerald-500' },
            markets: { color: '#3B82F6', iconClass: 'fa-shop', bgClass: 'bg-blue-500' },
            mosques: { color: '#F59E0B', iconClass: 'fa-mosque', bgClass: 'bg-amber-500' },
            hospitals: { color: '#EF4444', iconClass: 'fa-square-h', bgClass: 'bg-rose-500' }
        };

        Object.keys(results).forEach(tab => {
            const items = results[tab];
            const cfg = configs[tab];

            items.forEach(item => {
                // 1. Draw Marker
                const markerIcon = L.divIcon({
                    html: `<div class="relative flex items-center justify-center w-8 h-8 rounded-full border-2 border-white shadow-lg ${cfg.bgClass} text-white">
                             <i class="fa-solid ${cfg.iconClass} text-xs"></i>
                           </div>`,
                    className: 'custom-survey-marker',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });

                const marker = L.marker([item.lat, item.lng], { icon: markerIcon });
                marker.bindPopup(`
                    <div class="p-1 text-right font-sans text-xs">
                        <strong class="text-slate-800">${item.name}</strong>
                        <div class="text-slate-500 mt-1">${item.typeText}</div>
                        <div class="text-emerald-600 font-extrabold mt-1">${item.distanceText} (مسار المشي)</div>
                    </div>
                `);
                
                surveyLayerGroup.addLayer(marker);
                item.mapMarker = marker;

                // 2. Draw Route Polyline
                const polyline = L.polyline(item.routeCoords, {
                    color: cfg.color,
                    weight: 8,
                    opacity: 0.9,
                    dashArray: '2, 6'
                });

                polyline.bindTooltip(`${item.name} (${item.distanceText})`, { sticky: true });
                item.mapPolyline = polyline;

                surveyLayerGroup.addLayer(polyline);
            });
        });
    };

    window.surveyArea = async function (propertyId) {
        const state = getAlpineState();
        state.selectedProperty = null; // Hide modal if open
        
        const prop = state.properties.find(p => p.id === propertyId);
        if (!prop || !prop.geometry) {
            alert("لم يتم العثور على موقع هذا العقار.");
            return;
        }

        let lat, lng;
        if (prop.geometry.type === 'Point') {
            lng = prop.geometry.coordinates[0];
            lat = prop.geometry.coordinates[1];
        } else if (prop.geometry.type === 'Polygon') {
            const tempGeo = L.geoJSON(prop.geometry);
            const center = tempGeo.getBounds().getCenter();
            lat = center.lat;
            lng = center.lng;
        } else {
            alert("صيغة إحداثيات العقار غير صحيحة.");
            return;
        }

        state.surveyLoading = true;
        state.surveyResults = null;
        state.surveyCollapsed = false;

        if (surveyLayerGroup) {
            surveyLayerGroup.clearLayers();
        } else {
            surveyLayerGroup = L.layerGroup().addTo(map);
        }

        // Draw visual boundary (1km diameter = 500m radius)
        const surveyAreaCircle = L.circle([lat, lng], {
            radius: 500,
            color: '#10B981',
            fillColor: '#10B981',
            fillOpacity: 0.06,
            weight: 1.5,
            dashArray: '5, 5'
        }).addTo(surveyLayerGroup);

        map.fitBounds(surveyAreaCircle.getBounds(), { padding: [40, 40] });

        // Overpass API to fetch schools, markets, mosques, hospitals within 1km (1000m)
        const radius = 1000;
        const query = `
            [out:json][timeout:15];
            (
              node["amenity"="school"](around:${radius}, ${lat}, ${lng});
              way["amenity"="school"](around:${radius}, ${lat}, ${lng});
              
              node["shop"~"supermarket|convenience|mall"](around:${radius}, ${lat}, ${lng});
              node["amenity"="marketplace"](around:${radius}, ${lat}, ${lng});
              
              node["amenity"="place_of_worship"]["religion"="muslim"](around:${radius}, ${lat}, ${lng});
              
              node["amenity"~"hospital|clinic"](around:${radius}, ${lat}, ${lng});
              way["amenity"~"hospital|clinic"](around:${radius}, ${lat}, ${lng});
            );
            out body;
            >;
            out skel qt;
        `;

        const overpassUrl = `https://overpass-api.de/api/interpreter?data=${encodeURIComponent(query)}`;

        const fetchWithTimeout = async (url, ms = 5000) => {
            const controller = new AbortController();
            const id = setTimeout(() => controller.abort(), ms);
            try {
                const res = await fetch(url, { signal: controller.signal });
                clearTimeout(id);
                return res;
            } catch (e) {
                clearTimeout(id);
                throw e;
            }
        };

        try {
            const response = await fetchWithTimeout(overpassUrl, 8000);
            if (!response.ok) throw new Error("Overpass API Error");
            const data = await response.json();

            const schools = [];
            const markets = [];
            const mosques = [];
            const hospitals = [];

            const nodeLookup = {};
            data.elements.forEach(el => {
                if (el.type === 'node') {
                    nodeLookup[el.id] = el;
                }
            });

            data.elements.forEach(el => {
                let itemLat, itemLng;
                if (el.type === 'node') {
                    itemLat = el.lat;
                    itemLng = el.lon;
                } else if (el.type === 'way' && el.nodes && el.nodes.length > 0) {
                    let sumLat = 0, sumLng = 0, count = 0;
                    el.nodes.forEach(nid => {
                        if (nodeLookup[nid]) {
                            sumLat += nodeLookup[nid].lat;
                            sumLng += nodeLookup[nid].lon;
                            count++;
                        }
                    });
                    if (count > 0) {
                        itemLat = sumLat / count;
                        itemLng = sumLng / count;
                    }
                }

                if (!itemLat || !itemLng) return;

                const tags = el.tags || {};
                let name = tags.name || tags.name_ar || tags.name_en;
                
                if (!name) {
                    if (tags.amenity === 'school') name = "مدرسة غير مسماة";
                    else if (tags.shop === 'supermarket' || tags.shop === 'convenience' || tags.amenity === 'marketplace') name = "سوق محلي";
                    else if (tags.amenity === 'place_of_worship') name = "جامع / مسجد";
                    else if (tags.amenity === 'hospital' || tags.amenity === 'clinic') name = "مركز صحي / مستشفى";
                    else name = "مرفق خدمي";
                }

                const item = {
                    id: el.id,
                    name: name,
                    lat: itemLat,
                    lng: itemLng,
                    tags: tags
                };

                if (tags.amenity === 'school') {
                    item.typeText = "مدرسة";
                    schools.push(item);
                } else if (tags.shop === 'supermarket' || tags.shop === 'convenience' || tags.shop === 'mall' || tags.amenity === 'marketplace') {
                    item.typeText = "سوق / متجر";
                    markets.push(item);
                } else if (tags.amenity === 'place_of_worship' && tags.religion === 'muslim') {
                    item.typeText = "مسجد / جامع";
                    mosques.push(item);
                } else if (tags.amenity === 'hospital' || tags.amenity === 'clinic') {
                    item.typeText = "مستشفى / عيادة";
                    hospitals.push(item);
                }
            });

            const processGroup = async (group) => {
                // Calculate straight-line distances first
                group.forEach(item => {
                    const startLatLng = L.latLng(lat, lng);
                    const endLatLng = L.latLng(item.lat, item.lng);
                    const directDist = startLatLng.distanceTo(endLatLng);
                    item.distance = directDist;
                    item.distanceText = `~${Math.round(directDist)} متر`;
                    item.routeCoords = [[lat, lng], [item.lat, item.lng]];
                });

                // Sort by distance and limit to top 5 nearest to avoid hitting OSRM rate limits/hang-ups
                group.sort((a, b) => a.distance - b.distance);
                const limited = group.slice(0, 5);
                group.length = 0;
                group.push(...limited);

                for (let i = 0; i < group.length; i++) {
                    const item = group[i];
                    try {
                        const routeRes = await fetchWithTimeout(
                            `https://router.project-osrm.org/route/v1/foot/${lng},${lat};${item.lng},${item.lat}?geometries=geojson`,
                            1500
                        );
                        if (routeRes.ok) {
                            const routeData = await routeRes.json();
                            if (routeData.routes && routeData.routes.length > 0) {
                                const route = routeData.routes[0];
                                item.distance = route.distance;
                                item.distanceText = route.distance >= 1000 ? 
                                    `${(route.distance / 1000).toFixed(1)} كم` : 
                                    `${Math.round(route.distance)} متر`;
                                item.routeCoords = route.geometry.coordinates.map(c => [c[1], c[0]]);
                            }
                        }
                    } catch (e) {
                        console.warn("OSRM Route failed, fell back to straight-line:", e);
                    }
                }
                group.sort((a, b) => a.distance - b.distance);
            };

            await Promise.all([
                processGroup(schools),
                processGroup(markets),
                processGroup(mosques),
                processGroup(hospitals)
            ]);

            state.surveyResults = {
                schools: schools,
                markets: markets,
                mosques: mosques,
                hospitals: hospitals
            };
            
            drawSurveyFeatures();

        } catch (error) {
            console.error("Survey failed:", error);
            alert("عذراً، فشل استطلاع المنطقة المحيطة بسبب مشاكل في خوادم الخرائط. يرجى المحاولة لاحقاً.");
        } finally {
            state.surveyLoading = false;
        }
    };
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
