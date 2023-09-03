<script>
import { Button as VueButton } from 'flowbite-vue'
import * as echarts from 'echarts'

import { GaugeChart } from 'echarts/charts'
import { CanvasRenderer } from 'echarts/renderers'

echarts.use([
  GaugeChart,
  CanvasRenderer
])

export default {
  name: 'MarksPercentageChartComponent',
  components: { VueButton },
  data() {
    return {
      waiting: Boolean(),
      errored: Boolean(),
      gaugeData: Array({
        value: 0,
        name: 'Отлично',
        title: {
          offsetCenter: ['-20%', '-40%']
        },
        detail: {
          valueAnimation: true,
          offsetCenter: ['-20%', '-30%']
        }
      }, {
        value: 0,
        name: 'Нормально',
        title: {
          offsetCenter: ['20%', '-20%']
        },
        detail: {
          valueAnimation: true,
          offsetCenter: ['20%', '-10%']
        }
      }, {
        value: 0,
        name: 'Плохо',
        title: {
          offsetCenter: ['-20%', '0%']
        },
        detail: {
          valueAnimation: true,
          offsetCenter: ['-20%', '10%']
        }
      }, {
        value: 0,
        name: 'Без оценки',
        title: {
          offsetCenter: ['20%', '20%']
        },
        detail: {
          valueAnimation: true,
          offsetCenter: ['20%', '30%']
        }
      })
    }
  },
  mounted() {
    this.InitChartMarksPercentage()
  },
  methods: {
    InitChartMarksPercentage() {
      this.ax.get('statistics/marks_percentage').then(r => {
        const d = r.data.data
        for (const [name, value] of Object.entries(d)) {
          this.gaugeData.forEach(g => {
            if (g.name == name) g.value = value
          })
        }

        const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)")
        let theme = darkThemeMq.matches ? 'dark' : ''
        const chart = echarts.init(document.getElementById('marks_percentage'), theme)

        chart.setOption({
          series: [
            {
              type: 'gauge',
              startAngle: 90,
              endAngle: -270,
              pointer: {
                show: false
              },
              progress: {
                show: true,
                overlap: false,
                roundCap: true,
                clip: false,
                itemStyle: {
                  borderWidth: 1,
                  borderColor: '#464646'
                }
              },
              axisLine: {
                lineStyle: {
                  width: 40
                }
              },
              splitLine: {
                show: false,
                distance: 0,
                length: 10
              },
              axisTick: {
                show: false
              },
              axisLabel: {
                show: false,
                distance: 50
              },
              data: this.gaugeData,
              title: {
                fontSize: 14
              },
              detail: {
                width: 50,
                height: 14,
                fontSize: 14,
                color: 'inherit',
                borderColor: 'inherit',
                borderRadius: 20,
                borderWidth: 1,
                formatter: '{value}%'
              }
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
      <VueButton @click="InitChartMarksPercentage()" color="default">
        <span class="items-center font-bold dark:text-gray-900">Перезагрузить</span>
      </VueButton>
    </template>

    <div id="marks_percentage" :class="{ 'hidden': errored }" class="w-[600px] h-[600px]"></div>
  </div>
</template>