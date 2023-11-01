<script>
import { inject, defineAsyncComponent } from 'vue';
import {
  Button as VueButton,
  Avatar, Tabs, Tab
} from 'flowbite-vue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import {
  Zoom, Mousewheel,
  Keyboard, Pagination,
  Navigation, Virtual
} from 'swiper/modules';
import useClipboard from 'vue-clipboard3';

import { StringVal, FormatLinks, FormatDateTime } from '@utils/validation.js';

import MessageAttachments from '@temps/MessageAttachments.vue';
// const MessageAttachments = defineAsyncComponent(() => import('@temps/MessageAttachments.vue'));
import TemplateMessages from '@temps/Ticket/TemplateMessages.vue';
// const TemplateMessages = defineAsyncComponent(() => import('@temps/Ticket/TemplateMessages.vue'));
const Detalization = defineAsyncComponent(() => import('@temps/Ticket/Detalization.vue'));
const SystemChat = defineAsyncComponent(() => import('@temps/Ticket/SystemChat.vue'));

import 'swiper/css';
import 'swiper/css/zoom';
import 'swiper/css/pagination';
import 'swiper/css/navigation';
import 'swiper/css/virtual';

export default {
  name: 'TicketPage',
  components: {
    VueButton, Avatar, Tabs,
    Tab, Swiper, SwiperSlide,
    MessageAttachments, TemplateMessages,
    Detalization, SystemChat
  },
  props: {
    id: Number(),
    ticket: Object(),
  },
  data() {
    return {
      messages: Array(),
      participants_data: Array(),
      participants: Array(),
      errored: Boolean(),
      files: Array(),
      CreatingMessage: String(),
      search: String(),
      ActiveTab: String('details'),
      IsResolved: Boolean(),
      showModal: Boolean(),
      waiting: Boolean(),
      AllFiles: Array(),
      CurrentAttachmentId: Number(),
      VITE_APP_URL: String(import.meta.env.VITE_APP_URL),
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
      VITE_CRM_MARKETPLACE_ID: String(import.meta.env.VITE_CRM_MARKETPLACE_ID),
      marking: Boolean(),
      MarkIcons: Array({
        value: 1,
        name: 'bad',
        img: new URL('@assets/reactions/bad.png', import.meta.url),
        gif: new URL('@assets/reactions/bad.gif', import.meta.url),
      }, {
        value: 2,
        name: 'neutral',
        img: new URL('@assets/reactions/neutral.png', import.meta.url),
        gif: new URL('@assets/reactions/neutral.gif', import.meta.url),
      }, {
        value: 3,
        name: 'good',
        img: new URL('@assets/reactions/good.png', import.meta.url),
        gif: new URL('@assets/reactions/good.gif', import.meta.url),
      }),
      CurrentMark: Number(),
      IsParticipant: Boolean(),
      file: String(),
      dragging: Boolean(),
      showTempMessages: Boolean(),
    };
  },
  setup() {
    const UserData = inject('UserData');
    const toast = inject('createToast');
    const emitter = inject('emitter');
    const { toClipboard } = useClipboard();

    const copy = async data => {
      try {
        await toClipboard(data)
        console.log('Copied to clipboard')
      } catch (e) {
        console.error(e)
      }
    }

    return {
      UserData, toast,
      emitter,
      copy,
      modules: [Zoom, Mousewheel, Keyboard, Pagination, Navigation, Virtual]
    };
  },
  mounted() {
    this.IsResolved = this.ticket?.old_ticket_id > 0;
    this.IsParticipant = this.ticket.new_user_id == this.UserData.user_id || this.ticket.new_manager_id == this.UserData.user_id;
    this.GetParticipants();
    this.emitter.on('NewMessage', this.NewMessage);
    this.emitter.on('NewParticipant', this.GetParticipants);
    this.emitter.on('UseTemplateMessage', m => {
      this.CreatingMessage = `${this.CreatingMessage.trim()} ${m}`.trim();
      this.showTempMessages = false;
    });

    document.addEventListener('keydown', e => {
      if (e.key !== 'Escape') return;

      if (this.showModal) {
        this.showModal = false;
        // } else if (this.ticket?.active == 1) {
        //   this.ticket.active = 2
      }
    });
    // document.addEventListener('paste', this.AddAttachments)
  },
  methods: {
    Get() {
      this.ax.get(`messages?ticket=${this.$route.params.id}`).then(r => {
        this.messages = r.data.data.data;
        this.messages.forEach(m => {
          m.created_at = FormatDateTime(m.created_at);
          m.content = FormatLinks(m.content);
        });

        this.AllFiles = this.messages.map(m => m.attachments).flat();
        // this.AllFiles.forEach(f => f.link = this.messages[0].attachments_domain + f.link);
        this.errored = false;
        // this.ScrollChat();
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
        this.errored = true;
      }).finally(this.ScrollChat);
    },
    GetParticipants() {
      this.ax.get(`participants?ticket_id=${this.ticket.id}`).then(r => {
        this.participants_data = r.data.data;
        this.participants = this.PrepareParticipants([...this.participants_data]);
        this.IsParticipant = this.ticket.new_user_id == this.UserData.user_id || this.ticket.new_manager_id == this.UserData.user_id;

        if (!this.IsParticipant) {
          this.IsParticipant = this.participants_data.some(p => p.user_id == this.UserData.user_id);
        }
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      }).finally(this.Get);
    },
    PrepareParticipants(BusyManagers) {
      let y = {};

      BusyManagers.forEach(e => y[e.user_id] = e);
      y[this.ticket.new_user_id] = this.ticket.user;
      y[this.ticket.new_manager_id] = this.ticket.manager;

      return y;
    },
    Create() {
      if (this.waiting) return;
      this.waiting = true;

      const message = this.CreatingMessage.trim();

      if (this.files.length == 0) {
        const data = StringVal(message, 1, 1000);
        if (data.status) {
          this.toast(data.message, 'error');
          this.waiting = false;
          return;
        }
      }

      let form = new FormData();
      this.files.forEach((f, key) => form.append(key, f));
      if (message.length > 0) {
        form.append('content', message);
      }
      form.append('ticket_id', this.ticket.id);

      this.ax.post('messages', form).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'error');
          if (r.data.message == 'Тикет уже завершён') {
            this.CreatingMessage = '';
          }
          return;
        }

        r.data.data.created_at = FormatDateTime(r.data.data.created_at);
        r.data.data.content = FormatLinks(r.data.data.content);
        this.messages.push(r.data.data);
        this.ScrollChat();
        this.files = [];
        document.getElementById("attachments_input").value = '';
        this.CreatingMessage = '';
        r.data.data.attachments.forEach(a => this.AllFiles.push(a));

        const index = this.$parent.$parent.$data.AllTickets.findIndex(({ id }) => id == Number(r.data.data.ticket_id));
        this.$parent.$parent.$data.AllTickets[index].unread_messages = false;
        this.$parent.$parent.TicketsSorting();
        // const index = this.$parent.$parent.$parent.$data.AllTickets.findIndex(({ id }) => id == Number(r.data.data.ticket_id));
        // this.$parent.$parent.$parent.$data.AllTickets[index].unread_messages = false;
        // this.$parent.$parent.$parent.TicketsSorting();
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
        // this.messages.push({
        //   content: message,
        //   user_id: this.UserData.crm_id,
        //   created_at: FormatDateTime(),
        //   attachments: [],
        // });
      }).finally(() => this.waiting = false);
    },
    NewMessage(data) {
      const index = this.messages.findIndex(({ id }) => id == data.id);
      if (index > -1) return;

      data.created_at = FormatDateTime(data.created_at);
      if (data.created_at == "Invalid Date") {
        data.created_at = FormatDateTime(new Date());
      }

      data.content = FormatLinks(data.content);
      this.messages.push(data);
      this.ScrollChat();
      data.attachments.forEach(a => this.AllFiles.push(a));
    },
    MarkShowing() {
      this.marking = true;
      this.ticket.user_self_resolve_trying = true;
      setTimeout(() => this.ScrollChat(), 150);
    },
    CloseTicket(value) {
      if (this.waiting) return;
      this.waiting = true;

      this.CurrentMark = value;
      this.ax.post('resolved_tickets', {
        old_ticket_id: this.ticket.id,
        mark: this.CurrentMark,
      }).then(r => {
        // this.toast(r.data.message, r.data.status ? 'success' : 'error');
        // const index = this.$parent.$parent.$parent.$data.AllTickets.findIndex(({ id }) => id == this.ticket.id);
        // if (index > -1) {
        //   this.$parent.$parent.$parent.$data.AllTickets[index].splice(index, 1);
        // }
        this.emitter.emit('DeleteTicket', this.ticket.id, r.data.message);
        // this.$router.push('tickets');
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      })//.finally(() => this.waiting = false);
    },
    ContinueTicket() {
      if (this.ticket.user_self_resolve_trying) {
        delete this.ticket.user_self_resolve_trying;
        this.marking = false;
        this.messages.splice();
        return;
      }
      if (this.waiting) return;
      this.waiting = true;

      this.ax.patch(`tickets/${this.ticket.id}`, {
        active: true
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning');
          return;
        }
        this.ticket.active = 1;
        const index = this.$parent.$parent.$data.AllTickets.findIndex(({ id }) => id == this.ticket.id);
        if (index == -1) return;
        this.$parent.$parent.$data.AllTickets[index].marked_as_deleted = false;
        this.$parent.$parent.$data.AllTickets[index].unread_messages = false;
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      }).finally(() => this.waiting = false);
    },
    AddAttachments(event) {
      if (event.target.files == null) {
        return;
      } else if (event.target.files.length == 0) {
        console.log('There are no files for uploading');
        return;
      }
      const types = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/bmp',
        'image/webp',
        'image/heic',
      ];
      Array.from(event.target.files).forEach(f => {
        if (!types.includes(f['type'])) {
          this.toast('Вы можете отправить только изображения', 'warning');
          return;
        } else if (this.files.length == 5) {
          this.toast('За раз Вы можете отправить не более 5 файлов', 'warning');
          return;
        }

        this.files.push(f);
      });

      this.ScrollChat();
    },
    RemoveAttachment(key) {
      const index = this.files.findIndex((data, id) => id == key);
      this.files.splice(index, 1);
    },
    ScrollChat() {
      const el = document.getElementById('messages');
      setTimeout(() => {
        el.scrollTop = el.scrollHeight;
      }, 50);
    },
    SlideTo(swiper) {
      const index = this.AllFiles.findIndex(({ id }) => id == this.CurrentAttachmentId);
      swiper.slideTo(index + 1, 0);
    },
    DragFiles(e) {
      var files = e.target.files || e.dataTransfer.files;

      if (!files.length) {
        this.dragging = false;
        return;
      }

      this.AddAttachments(e);
      this.dragging = false;
      this.ScrollChat();
    },
    CopyTicketId() {
      this.copy(`${this.VITE_CRM_URL}marketplace/app/${this.VITE_CRM_MARKETPLACE_ID}/?id=${this.ticket.old_ticket_id ?? this.ticket.id}`)
    },
  }
}
</script>

<template>
  <div class="h-[calc(100vh-55px)] w-full grid grid-cols-4">
    <!-- <div class="h-full" :class="[UserData.is_admin || UserData.role_id == 2 ? 'col-span-3' : 'col-span-4']"> -->
    <div class="h-[calc(100vh-55px)] flex flex-col"
      :class="[UserData.is_admin || UserData.role_id == 2 ? 'col-span-3' : 'col-span-4']">
      <!-- Messaging block -->
      <div v-if="!dragging" @dragenter="dragging = true" id="messages"
        class="flex flex-col h-full gap-1 z-1 content-end py-1 px-2 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch"
        :class="UserData.email == 'kromer_stepan_406@udl.ru' ? 'custom-chat-bg-stepan bg-cover' : 'custom-chat-bg'">
        <template v-for="m in messages" v-bind:key="m">
          <div v-if="m.user_id != UserData.user_id" class="chat-message">
            <div class="flex items-end">
              <div
                class="flex flex-col space-y-2 text-sm max-w-sm xl:max-w-md 2xl:max-w-lg mx-2 order-2 items-start text-left opacity-90">
                <span
                  class="flex flex-col w-full px-4 py-2 rounded-lg inline-block rounded-bl-none bg-gray-50 whitespace-pre-wrap dark:text-gray-900 dark:bg-gray-300">
                  <MessageAttachments v-if="m.attachments.length > 0" :files="m.attachments"
                    :domain="m.attachments_domain" :message_id="m.id" />
                  <span v-html="m?.content" class="break-words"></span>
                  <span class="text-xs font-light tracking-tighter text-gray-400 dark:text-gray-500">
                    {{ m.created_at }}
                  </span>
                </span>
              </div>
              <div class="order-1">
                <a :href="`https://${ticket.bx_domain}/company/personal/user/${participants[m.user_id]?.crm_id}/`"
                  target="_blank">
                  <Avatar rounded size="sm" alt="avatar" :title="participants[m.user_id]?.name"
                    :img="participants[m.user_id]?.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
                </a>
              </div>
            </div>
          </div>
          <div v-else class="chat-message">
            <div class="flex items-end justify-end">
              <div
                class="flex flex-col space-y-2 text-sm max-w-sm xl:max-w-md 2xl:max-w-lg mx-2 order-1 items-end text-right opacity-90">
                <span
                  class="flex flex-col w-full px-4 py-2 rounded-lg inline-block rounded-br-none bg-indigo-300 whitespace-pre-wrap dark:text-gray-900 dark:bg-indigo-200">
                  <MessageAttachments v-if="m.attachments.length > 0" :files="m.attachments"
                    :domain="m.attachments_domain" :message_id="m.id" />
                  <span v-html="m?.content" class="break-words"></span>
                  <span class="text-xs font-light tracking-tighter text-gray-500 dark:text-gray-600">
                    {{ m.created_at }}
                  </span>
                </span>
              </div>
              <div class="order-1">
                <a :href="`https://${ticket.bx_domain}/company/personal/user/${UserData.crm_id}/`" target="_blank">
                  <Avatar rounded size="sm" :title="UserData.name" alt="avatar"
                    :img="UserData?.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
                </a>
              </div>
            </div>
          </div>
        </template>

        <!-- Mark Selecting -->
        <template v-if="UserData.user_id == ticket.new_user_id && (ticket?.active == 0 || marking)">
          <div class="chat-message">
            <div class="flex items-end">
              <div class="flex text-sm max-w-sm xl:max-w-md 2xl:max-w-lg mx-2 items-start text-left opacity-90">
                <span
                  class="flex flex-col w-full px-4 py-2 rounded-lg inline-block rounded-bl-none bg-gray-50 whitespace-pre-wrap dark:text-gray-900 dark:bg-gray-300">
                  <p>Для подтверждения завершения укажите оценку работы менеджер(а/ов) в рамках данного тикета</p>

                  <div class="flex flex-row">
                    <div v-for="icon in MarkIcons" v-bind:key="icon">
                      <img :src="icon.hover ? icon.gif : icon.img" :alt="icon.name" @click="CloseTicket(icon.value)"
                        @mouseover="icon.hover = true" @mouseleave="icon.hover = false" class="cursor-pointer opacity-100"
                        :class="{ 'grayscale': !icon.hover && CurrentMark != icon.value }" />
                    </div>
                  </div>
                </span>
              </div>
            </div>
          </div>
        </template>

        <!-- Error handling -->
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
              <div @click="Get()" class="text-sm mx-2 order-2 items-start text-left cursor-pointer">
                <span
                  class="px-4 py-2 rounded-lg inline-block rounded-bl-none bg-red-500 no-underline hover:underline border-0 focus:outline-none decoration-dotted underline-offset-4">
                  Нажмите, чтобы перезагрузить
                </span>
              </div>
            </div>
          </div>
        </template>

        <!-- Настройка стационарного телефона -->
        <template
          v-else-if="UserData.user_id == ticket.new_user_id && !IsResolved && ticket.reason == 'Настройка стационарного телефона'">
          <div class="chat-message">
            <div class="flex items-end justify-center">
              <div class="flex flex-col space-y-2 text-sm w-[80%] h-24 mx-2 order-2 items-start text-left opacity-90">
                <span
                  class="flex flex-col w-full px-4 py-2 text-center cursor-pointer rounded-lg break-words rounded-bl-none bg-gray-50 whitespace-pre-wrap dark:text-gray-900 dark:bg-gray-300">
                  <a :href="VITE_APP_URL + '/storage/Настройка стационарного телефона.pdf'" target="_blank">
                    Ознакомьтесь с инструкцией
                  </a>
                </span>
              </div>
            </div>
          </div>
        </template>
      </div>
      <!-- Drad and drop -->
      <div v-else @dragleave="dragging = false" :class="['dropZone', dragging ? 'dropZone-over' : '']">
        <div @drag="DragFiles" class="flex flex-col h-full items-center justify-center">
          <div>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
            </svg>
          </div>
          <span>Drop file or click to upload</span>
        </div>
        <input type="file" @change="DragFiles" multiple>
      </div>

      <!-- Inputs -->
      <div v-if="!UserData.is_admin && IsResolved" class="flex items-center justify-evenly w-full h-[60px]">
        <div class="flex flex-row gap-4">
          <p>ID: {{ ticket.old_ticket_id }}</p>
          <p>Тикет был завершён {{ ticket?.mark > 0 ? 'с оценкой ' + ticket?.mark + '/3' : 'без оценки' }}</p>
        </div>
      </div>
      <div v-else-if="!IsResolved && IsParticipant" class="h-[60px]">
        <!-- </div> :class="UserData.is_admin || UserData.role_id == 2 ? 'col-span-3' : 'col-span-4'"> -->
        <div v-if="ticket.new_user_id == UserData.user_id && (ticket.active == 0 || ticket.user_self_resolve_trying)">
          <button @click="ContinueTicket()" color="alternative" class="h-full w-full">
            Продолжить обсуждение
          </button>
        </div>
        <div v-else class="relative flex flex-col divide-y">
          <div v-if="UserData.role_id == 2 && showTempMessages || files.length > 0"
            class="absolute bottom-[59px] w-full flex flex-col opacity-90 flex-wrap space-y-3 divide-y align-bottom p-2 bg-gray-50 dark:bg-gray-700">
            <!-- Template Messages list -->
            <template v-if="UserData.role_id == 2 && showTempMessages">
              <TemplateMessages />
            </template>

            <!-- Attachments list -->
            <template v-if="files.length > 0">
              <div v-for="(file, key) in files" :key="key" class="file-listing">
                {{ file.name }}
                <span @click="RemoveAttachment(key)" class="cursor-pointer text-red-500 hover:text-red-700"> ✖</span>
              </div>
            </template>
          </div>

          <div class="flex flex-row items-center gap-1 px-3 py-2 bg-gray-50 dark:bg-gray-700">
            <!-- Template messages button -->
            <div v-if="UserData.role_id == 2" class="border-none bg-transparent p-2 hover:border-none focus:border-none">
              <div @click="showTempMessages = !showTempMessages" class="cursor-pointer" title="Шаблоны сообщений">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                  stroke="currentColor" class="w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                </svg>
              </div>
            </div>
            <!-- Copy ticket link button -->
            <div v-else-if="!UserData.is_admin && UserData.role_id != 2"
              class="border-none bg-transparent p-2 hover:border-none focus:border-none">
              <div @click="CopyTicketId()" class="cursor-pointer hover:text-sky-500" title="Скопировать ссылку на тикет">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                  stroke="currentColor" class="w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
              </div>
            </div>

            <!-- Attachments sending input -->
            <div class="border-none bg-transparent cursor-pointer p-2 hover:border-none focus:border-none">
              <input id="attachments_input" @change="AddAttachments" ref="attachments" type="file" accept="image/*"
                multiple class="hide-file-input" />
              <label for="attachments_input" class="cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                  stroke="currentColor" for="attachments_input" class="text-black-800 w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                </svg>
              </label>
            </div>

            <!-- Message sending input -->
            <div class="flex-1 relative px-1 rounded-t-lg"><!-- v-focus:[active]="$route.name == 'ticket'" -->
              <textarea v-model="CreatingMessage" @keydown.ctrl.enter.exact="Create()" v-focus rows="1"
                @paste="AddAttachments"
                class="resize-none block overflow-hidden p-2.5 pr-4 w-full text-sm text-gray-900 bg-white rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                placeholder="Введите сообщение..." />
              <div v-if="CreatingMessage.length > 0" @click="CreatingMessage = ''"
                class="absolute right-3 inset-y-0 flex items-center mr-1 cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                  stroke="currentColor" class="text-black-800 w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z" />
                </svg>
              </div>
            </div>

            <VueButton v-if="CreatingMessage.length > 0 || files.length > 0" @click="Create()"
              class="border-none hover:border-none focus:border-none" color="default">
              Отправить
            </VueButton>
            <VueButton v-if="ticket.new_user_id == UserData.user_id && !marking" @click="MarkShowing()"
              class="border-none hover:border-none focus:border-none" color="red">
              Завершить тикет
            </VueButton>
          </div>
        </div>
      </div>
    </div>


    <!-- Ticket info -->
    <div v-if="UserData.is_admin || UserData.role_id == 2" class="h-[calc(100vh-55px)]">
      <div class="tabs-nowrap truncate">
        <Tabs variant="underline" v-model="ActiveTab">
          <Tab name="details" title="Общее" />
          <Tab name="system_chat" title="Системный чат" />
        </Tabs>
      </div>

      <div
        :class="ticket.active == 0 || IsResolved || ActiveTab == 'details' ? 'h-[calc(100%-55px)]' : 'h-[calc(100%-55px-43px)]'">
        <KeepAlive>
          <Detalization v-if="ActiveTab == 'details'" :ticket="ticket" :participants="participants_data" />
        </KeepAlive>
        <SystemChat v-if="ActiveTab == 'system_chat'" :ticket="ticket" />
      </div>
    </div>
  </div>

  <!-- Attachments slider modal -->
  <!-- <Transition name="modal"> -->
  <div v-if="showModal" @click.self="showModal = false" class="modal-mask">
    <div @click.self="showModal = false" class="modal-body">
      <Swiper :slides-per-view="1" :space-between="50" :modules="modules" @afterInit="SlideTo" :loop="AllFiles.length > 0"
        :keyboard="{ enabled: true }" :pagination="{ clickable: true, type: 'fraction' }" grabCursor centeredSlides
        mousewheel zoom virtual navigation>
        <SwiperSlide v-for="(file, key) in  AllFiles " :key="key" :virtualIndex="key">
          <div class="swiper-zoom-container">
            <img :src="messages[0].attachments_domain + file?.link" :alt="file?.name" class="object-contain">
          </div>
        </SwiperSlide>
      </Swiper>
    </div>
  </div>
  <!-- </Transition> -->
</template>

<style>
.tabs-nowrap ul {
  flex-wrap: nowrap !important;
}

.dropZone {
  width: 100%;
  height: 100%;
  position: relative;
  border: 2px dashed #aaa;
}

/* .dropZone:hover {
  border: 2px solid #2e94c4;
}

.dropZone:hover .dropZone-title {
  color: #1975A0;
} */

.dropZone-info {
  color: white;
  position: absolute;
  top: 50%;
  width: 100%;
  transform: translate(0, -50%);
  text-align: center;
}

/* .dropZone-title {
  color: black;
} */

.dropZone input {
  position: absolute;
  cursor: pointer;
  top: 0px;
  right: 0;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
}

.dropZone-over {
  background: #eee;
  opacity: 0.8;
}

.dropZone-uploaded {
  width: 80%;
  height: 200px;
  position: relative;
  border: 2px dashed #eee;
}

.dropZone-uploaded-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #A8A8A8;
  position: absolute;
  top: 50%;
  width: 100%;
  transform: translate(0, -50%);
  text-align: center;
}
</style>
