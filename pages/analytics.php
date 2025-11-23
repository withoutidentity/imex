<?php
$page_title = 'วิเคราะห์ข้อมูล';
require_once '../config/config.php';
include '../includes/header.php';
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">วิเคราะห์ข้อมูล</h1>
                <p class="text-gray-600">ระบบวิเคราะห์ข้อมูลและข้อมูลเชิงลึก</p>
            </div>
            <div class="bg-purple-100 p-3 rounded-lg">
                <i class="fas fa-chart-line text-purple-600 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Coming Soon -->
    <div class="bg-white p-12 rounded-lg shadow-md text-center">
        <div class="max-w-md mx-auto">
            <i class="fas fa-tools text-6xl text-gray-400 mb-6"></i>
            <h2 class="text-2xl font-bold text-gray-800 mb-4">กำลังพัฒนา</h2>
            <p class="text-gray-600 mb-6">
                ระบบวิเคราะห์ข้อมูลกำลังอยู่ระหว่างการพัฒนา<br>
                ซึ่งจะรวมฟีเจอร์ต่างๆ เช่น การวิเคราะห์แนวโน้ม การทำนาย และข้อมูลเชิงลึก
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <i class="fas fa-chart-area text-blue-600 text-xl mb-2"></i>
                    <h3 class="font-semibold text-blue-800">วิเคราะห์แนวโน้ม</h3>
                    <p class="text-sm text-blue-600">วิเคราะห์แนวโน้มการจัดส่ง</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <i class="fas fa-brain text-green-600 text-xl mb-2"></i>
                    <h3 class="font-semibold text-green-800">การทำนาย</h3>
                    <p class="text-sm text-green-600">ทำนายความต้องการและการจัดส่ง</p>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <i class="fas fa-search text-purple-600 text-xl mb-2"></i>
                    <h3 class="font-semibold text-purple-800">ข้อมูลเชิงลึก</h3>
                    <p class="text-sm text-purple-600">ข้อมูลเชิงลึกเพื่อการตัดสินใจ</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 