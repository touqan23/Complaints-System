<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير إحصائيات النظام</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            background: #f5f5f0;
            color: #3e2723;
            padding: 40px 20px;
            direction: rtl;
            font-size: 16px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 4px 20px rgba(62, 39, 35, 0.1);
            border-radius: 12px;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #6d4c41 0%, #5d4037 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 38px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .header .date {
            font-size: 16px;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .section {
            margin-bottom: 50px;
        }

        .section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            color: #5d4037;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #a1887f;
            display: flex;
            align-items: center;
        }

        .section-title::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 24px;
            background: #8d6e63;
            margin-left: 12px;
            border-radius: 3px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #efebe9 0%, #f5f5f0 100%);
            padding: 25px;
            border-radius: 10px;
            border-right: 4px solid #8d6e63;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(62, 39, 35, 0.15);
        }

        .stat-card .label {
            color: #6d4c41;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            color: #3e2723;
            font-size: 36px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(62, 39, 35, 0.08);
            border-radius: 8px;
            overflow: hidden;
        }

        thead {
            background: linear-gradient(135deg, #8d6e63 0%, #7d5e54 100%);
            color: white;
        }

        th {
            padding: 16px;
            text-align: right;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 0.5px;
        }

        td {
            padding: 14px 16px;
            text-align: right;
            border-bottom: 1px solid #efebe9;
            color: #4e342e;
            font-size: 15px;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .badge-primary {
            background: #d7ccc8;
            color: #4e342e;
        }

        .badge-success {
            background: #c8e6c9;
            color: #2e7d32;
        }

        .badge-warning {
            background: #ffe0b2;
            color: #e65100;
        }

        .badge-danger {
            background: #ffcdd2;
            color: #c62828;
        }

        .footer {
            background: #efebe9;
            padding: 30px;
            text-align: center;
            color: #6d4c41;
            font-size: 13px;
            border-top: 2px solid #d7ccc8;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #8d6e63;
            font-style: italic;
        }

        @media print {
            body {
                padding: 0;
            }

            .container {
                box-shadow: none;
            }

            .stat-card:hover {
                transform: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 تقرير إحصائيات النظام</h1>
        <div class="date">تاريخ التقرير: {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    <div class="content">
        <!-- الإحصائيات العامة -->
        @if(isset($data['general_stats']))
            <div class="section">
                <h2 class="section-title">الإحصائيات العامة</h2>
                <div class="stats-grid">
                    @foreach($data['general_stats'] as $key => $value)
                        <div class="stat-card">
                            <div class="label">{{ ucfirst(str_replace('_', ' ', $key)) }}</div>
                            <div class="value">{{ number_format($value) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- الشكاوى حسب الحالة -->
        @if(isset($data['status_summary']) && $data['status_summary']->count() > 0)
            <div class="section">
                <h2 class="section-title">توزيع الشكاوى حسب الحالة</h2>
                <table>
                    <thead>
                    <tr>
                        <th>الحالة</th>
                        <th>العدد</th>
                        <th>النسبة المئوية</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $totalComplaints = $data['status_summary']->sum('total');
                    @endphp
                    @foreach($data['status_summary'] as $status)
                        <tr>
                            <td>
                                <span class="badge badge-primary">{{ $status->status }}</span>
                            </td>
                            <td><strong>{{ number_format($status->total) }}</strong></td>
                            <td>{{ $totalComplaints > 0 ? number_format(($status->total / $totalComplaints) * 100, 1) : 0 }}%</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- الشكاوى حسب القسم -->
        @if(isset($data['department_distribution']) && $data['department_distribution']->count() > 0)
            <div class="section">
                <h2 class="section-title">توزيع الشكاوى حسب القسم</h2>
                <table>
                    <thead>
                    <tr>
                        <th>القسم</th>
                        <th>عدد الشكاوى</th>
                        <th>النسبة المئوية</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $totalDeptComplaints = $data['department_distribution']->sum('total');
                    @endphp
                    @foreach($data['department_distribution'] as $dept)
                        <tr>
                            <td>{{ $dept->department->name ?? 'غير محدد' }}</td>
                            <td><strong>{{ number_format($dept->total) }}</strong></td>
                            <td>{{ $totalDeptComplaints > 0 ? number_format(($dept->total / $totalDeptComplaints) * 100, 1) : 0 }}%</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- أكثر المواقع -->
        @if(isset($data['top_locations']) && $data['top_locations']->count() > 0)
            <div class="section">
                <h2 class="section-title">أكثر مواقع الشكاوى</h2>
                <table>
                    <thead>
                    <tr>
                        <th>الترتيب</th>
                        <th>الموقع</th>
                        <th>عدد الشكاوى</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($data['top_locations'] as $index => $location)
                        <tr>
                            <td><span class="badge badge-primary">{{ $index + 1 }}</span></td>
                            <td>{{ $location->location ?? 'غير محدد' }}</td>
                            <td><strong>{{ number_format($location->total) }}</strong></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- أكثر الموظفين نشاطاً -->
        @if(isset($data['top_employees']) && $data['top_employees']->count() > 0)
            <div class="section">
                <h2 class="section-title">أكثر الموظفين نشاطاً</h2>
                <table>
                    <thead>
                    <tr>
                        <th>الترتيب</th>
                        <th>اسم الموظف</th>
                        <th>الشكاوى المعالجة</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($data['top_employees'] as $index => $employee)
                        <tr>
                            <td><span class="badge badge-success">{{ $index + 1 }}</span></td>
                            <td>{{ $employee->user->f_name ?? '' }} {{ $employee->user->l_name ?? '' }}</td>
                            <td><strong>{{ number_format($employee->handled) }}</strong></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif


        <!-- أداء الموظفين -->
        @if(isset($data['employee_performance']) && $data['employee_performance']->count() > 0)
            <div class="section">
                <h2 class="section-title">أداء الموظفين</h2>
                <table>
                    <thead>
                    <tr>
                        <th>اسم الموظف</th>
                        <th>الشكاوى المعالجة</th>
                        <th>متوسط وقت المعالجة (دقائق)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($data['employee_performance'] as $employee)
                        <tr>
                            <td>{{ $employee->user->f_name ?? '' }} {{ $employee->user->l_name ?? '' }}</td>
                            <td><strong>{{ number_format((int) $employee->total_handled) }}</strong></td>
                            <td>{{ $employee->avg_processing_time ? number_format((int)$employee->avg_processing_time, 1) : 'غير متوفر' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="footer">
        <p>تم إنشاء هذا التقرير تلقائياً بواسطة نظام إدارة الشكاوى</p>
        <p style="margin-top: 8px; font-size: 12px;">© {{ date('Y') }} جميع الحقوق محفوظة</p>
    </div>
</div>
</body>
</html>
