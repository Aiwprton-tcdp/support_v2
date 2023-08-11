<script>
import { inject } from 'vue'
import { Centrifuge } from 'centrifuge'
import { Input, Button, Avatar } from 'flowbite-vue'
// import { StringVal } from '@utils/validation.js'
import TicketNotFound from '@states/TicketNotFound.vue'

export default {
  name: 'Tickets',
  components: {
    Input, Button,
    Avatar, TicketNotFound
  },
  props: {
    id: Number()
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
      TicketsCount: Number(),
      limit: Number(15),
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')
    const emitter = inject('emitter')
    const ActiveTab = inject('ActiveTab')

    return {
      UserData, toast,
      emitter, ActiveTab
    }
  },
  mounted() {
    let complete = setInterval(() => {
      if (document.readyState === "complete") {
        clearInterval(complete)
        this.Get(++this.page)
        this.WebsocketInit()
      }
    }, 200)

    this.emitter.on('NewTicket', this.NewTicket)
    this.emitter.on('PatchTicket', this.PatchTicket)
    this.emitter.on('NewMessage', this.NewMessage)
    this.emitter.on('DeleteTicket', this.DeleteTicket)
    this.emitter.on('NewParticipant', this.NewParticipant)
  },
  methods: {
    Get(page = 1) {
      if (this.waiting) return
      this.waiting = true

      let data = this.search.trim()
      const reset_searching = this.searching
      this.searching = data != ''

      this.ax.get(`tickets?page=${page}&limit=${this.limit}&search=${data}`).then(r => {
        this.errored = !r.data.status

        if (data != '' || reset_searching) {
          this.AllTickets = r.data.data.data
        } else {
          if (r.data.status == false) {
            let message = this.UserData.is_admin
              ? 'Не созданы некоторые темы. Перейдите во вкладку "Темы" и заполните недостающие темы'
              : r.data.message
            this.toast(message, 'error')
          }

          this.TicketsCount = r.data.data.meta.total

          if (this.AllTickets.length == 0) {
            this.AllTickets = r.data.data.data
          } else {
            this.TicketsUnion(r.data.data.data)
          }
        }

        this.AllTickets.forEach(t => t.unread_messages = t.last_message_crm_id != this.UserData.crm_id)
        this.tickets = this.AllTickets

        const index = this.AllTickets.findIndex(({ id }) => id == this.CurrentTicket.id)
        if (index > -1) {
          this.AllTickets[index].unread_messages
            = this.AllTickets[index].last_message_crm_id != this.UserData.crm_id
        }

        this.errored = false
      }).catch(e => {
        if (e.response?.status == 401) {
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
    WebsocketInit() {
      const centrifuge = new Centrifuge("wss://aiwprton.sms19.ru:3089/connection/websocket", {
        debug: true,
        subscribeEndpoint: this.ax.defaults.baseURL + "websocket/subscribe",
        onRefresh: (ctx, cb) => {
          let promise = fetch(this.ax.defaults.baseURL + "websocket/refresh", {
            method: "POST",
            user_id: this.currentUserID,
          }).then(resp => {
            resp.json().then(data => {
              localStorage.setItem('support_socket', data.token)
              cb(data.token)
              centrifuge.setToken(data.token)
              centrifuge.connect()
            })
          })
        },
      })
      centrifuge.setToken(localStorage.getItem('support_socket'))

      centrifuge.on('disconnect', function (context) {
        console.log("disconnected")
      })

      centrifuge.on('connect', function (context) {
        console.log("connected")
      })

      const name = `#support.${this.UserData.crm_id}`
      const sub_message = centrifuge.newSubscription(`${name}.message`)
      const sub_ticket = centrifuge.newSubscription(`${name}.ticket`)
      const sub_patch_ticket = centrifuge.newSubscription(`${name}.ticket.patch`)
      const sub_delete_ticket = centrifuge.newSubscription(`${name}.ticket.delete`)
      const sub_participant = centrifuge.newSubscription(`${name}.participant`)

      sub_message.on('publication', msg => {
        const message = msg.data.message

        if (['tickets', 'ticket', 'new_ticket'].includes(this.$route.name)
          && message.user_id != this.UserData.crm_id) {
          if (message.ticket_id == this.CurrentTicket.id) {
            this.emitter.emit('NewMessage', message)
          } else {
            this.NewMessage(message)
          }
        }
      })

      sub_ticket.on('publication', msg => {
        const ticket = msg.data.ticket
        if (['tickets', 'ticket', 'new_ticket'].includes(this.$route.name)) {
          if (this.$route.name == 'new_ticket'
            && ticket.user_id == this.UserData.crm_id) return
          this.emitter.emit('NewTicket', ticket)
        }
      })

      sub_patch_ticket.on('publication', msg => {
        const ticket = msg.data.ticket
        if (['tickets', 'ticket', 'new_ticket'].includes(this.$route.name)) {
          this.emitter.emit('PatchTicket', ticket)
        }
      })

      sub_delete_ticket.on('publication', msg => {
        const msg_id = msg.data.id
        const message = msg.data.message
        const finished = msg.data.finished

        if (['tickets', 'ticket', 'new_ticket'].includes(this.$route.name)) {
          if (message == null && finished) {
            const index = this.AllTickets.findIndex(({ id }) => id == msg_id)
            this.AllTickets[index].marked_as_deleted = true
            this.AllTickets[index].unread_messages = true
            this.AllTickets[index].active = 0

            if (this.AllTickets[index].id == this.CurrentTicket.id) {
              this.CurrentTicket = this.AllTickets[index]
            }
          } else {
            this.emitter.emit('DeleteTicket', msg_id, message)
          }
        }
      })

      sub_participant.on('publication', msg => {
        const participant = msg.data.participant
        if (['tickets', 'ticket', 'new_ticket'].includes(this.$route.name)) {
          // if (this.$route.name == 'tickets'
          //   || this.$route.name == 'ticket'
          //   || this.$route.name == 'new_ticket') {
          this.emitter.emit('NewParticipant', participant)
        }
      })

      sub_message.subscribe()
      sub_ticket.subscribe()
      sub_patch_ticket.subscribe()
      sub_delete_ticket.subscribe()
      sub_participant.subscribe()
      centrifuge.connect()
    },
    GoToNewTicket() {
      this.CurrentTicket.id = 0
      this.$router.push({ name: 'new_ticket' })
    },
    TicketsSorting() {
      const crm_id = this.UserData.crm_id
      this.AllTickets = this.AllTickets.sort((t1, t2) =>
        (t1.unread_messages < t2.unread_messages) - (t1.unread_messages > t2.unread_messages)
        || (t1.last_message_crm_id == crm_id) - (t2.last_message_crm_id == crm_id)
        || (t2.weight - t1.weight)
        || (new Date(t1.last_message_date) - new Date(t2.last_message_date)))

      this.TicketsUnion()
    },
    TicketsUnion(data = []) {
      let map = new Map()
      Array.from([this.AllTickets, data].flat()).forEach(e => {
        map.set(e.id, e)
      })
      this.AllTickets = [...map.values()]
    },
    NewMessage(data) {
      if (this.CurrentTicket.id == data.ticket_id) return

      const index = this.AllTickets.findIndex(({ id }) => id == data.ticket_id)

      if (index == -1) {
        this.ax.get(`tickets/${data.ticket_id}`).then(r => {
          const ticket = r.data.data.data
          ticket.unread_messages = true
          this.AllTickets.push(ticket)
          this.TicketsSorting()
        }).catch(e => {
          this.toast(e.response.data.message, 'error')
        })
      } else {
        this.AllTickets[index].unread_messages = true
        this.TicketsSorting()
      }
    },
    NewTicket(data) {
      data.unread_messages = data.user_id != this.UserData.crm_id
      this.AllTickets.push(data)
      this.TicketsCount++
      this.TicketsSorting()
    },
    PatchTicket(data) {
      const index = this.AllTickets.findIndex(({ id }) => id == data.id)
      if (index == -1) return
      this.AllTickets[index].reason = data.reason
    },
    DeleteTicket(ticket_id, message) {
      this.toast(message, 'success')

      const index = this.tickets.findIndex(({ id }) => id == ticket_id)
      if (index == -1) return

      if (this.AllTickets.length < this.TicketsCount--) {
        let page = Math.floor(index / this.limit) + 1
        this.ax.get(`tickets?page=${page}&limit=${this.limit}`).then(r => {
          this.TicketsUnion(r.data.data.data)
          this.AllTickets.forEach(t => t.unread_messages = t.last_message_crm_id != this.UserData.crm_id)
          this.TicketsSorting()
        })
      }

      this.AllTickets.splice(index, 1)
      const is_current_ticket = this.CurrentTicket.id == ticket_id
      this.CurrentTicket.id = 0

      if (this.$route.name == 'ticket' && is_current_ticket) {
        this.$router.push('tickets')
      }
    },
    NewParticipant(data) {
      const index = this.tickets.findIndex(({ id }) => id == data.ticket_id)
      this.tickets[index].manager = data.new_manager
      this.tickets[index].manager_id = data.new_manager.crm_id
    },
    TryToGoToForcedTicket() {
      if (window.ticket_id > 0 && this.page == 1) {
        this.ax.get(`tickets/${window.ticket_id}`).then(r => {
          if (!r.data.status) {
            this.ActiveTab = 'archive'
            this.$router.push({ name: 'archive' })
            return
          }

          const ticket = r.data.data.data
          if (ticket == null) {
            this.ActiveTab = 'archive'
            this.$router.push({ name: 'archive' })
          } else {
            this.GoTo(ticket)
            window.ticket_id = 0
          }
        }).catch(e => {
          if (e.response?.status == 404) {
            this.ticket401 = true
            return
          }
          this.toast(e.response.data.message, 'error')
          this.errored = true
        })
      }
    },
    GoTo(t) {
      if (this.CurrentTicket.id == t.id) {
        this.CurrentTicket.id = 0
        this.$router.replace({ name: 'tickets' })
      } else {
        this.CurrentTicket = { ...t }
        const index = this.AllTickets.findIndex(({ id }) => id == t.id)
        if (index > -1) {
          this.AllTickets[index].unread_messages = false
          // = this.AllTickets[index].last_message_crm_id != this.UserData.crm_id
        }
        this.$router.push({ name: 'ticket', params: { id: t.id } })
      }
    },
    ClearSearch() {
      this.search = ''
      this.page = 1
      this.Get()
    },
    onScroll(e) {
      if (this.searching
        || this.waiting
        || this.AllTickets.length >= this.TicketsCount) return

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
      <Input @keyup.enter="Get()" v-model="search" placeholder="Поиск" label="" class="flex-1">
      <template #prefix>
        <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor"
          viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
      </template>
      <template #suffix v-if="search.length > 0">
        <svg @click="ClearSearch()" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
          stroke="currentColor" class="text-black-800 w-5 h-5 cursor-pointer">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z" />
        </svg>
      </template>
      </Input>
      <Button v-if="search.length > 0" @click="Get()" color="default">Искать</Button>
    </div>

    <Button :disabled="errored" @click="GoToNewTicket()" color="default">
      <span class="items-center font-bold dark:text-gray-900">&#10010;&nbsp;&nbsp;Новое обращение</span>
    </Button>
  </div>

  <div class="grid divide-x max-h-[calc(100vh-55px)]"
    :class="UserData.is_admin && UserData.role_id == 2 ? 'grid-cols-6' : 'grid-cols-5'">
    <!-- Skeleton -->
    <template v-if="waiting && this.page == 1">
      <div
        class="flex flex-col h-[calc(100vh-55px)] divide-y overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
        <div v-for="key in 10" class="flex flex-row items-center w-full gap-2 p-1">
          <svg class="w-10 h-10 text-gray-200 dark:text-gray-700" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
            fill="currentColor" viewBox="0 0 20 20">
            <path
              d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6Zm0 13a8.949 8.949 0 0 1-4.951-1.488A3.987 3.987 0 0 1 9 13h2a3.987 3.987 0 0 1 3.951 3.512A8.949 8.949 0 0 1 10 18Z" />
          </svg>
          <div class="max-w-[80%] flex flex-col">
            <div class="h-2.5 bg-gray-200 rounded-full dark:bg-gray-700 w-32 mb-2"></div>
            <div class="w-48 h-2 bg-gray-200 rounded-full dark:bg-gray-700"></div>
          </div>
        </div>
      </div>
    </template>

    <p v-else-if="AllTickets.length == 0" class="text-center text-gray-400 m-8">
      Здесь будет список Ваших активных тикетов
    </p>
    <p v-else-if="searching && AllTickets.length == 0" class="flex flex-col text-center text-gray-400 m-8">
      По данномму запросу нет совпадений
      <Button @click="ClearSearch()" color="default">Очистить</Button>
    </p>

    <div v-else id="tickets" @scroll="onScroll"
      class="flex flex-col h-[calc(100vh-55px)] divide-y overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
      <div v-for="t in tickets" class="p-1"
        :class="t.id == CurrentTicket?.id ? 'bg-blue-200 dark:bg-blue-500' : 'bg-white hover:bg-gray-100 dark:bg-gray-600 dark:hover:bg-gray-800'">
        <div @click.self="GoTo(t)" class="relative flex flex-row items-center w-full gap-2 cursor-pointer">
          <a :href="VITE_CRM_URL + 'company/personal/user/' + (UserData.crm_id == t.user_id ? t.manager_id : t.user_id) + '/'"
            target="_blank">
            <Avatar rounded size="sm" alt="avatar" :title="'Id тикета: ' + t.id"
              :img="(UserData.crm_id == t.user_id ? t.manager?.avatar : t.user?.avatar) ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
          </a>
          <div @click="GoTo(t)" class="max-w-[80%] flex flex-col cursor-pointer">
            <p class="truncate" :title="UserData.crm_id == t.user_id ? t.manager?.name : t.user?.name">
              {{ UserData.crm_id == t.user_id ? t.manager?.name : t.user?.name }}
            </p>
            <p class="truncate" :title="t.reason">{{ t.reason }}</p>
          </div>

          <div v-if="t.reset_deleted_mark" class="absolute inline-flex items-center justify-center right-0">
            <span title="Создатель вернул тикет в работу"
              class="flex w-2.5 h-2.5 bg-red-500 rounded-full flex-shrink-0"></span>
          </div>
          <div v-else-if="t.marked_as_deleted" class="absolute inline-flex items-center justify-center right-0">
            <span title="Ответственный пометил данный тикет на удаление"
              class="flex w-2.5 h-2.5 bg-yellow-300 rounded-full flex-shrink-0"></span>
          </div>
          <div v-else-if="t.unread_messages" class="absolute inline-flex items-center justify-center right-0">
            <span title="Есть сообщение, ожидающее Вашего ответа"
              class="flex w-2.5 h-2.5 bg-indigo-500 rounded-full flex-shrink-0"></span>
          </div>
        </div>
      </div>
    </div>

    <div class="h-[calc(100vh-55px)] flex flex-col items-center" :class="[UserData.is_admin && UserData.role_id == 2 ? 'col-span-5' : 'col-span-4',
    { 'justify-center': $route.name == 'tickets' }]">
      <div v-if="errored" class="flex flex-col">
        <p class="mx-auto">Произошла непредвиденная ошибка</p>

        <button @click="Get()"
          class="mx-auto text-sm pb-1 no-underline hover:underline border-0 focus:outline-none bg-transparent decoration-dotted underline-offset-4">
          <p>Перезагрузить</p>
        </button>
      </div>

      <TicketNotFound v-else-if="ticket401 && $route.name == 'tickets'" />

      <RouterView v-else-if="$route.name != 'tickets'" :key="$route.fullPath" :ticket="CurrentTicket" />

      <div v-else class="flex flex-col gap-3">
        <template v-if="AllTickets.length == 0">
          <p class="mx-auto text-center w-full lg:w-2/3">
            У Вас нет активных обращений. Чтобы создать новый тикет, нажмите на кнопку ниже и следуйте инструкции
          </p>
          <Button @click="GoToNewTicket()" color="default" class="mx-auto"><!-- style="background-color:#b5c3f4"-->
            <span class="mx-auto font-bold dark:text-gray-900">Создать</span>
          </Button>
        </template>
        <template v-else>
          <p class="mx-auto text-center text-gray-400 w-full lg:w-2/3">
            Чтобы начать, выберите тикет из списка
          </p>
        </template>
      </div>
    </div>
  </div>
</template>