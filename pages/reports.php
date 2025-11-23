<?php
$page_title = 'รายงานสรุป';
require_once '../config/config.php';
include '../includes/header.php';
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">รายงานสรุป</h1>
                <p class="text-gray-600">ระบบรายงานและสรุปผลการดำเนินงาน</p>
            </div>
            <div class="bg-green-100 p-3 rounded-lg">
                <i class="fas fa-chart-bar text-green-600 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Coming Soon -->
    <div class="bg-white p-12 rounded-lg shadow-md text-center">
        <div class="max-w-md mx-auto">
            <i class="fas fa-tools text-6xl text-gray-400 mb-6"></i>
            <h2 class="text-2xl font-bold text-gray-800 mb-4">กำลังพัฒนา</h2>
            <p class="text-gray-600 mb-6">
                ระบบรายงานกำลังอยู่ระหว่างการพัฒนา<br>
                ซึ่งจะรวมฟีเจอร์ต่างๆ เช่น รายงานสรุปรายวัน รายงานประสิทธิภาพ และสถิติการจัดส่ง
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <i class="fas fa-calendar-day text-blue-600 text-xl mb-2"></i>
                    <h3 class="font-semibold text-blue-800">รายงานรายวัน</h3>
                    <p class="text-sm text-blue-600">สรุปผลการจัดส่งประจำวัน</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <i class="fas fa-tachometer-alt text-green-600 text-xl mb-2"></i>
                    <h3 class="font-semibold text-green-800">รายงานประสิทธิภาพ</h3>
                    <p class="text-sm text-green-600">วิเคราะห์ประสิทธิภาพการจัดส่ง</p>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <i class="fas fa-chart-pie text-purple-600 text-xl mb-2"></i>
                    <h3 class="font-semibold text-purple-800">สถิติการจัดส่ง</h3>
                    <p class="text-sm text-purple-600">กราฟและสถิติต่างๆ</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 