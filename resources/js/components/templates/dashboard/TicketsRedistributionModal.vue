<script>
import { inject } from 'vue'
import { Button, Input, Modal, Toggle } from 'flowbite-vue'
import VueMultiselect from 'vue-multiselect'

export default {
  name: 'TicketsRedistributionModal',
  components: {
    Button, Input,
    Modal, Toggle,
    VueMultiselect
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
        // console.log(this.AllManagers)

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
        console.log('count < 1')
        this.count = 1
      } else if (this.count > this.ticket.tickets_count) {
        console.log(`count > ${this.ticket.tickets_count}`)
        this.count = this.ticket.tickets_count
      }

      // let datay = {
      //   user_crm_id: this.ticket.manager.crm_id,
      //   reason_id: this.ticket.reason_id,
      //   count: this.count,
      //   new_crm_ids: this.SelectedManagers.map(m => m.crm_id),
      // }
      // console.log(datay)
      // return
      this.ax.post('statistics/redistribute', {
        user_crm_id: this.ticket.manager.crm_id,
        reason_id: this.ticket.reason_id,
        count: this.count,
        new_crm_ids: this.SelectedManagers.map(m => m.crm_id),
      }).then(r => {
        if (r.data.status == false) {
          this.toast(r.data.message, 'error')
        }

        console.log(r.data)

        this.errored = false
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
    SelectedManagers(newValue, oldValue) {
      this.SelectedManagers = newValue
      console.log('watch this.SelectedManagers')
      console.log(this.SelectedManagers)
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
        <!-- <VueMultiselect v-model="OnlyInGroup" :options="managers" :multiple="true" :close-on-select="false"
          placeholder="Выберите менеджера" @select="AddToGroup" @remove="RemoveFromGroup" label="name" track-by="name">
          <template #noResult>Нет данных</template>

          <template slot="option" slot-scope="props">
            <img class="option__image" :src="props.option.avatar" alt="avatar">
            <span>{{ props.option.text }}</span>
          </template>

          <template slot="tag" slot-scope="{ option, remove }">
            <span class="multiselect__tag">
              <span>{{ option.text }}</span>
              <span class="multiselect__tag-icon" @click.prevent="remove(option)">
                &#10006;
              </span>
            </span>
          </template>
        </VueMultiselect> -->

        <Input v-model="count" type="number" label="Количество тикетов на передачу:" />

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
      <div class="flex flex-row-reverse">
        <button @click="Close()" type="button"
          class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">
          Отменить
        </button>
        <Button @click="Redistribute()" color="green">
          Подтвердить
        </Button>
      </div>
    </template>
  </Modal>
</template>
