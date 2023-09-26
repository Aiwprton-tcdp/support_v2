<script>
import { Button as VueButton } from 'flowbite-vue'
import * as echarts from 'echarts'

import {
  ToolboxComponent,
  TooltipComponent,
  GridComponent,
  LegendComponent
} from 'echarts/components'
import { BarChart } from 'echarts/charts'
import { UniversalTransition } from 'echarts/features'
import { CanvasRenderer } from 'echarts/renderers'

echarts.use([
  GridComponent,
  TooltipComponent,
  ToolboxComponent,
  LegendComponent,
  BarChart,
  UniversalTransition,
  CanvasRenderer
])

export default {
  name: 'AverageSolvingTimeChartComponent',
  components: { VueButton },
  data() {
    return {
      waiting: Boolean(),
      errored: Boolean(),
    }
  },
  mounted() {
    this.InitChartByAverageSolvingTime()
  },
  methods: {
    InitChartByAverageSolvingTime() {
      if (this.waiting) return
      this.waiting = true
      this.errored = false

      this.ax.get('statistics/average_solving_time').then(r => {
        this.waiting = false
        const d = r.data.data
        const xAxisData = Object.keys(d)

        let data = []
        const values = [0, 1, 2, 3]
        const namedValues = ['Без оценки', 'Плохо', 'Нормально', 'Отлично']

        values.forEach((v, key) => {
          let row = []
          xAxisData.forEach(key => {
            row.push(d[key][namedValues[v]] ?? 0)
          })

          if (row.filter(y => y > 0).length == 0) return

          data.push({
            name: namedValues[key],
            type: 'bar',
            barGap: 0,
            label: {
              show: true,
              align: 'center',
              verticalAlign: 'bottom',
              position: 'insideBottom',
              formatter: '{c}',
              fontSize: 10,
              rich: {
                name: {}
              }
            },
            emphasis: {
              focus: 'series'
            },
            data: row
          })
        })

        const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)")
        let theme = darkThemeMq.matches ? 'dark' : ''
        const chart = echarts.init(document.getElementById('average_solving_time'), theme)

        let option = {
          title: {
            text: 'Оценки за тикеты по менеджерам',
            left: 'center'
          },
          tooltip: {
            trigger: 'axis',
            axisPointer: {
              type: 'shadow'
            }
          },
          legend: {
            data: values
          },
          toolbox: {
            feature: {
              restore: {},
              saveAsImage: {}
            }
          },
          xAxis: [
            {
              type: 'category',
              axisTick: { show: false },
              data: xAxisData
            }
          ],
          yAxis: [
            {
              type: 'value'
            }
          ],
          series: data
        }

        chart.setOption(option)
      }).catch(e => {
        console.log(e)
        this.errored = true
      }).finally(() => this.waiting = false)
    },
  }
}
</script>

<template>
  <div class="flex flex-col gap-1">
    <template v-if="errored">
      <div>Ошибка</div>
      <VueButton @click="InitChartByAverageSolvingTime()" color="default">
        <span class="items-center font-bold dark:text-gray-900">Перезагрузить</span>
      </VueButton>
    </template>
    <template v-else-if="waiting">
      <div>Загрузка</div>
    </template>

    <div id="average_solving_time" :class="{ 'hidden': errored }" class="w-full h-[400px]"></div>
  </div>
</template>