<script>
import { inject } from 'vue'
import {
  Table as VueTable, TableHead,
  TableBody, TableHeadCell,
  TableRow, TableCell,
  Input as VueInput,
  Button as VueButton
} from 'flowbite-vue'
import VueMultiselect from 'vue-multiselect'
import DatePicker from 'vue-datepicker-next'
import VueSimpleRangeSlider from 'vue-simple-range-slider'

import 'vue-datepicker-next/index.css'
import 'vue-simple-range-slider/css'

import { FormatDateTime } from '@utils/validation.js'
import Pagination from '@temps/Pagination.vue'

export default {
  name: 'DetailsPage',
  components: {
    VueTable, TableHead,
    TableBody, TableHeadCell,
    TableRow, TableCell,
    VueInput, VueButton,
    VueMultiselect, DatePicker,
    VueSimpleRangeSlider, Pagination
  },
  data() {
    return {
      Tickets: Array(),
      FilteredTickets: Array(),
      CurrentTicket: Object(),
      users: Array(),
      reasons: Array(),
      FirstTry: Boolean(true),
      errored: Boolean(),
      waiting: Boolean(),
      searching: Boolean(),
      ticket401: Boolean(),
      search: String(),
      page: Number(),
      TicketsCount: Number(),
      ShowFilters: Boolean(),
      Filters: Object({
        from_id: Number(1),
        to_id: Number(100),
        id_range: Array(1, 100),
        users: Array(),
        reasons: Array(),
        from_weight: Number(1),
        to_weight: Number(100),
        weight_range: Array(1, 100),
        active: Boolean(true),
        inactive: Boolean(true),
        resolved: Boolean(true),
        date_range: Array(),
      }),
      Orders: Object({
        time: '',
      }),
      file: String(),
      dragging: Boolean(),
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
      VITE_CRM_MARKETPLACE_ID: String(import.meta.env.VITE_CRM_MARKETPLACE_ID),
      VITE_APP_URL: String(import.meta.env.VITE_APP_URL),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')

    return { UserData, toast }
  },
  mounted() {
    this.Get(++this.page)
  },
  methods: {
    Get(page = 1, limit = 10) {
      if (this.waiting) return
      this.waiting = true

      const data = this.search.trim()
      // const reset_searching = this.searching
      this.searching = data != ''
      let metadata = null

      let url_filters = `&min_id=${this.Filters.id_range[0]}
&max_id=${this.Filters.id_range[1]}
&min_w=${this.Filters.weight_range[0]}
&max_w=${this.Filters.weight_range[1]}
&users=${this.Filters.users.map(({ user_id }) => user_id)}
&reasons=${this.Filters.reasons.map(({ id }) => id)}
&from_date=${new Date(this.Filters.date_range[0]).toDateString()}
&to_date=${new Date(this.Filters.date_range[1]).toDateString()}`

      this.ax.get(`detalization?page=${page}
&limit=${limit}
&search=${data}
&active=${this.Filters.active}
&inactive=${this.Filters.inactive}
&resolved=${this.Filters.resolved}
&order_by_time=${this.Orders.time}
${this.FirstTry ? '' : url_filters}`).then(r => {
        this.Tickets = r.data.data.data//.filter(t => t.id != null)
        this.Tickets.forEach(t => t.created_at = FormatDateTime(t.created_at))
        // console.log(this.Tickets)
        this.FilteredTickets = this.Tickets

        metadata = r.data.data.meta
        if (this.FirstTry) {
          this.Filters.to_id = metadata.total
          this.Filters.id_range[1] = metadata.total
        }
        this.errored = !r.data.status
      }).catch(e => {
        console.log(e)
        this.toast(e.response.data.message, 'error')
        this.errored = true
      }).finally(() => {
        this.waiting = false
        this.FirstTry = false
        if (this.Tickets.length > 0 && this.Tickets[0].id != null) {
          this.PreparePagination(metadata)
        }
        this.GetUsers()
        this.GetReasons()
      })
    },
    GetUsers() {
      this.ax.get('bx/users').then(r => {
        this.users = r.data.data.data
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        // this.errored = true
      })
    },
    GetReasons() {
      this.ax.get('reasons').then(r => {
        this.reasons = r.data.data.data
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    PreparePagination(meta) {
      this.$refs.PaginationTemplate.page = meta.current_page
      this.$refs.PaginationTemplate.last_page = meta.last_page
      this.$refs.PaginationTemplate.results_info = `Результаты ${meta.from}-${meta.to} из ${meta.total}`
      this.$refs.PaginationTemplate.PrepareAvailablePages()
    },
    Order(name) {
      let order = this.Orders[name]

      if (order == true) {
        order = false
      } else if (order == false && typeof order != 'string') {
        order = ''
      } else {
        order = true
      }

      this.Orders[name] = order
      this.page = 1
      this.Get(this.page)
    },
    Filter() {
      this.page = 1
      this.Get(this.page)
      this.ShowFilters = false
    },
    ClearFilters() {
      // const now = new Date().toDateString()
      this.FirstTry = true
      this.Filters = {
        from_id: 1,
        to_id: 100,
        id_range: [1, 100],
        reason: 0,
        from_weight: 1,
        to_weight: 100,
        weight_range: [1, 100],
        users: [],
        reasons: [],
        active: true,
        inactive: true,
        resolved: true,
        date_range: [],
      }
    },
    onChange(e) {
      var files = e.target.files || e.dataTransfer.files

      if (!files.length) {
        this.dragging = false
        return
      }

      this.createFile(files[0])
    },
    createFile(file) {
      if (!file.type.match('image.*')) {
        alert('please select txt file')
        this.dragging = false
        return
      }

      if (file.size > 5000000) {
        alert('please check file size no over 5 MB.')
        this.dragging = false
        return
      }

      this.file = file
      console.log(this.file)
      this.dragging = false
    },
    removeFile() {
      this.file = ''
    }
  }
}
</script>

<template>
  <div class="fixed top-1 right-1 flex flex-row space-x-4 z-10">
    <div class="relative">
      <button :disabled="waiting" @click="ShowFilters = !ShowFilters"
        class="w-[120px] text-sm pb-1 no-underline hover:underline border-0 focus:outline-none bg-transparent decoration-dotted underline-offset-4">
        <div class="flex flex-row items-center justify-between">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
          </svg>
          <span class="items-center font-bold">Фильтры</span>
        </div>
      </button>

      <div :class="ShowFilters ? 'visible opacity-100' : 'invisible opacity-0'"
        class="absolute flex flex-col gap-2 !w-[98vw] left-[calc(-99vw+120px)] right-0 p-3 text-sm font-light text-gray-500 transition-opacity duration-300 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400">
        <div class="grid grid-cols-4 gap-2">
          <!-- Ids range -->
          <!-- <div v-if="Filters.from_id != Filters.to_id" class="col-span-1">
            <label for="ids">Id</label>
            <VueSimpleRangeSlider id="ids" v-model="Filters.id_range" :min="Filters.from_id" :max="Filters.to_id" />
          </div> -->

          <!-- Reasons -->
          <div class="col-span-3">
            <label for="reasons">Темы</label>
            <VueMultiselect id="reasons" v-model="Filters.reasons" :options="reasons" placeholder="Выберите тему"
              label="name" track-by="name" :show-labels="false" multiple />
          </div>

          <!-- Active or finished -->
          <div class="col-span-1 flex flex-wrap gap-2">
            <div class="flex items-center mb-4">
              <input id="checkbox_active" type="checkbox" v-model="Filters.active"
                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
              <label for="checkbox_active"
                class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Активные</label>
            </div>
            <div class="flex items-center mb-4">
              <input id="checkbox_inactive" type="checkbox" v-model="Filters.inactive"
                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
              <label for="checkbox_inactive"
                class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Неактивные</label>
            </div>
            <div class="flex items-center mb-4">
              <input id="checkbox_resolved" type="checkbox" v-model="Filters.resolved"
                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
              <label for="checkbox_resolved"
                class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Завершённые</label>
            </div>
            <!-- <Toggle v-model="Filters.active" label="Активные" color="green" />
            <Toggle v-model="Filters.resolved" label="Завершённые" :disabled="Filters.only_active" color="green" /> -->
          </div>

          <!-- Users -->
          <div class="col-span-3">
            <label for="users">Пользователи</label>
            <VueMultiselect id="users" v-model="Filters.users" :options="users" placeholder="Выберите пользователя"
              label="name" track-by="name" :show-labels="false" multiple />
          </div>

          <!-- Weight -->
          <div class="col-span-1">
            <label for="weights">Вес</label>
            <VueSimpleRangeSlider id="weights" v-model="Filters.weight_range" :min="Filters.from_weight"
              :max="Filters.to_weight" exponential />
          </div>

          <!-- Dates -->
          <div class="col-span-2">
            <label for="dates">Дата создания</label>
            <div id="dates">
              <DatePicker v-model:value="Filters.date_range" type="date" editable="false" range />
            </div>
          </div>
        </div>

        <div class="flex flex-row-reverse gap-1">
          <VueButton @click="Filter()">Применить</VueButton>
          <VueButton @click="ClearFilters()" color="alternative">Сбросить</VueButton>
        </div>
      </div>
    </div>
  </div>

  <div v-if="Tickets.length == 0" @click="ShowFilters = false" class="h-[calc(100vh-55px)] p-8">
    <p class="text-center text-gray-400">
      Нет данных
    </p>
  </div>

  <div v-else @click="ShowFilters = false">
    <div class="pb-2">
      <VueTable
        class="h-[calc(100vh-54px-60px)] overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
        <TableHead>
          <TableHeadCell>Id</TableHeadCell>
          <TableHeadCell>Тема</TableHeadCell>
          <TableHeadCell>Создатель</TableHeadCell>
          <TableHeadCell>Ответственный</TableHeadCell>
          <TableHeadCell>Вес</TableHeadCell>
          <TableHeadCell>Статус/Оценка</TableHeadCell>
          <TableHeadCell>Дата создания</TableHeadCell>
          <TableHeadCell>
            <div @click="Order('time')" class="flex gap-1 cursor-pointer" title="Сортировать">
              <p>Время</p>
              <div v-if="typeof Orders.time == 'boolean'" :class="{ 'rotate-180': Orders.time }">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                  stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                </svg>
              </div>
            </div>
          </TableHeadCell>
          <TableHeadCell><span class="sr-only">Edit</span></TableHeadCell>
        </TableHead>

        <TableBody>
          <TableRow v-for="t in FilteredTickets" v-bind:key="t">
            <TableCell>{{ t.id }} <p :title="t.bx_name" class="cursor-help">({{ t.bx_acronym }})</p>
            </TableCell>
            <TableCell>{{ t.reason }}</TableCell>
            <TableCell>{{ t.user.name }}</TableCell>
            <TableCell>{{ t.manager.name }}</TableCell>
            <TableCell>{{ t.weight }}</TableCell>
            <TableCell>{{ t.active ?? t.mark + '/3' }}</TableCell>
            <TableCell>{{ t.start_date }}</TableCell>
            <TableCell>{{ t.time }}</TableCell>
            <TableCell>
              <a :href="UserData.in_crm ? `${VITE_CRM_URL}marketplace/app/${VITE_CRM_MARKETPLACE_ID}/?id=${t.id}` : `${VITE_APP_URL}/?id=${t.id}`"
                target="_blank">
                Перейти
              </a>
            </TableCell>
          </TableRow>
        </TableBody>
      </VueTable>
    </div>

    <Pagination ref="PaginationTemplate" @invoke="Get" />
  </div>
</template>