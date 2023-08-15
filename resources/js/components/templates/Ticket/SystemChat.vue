<script>
import { inject } from 'vue'
import {
  Input, Button,
  Avatar
} from 'flowbite-vue'

import { StringVal, FormatLinks, FormatDateTime } from '@utils/validation.js'

export default {
  name: 'SystemChat',
  components: {
    Input, Button,
    Avatar
  },
  props: {
    ticket: Object(),
  },
  data() {
    return {
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
      Messages: Array(),
      CreatingMessage: String(),
      IsResolved: Boolean(),
      waiting: Boolean(),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')

    return {
      UserData,
      toast
    }
  },
  mounted() {
    this.IsResolved = this.ticket?.old_ticket_id > 0
    this.GetHiddenChatMessages()
  },
  methods: {
    GetHiddenChatMessages() {
      this.ax.get(`hidden_chat_messages?ticket=${this.ticket.id}`).then(r => {
        this.Messages = r.data.data.data
        this.ScrollChat()
        this.Messages.forEach(m => {
          m.created_at = FormatDateTime(m.created_at)
          m.content = FormatLinks(m.content)
        })
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    CreateMessage() {
      if (this.IsResolved || this.waiting) return
      this.waiting = true

      const message = this.CreatingMessage.trim()

      const data = StringVal(message, 1, 1000)
      if (data.status) {
        this.toast(data.message, 'error')
        this.waiting = false
        return
      }

      this.ax.post('hidden_chat_messages', {
        content: message,
        ticket_id: this.ticket.id,
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'error')
          if (r.data.message == 'Тикет уже завершён') {
            this.CreatingMessage = ''
          }
          return
        }

        const new_message = r.data.data
        new_message.created_at = FormatDateTime(new_message.created_at)
        new_message.content = FormatLinks(new_message.content)
        this.Messages.push(new_message)
        this.ScrollChat()
        this.CreatingMessage = ''
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      }).finally(() => this.waiting = false)
    },
    ScrollChat() {
      const el = document.getElementById('system_chat_messages')
      setTimeout(() => {
        el.scrollTop = el.scrollHeight
      }, 10)
    },
  }
}
</script>

<template>
  <!-- <div :class="ticket.active == 0 || IsResolved ? 'h-[calc(100%-55px)]' : 'h-[calc(100%-55px-43px)]'"> -->
  <!-- <div :class="ticket.active == 0 || IsResolved ? 'h-full' : 'h-[calc(100%-43px)]'"> -->
  <div id="system_chat_messages"
    class="flex flex-col gap-1 h-[calc(100%-15px)] content-end py-1 px-2 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <template v-for="m in Messages">
      <div class="chat-message">
        <div class="flex items-end mt-1">
          <div
            class="flex flex-col space-y-2 text-sm max-w-sm xl:max-w-md 2xl:max-w-lg mx-2 order-2 items-start text-left opacity-90">
            <span
              class="flex flex-col w-full px-4 py-2 rounded-lg inline-block rounded-bl-none bg-gray-50 whitespace-pre-wrap dark:text-gray-900 dark:bg-gray-300">
              <span v-html="m?.content" class="break-words" />
              <span class="text-xs font-light tracking-tighter text-gray-400 dark:text-gray-500">
                {{ m.created_at }}
              </span>
            </span>
          </div>
          <div class="order-1">
            <Avatar v-if="m.user_id == 0" rounded size="sm" title="Система" />
            <a v-else :href="`${VITE_CRM_URL}company/personal/user/${m.user_id}/`" target="_blank">
              <Avatar rounded size="sm" alt="avatar" :title="m.user?.name"
                :img="m.user?.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
            </a>
          </div>
        </div>
      </div>
    </template>
  </div>

  <div v-if="!IsResolved && ticket.active != 0" class="relative flex flex-row h-[60px] items-center gap-1 px-3 py-2">
    <textarea v-model="CreatingMessage" @keydown.ctrl.enter.exact="CreateMessage()" rows="1"
      class="resize-none block overflow-hidden p-2.5 pr-4 w-full text-sm text-gray-900 bg-white rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
      placeholder="Отправить: Ctrl+Enter" />
    <div v-if="CreatingMessage.length > 0" @click="CreatingMessage = ''"
      class="absolute right-3 inset-y-0 flex items-center mr-1 cursor-pointer">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
        class="text-black-800 w-6 h-6">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z" />
      </svg>
    </div>
  </div>
</template>