<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตารางกะทำงานพนักงาน - IMEX</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "IBM Plex Sans Thai", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #4f46e5 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .controls {
            padding: 20px 30px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 50px 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            background: white;
            color: #1e293b;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .search-box input::placeholder {
            color: #64748b;
        }

        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #64748b;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .filter-btn:hover, .filter-btn.active {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
        }

        .table-container {
            overflow-x: auto;
            padding: 0 30px 30px;
        }

        .schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
        }

        .schedule-table th {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #4f46e5 100%);
            color: white;
            padding: 15px 12px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .schedule-table th:first-child {
            border-top-left-radius: 10px;
        }

        .schedule-table th:last-child {
            border-top-right-radius: 10px;
        }

        .schedule-table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            background: white;
            color: #1e293b;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .schedule-table tr:hover td {
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* สีสำหรับแต่ละกะ */
        .schedule-table tr[data-department="normal"] td {
            background: linear-gradient(90deg, #eff6ff 0%, #dbeafe 100%);
            border-left: 4px solid #3b82f6;
        }

        .schedule-table tr[data-department="normal"]:hover td {
            background: linear-gradient(90deg, #dbeafe 0%, #bfdbfe 100%);
        }

        .schedule-table tr[data-department="production1"] td {
            background: linear-gradient(90deg, #f0fdf4 0%, #dcfce7 100%);
            border-left: 4px solid #22c55e;
        }

        .schedule-table tr[data-department="production1"]:hover td {
            background: linear-gradient(90deg, #dcfce7 0%, #bbf7d0 100%);
        }

        .schedule-table tr[data-department="production2"] td {
            background: linear-gradient(90deg, #fefce8 0%, #fef3c7 100%);
            border-left: 4px solid #eab308;
        }

        .schedule-table tr[data-department="production2"]:hover td {
            background: linear-gradient(90deg, #fef3c7 0%, #fde68a 100%);
        }

        .schedule-table tr[data-department="production3"] td {
            background: linear-gradient(90deg, #fff7ed 0%, #fed7aa 100%);
            border-left: 4px solid #f97316;
        }

        .schedule-table tr[data-department="production3"]:hover td {
            background: linear-gradient(90deg, #fed7aa 0%, #fdba74 100%);
        }

        .schedule-table tr[data-department="production4"] td {
            background: linear-gradient(90deg, #fdf2f8 0%, #fce7f3 100%);
            border-left: 4px solid #ec4899;
        }

        .schedule-table tr[data-department="production4"]:hover td {
            background: linear-gradient(90deg, #fce7f3 0%, #fbcfe8 100%);
        }

        .schedule-table tr:last-child td:first-child {
            border-bottom-left-radius: 10px;
        }

        .schedule-table tr:last-child td:last-child {
            border-bottom-right-radius: 10px;
        }

        .shift-name {
            font-weight: 600;
            color: #1e293b;
        }

        .time-cell {
            font-weight: 500;
            color: #475569;
        }

        .break-time {
            background: #fbbf24;
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .work-time {
            background: #10b981;
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .overtime {
            background: #ef4444;
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .time-with-icon {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .time-icon {
            font-size: 14px;
        }

        .ot-badge {
            background: #8b5cf6;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(139, 92, 246, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .department-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }

        .dept-normal {
            background: #3b82f6;
            color: white;
        }

        .dept-production1 {
            background: #22c55e;
            color: white;
        }

        .dept-production2 {
            background: #eab308;
            color: white;
        }

        .dept-production3 {
            background: #f97316;
            color: white;
        }

        .dept-production4 {
            background: #ec4899;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px 30px;
            background: #f8fafc;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #4f46e5;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #64748b;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: auto;
            }
            
            .filter-buttons {
                justify-content: center;
            }
            
            .schedule-table th,
            .schedule-table td {
                padding: 8px 6px;
                font-size: 12px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                padding: 15px;
            }
        }

        .loading {
            display: none;
            text-align: center;
            padding: 40px;
            color: #64748b;
        }

        .loading i {
            font-size: 2rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-clock"></i> ตารางกะทำงานพนักงาน</h1>
            <p>ระบบจัดการเวลาทำงานและโอเวอร์ไทม์</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">5</div>
                <div class="stat-label">แผนก</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">12</div>
                <div class="stat-label">กะทำงาน</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">8</div>
                <div class="stat-label">ชั่วโมงมาตรฐาน</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">3-9</div>
                <div class="stat-label">ชั่วโมง OT</div>
            </div>
        </div>

        <div class="controls">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="ค้นหาแผนกหรือกะทำงาน...">
                <i class="fas fa-search"></i>
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">ทั้งหมด</button>
                <button class="filter-btn" data-filter="normal">Normal</button>
                <button class="filter-btn" data-filter="production">Production</button>
                <button class="filter-btn" data-filter="overtime">มี OT</button>
            </div>
        </div>

        <div class="loading" id="loading">
            <i class="fas fa-spinner"></i>
            <p>กำลังโหลดข้อมูล...</p>
        </div>

        <div class="table-container">
            <table class="schedule-table" id="scheduleTable">
                <thead>
                    <tr>
                        <th>ชื่อกะ</th>
                        <th>เวลาเข้า</th>
                        <th>เวลาปฏิกาลาน</th>
                        <th>เวลาพักเที่ยง</th>
                        <th>เวลาเข้างาน(บ่าย)</th>
                        <th>เวลาเลิกงาน</th>
                        <th>เวลาพักก่อน OT</th>
                        <th>OT In</th>
                        <th>OT Out</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody id="scheduleBody">
                    <tr data-department="normal">
                        <td><span class="department-badge dept-normal">Normal</span><br><span class="shift-name">Normal</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>08.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>12.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>60 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>13.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>17.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>17.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>20.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 3H</span></td>
                    </tr>
                    <tr data-department="production1">
                        <td><span class="department-badge dept-production1">Productions 1</span><br><span class="shift-name">Productions 1/1</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>08.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>12.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>11.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>17.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>17.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>20.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 3H</span></td>
                    </tr>
                    <tr data-department="production1">
                        <td><span class="department-badge dept-production1">Productions 1</span><br><span class="shift-name">Productions 1/2</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>08.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>12.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>12.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>17.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>17.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>20.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 3H</span></td>
                    </tr>
                    <tr data-department="production1">
                        <td><span class="department-badge dept-production1">Productions 1</span><br><span class="shift-name">Productions 1/3</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>08.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>13.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>13.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>17.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>17.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>20.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 3H</span></td>
                    </tr>
                    <tr data-department="production2">
                        <td><span class="department-badge dept-production2">Productions 2</span><br><span class="shift-name">Productions 2/1</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>20.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>23.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>23.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>05.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>05.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>08.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 3H</span></td>
                    </tr>
                    <tr data-department="production2">
                        <td><span class="department-badge dept-production2">Productions 2</span><br><span class="shift-name">Productions 2/2</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>20.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>24.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>00.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>05.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>05.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>08.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 3H</span></td>
                    </tr>
                    <tr data-department="production2">
                        <td><span class="department-badge dept-production2">Productions 2</span><br><span class="shift-name">Productions 2/3</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>20.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>01.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>01.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>05.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>05.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>08.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 3H</span></td>
                    </tr>
                    <tr data-department="production3">
                        <td><span class="department-badge dept-production3">Productions 3</span><br><span class="shift-name">Productions 3/1</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>08.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>11.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>11.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>17.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>17.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>20.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 9H</span></td>
                    </tr>
                    <tr data-department="production3">
                        <td><span class="department-badge dept-production3">Productions 3</span><br><span class="shift-name">Productions 3/2</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>08.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>12.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>12.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>17.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>17.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>20.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 9H</span></td>
                    </tr>
                    <tr data-department="production3">
                        <td><span class="department-badge dept-production3">Productions 3</span><br><span class="shift-name">Productions 3/3</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>08.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>13.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>13.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>17.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>17.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>20.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 9H</span></td>
                    </tr>
                    <tr data-department="production4">
                        <td><span class="department-badge dept-production4">Productions 4</span><br><span class="shift-name">Productions 4/1</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>20.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>23.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>23.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>05.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>05.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>08.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 9H</span></td>
                    </tr>
                    <tr data-department="production4">
                        <td><span class="department-badge dept-production4">Productions 4</span><br><span class="shift-name">Productions 4/2</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>20.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>24.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>00.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>05.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>05.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>08.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 9H</span></td>
                    </tr>
                    <tr data-department="production4">
                        <td><span class="department-badge dept-production4">Productions 4</span><br><span class="shift-name">Productions 4/3</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-in-alt time-icon"></i>20.00 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-utensils time-icon"></i>01.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-coffee"></i>40 นาที</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-play time-icon"></i>01.40 น.</span></td>
                        <td class="time-cell"><span class="time-with-icon"><i class="fas fa-sign-out-alt time-icon"></i>05.00 น.</span></td>
                        <td><span class="break-time"><i class="fas fa-pause"></i>20 นาที</span></td>
                        <td><span class="work-time"><i class="fas fa-clock"></i>05.20 น.</span></td>
                        <td><span class="overtime"><i class="fas fa-moon"></i>08.00 น.</span></td>
                        <td><span class="ot-badge"><i class="fas fa-plus-circle"></i>OT 9H</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const scheduleTable = document.getElementById('scheduleTable');
        const scheduleBody = document.getElementById('scheduleBody');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const loading = document.getElementById('loading');

        // Search function
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = scheduleBody.querySelectorAll('tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Filter functionality
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                const rows = scheduleBody.querySelectorAll('tr');
                
                // Show loading
                loading.style.display = 'block';
                scheduleTable.style.display = 'none';
                
                setTimeout(() => {
                    rows.forEach(row => {
                        const department = row.getAttribute('data-department');
                        const hasOT = row.textContent.includes('OT');
                        
                        switch(filter) {
                            case 'all':
                                row.style.display = '';
                                break;
                            case 'normal':
                                row.style.display = department === 'normal' ? '' : 'none';
                                break;
                            case 'production':
                                row.style.display = department.includes('production') ? '' : 'none';
                                break;
                            case 'overtime':
                                row.style.display = hasOT ? '' : 'none';
                                break;
                        }
                    });
                    
                    // Hide loading
                    loading.style.display = 'none';
                    scheduleTable.style.display = 'table';
                }, 500);
            });
        });

        // Add hover effects and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate table rows on load
            const rows = scheduleBody.querySelectorAll('tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.5s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add click to highlight functionality
            rows.forEach(row => {
                row.addEventListener('click', function() {
                    // Remove highlight from all rows
                    rows.forEach(r => r.classList.remove('highlighted'));
                    // Add highlight to clicked row
                    this.classList.add('highlighted');
                });
            });
        });

        // Add some CSS for highlighted rows
        const style = document.createElement('style');
        style.textContent = `
            .highlighted td {
                background: #4f46e5 !important;
                color: white !important;
                border-color: #4f46e5 !important;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
