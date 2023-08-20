<script>
import { inject } from 'vue'
import { Button as VueButton } from 'flowbite-vue'

import { StringVal, FormatLinks, FormatDateTime } from '@utils/validation.js'

export default {
  name: 'NewTicket',
  components: { VueButton },
  data() {
    return {
      messages: Array(),
      errored: Boolean(),
      WasFirstTouch: Boolean(),
      CreatingMessage: String(),
      waiting: Boolean(),
    }
  },
  setup() {
    const toast = inject('createToast')
    const emitter = inject('emitter')

    return { toast, emitter }
  },
  mounted() {
    this.AddMessage('Это предварительный формат диалога!!!')
    this.AddMessage('Опишите суть проблемы в поле для ввода сообщения')
  },
  methods: {
    Check() {
      if (this.WasFirstTouch) return

      const message = this.CreatingMessage.trim()
      const validate = StringVal(message, 1, 1000)
      if (validate.status) {
        this.toast(validate.message, 'error')
        return
      }

      this.CreatingMessage = message
      this.AddMessage(FormatLinks(message), true)

      const SystemMessages = [
        'Проверьте Ваше сообщение на корректность и информативность',
        'Чтобы завершить создание обращения, нажмите кнопку `Подтвердить`',
        'При необходимости Вы можете отредактировать сообщение в поле ввода',
      ]
      let i = 0

      SystemMessages.forEach(m => setTimeout(() => {
        this.AddMessage(m)
      }, ++i * 250))
      this.WasFirstTouch = true
    },
    AddMessage(message, current = false) {
      this.messages.push({
        content: message,
        current: current,
        created_at: FormatDateTime(),
      })
      this.ScrollChat()
    },
    Create() {
      if (this.waiting) return
      this.waiting = true

      const message = this.CreatingMessage.trim()
      const validate = StringVal(message, 1, 1000)
      if (validate.status) {
        this.toast(validate.message, 'error')
        return
      }

      this.ax.post('tickets', {
        message: FormatLinks(message),
      }).then(r => {
        if (r.data.status == false) {
          this.toast(r.data.message, 'error')
        }

        if (r.data.status) {
          this.emitter.emit('NewTicket', r.data.data)
          this.$router.push({ name: 'tickets' })
        }
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      }).finally(() => this.waiting = false)
    },
    ScrollChat() {
      const el = document.getElementById('messages')
      setTimeout(() => {
        el.scrollTop = el.scrollHeight
      }, 1)
    },
  }
}
</script>

<template>
  <div id="messages"
    class="custom-chat-bg flex flex-col gap-1 h-full w-full content-end py-1 px-2 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <template v-for="m in messages" v-bind:key="m">
      <div v-if="m.current" class="chat-message">
        <div class="flex items-end justify-end">
          <div class="flex flex-col space-y-2 text-sm max-w-sm mx-2 order-1 items-end text-right opacity-90">
            <span
              class="flex flex-col px-4 py-2 rounded-lg inline-block rounded-br-none bg-indigo-300 whitespace-pre-wrap dark:text-gray-900 dark:bg-indigo-400">
              <span v-html="m.content"></span>
              <span class="text-xs font-light tracking-tighter text-gray-500 dark:text-gray-600">
                {{ m.created_at }}
              </span>
            </span>
          </div>
        </div>
      </div>
      <div v-else class="chat-message">
        <div class="flex items-end">
          <div class="flex flex-col space-y-2 text-sm max-w-sm mx-2 order-2 items-start text-left opacity-90">
            <span
              class="flex flex-col px-4 py-2 rounded-lg inline-block rounded-bl-none bg-gray-50 whitespace-pre-wrap dark:text-gray-900 dark:bg-gray-300">
              <span v-html="m.content"></span>
              <span class="text-xs font-light tracking-tighter text-gray-400 dark:text-gray-500">
                {{ m.created_at }}
              </span>
            </span>
          </div>
        </div>
      </div>
    </template>
  </div>

  <!-- Message sending block -->
  <div class="flex flex-row w-full items-center gap-1 px-3 py-2 bg-gray-50 dark:bg-gray-700">
    <div class="flex-1 relative px-1 rounded-t-lg">
      <textarea v-model="CreatingMessage" @keydown.ctrl.enter.exact="Check()" rows="1"
        class="resize-none block overflow-hidden p-2.5 pr-4 w-full text-sm text-gray-900 bg-white rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
        placeholder="Введите сообщение..." />
      <div v-if="CreatingMessage.length > 0" @click="CreatingMessage = ''"
        class="absolute right-3 inset-y-0 flex items-center mr-1 cursor-pointer">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
          class="text-black-800 w-6 h-6">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z" />
        </svg>
      </div>
    </div>

    <div class="flex flex-row gap-1">
      <VueButton v-if="WasFirstTouch" @click="Create()" :disabled="CreatingMessage.length == 0"
        class="border-none hover:border-none focus:border-none" color="green">
        Подтвердить
      </VueButton>
      <VueButton v-else-if="CreatingMessage.length > 0" @click="Check()"
        class="border-none hover:border-none focus:border-none" color="default">
        Проверить
      </VueButton>
    </div>
  </div>
</template>
