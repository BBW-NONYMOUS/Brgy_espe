<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Health Summary Report</title>
    <style>
        @page { margin: 18mm 14mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0; }
        h2 { font-size: 11px; margin: 14px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #0f766e; color: #0f766e; text-transform: uppercase; letter-spacing: .03em; }
        .header { text-align: center; margin-bottom: 8px; }
        .header p { margin: 1px 0; }
        .meta { width: 100%; margin-bottom: 6px; }
        .meta td { padding: 1px 0; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 3px 5px; text-align: left; }
        table.data th { background: #f1f5f9; font-weight: bold; }
        table.data td.num, table.data th.num { text-align: right; }
        .totals { width: 100%; border-collapse: separate; border-spacing: 4px; }
        .totals td { border: 1px solid #cbd5e1; padding: 5px; width: 20%; vertical-align: top; }
        .totals .value { font-size: 15px; font-weight: bold; color: #0f766e; }
        .muted { color: #64748b; }
        .overdue { color: #b91c1c; font-weight: bold; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">
        CONFIDENTIAL — contains health information protected under RA 10173 (Data Privacy Act of 2012).
        Generated {{ now()->format('F j, Y g:i A') }} by {{ $generatedBy }}.
    </div>

    <div class="header">
        <p class="muted">Republic of the Philippines · Province of {{ $healthCenter['province'] }} · Municipality of {{ $healthCenter['municipality'] }}</p>
        <h1>{{ $healthCenter['health_center'] }} — Barangay {{ $healthCenter['barangay'] }}</h1>
        <p><strong>Resident Health Summary Report</strong></p>
    </div>

    <table class="meta">
        <tr>
            <td><strong>Period:</strong> {{ \Illuminate\Support\Carbon::parse($summary['period']['from'])->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($summary['period']['to'])->format('M j, Y') }}</td>
            <td style="text-align:right"><strong>Purok:</strong> {{ $summary['purok'] ?? 'All puroks' }}</td>
        </tr>
    </table>

    @php
        $t = $summary['totals'];
        $cards = [
            ['Residents', $t['residents']], ['Households', $t['households']], ['Senior citizens', $t['seniors']],
            ['Pregnant women', $t['pregnant']], ['Children due for vaccines', $t['children_due']],
            ['Male', $t['male']], ['Female', $t['female']], ['PWDs', $t['pwd']], ['4Ps members', $t['four_ps']], ['Infants (<1)', $t['infants']],
        ];
    @endphp
    <table class="totals">
        @foreach (array_chunk($cards, 5) as $row)
            <tr>
                @foreach ($row as [$label, $value])
                    <td><div class="muted">{{ $label }}</div><div class="value">{{ number_format($value) }}</div></td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <h2>Population per purok</h2>
    <table class="data">
        <tr><th>Purok</th><th class="num">Households</th><th class="num">Residents</th><th class="num">Male</th><th class="num">Female</th></tr>
        @foreach ($summary['population'] as $row)
            <tr><td>{{ $row['purok'] }}</td><td class="num">{{ $row['households'] }}</td><td class="num">{{ $row['residents'] }}</td><td class="num">{{ $row['male'] }}</td><td class="num">{{ $row['female'] }}</td></tr>
        @endforeach
    </table>

    <h2>Age and sex distribution</h2>
    <table class="data">
        <tr><th>Age group</th><th class="num">Male</th><th class="num">Female</th><th class="num">Total</th></tr>
        @foreach ($summary['age_sex'] as $row)
            <tr><td>{{ $row['age_group'] }}</td><td class="num">{{ $row['male'] }}</td><td class="num">{{ $row['female'] }}</td><td class="num">{{ $row['total'] }}</td></tr>
        @endforeach
    </table>

    <h2>Common conditions (active / controlled)</h2>
    <table class="data">
        <tr><th>Condition</th><th>ICD-10</th><th class="num">Residents</th></tr>
        @forelse ($summary['conditions'] as $row)
            <tr><td>{{ $row['condition'] }}</td><td>{{ $row['icd10_code'] }}</td><td class="num">{{ $row['residents'] }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No recorded conditions.</td></tr>
        @endforelse
    </table>

    <table style="width:100%"><tr>
        <td style="width:50%; vertical-align:top; padding-right:6px">
            <h2>Health visits in period</h2>
            <table class="data">
                <tr><th>Visit type</th><th class="num">Visits</th></tr>
                @forelse ($summary['visits'] as $row)
                    <tr><td>{{ $row['visit_type'] }}</td><td class="num">{{ $row['visits'] }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">No visits in this period.</td></tr>
                @endforelse
            </table>
        </td>
        <td style="width:50%; vertical-align:top; padding-left:6px">
            <h2>Immunizations given in period</h2>
            <table class="data">
                <tr><th>Vaccine</th><th class="num">Doses</th></tr>
                @forelse ($summary['immunizations'] as $row)
                    <tr><td>{{ $row['vaccine'] }}</td><td class="num">{{ $row['doses'] }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">No immunizations in this period.</td></tr>
                @endforelse
            </table>
        </td>
    </tr></table>

    @if ($lists)
        <h2>Children due for immunization</h2>
        <table class="data">
            <tr><th>Code</th><th>Name</th><th class="num">Age (mo)</th><th>Purok</th><th>Doses due</th><th>Earliest due</th></tr>
            @forelse ($lists['children_due'] as $row)
                <tr>
                    <td>{{ $row['resident_code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ $row['age_months'] }}</td>
                    <td>{{ $row['purok'] }}</td><td>{{ implode(', ', $row['pending']) }}</td>
                    <td class="{{ $row['overdue'] ? 'overdue' : '' }}">{{ $row['earliest_due'] }}{{ $row['overdue'] ? ' (overdue)' : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No children are due for immunization.</td></tr>
            @endforelse
        </table>

        <h2>Pregnant women</h2>
        <table class="data">
            <tr><th>Code</th><th>Name</th><th class="num">Age</th><th>Purok</th><th>EDD</th><th class="num">AOG (wks)</th><th>G/P</th><th>Risk</th></tr>
            @forelse ($lists['pregnant'] as $row)
                <tr>
                    <td>{{ $row['resident_code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ $row['age'] }}</td><td>{{ $row['purok'] }}</td>
                    <td>{{ $row['edd'] }}</td><td class="num">{{ $row['aog_weeks'] }}</td><td>G{{ $row['gravida'] }}P{{ $row['para'] }}</td><td>{{ $row['risk_level'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No ongoing pregnancies recorded.</td></tr>
            @endforelse
        </table>
    @endif
</body>
</html>
