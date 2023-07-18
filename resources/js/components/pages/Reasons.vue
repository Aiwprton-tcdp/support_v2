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
    this.GetGroups()
  },
  methods: {
    Get() {
      this.ax.get('reasons').then(r => {
        this.AllReasons = r.data.data.data
        this.reasons = this.AllReasons
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    GetGroups() {
      this.ax.get('groups').then(r => {
        this.groups = r.data.data.data
        this.groups.forEach(r => r.value = r.id)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Create() {
      if (StringVal(this.NewReasonName)) return

      const data = {
        name: this.NewReasonName,
        weight: this.NewReasonWeight,
        group_id: this.NewReasonGroupId,
      }
      this.ax.post('reasons', data).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (!r.data.status) return

        this.AllReasons.push(r.data.data)
        this.reasons = this.AllReasons
        this.ClearCreationData()
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Patch(reason_id) {
      const name = this.PatchingName.trim()
      if (StringVal(name)) return

      this.ax.patch(`reasons/${reason_id}`, {
        name: name,
        weight: this.PatchingWeight,
        group_id: this.PatchingGroupId,
      }).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'warning')
        if (!r.data.status) return

        this.PrepareForPatch()
        const index = this.AllReasons.findIndex(({id}) => id == reason_id)
        this.AllReasons[index] = r.data.data
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Delete(reason_id) {
      this.ax.delete(`reasons/${reason_id}`).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (!r.data.status) return

        const index = this.AllReasons.findIndex(({id}) => id == reason_id)
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
    ClearSearch() {
      this.search = ''
      this.reasons = this.AllReasons
    },
    PrepareForPatch(data = null) {
      this.PatchingId = data?.id ?? 0
      this.PatchingName = data?.name ?? ''
      this.PatchingWeight = data?.weight ?? 0
      this.PatchingGroupId = data?.group_id ?? 0
    },
  }
}
</script>

<template>
<div v-if="AllReasons.length > 0" class="flex flex-wrap space-x-3 w-1/2 mb-4">
  <Input @keyup.enter="Search()" v-model="search" placeholder="Введите id или название" label="" class="flex-1">
    <template #prefix>
      <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
    </template>
  </Input>
  <Button v-if="search.length > 0" @click="Search()" color="default">Искать</Button>
  <Button v-if="search.length > 0" @click="ClearSearch()" color="light">Сброс</Button>
</div>

<Table hoverable>
  <TableHead>
    <TableHeadCell>Id</TableHeadCell>
    <TableHeadCell>Название</TableHeadCell>
    <TableHeadCell>Вес</TableHeadCell>
    <TableHeadCell>Группа</TableHeadCell>
    <TableHeadCell><span class="sr-only">Edit</span></TableHeadCell>
  </TableHead>

  <TableBody>
    <TableRow>
      <TableCell>~{{ AllReasons.length+1 }}</TableCell>
      <TableCell>
        <Input v-model.trim="NewReasonName" placeholder="Введите название для новой темы" />
      </TableCell>
      <TableCell>
        <Input v-model.number="NewReasonWeight" placeholder="Укажите вес" />
      </TableCell>
      <TableCell>
        <Select v-model.number="NewReasonGroupId" :options="groups" placeholder="Выберите группу" />
      </TableCell>
      <TableCell>
        <div class="space-x-3">
          <Button :disabled="NewReasonName.length < 3" @click="Create()" color="green">Добавить</Button>
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
        <Input v-else v-model.number="PatchingWeight" placeholder="Укажите вес" />
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

<div>
  <div v-if="errored">
    <p>Ошибка</p>
  </div>
  <div v-else-if="reasons.length == 0">
    <p>Нет данных</p>
  </div>
</div>
</template>