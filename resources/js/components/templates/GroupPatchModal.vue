<script>
import { inject } from 'vue'
import {
  Button as VueButton,
  Modal, Toggle
} from 'flowbite-vue'
import VueMultiselect from 'vue-multiselect'

export default {
  name: 'GroupPatchModal',
  components: {
    VueButton, Modal,
    Toggle, VueMultiselect
  },
  data() {
    return {
      visible: Boolean(),
      group: Object(),
      managers: Array(),
      OnlyInGroup: Array(),
      IsCollaborative: Boolean(),
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
          let mgf = r.data.data.filter(mg => mg.manager_id == m.id)
          m.in_group = mgf.length > 0
          if (m.in_group) {
            m.mg_id = mgf[0].id
            this.OnlyInGroup.push(m)
          }
        })
        console.log('this.managers')
        console.log(this.managers)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
        this.errored = true
      })
    },
    AddToGroup(data) {
      console.log(data)
      this.ax.post('manager_groups', {
        manager_id: data.id,
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
    ChangeCollaborative() {
      this.ax.patch(`groups/${this.group.id}`, this.group).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error')
        console.log(r.data)
      }).catch(e => {
        this.toast(e.response.data.message, 'error')
      })
    },
    Close() {
      this.visible = false
    },
  },
  watch: {
    OnlyInGroup(newValue) {
      this.OnlyInGroup = newValue
    }
  }
}
</script>

<template>
  <Modal v-if="visible" size="4xl" @close="Close">
    <template #header>
      <div class="flex items-center text-lg">
        <span>Группа: <b>{{ group.name }}</b></span>
      </div>
    </template>

    <template #body>
      <div v-if="errored">
        <p>Ошибка</p>
      </div>
      <div v-else class="flex flex-wrap space-2">
        <VueMultiselect v-model="OnlyInGroup" :options="managers" :multiple="true" :close-on-select="false"
          placeholder="Выберите менеджера" @select="AddToGroup" @remove="RemoveFromGroup" label="name" track-by="name">
          <template #noResult>Нет данных</template>

          <template slot="option" slot-scope="props">
            <img class="option__image" :src="props.option.avatar" alt="avatar">
            <span>{{ props.option.text }}</span>
          </template>

          <template slot="tag" slot-scope="{ option, remove }">
            <!-- <img class="tag__image" :src="option.avatar" alt="avatar"> -->
            <span class="multiselect__tag">
              <span>{{ option.text }}</span>
              <span class="multiselect__tag-icon" @click.prevent="remove(option)">
                &#10006;
              </span>
            </span>
          </template>
        </VueMultiselect>

        <Toggle @change="ChangeCollaborative" v-model="group.collaborative" label="Общая" color="green" />
      </div>
    </template>

    <template #footer>
      <div class="flex flex-row-reverse justify-between">
        <VueButton @click="Close" color="alternative">
          Закрыть
        </VueButton>
      </div>
    </template>
  </Modal>
</template>
