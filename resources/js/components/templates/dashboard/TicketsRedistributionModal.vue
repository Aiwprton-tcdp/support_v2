<script>
import { inject } from 'vue'
import {
  Button as VueButton,
  Input as VueInput,
  Modal
} from 'flowbite-vue'
import VueMultiselect from 'vue-multiselect'

export default {
  name: 'TicketsRedistributionModal',
  components: {
    VueButton, VueInput,
    Modal, VueMultiselect
  },
  data() {
    return {
      visible: Boolean(),
      ticket: Object(),
      count: Number(),
      AllManagers: Array(),
      AllGroups: Array(),
      ManagersAndGroupsList: Array(),
      PrepareToRedistribute: Boolean(),
      SelectedManagers: new Array(),
      errored: Boolean(),
    }
  },
  setup() {
    const toast = inject('createToast')
    return { toast }
  },
  methods: {
    GetAllManagers() {
      this.count = this.ticket.tickets_count

      this.ax.get('managers?role=2').then(r => {
        this.AllManagers = r.data.data.data
        this.AllManagers.forEach(u => u.value = u.id)

        if (this.AllManagers.length == 0) {
          this.toast('Нет ни одного менеджера', 'warning')
          return
        }

        this.ManagersAndGroupsList = [...this.AllManagers]
        console.log(this.ManagersAndGroupsList)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })//.finally(this.GetAllGroups)
    },
    GetAllGroups() {
      this.ax.get('manager_groups').then(r => {
        this.AllGroups = r.data.data
        console.log(this.AllGroups)
        // this.ManagersAndGroupsList = [...this.AllManagers]
        // console.log(this.ManagersAndGroupsList)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Redistribute() {
      if (this.count < 1) {
        this.count = 1
      } else if (this.count > this.ticket.tickets_count) {
        this.count = this.ticket.tickets_count
      }
      // console.log(this.ticket)
      // console.log(this.SelectedManagers)
      // console.log(this.SelectedManagers.map(m => m.user_id))
      // return

      this.ax.post('statistics/redistribute', {
        reason_id: this.ticket.reason_id,
        user_id: this.ticket.new_manager_id,
        new_users_ids: this.SelectedManagers.map(m => m.user_id),
        count: this.count,
      }).then(r => {
        if (r.data.status == true) {
          this.toast(r.data.message, 'success')
        }

        this.errored = false
        this.Close()
        this.$parent.GetActiveTickets()
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    Close() {
      this.SelectedManagers = []
      this.count = 1
      this.visible = false
    },
  },
  watch: {
    SelectedManagers(newValue) {
      this.SelectedManagers = newValue
      // console.log('watch this.SelectedManagers')
      // console.log(this.SelectedManagers)
    }
  }
}
</script>

<template>
  <Modal v-if="visible" size="4xl" @close="Close()">
    <template #header>
      <div class="flex flex-col text-lg">
        <p>Менеджер: <b>{{ ticket.manager.name }}</b></p>
        <p>Тема: <b>{{ ticket.reason_name }}</b></p>
        <p>Количество тикетов: <b>{{ ticket.tickets_count }}</b></p>
      </div>
    </template>

    <template #body>
      <div class="flex flex-row gap-2">
        <VueInput v-model="count" type="number" label="Количество тикетов на передачу:" />

        <div>
          <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">
            Список менеджеров для распределения тикетов:
          </label>
          <VueMultiselect v-model="SelectedManagers" :options="ManagersAndGroupsList" placeholder="Выберите менеджеров"
            label="name" track-by="name" :show-labels="false" :multiple="true" :close-on-select="false" class="flex-1" />
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex flex-row-reverse gap-1">
        <button @click="Close()" type="button"
          class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">
          Отменить
        </button>
        <VueButton @click="Redistribute()" color="green">
          Подтвердить
        </VueButton>
      </div>
    </template>
  </Modal>
</template>
