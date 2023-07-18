<script>
import { ref, inject } from 'vue'
import {
  Table, TableHead,
  TableBody, TableHeadCell,
  TableRow, TableCell,
  Input, Button,
  Select
} from 'flowbite-vue'

export default {
  name: 'Users',
  components: {
    Table, TableHead,
    TableBody, TableHeadCell,
    TableRow, TableCell,
    Input, Button,
    Select
  },
  data() {
    return {
      AllUsers: Array(),
      users: Array(),
      AllUsersWithRoles: Array(),
      // groups: Array(),
      roles: Array(),
      errored: Boolean(),
      search: String(),
      role: Number(),
      only_with_roles: Boolean(true),
    }
  },
  setup() {
    const toast = inject('createToast')
    return { toast }
  },
  mounted() {
    this.GetManagers()
    // this.GetGroups()
    this.GetRoles()
  },
  methods: {
    Get() {
      this.ax.get('bx/users').then(r => {
        this.AllUsers = r.data.data.data
        this.users = this.AllUsers
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    GetManagers() {
      this.ax.get('managers').then(r => {
        this.AllUsersWithRoles = r.data.data.data
        
        if (this.AllUsersWithRoles.length == 0) {
          this.toast('Пока что нет ни одного сотрудника с ролью')
          this.only_with_roles = false
          this.Get()
          return
        }

        this.AllUsersWithRoles.forEach(u => u.new_role_id = u.role_id)
        this.users = this.AllUsersWithRoles
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    GetRoles() {
      this.ax.get('roles').then(r => {
        this.roles = [{id: 0, name: 'Не указана'}].concat(r.data.data)
        this.roles.forEach(r => r.value = r.id)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Create(data) {
      if (data.new_role_id < 2) {
        this.Delete(data)
        return
      } else if (data.hasOwnProperty('id')) {
        this.Patch(data)
        return
      }

      this.ax.post('managers', {
        name: data.name,
        crm_id: data.crm_id,
        role_id: data.new_role_id,
      }).then(r => {
        this.toast(r.data.message, 'success')

        this.AllUsersWithRoles.push(r.data.data)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Patch(data) {
      this.ax.patch(`managers/${data.id}`, {
        name: data.name,
        role_id: data.new_role_id,
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'error')
          data.new_role_id = data.role_id
          return
        }
        this.toast(r.data.message, 'success')
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        data.new_role_id = data.role_id
      })
    },
    Delete(data) {
      this.ax.delete(`managers/${data.id}`).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'error')
          data.new_role_id = data.role_id
          return
        }
        this.toast(r.data.message, 'success')

        const index = this.AllUsersWithRoles.findIndex(({id}) => id == data.id)
        this.AllUsersWithRoles.splice(index, 1)
        this.users = this.AllUsersWithRoles

        if (this.AllUsersWithRoles.length == 0) {
          this.GetManagesOrAll()
        }
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        data.new_role_id = data.role_id
      })
    },
    GetManagesOrAll() {
      this.only_with_roles = !this.only_with_roles
      this.ClearSearch()
    },
    Search() {
      let data = this.search.trim()
      if (data.length == 0) {
        this.ClearSearch()
        return
      }

      const id = data.replaceAll(/[^0-9]+/g, '').trim()
      const text = data.replaceAll(/[^А-я ]+/g, '').trim().toLowerCase()
      const expression = u => 
        id.length > 0 && u.crm_id.toString().includes(id)
        || text.length > 0
        && (u.name.toLowerCase().includes(text)
          || u.post.toLowerCase().includes(text))

      this.users = (this.only_with_roles ? this.AllUsersWithRoles : this.AllUsers).filter(expression)
    },
    ClearSearch() {
      this.search = ''
      // this.users = this.only_with_roles ? this.AllUsersWithRoles : this.AllUsers
      
      this.users = this.only_with_roles
        ? this.AllUsersWithRoles.length == 0 ? this.GetManagers() : this.AllUsersWithRoles
        : this.AllUsers.length == 0 ? this.Get() : this.AllUsers
    },
  }
}
</script>

<template>
<div v-if="!only_with_roles && AllUsers.length > 0 || only_with_roles && AllUsersWithRoles.length > 0" class="space-y-4">
  <div class="flex flex-wrap space-x-4">
    <div class="flex flex-wrap space-x-3 w-1/2">
      <Input @keyup.enter="Search()" v-model="search" placeholder="Введите id, ФИО или должность" label="" class="flex-1">
        <template #prefix>
          <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </template>
      </Input>
      <Button v-if="search.length > 0" @click="Search()" color="default">Искать</Button>
      <Button v-if="search.length > 0" @click="ClearSearch()" color="light">Сброс</Button>
    </div>
    <button v-if="only_with_roles || !only_with_roles && this.AllUsersWithRoles.length > 0"
      @click="GetManagesOrAll()"
      class="text-sm pb-1 no-underline hover:underline border-0 focus:outline-none bg-transparent decoration-dotted underline-offset-4">
      <p v-if="!only_with_roles">показать только с ролями</p>
      <p v-else>показать всех</p>
    </button>
  </div>

  <Table v-if="users.length > 0" hoverable>
    <TableHead>
      <TableHeadCell>Id</TableHeadCell>
      <TableHeadCell>ФИО</TableHeadCell>
      <TableHeadCell>Должность</TableHeadCell>
      <TableHeadCell>Внутренний номер</TableHeadCell>
      <TableHeadCell>Роль</TableHeadCell>
    </TableHead>

    <TableBody>
      <TableRow v-for="u in users">
        <TableCell>{{ u.crm_id }}</TableCell>
        <TableCell>{{ u.name }}</TableCell>
        <TableCell>{{ u.post }} {{ u.role_id }}</TableCell>
        <TableCell>{{ u.inner_phone }}</TableCell>
        <TableCell>
          <div class="space-x-3">
            <Select @change="Create(u)" v-model.number="u.new_role_id" :options="roles" placeholder="Выбрать роль" />
          </div>
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>
</div>

<div v-else>
  <div v-if="errored">
    <p>Ошибка</p>
  </div>
  <div v-else>
    <p>Загрузка</p>
  </div>
</div>
</template>
