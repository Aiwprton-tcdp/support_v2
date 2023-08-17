<script>
import { inject } from 'vue'
import {
  Input, Button,
  Avatar
} from 'flowbite-vue'
import VueMultiselect from 'vue-multiselect'
import useClipboard from 'vue-clipboard3'

export default {
  name: 'Detalization',
  components: {
    Input, Button,
    Avatar, VueMultiselect
  },
  props: {
    ticket: Object(),
    participants: Array(),
  },
  data() {
    return {
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
      VITE_CRM_MARKETPLACE_ID: String(import.meta.env.VITE_CRM_MARKETPLACE_ID),
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
        this.Managers = this.AllManagers.filter(m => ![this.ticket.user_id, this.ticket.manager_id].includes(m.crm_id))
        this.EditParticipants = true

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
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    // GetParticipants() {
    //   this.ax.get(`participants?ticket_id=${this.ticket.id}`).then(r => {
    //     this.AllBusyManagers = r.data.data
    //     this.BusyManagers = [...this.AllBusyManagers]
    //     this.SendParticipants(this.BusyManagers)

    //     this.Managers = this.AllManagers.filter(m => this.ticket.manager_id != m.crm_id)
    //     // this.Managers = this.AllManagers.filter(m => this.ticket.manager_id != m.crm_id
    //     //   && !this.BusyManagers.some(bm => bm.crm_id == m.crm_id))
    //   }).catch(e => {
    //     this.toast(e.response.data.message, 'error')
    //   })
    // },
    AddParticipant(data) {
      if (this.IsResolved) return

      this.ax.post('participants', {
        ticket_id: this.ticket.id,
        user_crm_id: data.crm_id,
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning')
          return
        }

        this.ticket.manager = r.data.data.new_manager
        this.ticket.manager_id = r.data.data.new_manager.crm_id

        this.BusyManagers = [...this.AllBusyManagers]
        this.Managers = this.AllManagers.filter(m => this.ticket.manager_id != m.crm_id)

        let index = this.BusyManagers.findIndex(({ crm_id }) => crm_id == this.ticket.manager_id)
        if (index > -1) {
          this.BusyManagers.splice(index, 1)
        }

        index = this.BusyManagers.findIndex(({ crm_id }) => crm_id == r.data.data.new_participant_id)
        if (index == -1) {
          const m_index = this.Managers.findIndex(({ crm_id }) => crm_id == r.data.data.new_participant_id)
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
      this.ticket.manager_id = data.new_manager.crm_id
    },
    PatchTicket(data) {
      this.ticket.reason = data.reason
      this.ticket.reason_id = data.reason_id
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
        this.$router.push('tickets')
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    ChangeReason(data) {
      if (!window.confirm("Вы уверены, что хотите сменить тему?")) {
        return
      }

      this.ax.patch(`tickets/${this.ticket.id}`, {
        reason_id: data.id
      }).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'warning')
        }
        this.EditReason = false
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    CopyTicketId() {
      this.copy(`${this.VITE_CRM_URL}marketplace/app/${this.VITE_CRM_MARKETPLACE_ID}/?id=${this.ticket.id}`)
    },
  },
  watch: {
    BusyManagers(newValue, oldValue) {
      this.BusyManagers = newValue
    },
    participants(newValue, oldValue) {
      this.AllBusyManagers = newValue
      this.BusyManagers = this.AllBusyManagers

      this.Managers = this.AllManagers.filter(m => ![this.ticket.user_id, this.ticket.manager_id].includes(m.crm_id))
      // this.Managers = this.AllManagers.filter(m => this.ticket.manager_id != m.crm_id
      //   && !this.BusyManagers.some(bm => bm.crm_id == m.crm_id))
    }
  }
}
</script>

<template>
  <div
    class="flex flex-col gap-2 h-[calc(100%-15px)] items-center content-end py-1 px-2 overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
    <div class="flex flex-row items-center gap-3">
      <p for="ticket_info_id">ID тикета</p>
      <p id="ticket_info_id" @click="CopyTicketId()" title="Скопировать ссылку на тикет"
        class="flex items-center gap-1 font-medium text-blue-600 dark:text-blue-500 hover:underline cursor-pointer">
        #{{ ticket.id }}
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
          class="w-3 h-3">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
        </svg>
      </p>
    </div>

    <div class="flex items-center gap-1">
      <p id="ticket_info_reason" class="font-medium">
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

    <div class="grid grid-cols-4 justify-items-center gap-y-4 w-full">
      <!-- @mouseover="ShowUserInfo = true" @mouseleave="ShowUserInfo = false" -->
      <div class="relative col-span-2 w-full" @mouseleave="ShowUserInfo = false">
        <div @click="ShowUserInfo = !ShowUserInfo; ShowManagerInfo = false" class="flex justify-center">
          <Avatar rounded size="lg" alt="avatar" :title="ticket.user?.name"
            :img="ticket.user?.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
          <!-- <p>Создатель</p> -->
        </div>

        <div :class="ShowUserInfo ? 'visible opacity-100' : 'invisible opacity-0'"
          class="absolute z-10 !w-[200%] flex flex-col gap-2 p-3 inline-block text-sm font-light text-gray-500 transition-opacity duration-300 bg-white border border-gray-200 rounded-lg shadow-sm w-fit dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400">
          <div>
            <a :href="`${VITE_CRM_URL}company/personal/user/${ticket.user.crm_id}/`" target="_blank">
              <p>{{ ticket.user?.name }}</p>
            </a>
          </div>

          <template v-if="ticket.user?.post.length > 0">
            <p>Должность: {{ ticket.user?.post }}</p>
          </template>

          <div>
            <p>Подразделения:</p>
            <div class="flex flex-wrap gap-1">
              <template v-for="dep in ticket.user.departments">
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
            <p>Внутренний номер: {{ ticket.user.inner_phone }}</p>
          </template>
        </div>
      </div>

      <div class="relative col-span-2 w-full" @mouseleave="ShowManagerInfo = false">
        <div @click="ShowManagerInfo = !ShowManagerInfo; ShowUserInfo = false" class="flex justify-center">
          <Avatar rounded size="lg" alt="avatar" :title="ticket.manager?.name"
            :img="ticket.manager?.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
          <!-- <p class="flex items-center gap-1">
            Ответственный
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="w-3 h-3 cursor-pointer" title="Сменить ответственного"
              @click="EditParticipants = !EditParticipants">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
          </p> -->
        </div>

        <div :class="ShowManagerInfo ? 'visible opacity-100' : 'invisible opacity-0'"
          class="absolute z-10 !w-[200%] left-[-100%] flex flex-col gap-2 p-3 inline-block text-sm font-light text-gray-500 transition-opacity duration-300 bg-white border border-gray-200 rounded-lg shadow-sm w-fit dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400">
          <div>
            <a :href="`${VITE_CRM_URL}company/personal/user/${ticket.manager.crm_id}/`" target="_blank">
              <p>{{ ticket.manager?.name }}</p>
            </a>
          </div>

          <template v-if="ticket.manager?.post.length > 0">
            <p>Должность: {{ ticket.manager?.post }}</p>
          </template>

          <div>
            <p>Подразделения:</p>
            <div class="flex flex-wrap">
              <template v-for="dep in ticket.manager.departments">
                <div class="font-medium truncate mr-2">
                  <a :href="`${VITE_CRM_URL}company/structure.php?set_filter_structure=Y&structure_UF_DEPARTMENT=${dep}`"
                    target="_blank">
                    {{ Departments.find(({ id }) => id == dep)?.name }}
                  </a>
                </div>
              </template>
            </div>
          </div>

          <template v-if="ticket.manager.inner_phone > 0">
            <p>Внутренний номер: {{ ticket.manager.inner_phone }}</p>
          </template>
        </div>
      </div>

      <div class="col-span-2">
        <p>Создатель</p>
      </div>

      <div class="col-span-2">

        <p class="flex items-center gap-1">
          Ответственный
          <span title="Сменить ответственного" @click="GetManagers()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="w-3 h-3 cursor-pointer">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
          </span>
        </p>
      </div>
    </div>

    <div class="flex flex-col items-center gap-1 w-full">
      <p v-if="BusyManagers.length > 0">Прочие участники</p>

      <div class="flex flex-wrap my-2">
        <span v-for="m in BusyManagers"
          class="m-1 bg-blue-100 text-blue-800 text-sm font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
          {{ m?.name }}
        </span>
      </div>

      <VueMultiselect v-if="!IsResolved && EditParticipants" :options="Managers" placeholder="Выберите менеджера"
        @select="AddParticipant" label="name" track-by="name" :show-labels="false" />
      <VueMultiselect v-if="!IsResolved && EditReason" :options="Reasons" placeholder="Выберите тему"
        @select="ChangeReason" label="name" track-by="name" :show-labels="false" />
    </div>

    <div v-if="!IsResolved" class="flex flex-wrap items-center gap-3">
      <!-- <Button @click="EditParticipants = !EditParticipants" :color="EditParticipants ? 'alternative' : 'default'">
        <p v-if="EditParticipants">Отменить</p>
        <p v-else>Сменить ответственного</p>
      </Button>

      <Button @click="EditReason = !EditReason" :color="EditReason ? 'alternative' : 'default'">
        <p v-if="EditReason">Отменить</p>
        <p v-else>Сменить тему</p>
      </Button> -->

      <Button v-if="ticket.user_id != UserData.crm_id" @click="CloseTicket()" color="red">Завершить тикет</Button>
    </div>
  </div>
</template>