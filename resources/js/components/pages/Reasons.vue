<script>
import { inject } from 'vue'
import {
  Table, TableHead,
  TableBody, TableHeadCell,
  TableRow, TableCell,
  Input, Button,
  Select
} from 'flowbite-vue'
import { StringVal } from '@utils/validation.js'

export default {
  name: 'Reasons',
  components: {
    Table, TableHead,
    TableBody, TableHeadCell,
    TableRow, TableCell,
    Input, Button,
    Select
  },
  data() {
    return {
      AllReasons: Array(),
      reasons: Array(),
      checksum: Array(),
      NewReasonName: String(),
      NewReasonWeight: Number(1),
      NewReasonGroupId: Number(),
      PatchingId: Number(),
      PatchingName: String(),
      PatchingWeight: Number(1),
      PatchingGroupId: Number(),
      groups: Array(),
      errored: Boolean(),
      search: String(),
    }
  },
  setup() {
    const toast = inject('createToast')
    return { toast }
  },
  mounted() {
    this.Get()
  },
  methods: {
    Get() {
      this.ax.get('reasons').then(r => {
        this.AllReasons = r.data.data.data
        this.checksum = r.data.checksum
        this.reasons = this.AllReasons
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      }).finally(this.GetGroups)
    },
    GetGroups() {
      this.ax.get('groups').then(r => {
        this.groups = r.data.data.data
        this.groups.map(g => g.value = g.id)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Create() {
      const name = this.NewReasonName.trim()
      const validate = StringVal(name, 2)
      if (validate.status) {
        this.toast(validate.message, 'error')
        return
      }

      const data = {
        name: this.NewReasonName,
        weight: this.NewReasonWeight,
        group_id: this.NewReasonGroupId,
      }
      this.ax.post('reasons', data).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (!r.data.status) return

        const index = this.checksum.findIndex(r => r == this.NewReasonName)
        this.checksum.splice(index, 1)
        this.ClearCreationData()

        this.AllReasons.push(r.data.data)
        this.reasons = this.AllReasons
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Patch(reason_id) {
      const name = this.PatchingName.trim()
      const validate = StringVal(name, 2)
      if (validate.status) {
        this.toast(validate.message, 'error')
        return
      }

      this.ax.patch(`reasons/${reason_id}`, {
        name: name,
        weight: this.PatchingWeight,
        group_id: this.PatchingGroupId,
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning')
          return
        }

        this.checksum = r.data.checksum
        this.PrepareForPatch()
        const index = this.AllReasons.findIndex(({ id }) => id == reason_id)
        this.AllReasons[index] = r.data.data
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Delete(reason_id) {
      this.ax.delete(`reasons/${reason_id}`).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (!r.data.status) return

        this.checksum = r.data.checksum
        const index = this.AllReasons.findIndex(({ id }) => id == reason_id)
        this.AllReasons.splice(index, 1)
        this.reasons = this.AllReasons
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Search() {
      let data = this.search.trim()
      if (data.length == 0) {
        this.ClearSearch()
        return
      }

      const id = data.replaceAll(/[^0-9]+/g, '').trim()
      const text = data.replaceAll(/[^А-яA-z ]+/g, '').trim().toLowerCase()

      this.reasons = this.AllReasons.filter(r =>
        id.length > 0 && r.id.toString().includes(id) ||
        text.length > 0 && r.name.toLowerCase().includes(text)
      )
    },
    ClearCreationData() {
      this.NewReasonName = ''
      this.NewReasonWeight = 1
      this.NewReasonGroupId = 0
    },
    PrepareForPatch(data = null) {
      this.PatchingId = data?.id ?? 0
      this.PatchingName = data?.name ?? ''
      this.PatchingWeight = data?.weight ?? 0
      this.PatchingGroupId = data?.group_id ?? 0
    },
    ClearSearch() {
      this.search = ''
      this.reasons = this.AllReasons
    },
  }
}
</script>

<template>
  <!-- Search -->
  <div class="fixed top-1 right-1">
    <div v-if="AllReasons.length > 0" class="flex flex-row space-x-2">
      <Input @keyup.enter="Search()" v-model="search" placeholder="Поиск по id или названию" label="" class="w-64">
      <template #prefix v-if="search.length == 0">
        <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor"
          viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
      </template>
      <template #suffix v-else>
        <svg @click="ClearSearch()" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
          stroke="currentColor" class="text-black-800 w-5 h-5 cursor-pointer">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z" />
        </svg>
      </template>
      </Input>
      <Button :disabled="search.length == 0" @click="Search()" color="default">Искать</Button>
    </div>
  </div>

  <div v-if="checksum.length > 0" class="py-4">
    <p class="font-black">Следующие темы необходимо создать для более корректного определения тем:</p>
    <div
      class="flex flex-wrap gap-3 h-24 max-h-24 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
      <div v-for="reason in checksum" @click="NewReasonName = reason">
        <p
          class="text-red-500 font-bold cursor-pointer underline hover:no-underline border-0 focus:outline-none decoration-dotted underline-offset-4">
          {{ reason }}
        </p>
      </div>
    </div>
  </div>

  <Table :class="checksum.length > 0 ? 'max-h-[calc(100vh-206px)]' : 'max-h-[calc(100vh-54px)]'"
    class="overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <TableHead>
      <TableHeadCell>Id</TableHeadCell>
      <TableHeadCell>Название</TableHeadCell>
      <TableHeadCell>Вес</TableHeadCell>
      <TableHeadCell>Группа</TableHeadCell>
      <TableHeadCell><span class="sr-only">Edit</span></TableHeadCell>
    </TableHead>

    <TableBody>
      <TableRow>
        <TableCell>~{{ AllReasons.length + 1 }}</TableCell>
        <TableCell>
          <Input v-model.trim="NewReasonName" placeholder="Введите название для новой темы" />
        </TableCell>
        <TableCell>
          <Input v-model.number="NewReasonWeight" type="number" placeholder="Укажите вес" />
        </TableCell>
        <TableCell>
          <Select v-model.number="NewReasonGroupId" :options="groups" placeholder="Выберите группу" />
        </TableCell>
        <TableCell>
          <div class="space-x-3">
            <Button :disabled="NewReasonName.length < 2" @click="Create()" color="green">Добавить</Button>
            <Button v-if="NewReasonName.length > 0" @click="ClearCreationData()" color="light">Сброс</Button>
          </div>
        </TableCell>
      </TableRow>

      <TableRow v-for="r in reasons">
        <TableCell>{{ r.id }}</TableCell>
        <TableCell>
          <p v-if="PatchingId != r.id">{{ r.name }}</p>
          <Input v-else v-model="PatchingName" placeholder="Введите новое название" />
        </TableCell>
        <TableCell>
          <p v-if="PatchingId != r.id">{{ r.weight }}</p>
          <Input v-else v-model.number="PatchingWeight" type="number" placeholder="Укажите вес" />
        </TableCell>
        <TableCell>
          <p v-if="PatchingId != r.id">{{ groups.find(g => g.id == r.group_id)?.name }}</p>
          <Select v-else v-model.number="PatchingGroupId" :options="groups" placeholder="Выберите группу" />
        </TableCell>
        <TableCell>
          <div v-if="PatchingId == r.id" class="space-x-3">
            <Button @click="Patch(r.id)" color="green">Сохранить</Button>
            <Button @click="PrepareForPatch()" color="light">Отменить</Button>
          </div>
          <div v-else class="space-x-3">
            <Button @click="PrepareForPatch(r)" color="light">Редактировать</Button>
            <Button @click="Delete(r.id)" color="red">Удалить</Button>
          </div>
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>

  <div v-if="errored || reasons.length == 0" class="flex flex-col gap-3 mt-3">
    <p v-if="errored" class="mx-auto text-center text-gray-400 w-full lg:w-2/3">
      Произошла непредвиденная ошибка
    </p>
    <p v-else-if="reasons.length == 0" class="mx-auto text-center text-gray-400 w-full lg:w-2/3">
      Нет данных
    </p>
  </div>
</template>