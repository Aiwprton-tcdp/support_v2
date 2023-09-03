<script>
import { inject } from 'vue'
import { Button as VueButton, Input as VueInput } from 'flowbite-vue'

import { StringVal } from '@utils/validation.js'

export default {
  name: 'TemplateMessagesComponent',
  components: {
    VueButton, VueInput
  },
  data() {
    return {
      AllMessages: Array(),
      Messages: Array(),
      PatchingId: Number(),
      PatchingName: String(),
      CreatingName: String(),
      creating: Boolean(),
      editing: Boolean(),
    }
  },
  setup() {
    const toast = inject('createToast')
    const emitter = inject('emitter')

    return { toast, emitter }
  },
  mounted() {
    this.GetMessages()
  },
  methods: {
    GetMessages() {
      this.ax.get('template_messages').then(r => {
        this.AllMessages = r.data.data.data
        this.Messages = this.AllMessages
      })
    },
    Create() {
      const name = this.CreatingName.trim()
      const validate = StringVal(name, 2)
      if (validate.status) {
        this.toast(validate.message, 'error')
        return
      }

      // console.log(name)
      // this.AllMessages.push({
      //   id: this.AllMessages.length,
      //   content: name
      // })
      // this.creating = false
      // this.CreatingName = ''
      // return

      this.ax.post('template_messages', {
        content: name
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning')
          return
        }

        this.AllMessages.push(r.data.data)
        // this.Messages = this.AllMessages
        this.creating = false
        this.CreatingName = ''
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Patch() {
      if (this.PatchingId < 1) return
      const name = this.PatchingName.trim()
      const validate = StringVal(name, 2)
      if (validate.status) {
        this.toast(validate.message, 'error')
        return
      }

      // console.log(name)
      // const index = this.AllMessages.findIndex(({ id }) => id == this.PatchingId)
      // this.AllMessages[index].content = name
      // this.editing = false
      // this.PatchingId = 0
      // this.PatchingName = ''
      // return

      this.ax.patch(`template_messages/${this.PatchingId}`, {
        content: name
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning')
          return
        }

        const index = this.AllMessages.findIndex(({ id }) => id == this.PatchingId)
        this.AllMessages[index] = r.data.data
        this.editing = false
        this.PatchingId = 0
        this.PatchingName = ''
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Delete(message_id) {
      if (!window.confirm("Вы уверены, что хотите удалить шаблон?")) {
        return
      }

      this.ax.delete(`template_messages/${message_id}`).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'warning')
        if (!r.data.status) return

        const index = this.AllMessages.findIndex(({ id }) => id == message_id)
        this.AllMessages.splice(index, 1)
        this.Messages = this.AllMessages
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Use(message) {
      this.emitter.emit('UseTemplateMessage', message)
    },
  }
}
</script>

<template>
  <div v-if="Messages.length > 0"
    class="max-h-[30vh] max-w-full gap-1 z-1 content-end py-1 px-2 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <template v-for="(m, key) in Messages" v-bind:key="key">
      <div class="border-lg shadow-md p-1 max-w-full">
        <div v-if="editing && PatchingId == m.id" class="flex gap-1">
          <VueInput @keydown.ctrl.enter.exact="Patch()" v-model="PatchingName" v-focus placeholder="Введите сообщение"
            label="" class="flex-1" />

          <div @click="Patch()" class="text-green-500 cursor-pointer" title="Сохранить">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
          </div>
          <div @click="editing = false" class="text-red-500 cursor-pointer" title="Отменить">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
        </div>
        <div v-else class="flex gap-1">
          <p @click="Use(m.content)" class="cursor-pointer hover:text-sky-500 truncate">
            {{ key + 1 }}. {{ m.content }}
          </p>
          <div @click="editing = true; PatchingId = m.id; PatchingName = m.content" class="cursor-pointer"
            title="Редактировать шаблон">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
            </svg>
          </div>
          <div @click="editing = true; PatchingId = m.id; PatchingName = m.content" class="cursor-pointer"
            title="Удалить шаблон">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
        </div>
      </div>
    </template>
  </div>

  <div class="flex flex-row items-center gap-1">
    <div v-if="!creating" @click="creating = true" class="cursor-pointer" title="Добавить шаблон">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
        class="w-6 h-6">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </div>
    <template v-else>
      <VueInput @keydown.ctrl.enter.exact="Create()" v-model="CreatingName" v-focus placeholder="Введите сообщение"
        label="" class="flex-1" />

      <div @click="Create()" class="text-green-500 cursor-pointer">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
          class="w-6 h-6">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
      </div>
      <div @click="creating = false" class="text-red-500 cursor-pointer">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
          class="w-6 h-6">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </div>
    </template>
  </div>
</template>