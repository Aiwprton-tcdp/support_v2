<script>
import { Button as VueButton } from 'flowbite-vue'
import * as echarts from 'echarts'

import { GridComponent } from 'echarts/components'
import { BarChart } from 'echarts/charts'
import { UniversalTransition } from 'echarts/features'
import { CanvasRenderer } from 'echarts/renderers'

echarts.use([
  GridComponent,
  BarChart,
  CanvasRenderer,
  UniversalTransition
])

export default {
  name: 'CountOfTicketsByManagersChartComponent',
  components: { VueButton },
  data() {
    return {
      waiting: Boolean(),
      errored: Boolean()
    }
  },
  mounted() {
    this.InitCountOfTicketsByManagers()
  },
  methods: {
    InitCountOfTicketsByManagers() {
      this.ax.get('statistics/count_of_tickets_by_managers').then(r => {
        const d = r.data.data
        let names = []
        Object.keys(d).forEach(v => {
          let r = v.split(' ')
          names.push(`${r[0]} ${r[1]}`)
        })

        const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)")
        let theme = darkThemeMq.matches ? 'dark' : ''
        const chart = echarts.init(document.getElementById('count_of_tickets_by_managers'), theme)

        chart.setOption({
          title: {
            text: 'Общее количество тикетов в разрезе сотрудника',
            left: 'center'
          },
          xAxis: {
            type: 'category',
            axisLabel: { interval: 0, rotate: 30 },
            data: names
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
              data: Object.values(d),
              type: 'bar'
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
      <VueButton @click="InitCountOfTicketsByManagers()" color="default">
        <span class="items-center font-bold dark:text-gray-900">Перезагрузить</span>
      </VueButton>
    </template>

    <div id="count_of_tickets_by_managers" :class="{ 'hidden': errored }" class="w-full h-[500px]"></div>
  </div>
</template>