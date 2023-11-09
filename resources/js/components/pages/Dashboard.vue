<script>
import { inject, defineAsyncComponent } from 'vue'
import { Button as VueButton, Avatar } from 'flowbite-vue'

const TicketsRedistributionModal = defineAsyncComponent(() => import('@temps/dashboard/TicketsRedistributionModal.vue'))
const CountOfTicketsByDaysChartComponent = defineAsyncComponent(() => import('@temps/dashboard/CountOfTicketsByDaysChart.vue'))
const CountOfTicketsByManagersChartComponent = defineAsyncComponent(() => import('@temps/dashboard/CountOfTicketsByManagersChart.vue'))
const TicketsByGroupChartComponent = defineAsyncComponent(() => import('@temps/dashboard/TicketsByGroupChart.vue'))
const TicketsByReasonsChartComponent = defineAsyncComponent(() => import('@temps/dashboard/TicketsByReasonsChart.vue'))
const MarksPercentageChartComponent = defineAsyncComponent(() => import('@temps/dashboard/MarksPercentageChart.vue'))
const AverageSolvingTimeChartComponent = defineAsyncComponent(() => import('@temps/dashboard/AverageSolvingTimeChart.vue'))
const StatsByReasonsAndManagersPerDayChartComponent = defineAsyncComponent(() => import('@temps/dashboard/StatsByReasonsAndManagersPerDayChart.vue'))

// import TicketsRedistributionModal from '@temps/dashboard/TicketsRedistributionModal.vue'
// import TicketsByGroupChartComponent from '@temps/dashboard/TicketsByGroupChart.vue'
// import TicketsByReasonsChartComponent from '@temps/dashboard/TicketsByReasonsChart.vue'
// import MarksPercentageChartComponent from '@temps/dashboard/MarksPercentageChart.vue'

export default {
  name: 'DashboardPage',
  components: {
    VueButton, Avatar,
    TicketsRedistributionModal,
    CountOfTicketsByDaysChartComponent,
    CountOfTicketsByManagersChartComponent,
    TicketsByGroupChartComponent,
    TicketsByReasonsChartComponent,
    MarksPercentageChartComponent,
    AverageSolvingTimeChartComponent,
    StatsByReasonsAndManagersPerDayChartComponent,
  },
  data() {
    return {
      lostTickets: Array(),
      activeTickets: Array(),
      AllTickets: Array(),
      median: String(),
      avg_max_min: String(),
      avg_time_by_reasons: String(),
      waiting: Boolean(),
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
    }
  },
  setup() {
    const toast = inject('createToast')
    return { toast }
  },
  mounted() {
    this.GetActiveTickets()
    this.GetTicketsSolvingTimeMedian()
    this.GetAvgMaxMinTicketsPerDay()
    this.GetAvgTimeByReason()
  },
  methods: {
    GetActiveTickets() {
      this.ax.get('statistics/active_tickets').then(r => {
        if (r.data.status == false) {
          this.toast(r.data.message, 'error')
        }

        this.lostTickets = r.data.data.lost
        this.activeTickets = r.data.data.active
        this.AllTickets = this.lostTickets.concat(this.activeTickets)[0]
        this.errored = false
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    GetTicketsSolvingTimeMedian() {
      this.ax.get('statistics/tickets_solving_time_median').then(r => {
        if (r.data.status == false) {
          this.toast(r.data.message, 'error')
        }

        this.median = r.data.data
        this.errored = false
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    GetAvgMaxMinTicketsPerDay() {
      this.ax.get('statistics/avg_max_min_tickets_per_day').then(r => {
        if (r.data.status == false) {
          this.toast(r.data.message, 'error')
        }

        const f = r.data.data
        this.avg_max_min = 'Количество тикетов за день:\n'
          + `среднее: ${f.avg}\n`
          + `максимальное: ${f.max}\n`
          // + `минимальное: ${f.min}\n`
          + `за сегодня: ${f.today}`
        this.errored = false
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    GetAvgTimeByReason() {
      this.ax.get('statistics/avg_time_by_reasons').then(r => {
        if (r.data.status == false) {
          this.toast(r.data.message, 'error')
        }

        this.avg_time_by_reasons = 'Среднее время по темам:\n'
        r.data.data.forEach(d => {
          this.avg_time_by_reasons += `${d.name}: ${d.avg_time}\n`
        })
        this.errored = false
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    CacheReload() {
      if (this.waiting) return
      this.waiting = true

      this.ax.get('statistics/cache_reload').then(r => {
        if (r.data.status == true) {
          this.toast(r.data.message, 'success')
        }
        this.errored = r.data.status != true
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      }).finally(() => this.waiting = false)
    },
    ShowModal(data) {
      this.$refs.TicketsRedistribution.visible = true
      this.$refs.TicketsRedistribution.errored = false
      this.$refs.TicketsRedistribution.ticket = data
      this.$refs.TicketsRedistribution.GetAllManagers()
    },
  }
}
</script>

<template>
  <div class="fixed top-1 right-1 flex flex-row space-x-4 z-10">
    <VueButton :disabled="waiting" @click="CacheReload()" color="default">
      <span class="items-center font-semibold dark:text-gray-900">Обновить кеш</span>
    </VueButton>
  </div>

  <div class="grid grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2 m-2">
    <div class="flex flex-col gap-1">
      <p class="text-lg">Активные тикеты</p>
      <div
        class="flex flex-col gap-1 max-h-[calc(100vh-54px-45px)] overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
        <template v-for="(t, name) in AllTickets" v-bind:key="t">
          <div class="flex flex-row gap-1 items-center rounded-xl p-1 dark:text-gray-900 bg-gray-100 hover:bg-blue-50">
            <div class="min-w-[40px]">
              <a :href="VITE_CRM_URL + 'company/personal/user/' + t[Object.keys(t)[0]].manager.crm_id + '/'"
                target="_blank">
                <Avatar rounded size="sm" alt="avatar" :title="name"
                  :img="t[Object.keys(t)[0]].manager.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
              </a>
            </div>

            <div class="w-full flex flex-col">
              <p class="font-bold">{{ name }}</p>
              <template v-for="(r, reason_name) in t" v-bind:key="r">
                <span class="w-full flex flex-row items-center">
                  <p class="flex-1">{{ reason_name }}: <span class="font-bold">{{ r.tickets_count }}</span>
                  </p>
                  <p @click="ShowModal(r)" title="Распределить" class="flex-none font-bold">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                      stroke="currentColor"
                      class="w-5 h-5 cursor-pointer text-blue-500 hover:text-blue-400 hover:underline">
                      <path stroke-linecap="round" stroke-linejoin="round"
                        d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                    </svg>
                  </p>
                </span>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>

    <div class="col-span-2 lg:col-span-3 xl:col-span-4 flex flex-col space-x-2">
      <p>Медиана времени решения тикетов: </p>
      <p class="underline font-bold">{{ median }}</p>
      <p class="whitespace-pre">{{ avg_max_min }}</p>
      <p class="whitespace-pre">{{ avg_time_by_reasons }}</p>
    </div>
  </div>

  <div class="grid grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2 m-2">
    <div class="col-span-3 lg:col-span-4 xl:col-span-5">
      <CountOfTicketsByDaysChartComponent />
    </div>
    <div class="col-span-3 lg:col-span-4 xl:col-span-5">
      <CountOfTicketsByManagersChartComponent />
    </div>
    <div class="col-span-3 lg:col-span-4 xl:col-span-5">
      <MarksPercentageChartComponent />
    </div>
    <div class="col-span-3 lg:col-span-4 xl:col-span-5">
      <TicketsByReasonsChartComponent />
    </div>
    <div class="col-span-3 lg:col-span-4 xl:col-span-5">
      <TicketsByGroupChartComponent />
    </div>
    <div class="col-span-3 lg:col-span-4 xl:col-span-5">
      <AverageSolvingTimeChartComponent />
    </div>
    <div class="col-span-3 lg:col-span-4 xl:col-span-5">
      <StatsByReasonsAndManagersPerDayChartComponent />
    </div>
  </div>

  <!-- Modals -->
  <TicketsRedistributionModal ref="TicketsRedistribution" />
</template>