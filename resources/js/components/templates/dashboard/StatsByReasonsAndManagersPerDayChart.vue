<script>
// import { defineAsyncComponent } from 'vue';
import { Button as VueButton } from 'flowbite-vue';
import * as echarts from 'echarts';

import {
  ToolboxComponent,
  TooltipComponent,
  GridComponent,
  LegendComponent
} from 'echarts/components';
import { BarChart } from 'echarts/charts';
import { UniversalTransition } from 'echarts/features';
import { CanvasRenderer } from 'echarts/renderers';

// const echarts = defineAsyncComponent(() =>import('echarts'));

echarts.use([
  GridComponent,
  TooltipComponent,
  ToolboxComponent,
  LegendComponent,
  BarChart,
  UniversalTransition,
  CanvasRenderer
]);

export default {
  name: 'StatsByReasonsAndManagersPerDayChartComponent',
  components: { VueButton },
  data() {
    return {
      waiting: Boolean(),
      errored: Boolean(),
    }
  },
  mounted() {
    this.InitCharts();
  },
  methods: {
    InitCharts() {
      if (this.waiting) return;
      this.waiting = true;
      this.errored = false;

      this.ax.get('statistics/stats_by_reasons_and_managers_per_day').then(r => {
        this.waiting = false;
        const avgSolvingTimeByUsers = r.data.data.avgSolvingTimeByUsers;
        this.InitAvgSolvingTimeByDateAndUsers(avgSolvingTimeByUsers);
        const avgSolvingTimeByReasons = r.data.data.avgSolvingTimeByReasons;
        this.InitAvgSolvingTimeByDateAndReasons(avgSolvingTimeByReasons);

        const newTicketsCountByUsers = r.data.data.newTicketsCountByUsers;
        this.InitNewTicketsCountByDateAndUsers(newTicketsCountByUsers);
        const newTicketsCountByReasons = r.data.data.newTicketsCountByReasons;
        this.InitNewTicketsCountByDateAndReasons(newTicketsCountByReasons);

        const resolvedTicketsCountByUsers = r.data.data.resolvedTicketsCountByUsers;
        this.InitResolvedTicketsCountByDateAndUsers(resolvedTicketsCountByUsers);
        const resolvedTicketsCountByReasons = r.data.data.resolvedTicketsCountByReasons;
        this.InitResolvedTicketsCountByDateAndReasons(resolvedTicketsCountByReasons);
      }).catch(e => {
        console.log(e);
        this.errored = true;
      })
    },
    InitAvgSolvingTimeByDateAndUsers(data) {
      const td = data.filter(d => d.time != null && d.time != 0);
      const names = [...new Set(data.map(d => d.name))];
      // const times = data.map(d => d.time);
      const dates = [...new Set(td.map(d => d.date))].sort((a, b) => new Date(a) - new Date(b));

      let series = [];

      names.forEach((n, key) => {
        let row = [];
        dates.forEach(d => {
          const cv = data.filter(da => da.date == d && da.name == n)[0];
          row.push(cv?.time);
        });

        series.push({
          name: n,
          // name: namedValues[key],
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
        });
      });

      const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)");
      let theme = darkThemeMq.matches ? 'dark' : '';
      const chart = echarts.init(document.getElementById('avg_solving_time_by_date_and_users'), theme);

      let option = {
        title: {
          text: 'Среднее время решения тикетов по датам и сотрудникам (в часах)',
          left: 'center'
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          }
        },
        legend: {
          data: names,
          top: 30,
        },
        grid: {
          top: 80,
        },
        toolbox: {
          feature: {
            restore: {},
            saveAsImage: {}
          }
        },
        xAxis: [{
          type: 'category',
          axisTick: { show: false },
          data: dates
        }],
        yAxis: [{ type: 'value' }],
        series: series
      };

      chart.setOption(option);
    },
    InitAvgSolvingTimeByDateAndReasons(data) {
      const td = data.filter(d => d.time != null && d.time != 0);
      const names = [...new Set(data.map(d => d.name))];
      // const times = data.map(d => d.time);
      const dates = [...new Set(td.map(d => d.date))].sort((a, b) => new Date(a) - new Date(b));

      let series = [];

      names.forEach((n, key) => {
        let row = [];
        dates.forEach(d => {
          const cv = data.filter(da => da.date == d && da.name == n)[0];
          row.push(cv?.time);
        });

        series.push({
          name: n,
          // name: namedValues[key],
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
        });
      });

      const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)");
      let theme = darkThemeMq.matches ? 'dark' : '';
      const chart = echarts.init(document.getElementById('avg_solving_time_by_date_and_reasons'), theme);

      let option = {
        title: {
          text: 'Среднее время решения тикетов по датам и темам (в часах)',
          left: 'center'
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          }
        },
        legend: {
          data: names,
          top: 30,
        },
        grid: {
          top: 80,
        },
        toolbox: {
          feature: {
            restore: {},
            saveAsImage: {}
          }
        },
        xAxis: [{
          type: 'category',
          axisTick: { show: false },
          data: dates
        }],
        yAxis: [{ type: 'value' }],
        series: series
      };

      chart.setOption(option);
    },
    InitNewTicketsCountByDateAndUsers(data) {
      const td = data.filter(d => d.count > 0);
      const names = [...new Set(data.map(d => d.name))];
      // const times = data.map(d => d.time);
      const dates = [...new Set(td.map(d => d.date))].sort((a, b) => new Date(a) - new Date(b));

      let series = [];

      names.forEach((n, key) => {
        let row = [];
        dates.forEach(d => {
          const cv = data.filter(da => da.date == d && da.name == n)[0];
          row.push(cv?.count);
        });

        series.push({
          name: n,
          // name: namedValues[key],
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
        });
      });

      const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)");
      let theme = darkThemeMq.matches ? 'dark' : '';
      const chart = echarts.init(document.getElementById('new_tickets_count_by_date_and_users'), theme);

      let option = {
        title: {
          text: 'Количество упавших тикетов по датам и сотрудникам',
          left: 'center'
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          }
        },
        legend: {
          data: names,
          top: 30,
        },
        grid: {
          top: 80,
        },
        toolbox: {
          feature: {
            restore: {},
            saveAsImage: {}
          }
        },
        xAxis: [{
          type: 'category',
          axisTick: { show: false },
          data: dates
        }],
        yAxis: [{ type: 'value' }],
        series: series
      };

      chart.setOption(option);
    },
    InitNewTicketsCountByDateAndReasons(data) {
      const td = data.filter(d => d.count > 0);
      const names = [...new Set(data.map(d => d.name))];
      // const times = data.map(d => d.time);
      const dates = [...new Set(td.map(d => d.date))].sort((a, b) => new Date(a) - new Date(b));

      let series = [];

      names.forEach((n, key) => {
        let row = [];
        dates.forEach(d => {
          const cv = data.filter(da => da.date == d && da.name == n)[0];
          row.push(cv?.count);
        });

        series.push({
          name: n,
          // name: namedValues[key],
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
        });
      });

      const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)");
      let theme = darkThemeMq.matches ? 'dark' : '';
      const chart = echarts.init(document.getElementById('new_tickets_count_by_date_and_reasons'), theme);

      let option = {
        title: {
          text: 'Количество упавших тикетов по датам и темам',
          left: 'center'
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          }
        },
        legend: {
          data: names,
          top: 30,
        },
        grid: {
          top: 80,
        },
        toolbox: {
          feature: {
            restore: {},
            saveAsImage: {}
          }
        },
        xAxis: [{
          type: 'category',
          axisTick: { show: false },
          data: dates
        }],
        yAxis: [{ type: 'value' }],
        series: series
      };

      chart.setOption(option);
    },
    InitResolvedTicketsCountByDateAndUsers(data) {
      const td = data.filter(d => d.count > 0);
      const names = [...new Set(data.map(d => d.name))];
      // const times = data.map(d => d.time);
      const dates = [...new Set(td.map(d => d.date))].sort((a, b) => new Date(a) - new Date(b));
      // const dates = [...new Set(td.map(d => d.date))];

      let series = [];

      names.forEach((n, key) => {
        let row = [];
        dates.forEach(d => {
          const cv = data.filter(da => da.date == d && da.name == n)[0];
          row.push(cv?.count);
        });

        series.push({
          name: n,
          // name: namedValues[key],
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
        });
      });

      const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)");
      let theme = darkThemeMq.matches ? 'dark' : '';
      const chart = echarts.init(document.getElementById('resolved_tickets_count_by_date_and_users'), theme);

      let option = {
        title: {
          text: 'Количество решённых тикетов по датам и сотрудникам',
          left: 'center'
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          }
        },
        legend: {
          data: names,
          top: 30,
        },
        grid: {
          top: 80,
        },
        toolbox: {
          feature: {
            restore: {},
            saveAsImage: {}
          }
        },
        xAxis: [{
          type: 'category',
          axisTick: { show: false },
          data: dates
        }],
        yAxis: [{ type: 'value' }],
        series: series
      };

      chart.setOption(option);
    },
    InitResolvedTicketsCountByDateAndReasons(data) {
      const td = data.filter(d => d.count > 0);
      const names = [...new Set(data.map(d => d.name))];
      // const times = data.map(d => d.time);
      const dates = [...new Set(td.map(d => d.date))].sort((a, b) => new Date(a) - new Date(b));
      // const dates = [...new Set(td.map(d => d.date))];

      let series = [];

      names.forEach((n, key) => {
        let row = [];
        dates.forEach(d => {
          const cv = data.filter(da => da.date == d && da.name == n)[0];
          row.push(cv?.count);
        });

        series.push({
          name: n,
          // name: namedValues[key],
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
        });
      });

      const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)");
      let theme = darkThemeMq.matches ? 'dark' : '';
      const chart = echarts.init(document.getElementById('resolved_tickets_count_by_date_and_reasons'), theme);

      let option = {
        title: {
          text: 'Количество решённых тикетов по датам и темам',
          left: 'center'
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          }
        },
        legend: {
          data: names,
          top: 30,
        },
        grid: {
          top: 80,
        },
        toolbox: {
          feature: {
            restore: {},
            saveAsImage: {}
          }
        },
        xAxis: [{
          type: 'category',
          axisTick: { show: false },
          data: dates
        }],
        yAxis: [{ type: 'value' }],
        series: series
      };

      chart.setOption(option);
    },
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
      <VueButton @click="InitCharts()" color="default">
        <span class="items-center font-bold dark:text-gray-900">Перезагрузить</span>
      </VueButton>
    </template>

    <div id="avg_solving_time_by_date_and_users" :class="{ 'hidden': errored }" class="w-full h-[500px]"></div>
    <div id="avg_solving_time_by_date_and_reasons" :class="{ 'hidden': errored }" class="w-full h-[500px]"></div>

    <div id="new_tickets_count_by_date_and_users" :class="{ 'hidden': errored }" class="w-full h-[500px]"></div>
    <div id="new_tickets_count_by_date_and_reasons" :class="{ 'hidden': errored }" class="w-full h-[500px]"></div>

    <div id="resolved_tickets_count_by_date_and_users" :class="{ 'hidden': errored }" class="w-full h-[500px]"></div>
    <div id="resolved_tickets_count_by_date_and_reasons" :class="{ 'hidden': errored }" class="w-full h-[500px]"></div>
  </div>
</template>