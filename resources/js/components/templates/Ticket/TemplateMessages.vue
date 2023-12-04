<script>
import { inject } from 'vue'
import { Input as VueInput } from 'flowbite-vue'

import { StringVal } from '@utils/validation.js'

export default {
  name: 'TemplateMessagesComponent',
  components: { VueInput },
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

        const ids = JSON.parse(localStorage.getItem('support_pinned_template_messages'));
        new Set(Array.from(ids ?? [])).forEach(this.PreparePinned);
        this.$parent.templateMessages = this.Messages;
        this.$parent.pinnedTemplateMessages = this.Messages.filter(m => m.pinned);
        this.$parent.filteredTemplateMessages = this.Messages.filter(m => m.pinned);
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
    Pin(id) {
      const ids = JSON.parse(localStorage.getItem('support_pinned_template_messages'));
      const data = new Set(Array.from(ids ?? []));

      const map = this.Messages.map(t => t.id);
      Array.from(ids ?? []).filter(i => !map.includes(i)).forEach(i => data.delete(i));

      const index = this.Messages.findIndex(t => t.id == id);
      const need_to_pin = data.has(id) && this.Messages[index].pinned;

      if (need_to_pin) {
        this.PreparePinned(id, !need_to_pin);
        data.delete(id);
      } else if (!data.has(id) || !this.Messages[index].pinned) {
        data.add(id);
      }

      this.PreparePinned(id, !need_to_pin);
      localStorage.setItem('support_pinned_template_messages', JSON.stringify(Array.from(data)));
    },
    PreparePinned(id, need_to_pin = true) {
      const index = this.Messages.findIndex(t => t.id == id);
      if (index < 0) return;
      this.Messages[index].pinned = need_to_pin;
      this.Messages = this.Messages.sort((t1, t2) => t2?.pinned ?? false - t1?.pinned ?? false)
    },
  }
}
</script>

<template>
  <div v-if="Messages.length > 0"
    class="max-h-[30vh] max-w-full gap-1 z-1 content-end py-1 px-2 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <template v-for="m in Messages" :key="m.id">
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
          <div @click="Pin(m.id)" :class="m.pinned ? '' : 'opacity-30'"
            class="inline-flex items-center justify-center cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"
              class="w-4 h-4"
              :class="m.pinned ? 'text-blue-500 hover:text-blue-400' : 'text-gray-400 hover:text-gray-600 dark:text-gray-100 dark:hover:text-gray-300'">
              <title>{{ m.pinned ? 'Открепить' : 'Закрепить' }}</title>
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
          </div>
          <p @click="Use(m.content)" class="cursor-pointer hover:text-sky-500 break-all">
            {{ m.id + 1 }}. {{ m.content }}
          </p>
          <div @click="editing = true; PatchingId = m.id; PatchingName = m.content" class="cursor-pointer"
            title="Редактировать шаблон">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
            </svg>
          </div>
          <div @click="Delete(m.id)" class="text-red-500 cursor-pointer" title="Удалить шаблон">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
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