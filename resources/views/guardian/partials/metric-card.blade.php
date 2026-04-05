<div class="gd-metric-card" :class="{ 'gd-metric-card--alert': {{ $alert ?? 'false' }} }">
    <div class="gd-metric-card__label">{{ $label }}</div>
    <div class="gd-metric-card__value" x-text="{{ $value }}"></div>
    @if(isset($subtitle))
    <div class="gd-metric-card__subtitle" x-text="{{ $subtitle }}"></div>
    @endif
</div>
