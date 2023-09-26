<script>
import { inject } from 'vue'
import {
  Input as VueInput,
  Button as VueButton,
  Avatar
} from 'flowbite-vue'

import TicketNotFound from '@states/TicketNotFound.vue'

export default {
  name: 'ArchivePage',
  components: {
    VueInput, VueButton,
    Avatar, TicketNotFound
  },
  data() {
    return {
      AllTickets: Array(),
      tickets: Array(),
      CurrentTicket: Object(),
      managers: Array(),
      errored: Boolean(),
      waiting: Boolean(),
      searching: Boolean(),
      ticket401: Boolean(),
      search: String(),
      page: Number(),
      limit: Number(30),
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')
    const emitter = inject('emitter')

    return {
      UserData,
      toast,
      emitter
    }
  },
  mounted() {
    this.Get(++this.page)
  },
  methods: {
    Get(page = 1) {
      if (this.waiting) return
      this.waiting = true

      let data = this.search.trim()
      const reset_searching = this.searching
      this.searching = data != ''

      this.ax.get(`resolved_tickets?page=${page}&limit=${this.limit}&search=${data}`).then(r => {
        this.errored = !r.data.status
        if (r.data.status == false) {
          this.toast(r.data.message, 'error')
        }

        if (data != '' || reset_searching) {
          this.AllTickets = r.data.data.data
        } else {
          if (this.AllTickets.length == 0) {
            this.AllTickets = r.data.data.data
          } else {
            this.TicketsUnion(r.data.data.data)
          }
        }
        this.tickets = this.AllTickets
      }).catch(e => {
        if (e.response.status == 401) {
          this.Get()
          return
        }
        this.toast(e.response.data.message, 'error')
        this.errored = true
      }).finally(() => {
        this.TryToGoToForcedTicket()
        this.waiting = false
      })
    },
    TicketsUnion(data = []) {
      let map = new Map()
      Array.from([this.AllTickets, data].flat()).forEach(e => {
        map.set(e.id, e)
      })
      this.AllTickets = [...map.values()]
    },
    TryToGoToForcedTicket() {
      if (window.ticket_id > 0 && this.page == 1) {
        this.ax.get(`resolved_tickets/${window.ticket_id}`).then(r => {
          if (!r.data.status) {
            this.ticket401 = true
            return
          }

          const ticket = r.data.data
          if (ticket == null) {
            this.ticket401 = true
          } else {
            this.GoTo(ticket)
          }
        }).catch(e => {
          if (e.response?.status == 404) {
            this.ticket401 = true
            return
          }
          this.toast(e.response.data.message, 'error')
          this.errored = true
        }).finally(window.ticket_id = 0)
      }
    },
    GoTo(t) {
      if (this.CurrentTicket.old_ticket_id == t.old_ticket_id) {
        this.CurrentTicket.old_ticket_id = 0
        this.$router.push({ name: 'archive' })
      } else {
        this.CurrentTicket = { ...t }
        this.$router.push({ name: 'archive_ticket', params: { id: t.old_ticket_id } })
      }
    },
    Search() {
      let data = this.search.trim()
      if (data.length == 0) {
        this.ClearSearch()
        return
      }

      const id = data.replaceAll(/[^0-9]+/g, '').trim()
      const text = data.replaceAll(/[^А-яA-z ]+/g, '').trim().toLowerCase()

      this.tickets = this.AllTickets.filter(t =>
        id.length > 0 && t.id.toString().includes(id) ||
        text.length > 0 && t.reason.toLowerCase().includes(text) ||
        t.user.name.toLowerCase().includes(text) ||
        t.manager.name.toLowerCase().includes(text)
      )
    },
    ClearSearch() {
      this.search = ''
      this.page = 1
      this.Get()
    },
    onScroll(e) {
      if (this.searching
        || this.waiting
        || this.AllTickets.length % this.limit != 0) return

      const { scrollTop, offsetHeight, scrollHeight } = e.target

      if ((scrollTop + offsetHeight + 200) >= scrollHeight) {
        this.Get(++this.page)
      }
    },
  }
}
</script>

<template>
  <!-- Search in navigation -->
  <div class="fixed top-1 right-1 flex flex-row space-x-4">
    <div v-if="AllTickets.length > 0" class="flex flex-wrap space-x-2">
      <VueInput @keyup.enter="Get()" v-model="search" v-focus placeholder="Поиск" label="" class="flex-1">
        <template #prefix>
          <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor"
            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </template>
        <template #suffix v-if="search.length > 0">
          <svg @click="ClearSearch()" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
            stroke-width="1.5" stroke="currentColor" class="text-black-800 w-5 h-5 cursor-pointer">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z" />
          </svg>
        </template>
      </VueInput>
      <VueButton v-if="search.length > 0" @click="Get()" color="default">Искать</VueButton>
    </div>
  </div>

  <div class="grid divide-x max-h-[calc(100vh-55px)]"
    :class="UserData.is_admin && UserData.role_id == 2 ? 'grid-cols-5' : 'grid-cols-4'">
    <!-- Skeleton -->
    <template v-if="waiting && this.page == 1">
      <div
        class="flex flex-col h-[calc(100vh-55px)] divide-y overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
        <div v-for="key in 10" v-bind:key="key" class="flex flex-row items-center w-full gap-2 p-1 cursor-pointer">
          <svg class="w-10 h-10 text-gray-200 dark:text-gray-700" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
            fill="currentColor" viewBox="0 0 20 20">
            <path
              d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6Zm0 13a8.949 8.949 0 0 1-4.951-1.488A3.987 3.987 0 0 1 9 13h2a3.987 3.987 0 0 1 3.951 3.512A8.949 8.949 0 0 1 10 18Z" />
          </svg>
          <div class="max-w-[80%] flex flex-col cursor-pointer">
            <div class="h-2.5 bg-gray-200 rounded-full dark:bg-gray-700 w-32 mb-2"></div>
            <div class="w-48 h-2 bg-gray-200 rounded-full dark:bg-gray-700"></div>
          </div>
        </div>
      </div>
    </template>

    <p v-else-if="searching && AllTickets.length == 0" class="flex flex-col text-center text-gray-400 m-8">
      По данномму запросу нет совпадений
      <VueButton @click="ClearSearch()" color="default">Очистить</VueButton>
    </p>
    <p v-if="AllTickets.length == 0" class="text-center text-gray-400 m-10">
      Здесь будет список Ваших завершённых тикетов
    </p>

    <div v-else id="archive" @scroll="onScroll"
      class="flex flex-col max-h-[calc(100vh-55px)] divide-y overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
      <TransitionGroup name="list" tag="ul">
        <div v-for="t in tickets" v-bind:key="t" class="p-1"
          :class="t.old_ticket_id == CurrentTicket?.old_ticket_id ? 'bg-blue-200 dark:bg-blue-500' : 'bg-white hover:bg-gray-100 dark:bg-gray-600 dark:hover:bg-gray-800'">
          <div @click.self="GoTo(t)" class="flex flex-row items-center w-full gap-2 cursor-pointer">
            <a :href="VITE_CRM_URL + 'company/personal/user/' + (UserData.user_id == t.user_id ? t.manager_crm_id : t.user_crm_id) + '/'"
              target="_blank">
              <Avatar rounded size="sm" alt="avatar" :title="UserData.user_id == t.user_id ? t.manager.name : t.user.name"
                :img="(UserData.user_id == t.user_id ? t.manager.avatar : t.user.avatar) ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
            </a>
            <div @click="GoTo(t)" class="max-w-[80%] flex flex-col cursor-pointer">
              <p class="truncate" :title="UserData.user_id == t.user_id ? t.manager.name : t.user.name">
                {{ UserData.user_id == t.user_id ? t.manager.name : t.user.name }}
              </p>
              <p class="truncate" :title="t.reason">{{ t.reason }}</p>
            </div>
          </div>
        </div>
      </TransitionGroup>
    </div>

    <div class="flex flex-col justify-center h-[calc(100vh-55px)]"
      :class="UserData.is_admin && UserData.role_id == 2 ? 'col-span-4' : 'col-span-3'">
      <div v-if="errored" class="flex flex-col">
        <p class="mx-auto">Произошла непредвиденная ошибка</p>

        <button @click="Get()"
          class="mx-auto text-sm pb-1 no-underline hover:underline border-0 focus:outline-none bg-transparent decoration-dotted underline-offset-4">
          <p>Перезагрузить</p>
        </button>
      </div>

      <TicketNotFound v-else-if="ticket401 && $route.name == 'archive'" />

      <RouterView v-else-if="$route.name != 'archive'" :key="$route.fullPath" :ticket="CurrentTicket" />

      <div v-else class="flex flex-col gap-3">
        <template v-if="AllTickets.length == 0">
          <p class="mx-auto text-center w-full lg:w-2/3">
            У Вас нет завершённых обращений
          </p>
        </template>
        <template v-else>
          <p class="mx-auto text-center text-gray-400 w-full lg:w-2/3">
            Выберите тикет из списка
          </p>
        </template>
      </div>
    </div>
  </div>
</template>