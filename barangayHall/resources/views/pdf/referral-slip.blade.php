<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Referral Slip {{ $referral->referral_no }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.header', ['title' => 'Referral Slip'])

    @php($resident = $referral->resident)
    <table class="meta">
        <tr>
            <td><strong>Referral no.:</strong> {{ $referral->referral_no }}</td>
            <td style="text-align:center"><strong>Date:</strong> {{ $referral->referred_at->format('F j, Y g:i A') }}</td>
            <td style="text-align:right"><strong style="font-size:12px; {{ $referral->urgency->value === 'routine' ? '' : 'color:#b91c1c' }}">{{ strtoupper($referral->urgency->label()) }}</strong></td>
        </tr>
    </table>

    <table class="fields">
        <tr><td class="label">Referred to</td><td><strong>{{ $referral->facility->name }}</strong> ({{ $referral->facility->type->label() }}){{ $referral->facility->address ? ' · '.$referral->facility->address : '' }}{{ $referral->facility->contact_no ? ' · '.$referral->facility->contact_no : '' }}</td></tr>
    </table>

    <h2>Patient</h2>
    <table class="fields">
        <tr><td class="label">Name</td><td><strong>{{ $resident->full_name }}</strong></td></tr>
        <tr><td class="label">Resident ID</td><td>{{ $resident->resident_code }}</td></tr>
        <tr><td class="label">Sex / age</td><td>{{ $resident->sex->label() }} · {{ $resident->age }} years (born {{ $resident->birthdate->format('F j, Y') }})</td></tr>
        <tr><td class="label">Address</td><td>{{ $resident->household->address }}</td></tr>
        <tr><td class="label">Contact no.</td><td>{{ $resident->contact_no ?? '—' }}</td></tr>
        <tr><td class="label">PhilHealth no.</td><td>{{ $resident->philhealth_no ?? 'None recorded' }}</td></tr>
        <tr><td class="label">Blood type</td><td>{{ $resident->blood_type?->value ?? 'Unknown' }}</td></tr>
        @if ($contact)
            <tr><td class="label">Emergency contact</td><td>{{ $contact->name }} ({{ $contact->relationship }}) · {{ $contact->phone }}</td></tr>
        @endif
    </table>

    @if ($alerts->isNotEmpty())
        <div class="alert">
            <strong>MEDICAL ALERTS:</strong>
            @foreach ($alerts as $alert)
                <div>{{ $alert->severity->label() }} — {{ $alert->alert_type->label() }}: {{ $alert->description }}</div>
            @endforeach
        </div>
    @endif

    @if ($pregnancy)
        <h2>Pregnancy</h2>
        <table class="fields">
            <tr><td class="label">EDD / AOG</td><td>{{ $pregnancy->edd->format('F j, Y') }} · {{ $pregnancy->gestationalAgeWeeks() }} weeks</td></tr>
            <tr><td class="label">G / P · risk</td><td>G{{ $pregnancy->gravida }} P{{ $pregnancy->para }} · {{ $pregnancy->risk_level->label() }}{{ $pregnancy->riskFactors->isNotEmpty() ? ' ('.$pregnancy->riskFactors->pluck('name')->implode(', ').')' : '' }}</td></tr>
        </table>
    @endif

    <h2>Reason for referral</h2>
    <p style="margin:2px 0 6px; white-space: pre-line">{{ $referral->reason }}</p>

    @if ($visit)
        <h2>Findings at the health center ({{ $visit->visit_date->format('M j, Y') }})</h2>
        <table class="fields">
            @if ($visit->chief_complaint)<tr><td class="label">Chief complaint</td><td>{{ $visit->chief_complaint }}</td></tr>@endif
            @if ($visit->diagnosis)<tr><td class="label">Impression</td><td>{{ $visit->diagnosis }}</td></tr>@endif
            @if ($visit->vitalSign)
                @php($v = $visit->vitalSign)
                <tr><td class="label">Vital signs</td><td>
                    @if ($v->bp_systolic)BP {{ $v->bp_systolic }}/{{ $v->bp_diastolic }} mmHg · @endif
                    @if ($v->temperature_c)Temp {{ $v->temperature_c }} °C · @endif
                    @if ($v->pulse_rate)PR {{ $v->pulse_rate }}/min · @endif
                    @if ($v->respiratory_rate)RR {{ $v->respiratory_rate }}/min · @endif
                    @if ($v->weight_kg)Wt {{ $v->weight_kg }} kg @endif
                    @if ($v->height_cm)· Ht {{ $v->height_cm }} cm @endif
                </td></tr>
            @endif
        </table>
    @endif

    <table class="sign">
        <tr>
            <td><div style="height:22px"></div><div class="line">{{ $referral->referredBy->name }}<br><span class="muted">Referred by (signature over printed name)</span></div></td>
            <td><div style="height:22px"></div><div class="line">&nbsp;<br><span class="muted">Received by (receiving facility) · date / time</span></div></td>
        </tr>
    </table>

    <div class="cut">✂ - - - - - - - - - - - - RETURN SLIP — please complete and send back with the patient - - - - - - - - - - - - ✂</div>

    <p><strong>Return slip for {{ $referral->referral_no }}</strong> · {{ $resident->full_name }} ({{ $resident->resident_code }}) · to {{ $healthCenter['health_center'] }}, Barangay {{ $healthCenter['barangay'] }}</p>
    <table class="fields">
        <tr><td class="label">Date seen</td><td style="height:16px"></td></tr>
        <tr><td class="label">Findings / diagnosis</td><td style="height:34px"></td></tr>
        <tr><td class="label">Management given</td><td style="height:34px"></td></tr>
        <tr><td class="label">Recommendations / follow-up at the BHS</td><td style="height:34px"></td></tr>
    </table>
    <table class="sign">
        <tr>
            <td></td>
            <td><div style="height:18px"></div><div class="line">&nbsp;<br><span class="muted">Attending physician / health worker (signature over printed name)</span></div></td>
        </tr>
    </table>
</body>
</html>
