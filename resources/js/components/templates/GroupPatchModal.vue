<script>
import { inject } from 'vue'
import { Button, Modal } from 'flowbite-vue'
import VueMultiselect from 'vue-multiselect'

export default {
  name: 'GroupPatchModal',
  components: {
    Button, Modal,
    VueMultiselect
  },
  data() {
    return {
      visible: Boolean(),
      group: Object(),
      managers: Array(),
      OnlyInGroup: Array(),
      errored: Boolean(),
    }
  },
  setup() {
    const toast = inject('createToast')
    return { toast }
  },
  methods: {
    GetUsers() {
      this.ax.get('managers?role=2').then(r => {
        this.managers = r.data.data.data
        this.managers.forEach(u => u.value = u.id)
        
        if (this.managers.length == 0) {
          this.toast('Нет ни одного менеджера', 'warning')
          return
        }
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      }).finally(this.GetManagers)
    },
    GetManagers() {
      this.ax.get(`manager_groups?group=${this.group.id}`).then(r => {
        this.OnlyInGroup = []
        this.managers.forEach(m => {
          let mgf = r.data.data.filter(mg => mg.user_id == m.id)
          m.in_group = mgf.length > 0
          if (m.in_group) {
            m.mg_id = mgf[0].id
            this.OnlyInGroup.push(m)
          }
        })
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    AddToGroup(data) {
      console.log(data)
      this.ax.post('manager_groups', {
        user_id: data.id,
        group_id: this.group.id,
      }).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (r.data.status) {
          data.in_group = true
          data.mg_id = r.data.data.id
        }
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    RemoveFromGroup(data) {
      this.ax.delete(`manager_groups/${data.mg_id}`).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        if (r.data.status) {
          data.in_group = false
          delete data.mg_id
        }
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Close() {
      this.visible = false
    },
  },
  watch: {
    OnlyInGroup(newValue, oldValue) {
      this.OnlyInGroup = newValue
    }
  }
}
</script>

<template>
<Modal v-if="visible" size="4xl" @close="Close">
  <template #header>
    <div class="flex items-center text-lg">
      <span>Группа: </span><b>{{ group.name }}</b>
    </div>
  </template>

  <template #body>
    <div v-if="errored">
      <p>Ошибка</p>
    </div>
    <div v-else class="flex flex-wrap space-2">
      <VueMultiselect
        v-model="OnlyInGroup"
        :options="managers"
        :multiple="true"
        :close-on-select="false"
        placeholder="Выберите менеджера"
        @select="AddToGroup"
        @remove="RemoveFromGroup"
        label="name"
        track-by="name">
        <template #noResult>Нет данных</template>

        <template slot="option" slot-scope="props">
          <img class="option__image" :src="props.option.avatar" alt="avatar">
          <span>{{ props.option.text }}</span>
        </template>
        
        <template slot="tag" slot-scope="{ option, remove }">
          <!-- <img class="tag__image" :src="option.avatar" alt="avatar"> -->
          <span class="multiselect__tag">
            <span>{{ option.text }}</span>
            <span class="multiselect__tag-icon" @click.prevent="remove(option)"></span>
          </span>
        </template>
      </VueMultiselect>
    </div>
  </template>

  <template #footer>
    <div class="flex flex-row-reverse justify-between">
      <button @click="Close" type="button" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">
        Закрыть
      </button>
    </div>
  </template>
</Modal>
</template>
