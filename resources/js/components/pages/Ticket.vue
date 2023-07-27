<script>
import { inject } from 'vue'
import {
  Table, TableBody,
  TableRow, TableCell,
  Input, Button,
  Select, Avatar,
  Modal
} from 'flowbite-vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import {
  Zoom, Mousewheel,
  Keyboard, Pagination,
  Navigation, Virtual
} from 'swiper/modules'
import { StringVal, FormatLinks } from '@utils/validation.js'
import MessageAttachments from '@temps/MessageAttachments.vue'

import 'swiper/css'
import 'swiper/css/zoom'
import 'swiper/css/pagination'
import 'swiper/css/navigation'
import 'swiper/css/virtual'

export default {
  name: 'Ticket',
  components: {
    Table, TableBody,
    TableRow, TableCell,
    Input, Button,
    Select, Avatar,
    Modal, Swiper,
    SwiperSlide, MessageAttachments
  },
  props: {
    id: Number(),
    ticket: Object(),
  },
  data() {
    return {
      messages: Array(),
      errored: Boolean(),
      files: Array(),
      CreatingMessage: String(),
      CreatingMessageAttachments: Array(),
      PatchingId: Number(),
      PatchingMessage: String(),
      PatchingMessageAttachments: Array(),
      search: String(),
      showModal: Boolean(),
      AllFiles: Array(),
      CurrentAttachmentId: Number(),
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')
    const emitter = inject('emitter')

    return {
      UserData, toast,
      emitter,
      modules: [Zoom, Mousewheel, Keyboard, Pagination, Navigation, Virtual]
    }
  },
  mounted() {
    this.Get()
    this.emitter.on('NewMessage', this.NewMessage)
  },
  methods: {
    Get() {
      this.ax.get(`messages?ticket=${this.$route.params.id}`).then(r => {
        this.messages = r.data.data.data
        this.messages.forEach(m => {
          m.created_at = this.FormatDateTime(m.created_at)
          m.content = FormatLinks(m.content)
        })

        this.AllFiles = this.messages.map(m => m.attachments).flat()
        this.ScrollChat()
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    Create() {
      const message = this.CreatingMessage.trim() ?? ''

      if (this.files.length == 0) {
        const data = StringVal(message, 1, 1000)
        console.log(data)
        if (data.status) {
          this.toast(data.message, 'error')
          return
        }
      }

      let form = new FormData()
      this.files.forEach((f, key) => form.append(key, f))
      if (message.length > 0) {
        form.append('content', message)
      }
      // form.append('content', message.length > 0 ? message : '')
      form.append('ticket_id', this.ticket.id)

      this.ax.post('messages', form).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'error')
          if (r.data.message == 'Тикет уже завершён') {
            this.CreatingMessage = ''
          }
          return
        }

        r.data.data.created_at = this.FormatDateTime(r.data.data.created_at)
        r.data.data.content = FormatLinks(r.data.data.content)
        this.messages.push(r.data.data)
        this.ScrollChat()
        this.files = []
        document.getElementById("attachments_input").value = '';
        this.CreatingMessage = ''
        r.data.data.attachments.forEach(a => this.AllFiles.push(a))
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    NewMessage(data) {
      console.log(data)
      data.created_at = this.FormatDateTime(data.created_at)
      data.content = FormatLinks(data.content)
      this.messages.push(data)
      this.ScrollChat()
      this.AllFiles.concat(data.attachments)
    },
    Patch(message_id) {
      const message = this.PatchingMessage.trim()
      if (StringVal(message, 1, 1000)) return

      this.ax.patch(`messages/${message_id}`, {
        message: message
      }).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (!r.data.status) return

        const index = this.messages.findIndex(({ id }) => id == message_id)
        r.data.data.content = FormatLinks(r.data.data.content)
        this.messages[index] = r.data.data
        this.PatchingId = 0
        this.PatchingMessage = ''
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Delete(message_id) {
      this.ax.delete(`messages/${message_id}`).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'warning')
        if (!r.data.status) return

        const index = this.messages.findIndex(({ id }) => id == message_id)
        this.messages.splice(index, 1)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    AddAttachments(event) {
      Array.from(event.target.files).forEach(f => {
        if (this.files.length == 5) {
          this.toast('За раз Вы можете отправить не более 5 файлов', 'warning')
          return
        }

        this.files.push(f)
      })

      this.ScrollChat()
    },
    RemoveAttachment(key) {
      const index = this.files.findIndex((data, id) => id == key)
      this.files.splice(index, 1)
    },
    ScrollChat() {
      const el = document.getElementById('messages')
      setTimeout(() => {
        el.scrollTop = el.scrollHeight
      }, 1)
    },
    PrepareForPatch(data = null) {
      this.PatchingId = data?.id ?? 0
      this.PatchingMessage = data?.name
    },
    FormatDateTime(date) {
      let tyu = new Date(new Date(date).toDateString()) < new Date(new Date().toDateString())
      const options = tyu ? {
        month: '2-digit',
        year: '2-digit',
        day: '2-digit',
        hour: 'numeric',
        minute: 'numeric',
      } : {
        hour: 'numeric',
        minute: 'numeric',
      }
      return new Date(date).toLocaleTimeString('az-Cyrl-AZ', options)
    },
    slideTo(swiper) {
      const index = this.AllFiles.findIndex(({ id }) => id == this.CurrentAttachmentId)
      swiper.slideTo(index + 1, 0)
    }
  }
}
</script>

<template>
  <div id="messages"
    class="custom-chat-bg flex flex-col gap-1 h-full content-end py-1 px-2 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <template v-for="m in messages">
      <div v-if="m.user_id != UserData.crm_id" class="chat-message">
        <div class="flex items-end">
          <div class="flex flex-col space-y-2 text-sm max-w-md mx-2 order-2 items-start text-left opacity-90">
            <span
              class="flex flex-col px-4 py-2 rounded-lg inline-block rounded-bl-none bg-gray-50 whitespace-pre-wrap dark:text-gray-900 dark:bg-gray-300">
              <MessageAttachments v-if="m.attachments.length > 0" :files="m.attachments" :message_id="m.id" />
              <span v-html="m?.content"></span>
              <span class="text-xs font-light tracking-tighter text-gray-400 dark:text-gray-500">{{ m.created_at }}</span>
            </span>
          </div>
          <div class="order-1">
            <a :href="VITE_CRM_URL + 'company/personal/user/' + (m.user_id == ticket.user_id ? ticket.user.crm_id : ticket.manager.crm_id) + '/'"
              target="_blank">
              <Avatar rounded size="sm" alt="avatar"
                :title="m.user_id == ticket.manager_id ? ticket.manager.name : ticket.user.name"
                :img="(m.user_id == ticket.manager_id ? ticket.manager.avatar : ticket.user.avatar) ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
            </a>
          </div>
        </div>
      </div>
      <div v-else class="chat-message">
        <div class="flex items-end justify-end">
          <div class="flex flex-col space-y-2 text-sm max-w-md mx-2 order-1 items-end text-right opacity-90">
            <span
              class="flex flex-col px-4 py-2 rounded-lg inline-block rounded-br-none bg-indigo-300 whitespace-pre-wrap dark:text-gray-900 dark:bg-indigo-200">
              <MessageAttachments v-if="m.attachments.length > 0" :files="m.attachments" :message_id="m.id" />
              <span v-html="m?.content"></span>
              <span class="text-xs font-light tracking-tighter text-gray-500 dark:text-gray-600">{{ m.created_at }}</span>
            </span>
          </div>
          <div class="order-1">
            <a :href="VITE_CRM_URL + 'company/personal/user/' + ticket.user.crm_id + '/'" target="_blank">
              <Avatar rounded size="sm" :title="m.user_id == ticket.manager_id ? ticket.manager.name : ticket.user.name"
                alt="avatar"
                :img="(m.user_id == ticket.manager_id ? ticket.manager.avatar : ticket.user.avatar) ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
            </a>
          </div>
        </div>
      </div>
    </template>

    <!-- Error handler -->
    <template v-if="errored">
      <div class="chat-message">
        <div class="flex items-end">
          <div class="text-sm mx-2 order-2 items-start text-left">
            <span class="px-4 py-2 rounded-lg inline-block rounded-bl-none bg-red-500">
              Не удалось загрузить сообщения
            </span>
          </div>
        </div>
      </div>
      <div class="chat-message">
        <div class="flex items-end">
          <div class="text-sm mx-2 order-2 items-start text-left">
            <span @click="Get()"
              class="px-4 py-2 rounded-lg inline-block rounded-bl-none bg-red-500 cursor-pointer no-underline hover:underline border-0 focus:outline-none decoration-dotted underline-offset-4">
              Нажмите, чтобы перезагрузить
            </span>
          </div>
        </div>
      </div>
    </template>
  </div>

  <!-- Inputs -->
  <div class="flex flex-col divide-y">
    <!-- Attachments list -->
    <div v-if="files.length > 0" class="flex flex-wrap gap-3 align-bottom p-2 bg-gray-50 dark:bg-gray-700">
      <div v-for="(file, key) in files" :key="key" class="file-listing">
        {{ file.name }}
        <span @click="RemoveAttachment(key)" class="cursor-pointer text-red-500 hover:text-red-700"> ✖</span>
      </div>
    </div>

    <div class="flex flex-row items-center gap-1 px-3 py-2 bg-gray-50 dark:bg-gray-700">
      <!-- Attachments sending input -->
      <div class="border-none bg-transparent cursor-pointer p-2 hover:border-none focus:border-none">
        <input id="attachments_input" @change="AddAttachments($event)" ref="attachments" type="file" multiple
          class="hide-file-input" />
        <label for="attachments_input" class="cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            for="attachments_input" class="text-black-800 w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
          </svg>
        </label>
      </div>

      <!-- Message sending input -->
      <div class="flex-1 relative px-1 rounded-t-lg">
        <textarea v-model="CreatingMessage" @keydown.ctrl.enter.exact="Create()" rows="1"
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

      <div v-if="CreatingMessage.length > 0 || files.length > 0">
        <Button @click="Create()" class="border-none hover:border-none focus:border-none" color="default">
          Отправить
        </Button>
      </div>
    </div>
  </div>

  <!-- Attachments slider modal -->
  <div v-if="showModal" ref="carousel" @click.self="showModal = false" @keyup.esc.exact="showModal = false"
    class="fixed left-0 top-0 flex h-screen w-full items-center justify-center bg-black bg-opacity-50 py-10 px-24">
    <Swiper :slides-per-view="1" :space-between="50" :modules="modules" :loop="AllFiles.length > 0" @afterInit="slideTo"
      :keyboard="{ enabled: true }" :pagination="{ clickable: true, type: 'fraction' }" grabCursor centeredSlides
      mousewheel zoom virtual navigation>
      <SwiperSlide v-for="(file, key) in AllFiles" :key="key" :virtualIndex="key">
        <div class="swiper-zoom-container">
          <img :src="file?.link" :alt="file?.name" class="object-contain">
        </div>
      </SwiperSlide>
    </Swiper>
  </div>
</template>
