<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo: ระบบจัดการโซนและพนักงาน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .fadeIn { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-button.active { border-color: #3B82F6; color: #3B82F6; }
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .overlay {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        .overlay.show {
            display: block;
            opacity: 1;
        }
        @media (min-width: 1024px) {
            .sidebar {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="min-h-screen bg-gray-100">
    <!-- Top Navigation -->
    <nav class="bg-blue-800 shadow-lg relative z-30">
        <div class="px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <button onclick="toggleSidebar()" class="lg:hidden mr-3 text-white hover:text-blue-200">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="flex-shrink-0">
                        <h1 class="text-white text-xl font-bold">Demo: Zone Management System</h1>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="hidden md:flex items-center space-x-2 text-blue-200">
                        <i class="fas fa-database text-sm"></i>
                        <span class="text-sm">Demo Mode</span>
                    </div>
                    <a href="index.php" class="text-white hover:text-blue-200 transition-colors">
                        <i class="fas fa-home mr-2"></i>กลับหน้าหลัก
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Overlay for mobile -->
    <div id="overlay" class="overlay fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden" onclick="closeSidebar()"></div>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar fixed left-0 top-0 h-full w-64 bg-white shadow-lg z-25 lg:translate-x-0">
        <div class="flex flex-col h-full">
            <!-- Sidebar Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-users-cog text-white"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-800">Zone Manager</h2>
                        <p class="text-xs text-gray-500">Demo System</p>
                    </div>
                </div>
                <button onclick="closeSidebar()" class="lg:hidden text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 p-4">
                <div class="space-y-2">
                    <!-- Dashboard -->
                    <div class="mb-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">หลัก</h3>
                        <a href="#" onclick="showTab('zones')" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-tachometer-alt text-gray-400 group-hover:text-blue-600"></i>
                            <span class="text-gray-700 group-hover:text-blue-600">แดชบอร์ด</span>
                        </a>
                    </div>

                    <!-- Zone Management -->
                    <div class="mb-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">จัดการโซน</h3>
                        <a href="#" onclick="showTab('zones')" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-map-marked-alt text-gray-400 group-hover:text-blue-600"></i>
                            <span class="text-gray-700 group-hover:text-blue-600">รายการโซน</span>
                            <span class="ml-auto bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">4</span>
                        </a>
                        <a href="#" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-plus-circle text-gray-400 group-hover:text-green-600"></i>
                            <span class="text-gray-700 group-hover:text-green-600">เพิ่มโซนใหม่</span>
                        </a>
                        <a href="#" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-chart-area text-gray-400 group-hover:text-purple-600"></i>
                            <span class="text-gray-700 group-hover:text-purple-600">วิเคราะห์โซน</span>
                        </a>
                    </div>

                    <!-- Employee Management -->
                    <div class="mb-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">จัดการพนักงาน</h3>
                        <a href="#" onclick="showTab('employees')" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-users text-gray-400 group-hover:text-blue-600"></i>
                            <span class="text-gray-700 group-hover:text-blue-600">รายการพนักงาน</span>
                            <span class="ml-auto bg-green-100 text-green-600 text-xs px-2 py-1 rounded-full">20</span>
                        </a>
                        <a href="#" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-user-plus text-gray-400 group-hover:text-green-600"></i>
                            <span class="text-gray-700 group-hover:text-green-600">เพิ่มพนักงาน</span>
                        </a>
                        <a href="#" onclick="showTab('assignments')" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-user-check text-gray-400 group-hover:text-purple-600"></i>
                            <span class="text-gray-700 group-hover:text-purple-600">มอบหมายงาน</span>
                            <span class="ml-auto bg-purple-100 text-purple-600 text-xs px-2 py-1 rounded-full">18</span>
                        </a>
                    </div>

                    <!-- Reports & Analytics -->
                    <div class="mb-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">รายงาน</h3>
                        <a href="#" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-chart-bar text-gray-400 group-hover:text-blue-600"></i>
                            <span class="text-gray-700 group-hover:text-blue-600">สถิติการจัดส่ง</span>
                        </a>
                        <a href="#" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-clock text-gray-400 group-hover:text-orange-600"></i>
                            <span class="text-gray-700 group-hover:text-orange-600">ประสิทธิภาพ</span>
                        </a>
                        <a href="#" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-file-export text-gray-400 group-hover:text-green-600"></i>
                            <span class="text-gray-700 group-hover:text-green-600">ส่งออกรายงาน</span>
                        </a>
                    </div>

                    <!-- Tools -->
                    <div class="mb-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">เครื่องมือ</h3>
                        <a href="pages/leaflet_map.php" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-map text-gray-400 group-hover:text-blue-600"></i>
                            <span class="text-gray-700 group-hover:text-blue-600">แผนที่จัดส่ง</span>
                        </a>
                        <a href="pages/route_planner.php" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-route text-gray-400 group-hover:text-purple-600"></i>
                            <span class="text-gray-700 group-hover:text-purple-600">วางแผนเส้นทาง</span>
                        </a>
                        <a href="pages/import.php" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-upload text-gray-400 group-hover:text-green-600"></i>
                            <span class="text-gray-700 group-hover:text-green-600">นำเข้าข้อมูล</span>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-gray-200">
                <div class="bg-blue-50 rounded-lg p-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-user text-white text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-800">Demo User</p>
                            <p class="text-xs text-gray-500">Administrator</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 space-y-1">
                    <a href="setup_zone_employees.php" class="flex items-center justify-center w-full px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-md transition-colors">
                        <i class="fas fa-database mr-2"></i>ติดตั้งระบบ
                    </a>
                    <a href="pages/zones_enhanced.php" class="flex items-center justify-center w-full px-3 py-2 text-sm text-green-600 hover:bg-green-50 rounded-md transition-colors">
                        <i class="fas fa-arrow-right mr-2"></i>เข้าระบบจริง
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-6 rounded-lg shadow-lg mb-6 fadeIn">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2">ระบบจัดการโซนและพนักงาน</h1>
                    <p class="text-indigo-100">บริหารโซนการจัดส่งและมอบหมายพนักงานรับผิดชอบ (Demo)</p>
                </div>
                <div class="hidden lg:block">
                    <i class="fas fa-users-cog text-6xl opacity-20"></i>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 fadeIn">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3 text-lg"></i>
                <div>
                    <h4 class="font-semibold">✅ ระบบจัดการโซนและพนักงานพร้อมใช้งาน!</h4>
                    <p class="text-sm mt-1">ฟีเจอร์ครบครันสำหรับการบริหารโซนการจัดส่งและพนักงาน</p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 fadeIn">
            <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-blue-500">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-map-marked-alt text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">4</h3>
                        <p class="text-gray-600">โซนหลัก</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">20</h3>
                        <p class="text-gray-600">พนักงาน</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-purple-500">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                        <i class="fas fa-user-check text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">18</h3>
                        <p class="text-gray-600">มอบหมายแล้ว</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-orange-500">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600 mr-4">
                        <i class="fas fa-tasks text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">245</h3>
                        <p class="text-gray-600">งานรอดำเนินการ</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Features -->
        <div class="bg-white rounded-lg shadow-md mb-6 fadeIn">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6">
                    <button onclick="showTab('zones')" id="zones-tab" class="tab-button active border-b-2 py-4 px-1 text-sm font-medium">
                        <i class="fas fa-map-marked-alt mr-2"></i>จัดการโซน
                    </button>
                    <button onclick="showTab('employees')" id="employees-tab" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 py-4 px-1 text-sm font-medium">
                        <i class="fas fa-users mr-2"></i>จัดการพนักงาน
                    </button>
                    <button onclick="showTab('assignments')" id="assignments-tab" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 py-4 px-1 text-sm font-medium">
                        <i class="fas fa-user-check mr-2"></i>มอบหมายงาน
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                
                <!-- Zones Tab -->
                <div id="zones-content" class="tab-content active">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">รายการโซนการจัดส่ง</h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Zone Card 1 -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-lg transition-shadow">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <div class="w-4 h-4 rounded-full mr-3 bg-blue-500"></div>
                                        <h3 class="text-lg font-bold text-gray-800">พัฒนา</h3>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3">โซนพัฒนาการ</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-3 mb-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">89</div>
                                    <div class="text-xs text-gray-500">ทั้งหมด</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-orange-600">23</div>
                                    <div class="text-xs text-gray-500">รอดำเนินการ</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600">66</div>
                                    <div class="text-xs text-gray-500">เสร็จแล้ว</div>
                                </div>
                            </div>

                            <div class="border-t pt-3">
                                <div class="text-sm font-medium text-gray-700 mb-2">พนักงานรับผิดชอบ:</div>
                                <div class="text-xs text-gray-600">
                                    อริษา บัวเพชร (สาว) - primary; ธวัชชัย สัจจารักษ์ (นุ๊ก) - primary; ณัฐพล พลสังข์ (กอล์ฟ) - primary
                                </div>
                            </div>
                        </div>

                        <!-- Zone Card 2 -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-lg transition-shadow">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <div class="w-4 h-4 rounded-full mr-3 bg-green-500"></div>
                                        <h3 class="text-lg font-bold text-gray-800">ราชดำเนิน</h3>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3">โซนราชดำเนิน</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-3 mb-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">156</div>
                                    <div class="text-xs text-gray-500">ทั้งหมด</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-orange-600">42</div>
                                    <div class="text-xs text-gray-500">รอดำเนินการ</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600">114</div>
                                    <div class="text-xs text-gray-500">เสร็จแล้ว</div>
                                </div>
                            </div>

                            <div class="border-t pt-3">
                                <div class="text-sm font-medium text-gray-700 mb-2">พนักงานรับผิดชอบ:</div>
                                <div class="text-xs text-gray-600">
                                    อับดุลรอหีม เบ็ญโส๊ะ (ฮีม) - primary; วีรวุฒิ หมื่นยกพล (เอ็ม) - primary; ณัฐพล ดาราวรรณ (นิด) - primary
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employees Tab -->
                <div id="employees-content" class="tab-content">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800">รายการพนักงาน</h2>
                        <button class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>เพิ่มพนักงาน
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">รหัส/ชื่อ</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ตำแหน่ง</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">พื้นที่รับผิดชอบ</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">โซนที่มอบหมาย</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">อริษา บัวเพชร</div>
                                            <div class="text-sm text-gray-500">664921T000009</div>
                                            <div class="text-xs text-blue-600">ชื่อเล่น: สาว</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">SPT</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">สีแยกคูขวางฝั่งซ้าย - จนสะพานไดโนเสาร์</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="text-green-600">พัฒนา</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">ใช้งาน</span>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">ธวัชชัย สัจจารักษ์</div>
                                            <div class="text-sm text-gray-500">664921T000010</div>
                                            <div class="text-xs text-blue-600">ชื่อเล่น: นุ๊ก</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">SPT</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">สะพานไดโนเสาร์ ฝั่งขวา+ซ้ายไปถึงเมืองทอง</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="text-green-600">พัฒนา</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">ใช้งาน</span>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">สุภาพร สมาธิ</div>
                                            <div class="text-sm text-gray-500">664921T000024</div>
                                            <div class="text-xs text-blue-600">ชื่อเล่น: ตั้ก</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">SPT+C</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">สะพานแสงจันทร์ - โฮมโปร ซ้าย+ ขวา</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="text-green-600">พัฒนา</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">ใช้งาน</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Assignments Tab -->
                <div id="assignments-content" class="tab-content">
                    <div class="text-center py-12">
                        <i class="fas fa-hand-point-left text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-medium text-gray-500 mb-2">เลือกโซนเพื่อจัดการมอบหมายงาน</h3>
                        <p class="text-gray-400">คลิกที่ไอคอนตาในรายการโซนเพื่อดูรายละเอียดและจัดการพนักงาน</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 fadeIn">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-map-marked-alt text-2xl text-blue-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">จัดการโซน</h3>
                    <p class="text-gray-600 text-sm">สร้าง แก้ไข และจัดการโซนการจัดส่ง พร้อมข้อมูลสถิติแบบเรียลไทม์</p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-2xl text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">บริหารพนักงาน</h3>
                    <p class="text-gray-600 text-sm">เพิ่ม แก้ไข และติดตามพนักงาน พร้อมระบบมอบหมายงานอัตโนมัติ</p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-check text-2xl text-purple-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">มอบหมายงาน</h3>
                    <p class="text-gray-600 text-sm">กำหนดพนักงานหลัก สำรอง และสนับสนุน พร้อมแบ่งเปอร์เซ็นต์ภาระงาน</p>
                </div>
            </div>
        </div>

        <!-- Getting Started -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 fadeIn">
            <h3 class="text-lg font-semibold text-blue-800 mb-4">
                <i class="fas fa-rocket mr-2"></i>เริ่มต้นใช้งาน
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-blue-700 mb-2">ขั้นตอนการติดตั้ง:</h4>
                    <ol class="list-decimal list-inside text-sm text-blue-600 space-y-1">
                        <li>เริ่ม XAMPP และ MySQL</li>
                        <li>เรียก <code class="bg-blue-100 px-1 rounded">setup_zone_employees.php</code></li>
                        <li>เข้าสู่ระบบจัดการโซนและพนักงาน</li>
                    </ol>
                </div>
                
                <div>
                    <h4 class="font-medium text-blue-700 mb-2">หลังจากติดตั้ง:</h4>
                    <div class="space-y-2">
                        <a href="pages/zones_enhanced.php" class="block bg-blue-600 text-white text-center py-2 px-4 rounded-md hover:bg-blue-700 transition-colors text-sm">
                            <i class="fas fa-users-cog mr-2"></i>เข้าระบบจัดการโซน & พนักงาน
                        </a>
                        <a href="setup_zone_employees.php" class="block bg-green-600 text-white text-center py-2 px-4 rounded-md hover:bg-green-700 transition-colors text-sm">
                            <i class="fas fa-database mr-2"></i>ติดตั้งฐานข้อมูล
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sidebar functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    sidebar.classList.toggle('open');
    
    if (sidebar.classList.contains('open')) {
        overlay.classList.add('show');
    } else {
        overlay.classList.remove('show');
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
}

// Close sidebar when clicking on main content (mobile)
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const isClickInsideSidebar = sidebar.contains(e.target);
    const isMenuButton = e.target.closest('button') && e.target.closest('button').onclick && e.target.closest('button').onclick.toString().includes('toggleSidebar');
    
    if (!isClickInsideSidebar && !isMenuButton && window.innerWidth < 1024) {
        closeSidebar();
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    if (window.innerWidth >= 1024) {
        closeSidebar();
    }
});

// Active nav item highlighting
function setActiveNavItem(tabName) {
    // Remove active class from all nav items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('bg-blue-100', 'border-r-2', 'border-blue-600');
        item.querySelector('i').classList.remove('text-blue-600');
        item.querySelector('span').classList.remove('text-blue-600', 'font-medium');
    });
    
    // Add active class to corresponding nav item
    const activeItem = document.querySelector(`[onclick="showTab('${tabName}')"]`);
    if (activeItem) {
        activeItem.classList.add('bg-blue-100', 'border-r-2', 'border-blue-600');
        activeItem.querySelector('i').classList.add('text-blue-600');
        activeItem.querySelector('span').classList.add('text-blue-600', 'font-medium');
    }
}

<script>
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active styles from all tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-content').classList.add('active');
    
    // Add active styles to selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Update sidebar active state
    setActiveNavItem(tabName);
    
    // Close sidebar on mobile after selection
    if (window.innerWidth < 1024) {
        closeSidebar();
    }
}
</script>

</body>
</html> 