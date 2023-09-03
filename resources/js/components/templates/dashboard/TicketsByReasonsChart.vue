<script>
// import { defineAsyncComponent } from 'vue'
import { Button as VueButton } from 'flowbite-vue'
import * as echarts from 'echarts'

import {
  TitleComponent,
  TooltipComponent,
  LegendComponent
} from 'echarts/components'
import { PieChart } from 'echarts/charts'
import { LabelLayout } from 'echarts/features'
import { CanvasRenderer } from 'echarts/renderers'

// const echarts = defineAsyncComponent(() => import('echarts'))

echarts.use([
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  PieChart,
  CanvasRenderer,
  LabelLayout
])

export default {
  name: 'TicketsByReasonsChartComponent',
  components: { VueButton },
  data() {
    return {
      waiting: Boolean(),
      errored: Boolean(),
    }
  },
  mounted() {
    this.InitChartCountByReasons()
  },
  methods: {
    InitChartCountByReasons() {
      this.ax.get('statistics/tickets_by_reasons').then(r => {
        const d = r.data.data
        const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)")
        let theme = darkThemeMq.matches ? 'dark' : ''
        const chart = echarts.init(document.getElementById('tickets_by_reasons'), theme)

        chart.setOption({
          title: {
            text: 'Количество тикетов по темам',
            subtext: '',
            left: 'center'
          },
          tooltip: {
            trigger: 'item'
          },
          toolbox: {
            feature: {
              restore: {},
              saveAsImage: {}
            }
          },
          series: [{
            name: '',
            type: 'pie',
            radius: '50%',
            data: d,
            emphasis: {
              itemStyle: {
                shadowBlur: 10,
                shadowOffsetX: 0,
                shadowColor: 'rgba(0, 0, 0, 0.5)'
              }
            }
          }]
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
      <VueButton @click="InitChartByGroups()" color="default">
        <span class="items-center font-bold dark:text-gray-900">Перезагрузить</span>
      </VueButton>
    </template>

    <div id="tickets_by_reasons" :class="{ 'hidden': errored }" class="w-full h-[80vh]"></div>
  </div>
</template>