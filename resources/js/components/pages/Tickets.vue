<script>
import { inject } from 'vue'
import { Centrifuge } from 'centrifuge'
import { Input, Button, Avatar } from 'flowbite-vue'
import { StringVal } from '@utils/validation.js'
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
      ticket401: Boolean(),
      search: String(),
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')
    const emitter = inject('emitter')

    return {
      UserData, toast,
      emitter
    }
  },
  mounted() {
    let complete = setInterval(() => {
      if (document.readyState === "complete") {
        clearInterval(complete)
        this.Get()
      }
    }, 200)
    this.emitter.on('NewTicket', this.NewTicket)
  },
  methods: {
    Get(active = true) {
      this.ax.get(`tickets?active=${active}`).then(r => {
        if (r.data.status == false) {
          this.toast(r.data.message, 'error')
        }

        this.AllTickets = r.data.data.data
        this.tickets = this.AllTickets
      }).catch(e => {
        if (e.response.status == 401) {
          this.Get()
          return
        }
        this.toast(e.response.data.message, 'error')
        this.errored = true
      }).finally(() => {
        if (window.ticket_id > 0) {
          const ticket = this.tickets.find(t => t.id == window.ticket_id)
          // console.log(ticket)
          if (ticket == null) {
            this.ticket401 = true
            return
          }
          this.GoTo(ticket)
        }
        this.WebsocketInit()
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
        console.log(context)
      })

      centrifuge.on('connect', function (context) {
        console.log("connected")
        console.log(context)
      })

      const sub = centrifuge.newSubscription("#support." + this.UserData.crm_id)//ticket_id)

      sub.on('publication', msg => {
        const message = msg.data.message

        if (message.ticket_id == this.CurrentTicket.id
          && message.user_id != this.UserData.crm_id
          && this.$route.name == 'ticket'
          && this.$route.params?.id == message.ticket_id) {
          this.emitter.emit('NewMessage', message)
        }
      })

      sub.subscribe()
      centrifuge.connect()
    },
    Create() {
      this.CurrentTicket.id = 0
      this.$router.push({ name: 'new_ticket' })
    },
    NewTicket(data) {
      this.AllTickets.push(data)
      this.tickets = this.AllTickets
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
    GoTo(t) {
      if (this.CurrentTicket.id == t.id) {
        this.CurrentTicket.id = 0
        this.$router.push({ name: 'tickets' })
      } else {
        this.CurrentTicket = { ...t }
        this.$router.push({ name: 'ticket', params: { id: t.id } })
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
  <!-- Search in navigation -->
  <div class="fixed top-1 right-1 flex flex-row space-x-4">
    <div v-if="AllTickets.length > 0" class="flex flex-wrap space-x-2">
      <Input @keyup.enter="Search()" v-model="search" placeholder="Поиск" label="" class="flex-1">
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
      <Button v-if="search.length > 0" @click="Search()" color="default">Искать</Button>
    </div>

    <Button :disabled="errored" @click="Create()" color="default">
      <span class="items-center font-bold dark:text-gray-900">&#10010;&nbsp;&nbsp;Новое обращение</span>
    </Button>
  </div>

  <div class="grid grid-cols-3 divide-x h-full md:grid-cols-4">
    <p v-if="AllTickets.length == 0" class="text-center text-gray-400 m-10">
      Здесь будет список Ваших активных тикетов
    </p>

    <div v-else
      class="flex flex-col max-h-[calc(100vh-55px)] divide-y overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
      <div v-for="t in tickets" class="p-1"
        :class="t.id == CurrentTicket?.id ? 'bg-blue-200 dark:bg-blue-500' : 'bg-white hover:bg-gray-100 dark:bg-gray-600 dark:hover:bg-gray-800'">
        <div @click.self="GoTo(t)" class="flex flex-row items-center w-full gap-2 cursor-pointer">
          <a :href="VITE_CRM_URL + 'company/personal/user/' + (UserData.crm_id == t.user_id ? t.manager_id : t.user_id) + '/'"
            target="_blank">
            <Avatar rounded size="sm" alt="avatar" :title="UserData.crm_id == t.user_id ? t.manager.name : t.user.name"
              :img="(UserData.crm_id == t.user_id ? t.manager.avatar : t.user.avatar) ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
            <!-- <Avatar rounded size="sm" :title="UserData.crm_id == t.user_id ? t.manager.name : t.user.name" alt="avatar"
              :img="(UserData.crm_id == t.user_id ? t.manager.avatar : t.user.avatar) ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" /> -->
          </a>
          <!-- <img class="h-10 rounded-full cursor-pointer" :src="t.manager.avatar" alt=""> -->
          <div @click="GoTo(t)" class="max-w-[80%] flex flex-col cursor-pointer">
            <p class="truncate">{{ UserData.crm_id == t.user_id ? t.manager.name : t.user.name }}</p>
            <p class="truncate">{{ t.reason }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="flex flex-col justify-center col-span-2 md:col-span-3 h-[calc(100vh-55px)]">
      <div v-if="errored" class="flex flex-col">
        <p class="mx-auto">Произошла непредвиденная ошибка</p>

        <button @click="Get()"
          class="mx-auto text-sm pb-1 no-underline hover:underline border-0 focus:outline-none bg-transparent decoration-dotted underline-offset-4">
          <p>Перезагрузить</p>
        </button>
      </div>

      <TicketNotFound v-else-if="ticket401" />

      <RouterView v-else-if="$route.name != 'tickets'" :key="$route.fullPath" :ticket="CurrentTicket" />

      <div v-else class="flex flex-col gap-3">
        <template v-if="AllTickets.length == 0">
          <p class="mx-auto text-center w-full lg:w-2/3">
            У Вас нет активных обращений. Чтобы создать новый тикет, нажмите на кнопку ниже и следуйте инструкции
          </p>
          <Button @click="Create()" color="default" class="mx-auto"><!-- style="background-color:#b5c3f4"-->
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