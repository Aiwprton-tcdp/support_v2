<script>
import { inject } from 'vue'
import {
  Input, Button,
} from 'flowbite-vue'
import { StringVal } from '@utils/validation.js'

export default {
  name: 'Groups',
  components: {
    Input, Button,
  },
  data() {
    return {
      AllTickets: Array(),
      tickets: Array(),
      CurrentTicket: Object(),
      managers: Array(),
      errored: Boolean(),
      search: String(),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')
    return { UserData, toast }
  },
  mounted() {
    let complete = setInterval(() => {
      if (document.readyState === "complete") {
        clearInterval(complete)
        this.Get()
      }
    }, 200)
  },
  methods: {
    Get(active = true) {
      this.ax.get(`tickets?active=${active}`).then(r => {
        // console.log(r.status)
        console.log(r.data.data)
        this.AllTickets = r.data.data.data
        this.tickets = this.AllTickets
      }).catch(e => {
        if (e.response.status == 401) {
          this.Get()
          return
        }
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    Create(message) {
      if (StringVal(message)) return

      this.ax.post('tickets', {
        message: message,
      }).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (!r.data.status) return
        this.tickets.push(r.data.data)
        const new_message = r.data.new_message
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    // Patch(group_id) {
    //   if (StringVal(this.PatchingName)) return

    //   this.ax.patch(`groups/${group_id}`, {
    //     name: this.PatchingName
    //   }).then(r => {
    //     this.toast(r.data.message, r.data.status ? 'success' : 'error')
    //     if (!r.data.status) return

    //     const index = this.AllGroups.findIndex(({id}) => id == group_id)
    //     this.AllGroups[index] = r.data.data
    //     this.PatchingId = 0
    //     this.PatchingName = ''
    //     this.groups = this.AllGroups
    //   }).catch(e => {
    //     this.toast(e.response.data.message, 'error')
    //   })
    // },
    // Delete(group_id) {
    //   this.ax.delete(`groups/${group_id}`).then(r => {
    //     this.toast(r.data.message, r.data.status ? 'success' : 'warning')
    //     if (!r.data.status) return

    //     const index = this.AllGroups.findIndex(({id}) => id == group_id)
    //     this.AllGroups.splice(index, 1)
    //     this.groups = this.AllGroups
    //   }).catch(e => {
    //     this.toast(e.response.data.message, 'error')
    //   })
    // },
    Search() {
      let data = this.search.trim()
      if (data.length == 0) {
        this.ClearSearch()
        return
      }

      const id = data.replaceAll(/[^0-9]+/g, '').trim()
      const text = data.replaceAll(/[^А-яA-z ]+/g, '').trim().toLowerCase()

      this.tickets = this.AllTickets.filter(g =>
        id.length > 0 && g.id.toString().includes(id) ||
        text.length > 0 && g.name.toLowerCase().includes(text)
      )
    },
    ClearSearch() {
      this.search = ''
      this.tickets = this.AllTickets
    },
  }
}
</script>

<template>
  <div class="grid grid-cols-3 md:grid-cols-5 gap-2">
    <div v-if="errored || tickets.length == 0">
      <p v-if="errored">Ошибка</p>
      <p v-else>Нет данных</p>

      <button @click="Get()"
        class="text-sm pb-1 no-underline hover:underline border-0 focus:outline-none bg-transparent decoration-dotted underline-offset-4">
        <p>Перезагрузить</p>
      </button>
    </div>

    <div v-else class="flex flex-col h-[calc(100vh-100px)] gap-2">
      <div class="flex flex-wrap space-x-3">
        <Input @keyup.enter="Search()" v-model="search" placeholder="Поиск" label="" class="flex-1">
        <template #prefix>
          <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor"
            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </template>
        </Input>
        <Button v-if="search.length > 0" @click="Search()" color="default">Искать</Button>
        <Button v-if="search.length > 0" @click="ClearSearch()" color="light">Сброс</Button>
      </div>

      <div
        class="flex flex-col max-h-full gap-2 overflow-hidden mr-1 hover:mr-0 hover:overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
        <div v-for="t in tickets" class="rounded-md border p-3 cursor-pointer flex flex-col">
          <RouterLink :to="{ name: 'ticket', params: { id: t.id } }" @click="CurrentTicket = t">
            <div class="flex">
              <img class="w-8 h-8 mr-2 rounded-full" :src="t.manager.avatar" alt="">
              <!-- <img class="w-8 h-8 mr-2 rounded-full" :src="t.user.avatar" alt=""> -->
              <span class="truncate">{{ t.manager.name }}</span>
              <!-- <span>{{ t.user.name }}</span> -->
            </div>
            <span>{{ t.reason }}</span>
          </RouterLink>
        </div>
      </div>
    </div>

    <div class="flex flex-col col-span-2 md:col-span-4 h-[calc(100vh-100px)] mx-1">
      <Button @click="Create('У меня много лидов с невидимыми задачами, просьба убрать их с меня')"
      class="fixed top-0 right-0 w-48 mb-2"
        color="green">
        <span class="font-bold">&#10010;&nbsp;&nbsp;</span>
        Новое обращение
      </Button>

      <RouterView :data="CurrentTicket" />
    </div>
  </div>
</template>