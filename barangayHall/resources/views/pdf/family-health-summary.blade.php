<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Family Health Summary {{ $household->household_no }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.header', ['title' => 'Family Health Summary'])

    <table class="meta">
        <tr>
            <td><strong>Household:</strong> {{ $household->household_no }} · {{ $household->purok->name }}</td>
            <td style="text-align:right"><strong>Head:</strong> {{ $household->head?->full_name ?? 'Not set' }}</td>
        </tr>
        <tr><td colspan="2">{{ $household->address }}</td></tr>
    </table>

    <table class="totals">
        <tr>
            <td style="width:25%"><div class="muted">Members</div><div class="value">{{ $members->count() }}</div></td>
            <td style="width:25%"><div class="muted">Drinking water</div><div>{{ $household->waterSource?->name ?? 'Not recorded' }}</div>@if ($household->waterSource)<div class="{{ $household->waterSource->is_safe ? 'muted' : 'overdue' }}">{{ $household->waterSource->is_safe ? 'Safe source' : 'UNSAFE source' }}</div>@endif</td>
            <td style="width:25%"><div class="muted">Toilet / sanitation</div><div>{{ $household->toiletType?->name ?? 'Not recorded' }}</div>@if ($household->toiletType)<div class="muted">{{ $household->toiletType->is_sanitary ? 'Sanitary' : 'Not sanitary' }}</div>@endif</td>
            <td style="width:25%"><div class="muted">Programs</div><div>{{ $household->is_4ps ? '4Ps household' : 'Not 4Ps' }}</div><div class="muted">{{ $household->isGeotagged() ? 'Location recorded' : 'Location not recorded' }}</div></td>
        </tr>
    </table>

    <h2>Members</h2>
    <table class="data">
        <tr><th>Name</th><th>Relationship</th><th>Sex</th><th class="num">Age</th><th>Blood type</th><th>Flags</th></tr>
        @foreach ($members as $m)
            <tr>
                <td><strong>{{ $m['resident']->full_name }}</strong><br><span class="muted">{{ $m['resident']->resident_code }}</span></td>
                <td>{{ $m['resident']->relationship_to_head->label() }}</td>
                <td>{{ $m['resident']->sex->label() }}</td>
                <td class="num">{{ $m['age'] }}</td>
                <td>{{ $m['resident']->blood_type?->value ?? '—' }}</td>
                <td>
                    @foreach ($m['flags'] as [$label, $red])
                        <span class="badge {{ $red ? 'red' : '' }}">{{ $label }}</span>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </table>

    <h2>Health details</h2>
    @php($withDetails = $members->filter(fn ($m) => $m['details'] !== []))
    @forelse ($withDetails as $m)
        <table class="fields" style="margin-bottom:6px">
            <tr><td colspan="2" style="background:#f1f5f9"><strong>{{ $m['resident']->full_name }}</strong></td></tr>
            @foreach ($m['details'] as [$label, $value])
                <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
            @endforeach
        </table>
    @empty
        <p class="muted">No conditions, alerts, pregnancies or child health items recorded for this household.</p>
    @endforelse

    @if ($contacts->isNotEmpty())
        <h2>Emergency contacts</h2>
        <table class="data">
            <tr><th>For</th><th>Contact</th><th>Relationship</th><th>Phone</th></tr>
            @foreach ($contacts as $c)
                <tr><td>{{ $c->resident->full_name }}</td><td>{{ $c->name }}{{ $c->is_primary ? ' (primary)' : '' }}</td><td>{{ $c->relationship }}</td><td>{{ $c->phone }}</td></tr>
            @endforeach
        </table>
    @endif

    <p class="muted" style="margin-top:10px">Family planning methods are listed only for members who consented to recording them. Diagnoses and visit notes are not included in this summary.</p>
</body>
</html>
