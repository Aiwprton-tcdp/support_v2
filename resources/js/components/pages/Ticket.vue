<script>
import { inject } from 'vue'
import {
  Table, TableBody,
  TableRow, TableCell,
  Input, Button,
  Select
} from 'flowbite-vue'
import { StringVal } from '@utils/validation.js'

export default {
  name: 'Ticket',
  components: {
    Table, TableBody,
    TableRow, TableCell,
    Input, Button,
    Select
  },
  props: {
    id: Number(),
    data: Object(),
  },
  data() {
    return {
      messages: Array(),
      errored: Boolean(),
      CreatingMessage: String(),
      PatchingId: Number(),
      PatchingMessage: String(),
      search: String(),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')
    return { UserData, toast }
  },
  mounted() {
    this.Get()
  },
  methods: {
    Get() {
      this.ax.get(`messages?ticket=${this.$route.params.id}`).then(r => {
        this.messages = r.data.data.data
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    Create() {
      const message = this.CreatingMessage.trim()
      console.log(message)
      if (message.trim().length == 0) return
      if (StringVal(message, 1, 1000)) return

      this.ax.post('messages', {
        content: message,
        ticket_id: this.data.id,
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'error')
          if (r.data.message == 'Тикет уже завершён') {
            this.CreatingMessage = ''
          }
          return
        }

        this.messages.push(r.data.data)
        this.CreatingMessage = ''
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
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
    PrepareForPatch(data = null) {
      this.PatchingId = data?.id ?? 0
      this.PatchingMessage = data?.name
    },
  },
}
</script>

<template>
  <div id="messages"
    class="custom-chat-bg flex flex-col gap-1 h-full py-1 px-2 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <template v-for="m in messages">
      <div v-if="m.user_id != UserData.crm_id" class="chat-message">
        <div class="flex items-end">
          <div class="flex flex-col space-y-2 text-sm max-w-sm mx-2 order-2 items-start text-left">
            <span class="px-4 py-2 rounded-lg inline-block rounded-bl-none bg-gray-400 text-gray-900">
              {{ m.content }}
            </span>
          </div>
          <div class="shadow-inner order-1 border-2 rounded-full">
            <img :src="(m.user_id == data.user_id ? data.user.avatar : data.manager.avatar) ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" alt="avatar" class="w-6 h-6 rounded-full">
          </div>
        </div>
      </div>
      <div v-else class="chat-message">
        <div class="flex items-end justify-end">
          <div class="flex flex-col space-y-2 text-sm max-w-sm mx-2 order-1 items-end text-right">
            <span class="px-4 py-2 rounded-lg inline-block rounded-br-none bg-blue-600 text-white">
              {{ m.content }}
            </span>
          </div>
          <div class="shadow-inner order-1 border-2 rounded-full">
            <img :src="data.user.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" alt="avatar" class="w-6 h-6 rounded-full">
          </div>
        </div>
      </div>
    </template>

    <div>
      <div v-if="errored">
        <p>Ошибка</p>
      </div>
      <div v-else-if="messages.length == 0">
        <p>Нет данных</p>
      </div>
    </div>
  </div>

  <div class="relative flex flex-row items-center gap-1 px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-700">
    <div @click="" class="px-3 py-2 border-none hover:border-none focus:border-none bg-transparent cursor-pointer">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
        class="text-black-800 w-6 h-6">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
      </svg>
    </div>

    <div class="flex-1">
      <div class="relative px-1 py-2 rounded-t-lg">
        <textarea v-model="CreatingMessage" @keyup.ctrl.enter.exact="Create()" rows="1"
          class="resize-none block overflow-hidden p-2.5 pr-4 w-full text-sm text-gray-900 bg-white rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
          placeholder="Введите сообщение...">
        </textarea>
        <div v-if="CreatingMessage.length > 0" @click="CreatingMessage = ''"
          class="absolute right-2.5 bottom-2 py-2 cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="text-black-800 w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z" />
          </svg>
        </div>
      </div>
    </div>

    <div v-if="CreatingMessage.length > 0" class="flex items-center justify-between px-3 py-2">
      <Button @click="Create()" class="border-none hover:border-none focus:border-none"
        color="default">
        Отправить
      </Button>
    </div>
  </div>

  <!-- Отправка файлов -->

  <!-- <label for="files_input" class="cursor-pointer h-6 w-6 text-gray-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
            </svg>
        </label>
        <input id="files_input" type="file" ref="files" multiple v-on:change="HandleFileUpload()" class="hidden"/> -->

  <!-- <div class="relative flex mt-1 items-center">
    <input type="text" v-model="CreatingMessage" v-on:keyup.enter="Create()" placeholder="Написать сообщение..."
      class="w-full focus:outline-none focus:placeholder-gray-400 text-gray-600 placeholder-gray-600 bg-gray-200 rounded-md py-2" />

    <div class="absolute right-0 inset-y-0 hidden sm:flex">
      <Button type="Button" v-on:click="Create()"
        class="inline-flex justify-center rounded-lg p-2 transition duration-500 ease-in-out text-white bg-blue-500 hover:bg-blue-400 focus:outline-none">
        <span class="font-bold">Отправить</span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
          class="h-6 w-6 ml-2 transform rotate-90">
          <path
            d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z">
          </path>
        </svg>
      </Button>
    </div> -->
  <!-- </div> -->
</template>

<style>
.custom-chat-bg {
  background-image: linear-gradient(rgb(141 174 241 / 20%), rgb(135 80 156 / 20%)), url(https://t3.ftcdn.net/jpg/03/27/51/56/360_F_327515607_Hcps04aaEc7Ki43d1XZPxwcv0ZaIaorh.jpg)
}
</style>
