<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 12px;
        }
        h2 { text-align: center; margin-bottom: 20px; }
        table {
            width: 100%; border-collapse: collapse; margin-top: 10px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        td, th {
            padding: 6px;
        }
    </style>
</head>
<body>

<h2>{{ $title }}</h2>
<p><strong>Report date</strong> {{ $date }}</p>

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
