<template>
  <div class="stats-root">
    <!-- kopsummas -->
    <div class="stat-tiles">
      <div class="stat-tile" data-testid="stat-users">
        <div class="stat-label">{{ t('admin.stats.users') }}</div>
        <div class="stat-value">{{ stats.totals.users }}</div>
        <div class="stat-sub">{{ t('admin.stats.newThisWeek').replace('{n}', stats.totals.new_users_week) }}</div>
      </div>
      <div class="stat-tile">
        <div class="stat-label">{{ t('admin.stats.books') }}</div>
        <div class="stat-value">{{ stats.totals.books }}</div>
        <div class="stat-sub">{{ t('admin.stats.genreCount').replace('{n}', genreTotal) }}</div>
      </div>
      <div class="stat-tile">
        <div class="stat-label">{{ t('admin.stats.swapsDone') }}</div>
        <div class="stat-value">{{ stats.totals.swaps_accepted }}</div>
        <div class="stat-sub">{{ t('admin.stats.pending').replace('{n}', stats.totals.swaps_pending) }}</div>
      </div>
      <div class="stat-tile">
        <div class="stat-label">{{ t('admin.stats.rating') }}</div>
        <div class="stat-value">{{ stats.totals.rating_avg ?? '-' }}<span v-if="stats.totals.rating_avg" class="stat-unit"> / 5</span></div>
        <div class="stat-sub">{{ t('admin.stats.ratingCount').replace('{n}', stats.totals.rating_count) }}</div>
      </div>
    </div>

    <div class="chart-grid">
      <!-- jauni lietotāji pa nedēļām - stabiņi -->
      <section class="chart-card" data-testid="chart-users">
        <h3 class="chart-title">{{ t('admin.stats.newUsers') }}</h3>
        <p class="chart-sub">{{ t('admin.stats.last12') }}</p>
        <div ref="usersBox" class="chart-box" @mouseleave="hover = null">
          <svg :width="w.users" :height="H" role="img" :aria-label="t('admin.stats.newUsers')">
            <g v-for="tick in ticks(usersMax)" :key="tick">
              <line :x1="PL" :x2="w.users - PR" :y1="y(tick, usersMax)" :y2="y(tick, usersMax)" class="grid" />
              <text :x="PL - 6" :y="y(tick, usersMax) + 4" class="axis" text-anchor="end">{{ tick }}</text>
            </g>
            <path v-for="(wk, i) in stats.weeks" :key="'b' + i"
              :d="barPath(i, wk.users, usersMax, w.users)" class="bar"
              :class="{ dim: hover && hover.chart === 'users' && hover.i !== i }" />
            <!-- tikai lielākā un pēdējā vērtība -->
            <template v-for="(wk, i) in stats.weeks" :key="'l' + i">
              <text v-if="wk.users > 0 && labelled(usersSeries, i)" :x="cx(i, w.users)" :y="y(wk.users, usersMax) - 6" class="val" text-anchor="middle">{{ wk.users }}</text>
            </template>
            <text v-for="(wk, i) in stats.weeks" v-show="showX(i, w.users)" :key="'x' + i"
              :x="cx(i, w.users)" :y="H - 6" class="axis" text-anchor="middle">{{ short(wk.start) }}</text>
            <line :x1="PL" :x2="w.users - PR" :y1="H - PB" :y2="H - PB" class="base" />
            <!-- lielāks laukums peles kursoram nekā pats stabiņš -->
            <rect v-for="(wk, i) in stats.weeks" :key="'h' + i" :x="PL + i * band(w.users)" :y="PT" :width="band(w.users)" :height="H - PT - PB"
              class="hit" @mouseenter="hover = { chart: 'users', i }" />
          </svg>
          <div v-if="hover && hover.chart === 'users'" class="tip" :style="tipStyle(w.users)">
            <div class="tip-head">{{ weekRange(stats.weeks[hover.i].start) }}</div>
            <div><b>{{ stats.weeks[hover.i].users }}</b> {{ t('admin.stats.newUsersShort') }}</div>
          </div>
        </div>
        <details class="chart-table">
          <summary>{{ t('admin.stats.table') }}</summary>
          <table class="table table-sm mb-0">
            <thead><tr><th>{{ t('admin.stats.week') }}</th><th class="text-end">{{ t('admin.stats.count') }}</th></tr></thead>
            <tbody><tr v-for="wk in stats.weeks" :key="wk.start"><td>{{ weekRange(wk.start) }}</td><td class="text-end">{{ wk.users }}</td></tr></tbody>
          </table>
        </details>
      </section>

      <!-- apmaiņas pa nedēļām - 2 līnijas -->
      <section class="chart-card" data-testid="chart-swaps">
        <h3 class="chart-title">{{ t('admin.stats.swapsWeek') }}</h3>
        <p class="chart-sub">{{ t('admin.stats.last12') }}</p>
        <div class="legend">
          <span><i class="key s1"></i>{{ t('admin.stats.requests') }}</span>
          <span><i class="key s2"></i>{{ t('admin.stats.accepted') }}</span>
        </div>
        <div ref="swapsBox" class="chart-box" @mouseleave="hover = null">
          <svg :width="w.swaps" :height="H" role="img" :aria-label="t('admin.stats.swapsWeek')">
            <g v-for="tick in ticks(swapsMax)" :key="tick">
              <line :x1="PL" :x2="w.swaps - PR" :y1="y(tick, swapsMax)" :y2="y(tick, swapsMax)" class="grid" />
              <text :x="PL - 6" :y="y(tick, swapsMax) + 4" class="axis" text-anchor="end">{{ tick }}</text>
            </g>
            <text v-for="(wk, i) in stats.weeks" v-show="showX(i, w.swaps)" :key="'x' + i"
              :x="cx(i, w.swaps)" :y="H - 6" class="axis" text-anchor="middle">{{ short(wk.start) }}</text>
            <line :x1="PL" :x2="w.swaps - PR" :y1="H - PB" :y2="H - PB" class="base" />
            <line v-if="hover && hover.chart === 'swaps'" :x1="cx(hover.i, w.swaps)" :x2="cx(hover.i, w.swaps)" :y1="PT" :y2="H - PB" class="cross" />
            <polyline :points="linePoints('requests', w.swaps)" class="line s1" />
            <polyline :points="linePoints('accepted', w.swaps)" class="line s2" />
            <!-- pēdējās nedēļas vērtība līnijas galā -->
            <text v-for="e in endLabels" :key="e.key" :x="cx(stats.weeks.length - 1, w.swaps) + 8" :y="e.y + 4" class="val">{{ e.v }}</text>
            <template v-if="hover && hover.chart === 'swaps'">
              <circle :cx="cx(hover.i, w.swaps)" :cy="y(stats.weeks[hover.i].requests, swapsMax)" r="4.5" class="dot s1" />
              <circle :cx="cx(hover.i, w.swaps)" :cy="y(stats.weeks[hover.i].accepted, swapsMax)" r="4.5" class="dot s2" />
            </template>
            <rect v-for="(wk, i) in stats.weeks" :key="'h' + i" :x="PL + i * band(w.swaps)" :y="PT" :width="band(w.swaps)" :height="H - PT - PB"
              class="hit" @mouseenter="hover = { chart: 'swaps', i }" />
          </svg>
          <div v-if="hover && hover.chart === 'swaps'" class="tip" :style="tipStyle(w.swaps)">
            <div class="tip-head">{{ weekRange(stats.weeks[hover.i].start) }}</div>
            <div><i class="key s1"></i>{{ t('admin.stats.requests') }} <b>{{ stats.weeks[hover.i].requests }}</b></div>
            <div><i class="key s2"></i>{{ t('admin.stats.accepted') }} <b>{{ stats.weeks[hover.i].accepted }}</b></div>
          </div>
        </div>
        <details class="chart-table">
          <summary>{{ t('admin.stats.table') }}</summary>
          <table class="table table-sm mb-0">
            <thead><tr><th>{{ t('admin.stats.week') }}</th><th class="text-end">{{ t('admin.stats.requests') }}</th><th class="text-end">{{ t('admin.stats.accepted') }}</th></tr></thead>
            <tbody><tr v-for="wk in stats.weeks" :key="wk.start"><td>{{ weekRange(wk.start) }}</td><td class="text-end">{{ wk.requests }}</td><td class="text-end">{{ wk.accepted }}</td></tr></tbody>
          </table>
        </details>
      </section>

      <!-- žanri un pilsētas - horizontāli stabiņi -->
      <section v-for="list in lists" :key="list.key" class="chart-card" :data-testid="'chart-' + list.key">
        <h3 class="chart-title">{{ t(list.title) }}</h3>
        <p class="chart-sub">{{ t(list.sub) }}</p>
        <p v-if="!list.rows.length" class="chart-empty">{{ t('admin.stats.empty') }}</p>
        <ul class="hbars">
          <li v-for="(r, i) in list.rows" :key="i" class="hbar-row"
            :class="{ rest: r.name === null }"
            :title="(r.name ?? t(list.rest)) + ': ' + r.count">
            <span class="hbar-name">{{ r.name ?? t(list.rest) }}</span>
            <span class="hbar-track"><span class="hbar-fill" :style="{ width: (r.count / list.max * 100) + '%' }"></span></span>
            <span class="hbar-val">{{ r.count }}</span>
          </li>
        </ul>
        <p v-if="list.note" class="chart-note">{{ list.note }}</p>
      </section>
    </div>
  </div>
</template>

<script>
import langMixin from '../langMixin.js'

export default {
  name: 'AdminStats',
  mixins: [langMixin],
  props: { stats: { type: Object, required: true } },

  data() {
    return {
      // grafika izmēri - platumu nolasa no kastes, augstums fiksēts
      H: 220, PL: 30, PR: 26, PT: 14, PB: 26,
      w: { users: 400, swaps: 400 },
      hover: null,
      observer: null,
    }
  },

  computed: {
    usersSeries() { return this.stats.weeks.map(w => w.users) },
    usersMax() { return this.niceMax(Math.max(...this.usersSeries)) },
    swapsMax() { return this.niceMax(Math.max(...this.stats.weeks.flatMap(w => [w.requests, w.accepted]))) },
    // līniju galu vērtības - ja tuvu viena otrai, pabīda, lai nepārklājas
    endLabels() {
      const last = this.stats.weeks[this.stats.weeks.length - 1]
      if (!last) return []
      const a = { key: 'r', v: last.requests, y: this.y(last.requests, this.swapsMax) }
      const b = { key: 'a', v: last.accepted, y: this.y(last.accepted, this.swapsMax) }
      if (Math.abs(a.y - b.y) < 12) {
        const mid = (a.y + b.y) / 2
        a.y = mid - 6
        b.y = mid + 6
      }
      return [a, b]
    },
    genreTotal() { return this.stats.genres.filter(g => g.name).length },

    lists() {
      const max = rows => Math.max(1, ...rows.map(r => r.count))
      return [
        { key: 'genres', title: 'admin.stats.genres', sub: 'admin.stats.genresSub', rest: 'admin.stats.rest', rows: this.stats.genres, max: max(this.stats.genres) },
        {
          key: 'cities', title: 'admin.stats.cities', sub: 'admin.stats.citiesSub', rest: 'admin.stats.restCities',
          rows: this.stats.cities, max: max(this.stats.cities),
          note: this.t('admin.stats.noLocation').replace('{n}', this.stats.no_location).replace('{m}', this.stats.elsewhere),
        },
      ]
    },
  },

  mounted() {
    // pārzīmē, kad mainās loga platums
    this.observer = new ResizeObserver(() => this.measure())
    this.observer.observe(this.$refs.usersBox)
    this.observer.observe(this.$refs.swapsBox)
    this.measure()
  },

  beforeUnmount() {
    this.observer?.disconnect()
  },

  methods: {
    measure() {
      this.w.users = Math.max(240, this.$refs.usersBox?.clientWidth || 400)
      this.w.swaps = Math.max(240, this.$refs.swapsBox?.clientWidth || 400)
    },

    // augšējā robeža, kas dalās ar 4 (asij 4 vienādi soļi)
    niceMax(v) {
      return Math.max(4, Math.ceil(v / 4) * 4)
    },
    ticks(max) {
      return [0, 1, 2, 3, 4].map(k => (max / 4) * k)
    },

    band(width) { return (width - this.PL - this.PR) / this.stats.weeks.length },
    cx(i, width) { return this.PL + this.band(width) * (i + 0.5) },
    y(v, max) { return this.PT + (this.H - this.PT - this.PB) * (1 - v / max) },

    // stabiņš ar noapaļotu augšu, apakša pie ass taisna
    barPath(i, v, max, width) {
      if (!v) return ''
      const bw = Math.min(28, this.band(width) * 0.6)
      const x = this.cx(i, width) - bw / 2
      const base = this.H - this.PB
      const top = this.y(v, max)
      const r = Math.min(4, bw / 2, base - top)
      return `M${x},${base} V${top + r} Q${x},${top} ${x + r},${top} H${x + bw - r} Q${x + bw},${top} ${x + bw},${top + r} V${base} Z`
    },

    linePoints(key, width) {
      return this.stats.weeks.map((wk, i) => `${this.cx(i, width)},${this.y(wk[key], this.swapsMax)}`).join(' ')
    },

    // vērtību raksta tikai pie lielākā un pēdējā stabiņa
    labelled(series, i) {
      const max = Math.max(...series)
      return i === series.length - 1 || (series[i] === max && series.indexOf(max) === i)
    },

    // šaurā grafikā rāda katru otro datumu
    showX(i, width) {
      const every = this.band(width) < 38 ? 2 : 1
      return (this.stats.weeks.length - 1 - i) % every === 0
    },

    short(date) {
      const [, m, d] = date.split('-')
      return `${d}.${m}`
    },
    weekRange(date) {
      const start = new Date(date + 'T00:00:00')
      const end = new Date(start)
      end.setDate(end.getDate() + 6)
      const f = d => String(d.getDate()).padStart(2, '0') + '.' + String(d.getMonth() + 1).padStart(2, '0')
      return `${f(start)} - ${f(end)}`
    },

    // tooltip blakus izvēlētajai nedēļai, labajā pusē - pa kreisi
    tipStyle(width) {
      const x = this.cx(this.hover.i, width)
      return x > width / 2 ? { right: (width - x + 12) + 'px', top: '8px' } : { left: (x + 12) + 'px', top: '8px' }
    },
  },
}
</script>

<style scoped>
.stats-root {
  --s-bar: #3a6b3e;
  --s-1: #2a78d6;
  --s-2: #eb6834;
  --s-ink: #1a1612;
  --s-ink-2: #52514e;
  --s-muted: #898781;
  --s-grid: #e1e0d9;
  --s-base: #c3c2b7;
  --s-card: #fff;
  --s-track: #f0efec;
}

.stat-tiles {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}
.stat-tile {
  background: var(--s-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px 16px;
}
.stat-label { font-size: .8rem; color: var(--s-ink-2); }
.stat-value { font-size: 1.9rem; font-weight: 700; color: var(--s-ink); line-height: 1.2; font-variant-numeric: tabular-nums; }
.stat-unit { font-size: 1rem; font-weight: 500; color: var(--s-muted); }
.stat-sub { font-size: .78rem; color: var(--s-muted); }

.chart-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.chart-card {
  background: var(--s-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  min-width: 0;
}
.chart-title { font-size: 1rem; font-weight: 600; color: var(--s-ink); margin: 0; }
.chart-sub { font-size: .8rem; color: var(--s-muted); margin: 2px 0 8px; }
.chart-box { position: relative; width: 100%; }
.chart-box svg { display: block; }
.chart-empty, .chart-note { font-size: .8rem; color: var(--s-muted); margin: 8px 0 0; }

.grid { stroke: var(--s-grid); stroke-width: 1; }
.base { stroke: var(--s-base); stroke-width: 1; }
.axis { font-size: 11px; fill: var(--s-muted); }
.val { font-size: 11px; font-weight: 600; fill: var(--s-ink-2); }
.bar { fill: var(--s-bar); transition: opacity .15s; }
.bar.dim { opacity: .45; }
.hit { fill: transparent; cursor: default; }
.cross { stroke: var(--s-base); stroke-width: 1; stroke-dasharray: 3 3; }
.line { fill: none; stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; }
.line.s1 { stroke: var(--s-1); }
.line.s2 { stroke: var(--s-2); }
.dot { stroke: var(--s-card); stroke-width: 2; }
.dot.s1 { fill: var(--s-1); }
.dot.s2 { fill: var(--s-2); }

.legend { display: flex; gap: 14px; font-size: .8rem; color: var(--s-ink-2); margin-bottom: 4px; }
.key { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
.key.s1 { background: var(--s-1); }
.key.s2 { background: var(--s-2); }

.tip {
  position: absolute;
  pointer-events: none;
  background: var(--s-card);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 6px 10px;
  font-size: .8rem;
  color: var(--s-ink-2);
  box-shadow: 0 4px 12px rgba(0, 0, 0, .12);
  white-space: nowrap;
}
.tip-head { font-weight: 600; color: var(--s-ink); margin-bottom: 2px; }
.tip b { color: var(--s-ink); }

.chart-table { margin-top: 8px; font-size: .8rem; }
.chart-table summary { color: var(--s-muted); cursor: pointer; }

.hbars { list-style: none; padding: 0; margin: 4px 0 0; display: flex; flex-direction: column; gap: 8px; }
.hbar-row { display: grid; grid-template-columns: minmax(80px, 34%) 1fr 32px; align-items: center; gap: 8px; font-size: .85rem; }
.hbar-name { color: var(--s-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hbar-track { height: 14px; background: var(--s-track); border-radius: 4px; overflow: hidden; }
.hbar-fill { display: block; height: 100%; background: var(--s-bar); border-radius: 0 4px 4px 0; }
.hbar-val { text-align: right; color: var(--s-ink-2); font-variant-numeric: tabular-nums; }
.hbar-row.rest .hbar-name { color: var(--s-muted); font-style: italic; }
.hbar-row.rest .hbar-fill { background: var(--s-muted); }
.hbar-row:hover .hbar-fill { filter: brightness(1.15); }

@media (max-width: 767.98px) {
  .stat-tiles { grid-template-columns: 1fr 1fr; gap: 8px; }
  .stat-value { font-size: 1.5rem; }
  .chart-grid { grid-template-columns: 1fr; }
}
</style>

<style>
/* tumšais režīms - bez scoped, citādi [data-theme] nestrādā */
[data-theme="dark"] .stats-root {
  --s-bar: #6aab6e;
  --s-1: #3987e5;
  --s-2: #d95926;
  --s-ink: #f0efec;
  --s-ink-2: #c3c2b7;
  --s-muted: #898781;
  --s-grid: #2e2820;
  --s-base: #4a4038;
  --s-card: #211d18;
  --s-track: #2e2820;
}
</style>
