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
import GroupPatchModal from '@temps/GroupPatchModal.vue'

export default {
  name: 'Groups',
  components: {
    Table, TableHead,
    TableBody, TableHeadCell,
    TableRow, TableCell,
    Input, Button,
    Select, GroupPatchModal
  },
  data() {
    return {
      AllGroups: Array(),
      groups: Array(),
      errored: Boolean(),
      NewGroupName: String(),
      PatchingId: Number(),
      PatchingName: String(),
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
      this.ax.get('groups').then(r => {
        this.AllGroups = r.data.data.data
        this.groups = this.AllGroups
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    Create() {
      const name = this.NewGroupName.trim()
      const validate = StringVal(name)
      if (validate.status) {
        this.toast(validate.message, 'error')
        return
      }

      this.ax.post('groups', {
        name: this.NewGroupName,
      }).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (!r.data.status) return

        this.AllGroups.push(r.data.data)
        this.groups = this.AllGroups
        this.NewGroupName = ''
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Patch(group_id) {
      const name = this.PatchingName.trim()
      const validate = StringVal(name)
      if (validate.status) {
        this.toast(validate.message, 'error')
        return
      }

      this.ax.patch(`groups/${group_id}`, {
        name: name
      }).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (!r.data.status) return

        const index = this.AllGroups.findIndex(({ id }) => id == group_id)
        this.AllGroups[index] = r.data.data
        this.PatchingId = 0
        this.PatchingName = ''
        this.groups = this.AllGroups
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Delete(group_id) {
      this.ax.delete(`groups/${group_id}`).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'warning')
        if (!r.data.status) return

        const index = this.AllGroups.findIndex(({ id }) => id == group_id)
        this.AllGroups.splice(index, 1)
        this.groups = this.AllGroups
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

      this.groups = this.AllGroups.filter(g =>
        id.length > 0 && g.id.toString().includes(id) ||
        text.length > 0 && g.name.toLowerCase().includes(text)
      )
    },
    ClearSearch() {
      this.search = ''
      this.groups = this.AllGroups
    },
    ShowModal(data) {
      this.$refs.GroupPatch.visible = true
      this.$refs.GroupPatch.group = data
      this.$refs.GroupPatch.GetUsers()
    },
    PrepareForPatch(data = null) {
      this.PatchingId = data?.id ?? 0
      this.PatchingName = data?.name
    },
  },
}
</script>

<template>
  <!-- Search -->
  <div class="fixed top-1 right-1">
    <div v-if="AllGroups.length > 0" class="flex flex-row space-x-2">
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

  <Table
    class="max-h-[calc(100vh-54px)] overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <TableHead>
      <TableHeadCell>Id</TableHeadCell>
      <TableHeadCell>Название</TableHeadCell>
      <TableHeadCell><span class="sr-only">Edit</span></TableHeadCell>
    </TableHead>

    <TableBody>
      <TableRow>
        <TableCell>~{{ AllGroups.length + 1 }}</TableCell>
        <TableCell>
          <Input v-model.trim="NewGroupName" placeholder="Введите название новой группы" />
        </TableCell>
        <TableCell>
          <div class="space-x-3">
            <Button :disabled="NewGroupName.length < 3" @click="Create()" color="green">Добавить</Button>
            <Button v-if="NewGroupName.length > 0" @click="NewGroupName = ''" color="light">Сброс</Button>
          </div>
        </TableCell>
      </TableRow>

      <TableRow v-for="g in groups">
        <TableCell>{{ g.id }}</TableCell>
        <TableCell>
          <p v-if="PatchingId != g.id">{{ g.name }}</p>
          <Input v-else v-model="PatchingName" placeholder="Введите новое название" />
        </TableCell>
        <TableCell>
          <div v-if="PatchingId == g.id" class="space-x-3">
            <Button @click="Patch(g.id)" color="green">Сохранить</Button>
            <Button @click="PrepareForPatch()" color="light">Отменить</Button>
          </div>
          <div v-else class="space-x-3">
            <Button v-if="!g.alone" @click="ShowModal(g)" color="light">Открыть</Button>
            <Button @click="PrepareForPatch(g)" color="light">Редактировать</Button>
            <Button @click="Delete(g.id)" color="red">Удалить</Button>
          </div>
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>

  <GroupPatchModal ref="GroupPatch" />

  <div v-if="errored || groups.length == 0" class="flex flex-col gap-3 mt-3">
    <p v-if="errored" class="mx-auto text-center text-gray-400 w-full lg:w-2/3">
      Произошла непредвиденная ошибка
    </p>
    <p v-else-if="groups.length == 0" class="mx-auto text-center text-gray-400 w-full lg:w-2/3">
      Нет данных
    </p>
  </div>
</template>