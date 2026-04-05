<div class="gd-date-filter">
    <button class="gd-date-filter__btn" :class="{ 'active': dateRange === '1h' }" @click="setDateRange('1h')">1h</button>
    <button class="gd-date-filter__btn" :class="{ 'active': dateRange === '6h' }" @click="setDateRange('6h')">6h</button>
    <button class="gd-date-filter__btn" :class="{ 'active': dateRange === '24h' }" @click="setDateRange('24h')">24h</button>
    <button class="gd-date-filter__btn" :class="{ 'active': dateRange === '7d' }" @click="setDateRange('7d')">7d</button>
    <button class="gd-date-filter__btn" :class="{ 'active': dateRange === '30d' }" @click="setDateRange('30d')">30d</button>
</div>
