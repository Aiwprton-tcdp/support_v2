<script>
import { Button as VueButton } from 'flowbite-vue'
import * as echarts from 'echarts'

import { GridComponent } from 'echarts/components'
import { LineChart } from 'echarts/charts'
import { UniversalTransition } from 'echarts/features'
import { CanvasRenderer } from 'echarts/renderers'

echarts.use([
  GridComponent,
  LineChart,
  CanvasRenderer,
  UniversalTransition
])

export default {
  name: 'CountOfTicketsByDaysChartComponent',
  components: { VueButton },
  data() {
    return {
      waiting: Boolean(),
      errored: Boolean()
    }
  },
  mounted() {
    this.InitCountOfTicketsByDays()
  },
  methods: {
    InitCountOfTicketsByDays() {
      this.ax.get('statistics/count_of_tickets_by_days').then(r => {
        const d = r.data.data

        const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)")
        let theme = darkThemeMq.matches ? 'dark' : ''
        const chart = echarts.init(document.getElementById('count_of_tickets_by_days'), theme)

        chart.setOption({
          title: {
            text: 'Общее количество тикетов в разрезе дня',
            left: 'center'
          },
          xAxis: {
            type: 'category',
            boundaryGap: false,
            data: d.map(r => r.date)
          },
          yAxis: {
            type: 'value'
          },
          toolbox: {
            feature: {
              restore: {},
              saveAsImage: {}
            }
          },
          series: [
            {
              data: d.map(r => r.count),
              type: 'line'
            }
          ]
        })
      }).catch(e => {
        console.log(e)
        this.errored = true
      })
    }
  }
}
</script>

<template>
  <div class="flex flex-col gap-1">
    <template v-if="waiting">
      <div>Загрузка</div>
    </template>
    <template v-else-if="errored">
      <div>Ошибка</div>
      <VueButton @click="InitCountOfTicketsByDays()" color="default">
        <span class="items-center font-bold dark:text-gray-900">Перезагрузить</span>
      </VueButton>
    </template>

    <div id="count_of_tickets_by_days" :class="{ 'hidden': errored }" class="w-full h-[400px]"></div>
  </div>
</template>