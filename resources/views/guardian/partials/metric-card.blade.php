<div class="gd-stat-card" :class="{ 'gd-stat-card--alert': {{ $alert ?? 'false' }} }">
    <div class="gd-stat-card__header">
        <span class="gd-stat-card__dot" style="background: var(--gd-{{ $color ?? 'accent' }})"></span>
        <span class="gd-stat-card__label">{{ $label ?? $title ?? '' }}</span>
    </div>
    <div class="gd-stat-card__value" x-text="{{ $value ?? $xValue ?? "'—'" }}"></div>
    @if(isset($subtitle))
    <div class="gd-stat-card__trend" x-text="{{ $subtitle }}"></div>
    @endif
</div>
