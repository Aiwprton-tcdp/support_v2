<script>
import { inject } from 'vue'
import {
  Table, TableHead,
  TableBody, TableHeadCell,
  TableRow, TableCell,
} from 'flowbite-vue'

export default {
  name: 'Roles',
  components: {
    Table, TableHead,
    TableBody, TableHeadCell,
    TableRow, TableCell,
  },
  data() {
    return {
      roles: Array(),
      errored: Boolean(),
    }
  },
  setup() {
    const toast = inject('createToast')
    return { toast }
  },
  mounted() {
    this.GetRoles()
  },
  methods: {
    GetRoles() {
      this.ax.get('roles').then(r => {
        this.roles = r.data.data
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
  }
}
</script>

<template>
<Table v-if="roles.length > 0" hoverable>
  <TableHead>
    <TableHeadCell>Id</TableHeadCell>
    <TableHeadCell>Название</TableHeadCell>
    <TableHeadCell><span class="sr-only">Edit</span></TableHeadCell>
  </TableHead>

  <TableBody>
    <TableRow v-for="r in roles">
      <TableCell>{{ r.id }}</TableCell>
      <TableCell>{{ r.name }}</TableCell>
      <TableCell><span class="sr-only">Edit</span></TableCell>
    </TableRow>
  </TableBody>
</Table>

<div>
  <div v-if="errored">
    <p>Ошибка</p>
  </div>
  <div v-else-if="roles.length == 0">
    <p>Нет данных</p>
  </div>
</div>
</template>