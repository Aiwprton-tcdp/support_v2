<script>
import {
  Table as VueTable, TableHead,
  TableBody, TableHeadCell,
  TableRow, TableCell,
  Button as VueButton
} from 'flowbite-vue';

export default {
  name: 'TicketsByDepartmentsChartComponent',
  components: {
    VueTable, TableHead,
    TableBody, TableHeadCell,
    TableRow, TableCell,
    VueButton
  },
  data() {
    return {
      waiting: Boolean(),
      errored: Boolean(),
      tickets_by_departments_table: Array(),
    };
  },
  mounted() {
    this.InitChartByDepartments();
  },
  methods: {
    InitChartByDepartments() {
      if (this.waiting) return;
      this.waiting = true;
      this.errored = false;

      this.ax.get('statistics/tickets_by_departments').then(r => {
        this.waiting = false;
        this.tickets_by_departments_table = r.data.data;
      }).catch(e => {
        console.log(e);
        this.errored = true;
      });
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
      <VueButton @click="InitChartByGroups()" color="default">
        <span class="items-center font-bold dark:text-gray-900">Перезагрузить</span>
      </VueButton>
    </template>

    <h3>Количество созданных тикетов по отделам</h3>

    <VueTable hoverable :class="{ 'hidden': errored }"
      class="max-h-[50vh] max-w-sm overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
      <TableHead class="sticky top-0">
        <TableHeadCell class="!p-0 !px-2">
          Название отдела
        </TableHeadCell>
        <TableHeadCell class="!p-0 !px-2">Кол-во тикетов</TableHeadCell>
      </TableHead>

      <TableBody v-for="(t, name) in tickets_by_departments_table" :key="name">
        <TableRow>
          <TableCell class="!p-0 !px-2 !border">{{ name }}</TableCell>
          <TableCell class="!p-0 !px-2 !border">{{ t }}</TableCell>
        </TableRow>
      </TableBody>
    </VueTable>
  </div>
</template>