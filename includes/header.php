<?php
if (!isset($_SESSION)) {
    session_start();
}

// Determine the base path for navigation links
$current_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = '';

// If we're in a subdirectory (like pages/), adjust the base path
if (basename($current_dir) === 'pages') {
    $base_path = '../';
}

// Current script basename for active nav highlighting
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Red Theme CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/red-theme.css">
    
    <!-- Tailwind Color Override -->
    <style>
        /* Override Tailwind blue gradients to red */
        .from-blue-600 {
            --tw-gradient-from: #c92021 var(--tw-gradient-from-position);
            --tw-gradient-to: rgb(201 32 33 / 0) var(--tw-gradient-to-position);
            --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to);
        }
        
        .to-blue-800 {
            --tw-gradient-to: #dc2626 var(--tw-gradient-to-position);
        }
        
        .from-blue-700 {
            --tw-gradient-from: #b91c1c var(--tw-gradient-from-position);
            --tw-gradient-to: rgb(185 28 28 / 0) var(--tw-gradient-to-position);
            --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to);
        }
        
        .to-blue-900 {
            --tw-gradient-to: #991b1b var(--tw-gradient-to-position);
        }
        
        /* Override blue colors to red */
        .bg-blue-600 {
            background-color: #dc2626 !important;
        }
        
        .bg-blue-700 {
            background-color: #b91c1c !important;
        }
        
        .bg-blue-800 {
            background-color: #991b1b !important;
        }
        
        .text-blue-600 {
            color: #dc2626 !important;
        }
        
        .text-blue-700 {
            color: #b91c1c !important;
        }
        
        .text-blue-800 {
            color: #991b1b !important;
        }
        
        .border-blue-600 {
            border-color: #dc2626 !important;
        }
        
        .border-blue-700 {
            border-color: #b91c1c !important;
        }
        
        .border-blue-800 {
            border-color: #991b1b !important;
        }
        
        /* Hover states */
        .hover\:bg-blue-700:hover {
            background-color: #b91c1c !important;
        }
        
        .hover\:bg-blue-800:hover {
            background-color: #991b1b !important;
        }
        
        .hover\:text-blue-600:hover {
            color: #dc2626 !important;
        }
        
        .hover\:border-blue-700:hover {
            border-color: #b91c1c !important;
        }
        
        /* Focus states */
        .focus\:ring-blue-500:focus {
            --tw-ring-color: #ef4444 !important;
        }
        
        .focus\:border-blue-500:focus {
            border-color: #ef4444 !important;
        }
        
        /* Additional blue overrides */
        .bg-blue-50 {
            background-color: #fef2f2 !important;
        }
        
        .bg-blue-100 {
            background-color: #fee2e2 !important;
        }
        
        .bg-blue-200 {
            background-color: #fecaca !important;
        }
        
        .bg-blue-500 {
            background-color: #ef4444 !important;
        }
        
        .text-blue-50 {
            color: #fef2f2 !important;
        }
        
        .text-blue-100 {
            color: #fee2e2 !important;
        }
        
        .text-blue-200 {
            color: #fecaca !important;
        }
        
        .text-blue-300 {
            color: #fca5a5 !important;
        }
        
        .text-blue-400 {
            color: #f87171 !important;
        }
        
        .text-blue-500 {
            color: #ef4444 !important;
        }
        
        .border-blue-50 {
            border-color: #fef2f2 !important;
        }
        
        .border-blue-100 {
            border-color: #fee2e2 !important;
        }
        
        .border-blue-200 {
            border-color: #fecaca !important;
        }
        
        .border-blue-300 {
            border-color: #fca5a5 !important;
        }
        
        .border-blue-400 {
            border-color: #f87171 !important;
        }
        
        .border-blue-500 {
            border-color: #ef4444 !important;
        }
        
        /* Ring colors */
        .ring-blue-500 {
            --tw-ring-color: #ef4444 !important;
        }
        
        /* Decoration colors */
        .decoration-blue-500 {
            text-decoration-color: #ef4444 !important;
        }
        
        /* Placeholder colors */
        .placeholder-blue-500::placeholder {
            color: #ef4444 !important;
        }
    </style>
    
    <!-- Leaflet.js as alternative to Google Maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Google Maps API (only load if key is configured) -->
    <?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY && GOOGLE_MAPS_API_KEY !== 'YOUR_GOOGLE_MAPS_API_KEY_HERE'): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places"></script>
    <?php else: ?>
    <script>
        // Provide Google Maps API fallback using Leaflet
        window.google = {
            maps: {
                Map: function(element, options) {
                    this.leafletMap = L.map(element).setView([options.center.lat, options.center.lng], options.zoom);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(this.leafletMap);
                    return this;
                },
                LatLng: function(lat, lng) {
                    return {lat: lat, lng: lng};
                },
                LatLngBounds: function() {
                    this.extend = function() {};
                    this.getCenter = function() { return {lat: 8.4304, lng: 99.9631}; };
                    return this;
                },
                Marker: function(options) {
                    if (window.currentMapInstance && window.currentMapInstance.leafletMap) {
                        this.leafletMarker = L.marker([options.position.lat, options.position.lng])
                            .addTo(window.currentMapInstance.leafletMap);
                        if (options.title) {
                            this.leafletMarker.bindPopup(options.title);
                        }
                    }
                    this.addListener = function() {};
                    return this;
                },
                Polygon: function(options) {
                    if (window.currentMapInstance && window.currentMapInstance.leafletMap && options.paths) {
                        const coords = options.paths.map(p => [p.lat, p.lng]);
                        this.leafletPolygon = L.polygon(coords, {
                            color: options.strokeColor || '#0000ff',
                            fillColor: options.fillColor || '#0000ff',
                            fillOpacity: options.fillOpacity || 0.2
                        }).addTo(window.currentMapInstance.leafletMap);
                    }
                    return this;
                },
                InfoWindow: function(options) {
                    this.open = function() {};
                    this.close = function() {};
                    return this;
                },
                Size: function(w, h) { return {width: w, height: h}; },
                Point: function(x, y) { return {x: x, y: y}; }
            }
        };
        
        // Set current map instance for marker creation
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof initZonesMap === 'function') {
                setTimeout(function() {
                    try {
                        initZonesMap();
                    } catch (e) {
                        console.log('Map initialization completed with Leaflet fallback');
                    }
                }, 1000);
            }
        });
    </script>
    <?php endif; ?>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts - IBM Plex Sans Thai -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@100;200;300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Sidebar Animation */
        .sidebar {
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar-closed {
            transform: translateX(-100%);
        }
        
        /* Content area adjustment */
        .content-area {
            transition: margin-left 0.3s ease-in-out;
        }
        
        .content-expanded {
            margin-left: 0;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 50;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-red-50/30 to-gray-100">
    <!-- Sidebar -->
    <div id="sidebar" class="sidebar fixed left-0 top-0 h-full w-64 bg-gradient-to-b from-red-600 to-red-800 text-white shadow-2xl z-50">
        <div class="flex flex-col h-full">
            <!-- Sidebar Header -->
            <div class="flex items-center justify-between p-4 border-b border-red-500/30 bg-red-700/50">
                <div class="flex items-center space-x-3">
                    <div class="bg-white/20 p-2 rounded-lg">
                        <i class="fas fa-truck text-2xl text-white"></i>
                    </div>
                    <h1 class="text-lg font-bold truncate bg-gradient-to-r from-white to-red-100 bg-clip-text text-transparent">IMAG EXPRESS</h1>
                </div>
                <button id="sidebar-close" class="md:hidden text-white hover:text-red-300 transition-colors duration-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-3 space-y-1">
                    <!-- Dashboard -->
                    <a href="<?php echo $base_path; ?>index.php" class="group flex items-center gap-3 p-3 rounded-xl transition-all duration-300 hover:bg-white/15 hover:shadow-lg hover:scale-105 <?php echo $current_page === 'index.php' ? 'bg-white/20 shadow-lg' : ''; ?>">
                        <div class="w-10 h-10 grid place-items-center rounded-xl bg-white/20 text-white group-hover:bg-white/30 transition-all duration-300 <?php echo $current_page === 'index.php' ? 'bg-white/30 shadow-lg' : ''; ?>">
                            <i class="fas fa-home text-lg"></i>
                        </div>
                        <span class="font-semibold text-white group-hover:text-red-100">หน้าหลัก</span>
                    </a>

                    <!-- Import Section -->
                    <div class="pt-6 pb-3 px-1">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-0.5 bg-red-400/60 rounded-full"></div>
                            <h3 class="text-xs font-bold text-red-200/90 uppercase tracking-wider">นำเข้าข้อมูล</h3>
                        </div>
                    </div>

                    <a href="<?php echo $base_path; ?>pages/import.php" class="group flex items-center gap-3 p-3 rounded-xl transition-all duration-300 hover:bg-white/15 hover:shadow-lg hover:scale-105 <?php echo $current_page === 'import.php' ? 'bg-white/20 shadow-lg' : ''; ?>">
                        <div class="w-10 h-10 grid place-items-center rounded-xl bg-white/20 text-white group-hover:bg-white/30 transition-all duration-300 <?php echo $current_page === 'import.php' ? 'bg-white/30 shadow-lg' : ''; ?>">
                            <i class="fas fa-file-import text-lg"></i>
                        </div>
                        <span class="font-semibold text-white group-hover:text-red-100">นำเข้าข้อมูล</span>
                    </a>

                
                    <!-- Zone Management -->
                



                   

                    <!-- Delivery Management -->
                    <div class="pt-6 pb-3 px-1">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-0.5 bg-red-400/60 rounded-full"></div>
                            <h3 class="text-xs font-bold text-red-200/90 uppercase tracking-wider">จัดการจัดส่ง</h3>
                        </div>
                    </div>

                    <a href="<?php echo $base_path; ?>pages/delivery.php" class="group flex items-center gap-3 p-3 rounded-xl transition-all duration-300 hover:bg-white/15 hover:shadow-lg hover:scale-105 <?php echo $current_page === 'delivery.php' ? 'bg-white/20 shadow-lg' : ''; ?>">
                        <div class="w-10 h-10 grid place-items-center rounded-xl bg-white/20 text-white group-hover:bg-white/30 transition-all duration-300 <?php echo $current_page === 'delivery.php' ? 'bg-white/30 shadow-lg' : ''; ?>">
                            <i class="fas fa-truck-moving text-lg"></i>
                        </div>
                        <span class="font-semibold text-white group-hover:text-red-100">จัดการการจัดส่ง</span>
                    </a>

                    <a href="<?php echo $base_path; ?>pages/rider.php" class="group flex items-center gap-3 p-3 rounded-xl transition-all duration-300 hover:bg-white/15 hover:shadow-lg hover:scale-105 <?php echo $current_page === 'rider.php' ? 'bg-white/20 shadow-lg' : ''; ?>">
                        <div class="w-10 h-10 grid place-items-center rounded-xl bg-white/20 text-white group-hover:bg-white/30 transition-all duration-300 <?php echo $current_page === 'rider.php' ? 'bg-white/30 shadow-lg' : ''; ?>">
                            <i class="fas fa-users text-lg"></i>
                        </div>
                        <span class="font-semibold text-white group-hover:text-red-100">จัดการ Rider</span>
                    </a>

                   

                    <!-- Reports -->
                    <div class="pt-6 pb-3 px-1">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-0.5 bg-red-400/60 rounded-full"></div>
                            <h3 class="text-xs font-bold text-red-200/90 uppercase tracking-wider">รายงาน</h3>
                        </div>
                    </div>


                    <a href="<?php echo $base_path; ?>pages/reports.php" class="group flex items-center gap-3 p-3 rounded-xl transition-all duration-300 hover:bg-white/15 hover:shadow-lg hover:scale-105 <?php echo $current_page === 'reports.php' ? 'bg-white/20 shadow-lg' : ''; ?>">
                        <div class="w-10 h-10 grid place-items-center rounded-xl bg-white/20 text-white group-hover:bg-white/30 transition-all duration-300 <?php echo $current_page === 'reports.php' ? 'bg-white/30 shadow-lg' : ''; ?>">
                            <i class="fas fa-chart-bar text-lg"></i>
                        </div>
                        <span class="font-semibold text-white group-hover:text-red-100">รายงานสรุป</span>
                    </a>

                </div>
            </nav>
            
            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-red-500/30 bg-red-800/30">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-user text-white"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white truncate">Administrator</p>
                        <p class="text-xs text-red-200/80 truncate">admin@imagexpress.com</p>
                    </div>
                    <div class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center hover:bg-white/20 transition-colors cursor-pointer">
                        <i class="fas fa-cog text-white text-sm"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div id="sidebar-overlay" class="sidebar-overlay hidden md:hidden"></div>
    
    <!-- Top Bar -->
    <div class="content-area ml-64">
        <div class="bg-white shadow-sm border-b border-gray-200 px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <button id="sidebar-collapse" class="hidden md:block text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-lg font-semibold text-gray-900">
                        <?php echo isset($page_title) ? $page_title : 'Dashboard'; ?>
                    </h2>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bell text-xl"></i>
                    </button>
                    <button class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-cog text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <main class="p-6"> 