{{-- Letterhead + confidentiality footer shared by every PDF. Expects $healthCenter, $title, $generatedBy. --}}
<div class="footer">
    CONFIDENTIAL — contains health information protected under RA 10173 (Data Privacy Act of 2012).
    Generated {{ now()->format('F j, Y g:i A') }} by {{ $generatedBy }}.
</div>

<div class="header">
    <p class="muted">Republic of the Philippines · Province of {{ $healthCenter['province'] }} · Municipality of {{ $healthCenter['municipality'] }}</p>
    <h1>{{ $healthCenter['health_center'] }} — Barangay {{ $healthCenter['barangay'] }}</h1>
    <p><strong>{{ $title }}</strong></p>
</div>
