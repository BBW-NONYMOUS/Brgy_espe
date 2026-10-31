<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }} Report</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.header', ['title' => $report['title'].' Report'])

    <table class="meta">
        <tr>
            <td><strong>Period:</strong> {{ \Illuminate\Support\Carbon::parse($report['period']['from'])->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($report['period']['to'])->format('M j, Y') }}</td>
            <td style="text-align:right"><strong>Purok:</strong> {{ $report['purok'] ?? 'All puroks' }}</td>
        </tr>
        @if (! empty($report['scope_note']))
            <tr><td colspan="2" class="muted">{{ $report['scope_note'] }}</td></tr>
        @endif
    </table>

    <table class="totals">
        @foreach (array_chunk($report['totals'], 5) as $row)
            <tr>
                @foreach ($row as $total)
                    <td style="width: {{ 100 / max(count($row), 1) }}%">
                        <div class="muted">{{ $total['label'] }}</div>
                        <div class="value">{{ is_numeric($total['value']) ? number_format($total['value'], is_float($total['value']) ? 1 : 0) : $total['value'] }}</div>
                        @if (! empty($total['hint']))<div class="muted">{{ $total['hint'] }}</div>@endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    @foreach ($report['tables'] as $table)
        <h2>{{ $table['title'] }}</h2>
        @if ($table['description'])<p class="muted" style="margin:0 0 4px">{{ $table['description'] }}</p>@endif
        <table class="data">
            <tr>
                @foreach ($table['columns'] as $column)
                    <th class="{{ $column['numeric'] ? 'num' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
            @forelse ($table['rows'] as $row)
                <tr>
                    @foreach ($table['columns'] as $column)
                        <td class="{{ $column['numeric'] ? 'num' : '' }}">{{ $row[$column['key']] ?? '—' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($table['columns']) }}" class="muted">{{ $table['empty'] }}</td></tr>
            @endforelse
        </table>
    @endforeach
</body>
</html>
