<script>
import { inject } from 'vue'
import {
  Table, TableHead,
  TableBody, TableHeadCell,
  TableRow, TableCell,
  Input, Button,
  Avatar, Toggle
} from 'flowbite-vue'
import VueMultiselect from 'vue-multiselect'
import VueSimpleRangeSlider from 'vue-simple-range-slider'

import { FormatDateTime } from '@utils/validation.js'
import TicketNotFound from '@states/TicketNotFound.vue'
import Pagination from '@temps/Pagination.vue'

export default {
  name: 'Tickets',
  components: {
    Table, TableHead,
    TableBody, TableHeadCell,
    TableRow, TableCell,
    Input, Button,
    Avatar, Toggle,
    VueMultiselect, VueSimpleRangeSlider,
    TicketNotFound, Pagination
  },
  data() {
    return {
      Tickets: Array(),
      FilteredTickets: Array(),
      CurrentTicket: Object(),
      managers: Array(),
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
        to_id: Number(),
        reason: Number(),
        weight: Number(),
        only_active: Boolean(),
        only_resolved: Boolean(),
        from_date: String(),
        to_date: String(),
      }),

      file: String(),
      dragging: Boolean(),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')

    return {
      UserData, toast
    }
  },
  mounted() {
    this.Get(++this.page)
  },
  methods: {
    Get(page = 1, limit = 10) {
      if (this.waiting) return
      this.waiting = true

      const data = this.search.trim()
      const reset_searching = this.searching
      this.searching = data != ''

      this.ax.get(`detalization?page=${page}&limit=${limit}&search=${data}`).then(r => {
        this.Tickets = r.data.data.data
        this.Tickets.forEach(({ created_at }) => created_at = FormatDateTime(created_at))
        this.FilteredTickets = this.Tickets
        if (this.Tickets.length > 0 && this.Tickets[0].id != null) {
          this.PreparePagination(r.data.data.meta)
        }
        this.errored = !r.data.status
      }).catch(e => {
        console.log(e)
        this.toast(e.response.data.message, 'error')
        this.errored = true
      }).finally(() => this.waiting = false)
    },
    PreparePagination(meta) {
      this.$refs.PaginationTemplate.page = meta.current_page
      this.$refs.PaginationTemplate.last_page = meta.last_page
      this.$refs.PaginationTemplate.results_info = `Результаты ${meta.from}-${meta.to} из ${meta.total}`
      this.$refs.PaginationTemplate.PrepareAvailablePages()
    },
    Filter() {
      console.log(this.Filters)
    },
    ClearFilters() {
      const now = new Date().toDateString()
      console.log('now')
      console.log(now)
      this.Filters = {
        id_range: [0, 100],
        reason: 0,
        from_weight: 0,
        to_weight: 0,
        only_active: false,
        only_resolved: false,
        from_date: now,
        to_date: now,
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
      <Button :disabled="waiting" @click="ShowFilters = !ShowFilters" color="default" class="w-[100px]">
        <span class="items-center font-bold dark:text-gray-900">Фильтры</span>
      </Button>

      <div :class="ShowFilters ? 'visible opacity-100' : 'invisible opacity-0'"
        class="absolute flex flex-col !w-[98vw] left-[calc(-99vw+100px)] right-0 gap-2 p-3 inline-block text-sm font-light text-gray-500 transition-opacity duration-300 bg-white border border-gray-200 rounded-lg shadow-sm w-fit dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400">
        <p>Фильтры:</p>
        <div class="flex flex-wrap gap-2">
          <VueSimpleRangeSlider :min="1" :max="100" v-model="Filters.id_range">
            <template #prefix="{ value }">#</template>
            <!-- <template #prefix="{ value }">$</template> -->
          </VueSimpleRangeSlider>
          <Input v-model="Filters.from_id" type="number" placeholder="Id с" />
          <Input v-model="Filters.to_id" type="number" placeholder="Id по" />

          <p>Тема</p>
          <VueMultiselect v-model="OnlyInGroup" :options="managers" :multiple="true" :close-on-select="false"
            placeholder="Выберите менеджера" @select="AddToGroup" @remove="RemoveFromGroup" label="name" track-by="name">
            <template #noResult>Нет данных</template>

            <template slot="option" slot-scope="props">
              <img class="option__image" :src="props.option.avatar" alt="avatar">
              <span>{{ props.option.text }}</span>
            </template>

            <template slot="tag" slot-scope="{ option, remove }">
              <!-- <img class="tag__image" :src="option.avatar" alt="avatar"> -->
              <span class="multiselect__tag">
                <span>{{ option.text }}</span>
                <span class="multiselect__tag-icon" @click.prevent="remove(option)">
                  &#10006;
                </span>
              </span>
            </template>
          </VueMultiselect>

          <Input v-model="Filters.from_weight" type="number" placeholder="Id с" />
          <Input v-model="Filters.from_weight" type="number" placeholder="Id по" />

          <Toggle v-model="Filters.only_active" label="Активные" color="green" />
          <Toggle v-model="Filters.only_resolved" label="Завершённые" :disabled="Filters.only_active" color="green" />
          <p>Вес</p>
          <p>Дата создания</p>
        </div>

        <div class="flex flex-row-reverse">
          <Button @click="Filter()">Применить</Button>
          <Button @click="ClearFilters()">Сбросить</Button>
        </div>
      </div>
    </div>
  </div>

  <div v-if="Tickets.length == 0 || Tickets[0].id == null" class="h-[calc(100vh-55px)] p-8">
    <!-- <p class="text-center text-gray-400">
      Нет данных
    </p> -->
    <div v-if="!dragging" @dragenter="dragging = true" class="h-full w-full">
      <p>123123123</p>
    </div>
    <div v-else :class="['dropZone', dragging ? 'dropZone-over' : '']" @dragleave="dragging = false">
      <div class="dropZone-info" @drag="onChange">
        <span class="fa fa-cloud-upload dropZone-title"></span>
        <span class="dropZone-title">Drop file or click to upload</span>
        <div class="dropZone-upload-limit-info">
          <div>extension support: txt</div>
          <div>maximum file size: 5 MB</div>
        </div>
      </div>
      <input type="file" @change="onChange">
    </div>
  </div>

  <div v-else @click="ShowFilters = false">
    <div class="pb-2">
      <Table
        class="max-h-[calc(100vh-54px-60px)] overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
        <TableHead>
          <TableHeadCell>Id</TableHeadCell>
          <TableHeadCell>Тема</TableHeadCell>
          <TableHeadCell>Вес</TableHeadCell>
          <TableHeadCell>Дата создания</TableHeadCell>
          <TableHeadCell><span class="sr-only">Edit</span></TableHeadCell>
        </TableHead>

        <TableBody>
          <TableRow v-for="t in FilteredTickets">
            <TableCell>{{ t.id }}</TableCell>
            <TableCell>{{ t.reason }}</TableCell>
            <TableCell>{{ t.weight }}</TableCell>
            <TableCell>{{ t.created_at }}</TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Pagination ref="PaginationTemplate" @invoke="Get" />
  </div>
</template>

<style>
.dropZone {
  width: 80%;
  height: 200px;
  position: relative;
  border: 2px dashed #eee;
}

.dropZone:hover {
  border: 2px solid #2e94c4;
}

.dropZone:hover .dropZone-title {
  color: #1975A0;
}

.dropZone-info {
  color: #A8A8A8;
  position: absolute;
  top: 50%;
  width: 100%;
  transform: translate(0, -50%);
  text-align: center;
}

.dropZone-title {
  color: #787878;
}

.dropZone input {
  position: absolute;
  cursor: pointer;
  top: 0px;
  right: 0;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
}

.dropZone-upload-limit-info {
  display: flex;
  justify-content: flex-start;
  flex-direction: column;
}

.dropZone-over {
  background: #5C5C5C;
  opacity: 0.8;
}

.dropZone-uploaded {
  width: 80%;
  height: 200px;
  position: relative;
  border: 2px dashed #eee;
}

.dropZone-uploaded-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #A8A8A8;
  position: absolute;
  top: 50%;
  width: 100%;
  transform: translate(0, -50%);
  text-align: center;
}

.removeFile {
  width: 200px;
}
</style>