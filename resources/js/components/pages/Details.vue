<script>
import { inject } from 'vue'
import { Input, Button, Avatar } from 'flowbite-vue'

export default {
  name: 'Details',
  components: {
    Input, Button,
    Avatar
  },
  props: {
    id: Number()
  },
  data() {
    return {
      lostTickets: Array(),
      activeTickets: Array(),
      allTickets: Array(),
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
      this.ax.get('detalization/active_tickets').then(r => {
        if (r.data.status == false) {
          this.toast(r.data.message, 'error')
        }

        this.lostTickets = r.data.data.lost
        this.activeTickets = r.data.data.active

        this.allTickets = this.lostTickets.concat(this.activeTickets)

        this.errored = false
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    }
  }
}
</script>

<template>
  <div class="grid grid-cols-4 gap-2">
    <div class="flex flex-col gap-1">
      <p>Активные тикеты</p>
      <template v-for="t in allTickets">
        <div class="flex flex-row items-center rounded-xl bg-gray-100 p-1">
          <a :href="VITE_CRM_URL + 'company/personal/user/' + t.manager.crm_id + '/'" target="_blank">
            <Avatar rounded size="sm" alt="avatar" :title="t.manager.name"
              :img="t.manager.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
          </a>
          <div class="flex flex-col">
            <p>{{ t.manager.name }}</p>
            <p>{{ t.tickets_count }}</p>
          </div>
        </div>
      </template>
    </div>

    <div class="flex flex-col gap-1">
      <p>Активные тикеты</p>
      <template v-for="t in allTickets">
        <div class="flex flex-row items-center rounded-xl bg-gray-100 p-1">
          <a :href="VITE_CRM_URL + 'company/personal/user/' + t.manager.crm_id + '/'" target="_blank">
            <Avatar rounded size="sm" alt="avatar" :title="t.manager.name"
              :img="t.manager.avatar ?? 'https://e7.pngegg.com/pngimages/981/645/png-clipart-default-profile-united-states-computer-icons-desktop-free-high-quality-person-icon-miscellaneous-silhouette-thumbnail.png'" />
          </a>
          <div class="flex flex-col">
            <p>{{ t.manager.name }}</p>
            <p>{{ t.tickets_count }}</p>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>