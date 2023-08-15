<script>
import { inject } from 'vue'
import { Input, Button, Avatar } from 'flowbite-vue'
import TicketsRedistributionModal from '@temps/dashboard/TicketsRedistributionModal.vue'

export default {
  name: 'Dashboard',
  components: {
    Input, Button,
    Avatar, TicketsRedistributionModal
  },
  data() {
    return {
      lostTickets: Array(),
      activeTickets: Array(),
      AllTickets: Array(),
      VITE_CRM_URL: String(import.meta.env.VITE_CRM_URL),
    }
  },
  setup() {
    const UserData = inject('UserData')
    const toast = inject('createToast')
    const emitter = inject('emitter')

    return {
      UserData,
      toast,
      emitter
    }
  },
  mounted() {
    this.GetActiveTickets()
  },
  methods: {
    GetActiveTickets() {
      this.ax.get('statistics/active_tickets').then(r => {
        if (r.data.status == false) {
          this.toast(r.data.message, 'error')
        }

        this.lostTickets = r.data.data.lost
        this.activeTickets = r.data.data.active
        this.AllTickets = this.lostTickets.concat(this.activeTickets)
        this.errored = false
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    ShowModal(data) {
      this.$refs.TicketsRedistribution.visible = true
      this.$refs.TicketsRedistribution.errored = false
      this.$refs.TicketsRedistribution.ticket = data
      this.$refs.TicketsRedistribution.GetAllManagers()
    },
  }
}
</script>

<template>
  <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
    <div class="flex flex-col gap-1">
      <p class="text-lg">Активные тикеты</p>
      <template v-for="t in AllTickets">
        <div class="flex flex-row gap-1 items-center rounded-xl p-1 bg-gray-100 hover:bg-blue-50">
          <div class="min-w-[40px]">
            <a :href="VITE_CRM_URL + 'company/personal/user/' + t.manager.crm_id + '/'" target="_blank">
              <Avatar rounded size="sm" alt="avatar" :title="t.manager.name"
                :img="t.manager.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
            </a>
          </div>

          <div class="flex flex-col">
            <p>{{ t.manager.name }}</p>
            <div class="flex flex-wrap gap-1">
              <p>{{ t.reason_name }}: {{ t.tickets_count }}</p>
              <div @click="ShowModal(t)" title="Распределить">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                  stroke="currentColor" class="w-5 h-5 cursor-pointer text-blue-500 hover:text-blue-400 hover:underline">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>

  <!-- Modals -->
  <TicketsRedistributionModal ref="TicketsRedistribution" />
</template>