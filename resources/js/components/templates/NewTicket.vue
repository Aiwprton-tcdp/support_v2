<script>
import { inject, defineAsyncComponent } from 'vue'
import { Button as VueButton, Input as VueInput } from 'flowbite-vue'
import { mask } from 'vue-the-mask'

import { StringVal, FormatLinks, FormatDateTime } from '@utils/validation.js'

const TheCallIsRequiredModal = defineAsyncComponent(() => import('@temps/NewTicket/TheCallIsRequiredModal.vue'))

export default {
  name: 'NewTicket',
  components: {
    VueButton, VueInput,
    TheCallIsRequiredModal
  },
  directives: { mask },
  data() {
    return {
      messages: Array(),
      waiting: Boolean(),
      errored: Boolean(),
      WasFirstTouch: Boolean(),
      HasAnyDeskAddress: Boolean(false),
      hintsShowed: Boolean(false),
      CreatingMessage: String(),
      AnyDeskAddress: String(),
      VITE_APP_URL: String(import.meta.env.VITE_APP_URL),
      instructions: Array(),
      checkedInstructions: Array(),
      reasons: Array(),
      reason: String(),
    }
  },
  setup() {
    const toast = inject('createToast')
    const emitter = inject('emitter')

    return { toast, emitter }
  },
  mounted() {
    this.AddMessage('Это предварительный формат диалога!!!');
    this.AddMessage('Кратко опишите суть проблемы в поле для ввода сообщения');

    this.GetReasonsWhenCallIsRequired();

    document.addEventListener('click', e => {
      if (e.target.nodeName == 'A') {
        this.CheckInstructionLinkUsed(e.target.href);
      }
    });
    document.addEventListener('auxclick', e => {
      if (e.button == 1 && e.target.nodeName == 'A') {
        this.CheckInstructionLinkUsed(e.target.href);
      }
    });
  },
  methods: {
    CheckInstructionLinkUsed(url) {
      console.log('this.instructions');
      console.log(this.instructions);
      this.instructions.forEach(i => {
        if (encodeURI(i.content).includes(url) && !this.checkedInstructions.includes(i.id)) {
          this.checkedInstructions.push(i.id);
          console.log('checked: %d', this.checkedInstructions.length);
          console.table(this.checkedInstructions, ['id']);
        }
      });
    },
    GetReasonsWhenCallIsRequired() {
      this.ax.get('reasons?call_required=true').then(r => {
        this.reasons = r.data.data.data;
        this.reasons.forEach(r => r.call_required = r.call_required == 1);
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
        this.errored = true;
      });
    },
    Check() {
      if (this.waiting) return
      this.waiting = true

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
        'Чтобы продолжить создание обращения, нажмите кнопку \'Подтвердить\'',
        'При необходимости Вы можете отредактировать сообщение в поле ввода',
      ]

      SystemMessages.forEach((m, key) => setTimeout(() => {
        this.AddMessage(m)
        this.waiting = key < SystemMessages.length - 1
      }, key * 100))
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
    TryToShowTheHints() {
      if (this.waiting) return;
      this.waiting = true;

      const message = this.CreatingMessage.trim();

      this.ax.post('reason/init', { message }).then(r => {
        if (r.data.status == false) {
          this.toast(r.data.message, 'error');
        }

        this.reason = r.data.data.name;
        // if (this.reason == 'Неопределённая тема') {
        //   this.waiting = false;
        //   return this.reformulationRequesting(message);
        // }

        if (this.reasons.filter(r => r.name == this.reason).length > 0) {
          return this.ShowTheCallIsRequiredModal();
        }

        return this.GoToNext();
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      }).finally(() => this.waiting = false);
    },
    GoToNext() {
      if (this.reason != 'Настройка стационарного телефона') {
        this.waiting = false;
        return this.GoToAnyDeskAdding();
      }

      let SystemMessages = [
        'Перед тем, как завершить создание тикета, необходимо ознакомиться с предложенными ниже инструкциями',
      ];

      this.getInstructions().then(() => {
        this.instructions.forEach((i, key) => {
          // SystemMessages.push(`<a href="${i.content}" target="_blank">Инструкция ${key + 1}</a>`);
          const content = FormatLinks(i.content, true);
          SystemMessages.push(`<p><b>Инструкция ${key + 1}:</b> ${content}</p>`);
        });
        SystemMessages.push('Если инструкции не помогли решить Вашу проблему, продолжите процесс создания тикета');

        this.AddMessage(FormatLinks(this.CreatingMessage.trim()), true);
        SystemMessages.forEach((m, key) => setTimeout(() => {
          this.AddMessage(m);
          this.waiting = key < SystemMessages.length - 1;
        }, key * 100));

        this.hintsShowed = true;
      });
    },
    async getInstructions() {
      await this.ax.get(`instructions?reason_name=${this.reason}`).then(r => {
        this.instructions = r.data.data.data;
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      });
    },
    reformulationRequesting(message) {
      this.AddMessage(FormatLinks(message), true);
      setTimeout(() => this.AddMessage('Не удаётся определить проблему. Попробуйте указать иную формулировку.'), 100);
    },
    GoToAnyDeskAdding() {
      if (this.waiting) return

      const message = this.CreatingMessage.trim()
      const validate = StringVal(message, 1, 1000)
      if (validate.status) {
        this.toast(validate.message, 'error')
        return
      }

      if (!this.hintsShowed) {
        this.AddMessage(FormatLinks(message), true);
      }
      this.AddMessage('Укажите адрес AnyDesk, чтобы в перспективе ускорить решение проблемы')
      console.table(this.checkedInstructions);

      this.HasAnyDeskAddress = true
      this.CreatingMessage = message
      // this.$nextTick(() => this.AnyDeskAddress = '')
    },
    Create() {
      if (this.waiting) return
      this.waiting = true

      const message = this.CreatingMessage.trim()
      const anydesk = this.AnyDeskAddress.trim()
      this.AddMessage(`AnyDesk: ${anydesk}`, true)

      this.ax.post('tickets', {
        message: message,
        anydesk: anydesk,
        checked_instructions: this.checkedInstructions,
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
    ShowTheCallIsRequiredModal() {
      this.$refs.TheCallIsRequired.visible = true;
    },
  }
}
</script>

<template>
  <div id="messages"
    class="custom-chat-bg flex flex-col h-full w-full content-end py-1 px-2 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <TransitionGroup name="list" tag="ul">
      <div v-for="m in messages" :key="m" class="my-1">
        <div class="chat-message">
          <div class="flex items-end" :class="{ 'justify-end': m.current }">
            <div class="flex flex-col space-y-2 text-sm max-w-sm mx-2 opacity-90"
              :class="m.current ? 'order-1 items-end text-right' : 'order-2 items-start text-left'">
              <span class="flex flex-col px-4 py-2 rounded-lg" :class="m.current ? 'rounded-br-none bg-indigo-300 whitespace-pre-wrap dark:text-gray-900 dark:bg-indigo-400' :
                'rounded-bl-none bg-gray-50 whitespace-pre-wrap dark:text-gray-900 dark:bg-gray-300'">
                <span v-if="!m.current" class="text-xs font-light tracking-tighter"
                  :class="m.current ? 'text-gray-500 dark:text-gray-600' : 'text-gray-400 dark:text-gray-500'">Система
                </span>
                <span v-html="m.content"></span>
                <span class="text-xs font-light tracking-tighter"
                  :class="m.current ? 'text-gray-500 dark:text-gray-600' : 'text-gray-400 dark:text-gray-500'">
                  {{ m.created_at }}
                </span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </TransitionGroup>
  </div>

  <!-- Message sending block -->
  <div class="flex flex-row w-full h-58 items-center gap-1 px-3 py-2 bg-gray-50 dark:bg-gray-700">
    <div class="flex-1 relative px-1 rounded-t-lg">
      <Transition name="fade" mode="out-in">
        <textarea v-if="!HasAnyDeskAddress" v-model="CreatingMessage"
          @keydown.ctrl.enter.exact="WasFirstTouch ? hintsShowed ? GoToAnyDeskAdding() : TryToShowTheHints() : Check()"
          v-focus rows="1"
          class="resize-none block overflow-hidden p-2.5 pr-4 w-full text-sm text-gray-900 bg-white rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
          placeholder="Введите сообщение..." />
        <VueInput v-else v-model="AnyDeskAddress" @keydown.ctrl.enter.exact="Create()" v-focus
          v-mask="[' ### ### ###', '# ### ### ###']" placeholder="987 654 321" />
      </Transition>
      <div v-if="CreatingMessage.length > 0 && !HasAnyDeskAddress || AnyDeskAddress.length > 0 && HasAnyDeskAddress"
        @click="CreatingMessage = ''; AnyDeskAddress = ''"
        class="absolute right-3 inset-y-0 flex items-center mr-1 cursor-pointer">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
          class="text-black-800 w-6 h-6">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z" />
        </svg>
      </div>
    </div>

    <div class="flex flex-row gap-1">
      <VueButton v-if="HasAnyDeskAddress" @click="Create()" :disabled="AnyDeskAddress.length < 12"
        class="border-none hover:border-none focus:border-none" color="green">
        Завершить создание обращения
      </VueButton>
      <VueButton v-else-if="hintsShowed" @click="GoToAnyDeskAdding()" :disabled="waiting"
        class="border-none hover:border-none focus:border-none" color="default">
        Продолжить
      </VueButton>
      <VueButton v-else-if="WasFirstTouch" @click="TryToShowTheHints()" :disabled="CreatingMessage.length == 0 || waiting"
        class="border-none hover:border-none focus:border-none" color="green">
        Подтвердить
      </VueButton>
      <VueButton v-else-if="CreatingMessage.length > 0 && !WasFirstTouch" @click="Check()"
        class="border-none hover:border-none focus:border-none" color="default">
        Проверить
      </VueButton>
    </div>
  </div>

  <!-- Modals -->
  <Teleport to="body">
    <TheCallIsRequiredModal ref="TheCallIsRequired" />
  </Teleport>
</template>
