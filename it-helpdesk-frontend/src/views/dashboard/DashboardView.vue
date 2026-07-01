<template>
  <div>
    <!-- Range selector (segmented) -->
    <div class="flex items-center justify-between mb-6">
      <div class="inline-flex rounded-btn bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 p-1 shadow-soft">
        <button v-for="r in ranges" :key="r.value" @click="range = r.value; loadData()"
          class="px-3.5 py-1.5 rounded-[10px] text-sm font-semibold transition"
          :class="range === r.value
            ? 'bg-brand-600 text-white shadow-soft'
            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-700'">
          {{ t(`dashboard.range.${r.key}`) }}
        </button>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
      <StatCard variant="open" :icon="TicketIcon"
        :value="stats?.total_open ?? '—'" :label="t('dashboard.totalOpen')" />
      <StatCard variant="progress" :icon="ClockIcon"
        :value="stats?.avg_resolution_hours ? t('dashboard.hours', { n: stats.avg_resolution_hours }) : '—'"
        :label="t('dashboard.avgResolution')" />
      <StatCard variant="resolved" :icon="ShieldCheckIcon"
        :value="slaData?.overall_compliance != null ? `${slaData.overall_compliance}%` : '—'"
        :label="t('dashboard.slaCompliance')" />
      <StatCard variant="pending" :icon="ExclamationTriangleIcon"
        :value="slaData?.currently_at_risk ?? '—'" :label="t('dashboard.slaAtRisk')" />
    </div>

    <!-- Charts row 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
      <BaseCard>
        <h3 class="font-semibold text-gray-700 mb-4">{{ t('dashboard.trend') }}</h3>
        <apexchart v-if="trendSeries.length" type="line" height="220"
          :options="trendOptions" :series="trendSeries" />
      </BaseCard>
      <BaseCard>
        <h3 class="font-semibold text-gray-700 mb-4">{{ t('dashboard.ticketsByStatus') }}</h3>
        <apexchart v-if="statusSeries.length" type="donut" height="220"
          :options="statusOptions" :series="statusSeries" />
      </BaseCard>
    </div>

    <!-- Charts row 2 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
      <BaseCard>
        <h3 class="font-semibold text-gray-700 mb-4">{{ t('dashboard.ticketsByPriority') }}</h3>
        <apexchart v-if="prioritySeries.length" type="bar" height="200"
          :options="priorityOptions" :series="prioritySeries" />
      </BaseCard>
      <BaseCard class="lg:col-span-2">
        <h3 class="font-semibold text-gray-700 mb-4">{{ t('dashboard.ticketsByDept') }}</h3>
        <apexchart v-if="deptSeries.length" type="bar" height="200"
          :options="deptOptions" :series="deptSeries" />
      </BaseCard>
    </div>

    <!-- IT Staff workload -->
    <BaseCard>
      <h3 class="font-semibold text-gray-700 mb-4">{{ t('dashboard.itWorkload') }}</h3>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
        <div v-for="staff in stats?.it_staff_load" :key="staff.id"
          class="flex items-center gap-3 p-3 bg-gray-50 rounded-input">
          <div class="w-9 h-9 rounded-full bg-brand-100 flex items-center justify-center text-sm font-bold text-brand-700 flex-shrink-0">
            {{ staff.name?.[0]?.toUpperCase() }}
          </div>
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-800 truncate">{{ staff.name }}</p>
            <p class="text-xs text-gray-500">{{ staff.open_count }} open</p>
          </div>
        </div>
        <p v-if="!stats?.it_staff_load?.length" class="text-sm text-gray-400">No IT staff found</p>
      </div>
    </BaseCard>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { TicketIcon, ClockIcon, ShieldCheckIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { dashboardApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { useRouter } from 'vue-router'
import StatCard from '@/components/ui/StatCard.vue'
import BaseCard from '@/components/ui/BaseCard.vue'

const { t, locale } = useI18n()
const auth = useAuthStore()
const router = useRouter()

if (!auth.isItStaff) router.push({ name: 'tickets' })

const range = ref(30)
const ranges = [{ key: '7d', value: 7 }, { key: '30d', value: 30 }, { key: '90d', value: 90 }]

const stats = ref<any>(null)
const slaData = ref<any>(null)

async function loadData() {
  const [s, sla] = await Promise.all([
    dashboardApi.stats(range.value),
    dashboardApi.sla(range.value)
  ])
  stats.value = s.data
  slaData.value = sla.data
}

const theme = useThemeStore()

// ApexCharts defaults to dark text; follow the app theme so legends/axes
// stay readable in dark mode.
const baseChart = computed(() => ({
  toolbar: { show: false },
  foreColor: theme.isDark ? '#cbd5e1' : '#373d3f',
}))
const baseTooltip = computed(() => ({ theme: theme.isDark ? 'dark' : 'light' }))

const trendSeries = computed(() => {
  if (!stats.value?.trends?.length) return []
  return [
    { name: 'Created', data: stats.value.trends.map((t: any) => t.created) },
    { name: 'Resolved', data: stats.value.trends.map((t: any) => t.resolved) }
  ]
})

const trendOptions = computed(() => ({
  chart: baseChart.value,
  tooltip: baseTooltip.value,
  xaxis: { categories: stats.value?.trends?.map((t: any) => t.date) || [] },
  colors: ['#dc2626', '#16a34a'],
  stroke: { curve: 'smooth', width: 3 },
  legend: { position: 'top' }
}))

const statusLabels = ['open', 'in_progress', 'pending', 'resolved', 'closed']
// Aligned to the redesign palette: open=red, in_progress=blue, pending=amber,
// resolved=green, closed=slate.
const statusColors = ['#dc2626', '#2563eb', '#d97706', '#16a34a', '#64748b']

const statusSeries = computed(() =>
  stats.value ? statusLabels.map(s => stats.value.status_counts[s] || 0) : []
)
const statusOptions = computed(() => ({
  labels: statusLabels.map(s => t(`ticket.${s}`)),
  colors: statusColors,
  legend: { position: 'bottom' },
  tooltip: baseTooltip.value,
  chart: baseChart.value
}))

const priorityLabels = ['critical', 'high', 'medium', 'low']
const prioritySeries = computed(() =>
  stats.value ? [{ name: 'Open', data: priorityLabels.map(p => stats.value.priority_counts[p] || 0) }] : []
)
const priorityOptions = computed(() => ({
  chart: baseChart.value,
  tooltip: baseTooltip.value,
  xaxis: { categories: priorityLabels.map(p => t(`ticket.${p}`)) },
  colors: ['#dc2626', '#f97316', '#2563eb', '#64748b'],
  plotOptions: { bar: { distributed: true, borderRadius: 6 } },
  legend: { show: false }
}))

const deptSeries = computed(() =>
  stats.value?.department_counts?.length
    ? [{ name: 'Tickets', data: stats.value.department_counts.map((d: any) => d.count) }]
    : []
)
const deptOptions = computed(() => ({
  chart: baseChart.value,
  tooltip: baseTooltip.value,
  xaxis: {
    categories: stats.value?.department_counts?.map((d: any) =>
      locale.value === 'zh' ? d.department?.name_zh : d.department?.name) || []
  },
  colors: ['#dc2626'],
  plotOptions: { bar: { borderRadius: 6 } }
}))

onMounted(loadData)
</script>
