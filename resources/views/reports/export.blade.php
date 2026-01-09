<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>

    <style>

        @page {
            margin: 20mm;
        }

        body {
            font-family: 'Cairo', 'Tahoma', 'Arial', sans-serif;
            direction: rtl;
            text-align: left;
            font-size: 14px;
            line-height: 1.8;
            color: #333;
        }

        h2 {
            text-align: left;
            margin-bottom: 20px;
            color: #3e2723;
            font-size: 22px;
            font-weight: 700;
        }

        .report-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 4px solid #3e2723;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th {
            background-color: #3e2723;
            color: white;
            font-weight: 600;
            padding: 12px 10px;
            text-align: left;
        }

        td {
            padding: 10px;
            background-color: white;
            text-align: left;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>

<h2>{{ $title }}</h2>

<div class="report-info">
    <p><strong>Report Date:</strong> {{ $date }}</p>
</div>

<table>
    <thead>
    <tr>
        @foreach(array_keys($data->first()) as $column)
            <th>{{ $column }}</th>
        @endforeach
    </tr>
    </thead>

    <tbody>
    @foreach($data as $row)
        <tr>
            @foreach($row as $value)
                <td>{{ $value }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
