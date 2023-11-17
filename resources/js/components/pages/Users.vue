<script>
import { inject } from 'vue'
import {
  Table as VueTable, TableHead,
  TableBody, TableHeadCell,
  TableRow, TableCell,
  Input as VueInput,
  Button as VueButton,
  Select as VueSelect
} from 'flowbite-vue'
import useClipboard from 'vue-clipboard3';

export default {
  name: 'UsersPage',
  components: {
    VueTable, TableHead,
    TableBody, TableHeadCell,
    TableRow, TableCell,
    VueInput, VueButton,
    VueSelect
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
    const { toClipboard } = useClipboard();

    const copy = async data => {
      try {
        await toClipboard(data)
        console.log('Copied to clipboard')
      } catch (e) {
        console.error(e)
      }
    }
    return { toast, copy }
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
        // console.log('this.AllUsers')
        // console.log(this.AllUsers)
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
        // console.log('this.AllUsersWithRoles')
        // console.log(this.AllUsersWithRoles)
        this.users = this.AllUsersWithRoles
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    GetRoles() {
      this.ax.get('roles').then(r => {
        this.roles = [{ id: 0, name: 'Не указана' }].concat(r.data.data)
        this.roles.forEach(r => r.value = r.id)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Create(data) {
      if (!this.only_with_roles && data.new_role_id == 0) {
        return
      } else if (data.user_id == null) {
        this.toast('Данный пользователь ещё не прошёл аутентификацию в интеграции', 'error')
        return
      } else if (data.new_role_id < 2) {
        this.Delete(data)
        return
      } else if (Object.prototype.hasOwnProperty.call(data, 'id')) {
        this.Patch(data)
        return
      }

      this.ax.post('managers', {
        name: data.name,
        email: data.email,
        // crm_id: data.crm_id,
        role_id: data.new_role_id,
      }).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (!r.data.status) {
          const index = this.users.findIndex(({ user_id }) => user_id == data.user_id)
          this.users[index].new_role_id = 0
        }

        this.AllUsersWithRoles.push(r.data.data)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        const index = this.users.findIndex(({ user_id }) => user_id == data.user_id)
        this.users[index].new_role_id = 0
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

        const index = this.AllUsersWithRoles.findIndex(({ id }) => id == data.id)
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
        id.length > 0 && u.user_id.toString().includes(id)
        || text.length > 0
        && (u.name.toLowerCase().includes(text)
          || u?.post.toLowerCase().includes(text))

      this.users = (this.only_with_roles ? this.AllUsersWithRoles : this.AllUsers).filter(expression)
    },
    ClearSearch() {
      this.search = ''
      this.users = this.only_with_roles
        ? this.AllUsersWithRoles.length == 0 ? this.GetManagers() : this.AllUsersWithRoles
        : this.AllUsers.length == 0 ? this.Get() : this.AllUsers
    },
    GetToken(id) {
      this.ax.get(`token/gen/${id}`).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'error');
          return;
        }
        const token = r.data.data;
        this.copy(token);
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      });
    },
  }
}
</script>

<template>
  <template v-if="!only_with_roles && AllUsers.length > 0 || only_with_roles && AllUsersWithRoles.length > 0">
    <div class="fixed top-1 right-1 space-y-4">
      <div class="flex flex-wrap space-x-4">
        <button v-focus v-if="only_with_roles || !only_with_roles && this.AllUsersWithRoles.length > 0"
          @click="GetManagesOrAll()"
          class="text-sm pb-1 no-underline hover:underline border-0 focus:outline-none bg-transparent decoration-dotted underline-offset-4">
          <p v-if="!only_with_roles">показать только с ролями</p>
          <p v-else>показать всех</p>
        </button>

        <div class="flex flex-row space-x-2">
          <VueInput @keyup.enter="Search()" v-model="search" v-focus placeholder="Поиск" label="" class="w-48">
            <template #prefix v-if="search.length == 0">
              <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor"
                viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
            </template>
            <template #suffix v-else>
              <svg @click="ClearSearch()" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke-width="1.5" stroke="currentColor" class="text-black-800 w-5 h-5 cursor-pointer">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z" />
              </svg>
            </template>
          </VueInput>
          <VueButton :disabled="search.length == 0" @click="Search()" color="default">
            Искать
          </VueButton>
        </div>
      </div>
    </div>

    <VueTable
      class="max-h-[calc(100vh-54px)] overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
      <TableHead>
        <!-- <TableHeadCell>Crm_id</TableHeadCell> -->
        <TableHeadCell>ФИО</TableHeadCell>
        <TableHeadCell v-if="!only_with_roles">Должность</TableHeadCell>
        <!-- <TableHeadCell v-if="!only_with_roles">Внутренний номер</TableHeadCell> -->
        <TableHeadCell>Токен</TableHeadCell>
        <TableHeadCell>Роль</TableHeadCell>
      </TableHead>

      <TableBody>
        <TableRow v-for="u in users" v-bind:key="u">
          <!-- <TableCell>{{ u.crm_id }}</TableCell> -->
          <TableCell>
            <span class="flex items-center text-sm font-medium text-gray-900 dark:text-white me-3">
              <span v-if="only_with_roles" class="flex w-3 h-3 me-3 rounded-full cursor-help"
                :class="u.in_work ? 'bg-green-500' : 'bg-red-500'" :title="u.in_work ? 'В работе' : 'Не в работе'"></span>
              {{ u.name }}
            </span>
          </TableCell>
          <TableCell v-if="!only_with_roles">{{ u.post }}</TableCell>
          <!-- <TableCell v-if="!only_with_roles">{{ u.inner_phone }}</TableCell> -->
          <TableCell>
            <div class="space-x-3">
              <svg @click="GetToken(u.user_id)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke-width="1.5" stroke="currentColor" class="w-6 h-6 cursor-pointer hover:text-sky-500">
                <title>Скопировать токен</title>
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M16.5 8.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v8.25A2.25 2.25 0 006 16.5h2.25m8.25-8.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-7.5A2.25 2.25 0 018.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 00-2.25 2.25v6" />
              </svg>
            </div>
          </TableCell>
          <TableCell>
            <div class="space-x-3">
              <VueSelect @change="Create(u)" v-model.number="u.new_role_id" :options="roles" placeholder="Выбрать роль" />
            </div>
          </TableCell>
        </TableRow>
      </TableBody>
    </VueTable>
  </template>

  <div v-else-if="errored" class="flex flex-col gap-3 mt-3">
    <p v-if="errored" class="mx-auto text-center text-gray-400 w-full lg:w-2/3">
      Произошла непредвиденная ошибка
    </p>
    <p v-else-if="groups.length == 0" class="mx-auto text-center text-gray-400 w-full lg:w-2/3">
      Загружаем данных
    </p>
  </div>
</template>
