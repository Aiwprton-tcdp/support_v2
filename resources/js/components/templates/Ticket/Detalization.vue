<script>
import { inject } from 'vue'
import {
  Button as VueButton,
  Toggle, Avatar
} from 'flowbite-vue'
import VueMultiselect from 'vue-multiselect'
import useClipboard from 'vue-clipboard3'
import { mask } from 'vue-the-mask'
import gsap from 'gsap'

export default {
  name: 'DetalizationComponent',
  components: {
    VueButton, Toggle,
    Avatar, VueMultiselect
  },
  directives: { mask },
  props: {
    ticket: Object(),
    participants: Array(),
  },
  data() {
    return {
      AllManagers: Array(),
      Managers: Array(),
      AllBusyManagers: Array(),
      BusyManagers: Array(),
      Departments: Array(),
      AllReasons: Array(),
      Reasons: Array(),
      EditParticipants: Boolean(),
      EditReason: Boolean(),
      IsResolved: Boolean(),
      ShowUserInfo: Boolean(),
      ShowManagerInfo: Boolean(),
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
      VITE_CRM_MARKETPLACE_ID: String(import.meta.env.VITE_CRM_MARKETPLACE_ID),
      NumericAnyDesk: Number(),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')
    const emitter = inject('emitter')
    const { toClipboard } = useClipboard()

    const copy = async data => {
      try {
        await toClipboard(data)
        console.log('Copied to clipboard')
      } catch (e) {
        console.error(e)
      }
    }

    return {
      UserData,
      toast,
      emitter,
      copy
    }
  },
  mounted() {
    this.emitter.on('NewParticipant', this.NewParticipant)
    this.emitter.on('PatchTicket', this.PatchTicket)

    this.IsResolved = this.ticket?.old_ticket_id > 0
    this.GetDepartments()

    if (this.ticket.anydesk != null) {
      let digits = this.ticket.anydesk.replaceAll(/[^\d]*/gi, '')
      gsap.to(this, { duration: 1, NumericAnyDesk: Number(digits) || 0 })
    }
  },
  methods: {
    GetDepartments() {
      this.ax.get('bx/departments').then(r => {
        this.Departments = r.data.data.data
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    GetManagers() {
      if (this.EditParticipants) {
        this.EditParticipants = false
        return
      }

      this.ax.get('managers?role=2').then(r => {
        this.AllManagers = r.data.data.data
        this.AllManagers.forEach(u => u.value = u.id)
        this.Managers = this.AllManagers.filter(m => ![this.ticket.new_user_id, this.ticket.new_manager_id].includes(m.user_id))
        this.EditParticipants = true
        this.EditReason = false

        if (this.AllManagers.length == 0) {
          this.toast('Нет ни одного менеджера', 'warning')
          return
        }
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    GetReasons() {
      if (this.EditReason) {
        this.EditReason = false
        return
      }

      this.ax.get('reasons').then(r => {
        this.AllReasons = r.data.data.data
        this.Reasons = this.AllReasons.filter(r => r.id != this.ticket.reason_id)
        this.EditReason = true
        this.EditParticipants = false
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    AddParticipant(data) {
      if (this.IsResolved) return

      this.ax.post('participants', {
        ticket_id: this.ticket.id,
        user_id: data.user_id,
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning')
          return
        }

        this.NewParticipant(r.data.data)

        this.BusyManagers = [...this.AllBusyManagers]
        this.Managers = this.AllManagers.filter(m => this.ticket.new_manager_id != m.user_id)

        let index = this.BusyManagers.findIndex(({ user_id }) => user_id == this.ticket.new_manager_id)
        if (index > -1) {
          this.BusyManagers.splice(index, 1)
        }

        index = this.BusyManagers.findIndex(({ user_id }) => user_id == r.data.data.new_participant_id)
        if (index == -1) {
          const m_index = this.Managers.findIndex(({ user_id }) => user_id == r.data.data.new_participant_id)
          this.BusyManagers.push(this.Managers[m_index])
        }
        this.EditParticipants = false
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
      // this.SendParticipants(this.BusyManagers)
    },
    NewParticipant(data) {
      this.ticket.manager = data.new_manager
      this.ticket.new_manager_id = data.new_manager_id
      this.ticket.manager_id = data.new_manager.crm_id
    },
    PatchTicket(data) {
      // if (this.ticket.reason_id == data.reason_id) return
      this.ticket.reason = data.reason
      this.ticket.reason_id = data.reason_id
      this.ticket.incompetence = data.incompetence == 1
      this.ticket.technical_problem = data.technical_problem == 1
      this.Reasons = this.AllReasons.filter(r => r.id != this.ticket.reason_id)
    },
    CloseTicket() {
      if (!window.confirm("Вы уверены, что хотите завершить тикет?")) {
        return
      }

      this.ax.patch(`tickets/${this.ticket.id}`, {
        active: false
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning')
          return
        }

        this.emitter.emit('DeleteTicket', this.ticket.id, r.data.message)
        // this.$router.push('tickets')
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Change() {
      if (this.IsResolved) {
        return this.ChangeInResolved();
      }
      console.log('Change');
      console.log(this.ticket);
      this.ax.patch(`tickets/${this.ticket.id}`, {
        incompetence: this.ticket.incompetence,
        technical_problem: this.ticket.technical_problem,
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning');
        }
        console.log(this.ticket);
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      });
    },
    ChangeReason(reason_id) {
      // if (!window.confirm("Вы уверены, что хотите сменить тему?")) {
      //   return
      // }

      this.ax.patch(`tickets/${this.ticket.id}`, { reason_id }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning')
        }
        this.EditReason = false
        this.emitter.emit('PatchTicket', r.data.data)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    ChangeInResolved() {
      this.ax.patch(`resolved_tickets/${this.ticket.id}`, {
        incompetence: this.ticket.incompetence,
        technical_problem: this.ticket.technical_problem,
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning');
        }
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      });
    },
    ChangeReasonInResolved(data) {
      // if (!window.confirm("Вы уверены, что хотите сменить тему?")) {
      //   return
      // }

      this.ax.patch(`resolved_tickets/${this.ticket.id}`, {
        reason_id: data.id
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning')
        }
        this.EditReason = false
        this.emitter.emit('PatchTicket', r.data.data)
        this.toast(r.data.message, 'success')
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    CopyTicketId() {
      this.copy(`${this.VITE_CRM_URL}marketplace/app/${this.VITE_CRM_MARKETPLACE_ID}/?id=${this.ticket.old_ticket_id ?? this.ticket.id}`)
    },
    CopyData(data, is_bio = false) {
      if (is_bio) {
        let r = data.split(' ')
        data = `${r[0]} ${r[1]}`
      }

      this.copy(data)
    },
  },
  watch: {
    BusyManagers(newValue) {
      this.BusyManagers = newValue
    },
    participants(newValue) {
      this.AllBusyManagers = newValue
      this.BusyManagers = this.AllBusyManagers

      // this.Managers = this.AllManagers.filter(m => ![this.ticket.user_id, this.ticket.manager_id].includes(m.crm_id))
      this.Managers = this.AllManagers.filter(m => ![this.ticket.new_user_id, this.ticket.new_manager_id].includes(m.user_id))
      // this.Managers = this.AllManagers.filter(m => this.ticket.manager_id != m.crm_id
      //   && !this.BusyManagers.some(bm => bm.crm_id == m.crm_id))
    }
  }
}
</script>

<template>
  <div
    class="flex flex-col gap-2 h-[100%] items-center content-end py-1 px-2 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <div class="flex flex-col min-[1200px]:flex-row w-full justify-between gap-3">
      <p @click="CopyTicketId()" title="Скопировать ссылку на тикет" class="cursor-pointer hover:text-sky-500">
        ID: {{ ticket.old_ticket_id ?? ticket.id }}
      </p>

      <div v-if="!IsResolved && ticket.anydesk != null" @click="CopyData(ticket.anydesk)">
        <p class="cursor-pointer hover:text-sky-500" title="Скопировать адрес AnyDesk">
          <!-- AnyDesk: {{ ticket.anydesk }} -->
          AnyDesk: {{ NumericAnyDesk.toFixed(0).replaceAll(/(\d)()(?=(\d{3})+(?!\d))/gi, '$1 ') }}
          <!-- <input v-model.number="NumericAnyDesk" v-mask="[' ### ### ###', '# ### ### ###']" /> -->
        </p>
      </div>
    </div>

    <div class="flex flex-col w-full">
      <div class="flex items-center gap-1">
        <p @click="CopyData(ticket.reason)" class="cursor-pointer hover:text-sky-500" title="Скопировать тему">
          {{ ticket.reason }}
        </p>
        <span title="Сменить тему" @click="GetReasons()">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-3 h-3 cursor-pointer">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
          </svg>
        </span>
      </div>

      <VueMultiselect v-if="!IsResolved && EditReason" :options="Reasons" placeholder="Выберите тему"
        @select="ChangeReason" label="name" track-by="name" :show-labels="false" v-focus />

      <VueMultiselect v-if="IsResolved && EditReason" :options="Reasons" placeholder="Выберите тему"
        @select="ChangeReasonInResolved" label="name" track-by="name" :show-labels="false" v-focus />
    </div>

    <div
      class="flex flex-col w-full px-3 py-2 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700">
      <div class="flex items-center gap-1">
        <label>Ответственный</label>

        <span v-if="!IsResolved" title="Сменить ответственного" @click="GetManagers()">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-3 h-3 cursor-pointer">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
          </svg>
        </span>

        <p @click="CopyData(ticket.manager_id)" class="cursor-pointer hover:text-sky-500" title="Скопировать CRM Id">
          {{ ticket.manager_id }}
        </p>
      </div>

      <VueMultiselect v-if="!IsResolved && EditParticipants" :options="Managers" placeholder="Выберите менеджера"
        @select="AddParticipant" label="name" track-by="name" :show-labels="false" v-focus />

      <div class="flex flex-row items-center gap-1">
        <a :href="`https://${ticket.bx_domain}/company/personal/user/${ticket.manager_id}/`" target="_blank">
          <Avatar rounded size="sm" alt="avatar"
            :img="ticket.manager?.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
        </a>
        <p @click="CopyData(ticket.manager.name, true)" class="cursor-pointer hover:text-sky-500" title="Скопировать ФИО">
          {{ ticket.manager?.name }}
        </p>
      </div>
    </div>

    <div
      class="flex flex-col w-full px-3 py-2 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700">
      <div class="flex flex-row items-center gap-1">
        <label>Создатель</label>
        <p @click="CopyData(ticket.user.crm_id)" class="cursor-pointer hover:text-sky-500" title="Скопировать CRM Id">
          {{ ticket.user.crm_id }}
        </p>
      </div>

      <div class="flex flex-row items-center gap-1">
        <a :href="`https://${ticket.bx_domain}/company/personal/user/${ticket.user.crm_id}/`" target="_blank">
          <Avatar rounded size="sm" alt="avatar"
            :img="ticket.user?.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
        </a>
        <p @click="CopyData(ticket.user.name, true)" class="cursor-pointer hover:text-sky-500" title="Скопировать ФИО">
          {{ ticket.user?.name }}
        </p>
      </div>

      <template v-if="ticket.user?.post?.length > 0">
        <!-- <p>Должность: {{ ticket.user?.post }}</p> -->
        <p class="text-sm text-gray-400">{{ ticket.user?.post }}</p>
      </template>

      <div>
        <p>Подразделения:</p>
        <div class="flex flex-wrap gap-1">
          <template v-for="dep in ticket.user.departments" v-bind:key="dep">
            <div class="font-medium truncate">
              <a :href="`${VITE_CRM_URL}company/structure.php?set_filter_structure=Y&structure_UF_DEPARTMENT=${dep}`"
                target="_blank">
                {{ Departments.find(({ id }) => id == dep)?.name }}
              </a>
            </div>
          </template>
        </div>
      </div>

      <template v-if="ticket.user.inner_phone > 0">
        <p class="text-sm text-gray-400">Внутренний номер: {{ ticket.user.inner_phone }}</p>
      </template>

      <template v-if="ticket.user.personal_phone.length > 0">
        <p class="text-sm text-gray-400">Личный номер: {{ ticket.user.personal_phone.replaceAll(/[A-zА-яЁё]*/gms, '') }}
        </p>
      </template>

      <template v-if="ticket.user.work_phone.length > 0">
        <p class="text-sm text-gray-400">Рабочий номер: {{ ticket.user.work_phone.replaceAll(/[A-zА-яЁё]*/gms, '') }}</p>
      </template>
    </div>

    <div
      class="flex flex-col gap-1 w-full px-3 py-2 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700">
      <div class="flex flex-row items-center gap-1">Тип проблемы</div>

      <Toggle @change="Change()" v-model="ticket.incompetence" label="Некомпетентность" color="green" />
      <Toggle @change="Change()" v-model="ticket.technical_problem" label="Техническая проблема" color="green" />
    </div>

    <div v-if="BusyManagers.length > 0"
      class="flex flex-col block w-full px-3 py-2 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700">
      <p>Прочие участники:</p>

      <div class="flex flex-wrap my-2">
        <span v-for="m in BusyManagers" v-bind:key="m"
          class="m-1 bg-blue-100 text-blue-800 text-sm font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
          {{ m?.name }}
        </span>
      </div>
    </div>

    <div class="mt-4">
      <template v-if="IsResolved">
        <p>Тикет был завершён</p>
        <p>{{ ticket.mark > 0 ? 'с оценкой ' + ticket.mark + '/3' : 'без оценки' }}</p>
      </template>
      <template v-else>
        <VueButton v-if="ticket.new_user_id != UserData.user_id" @click="CloseTicket()" color="red">Завершить тикет
        </VueButton>
      </template>
    </div>
  </div>
</template>