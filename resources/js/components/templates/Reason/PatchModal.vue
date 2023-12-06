<script>
import { inject } from 'vue';
import {
  Input as VueInput,
  Button as VueButton,
  Select as VueSelect,
  Toggle
} from 'flowbite-vue';

export default {
  name: 'PatchModal',
  components: {
    VueInput, VueButton,
    VueSelect, Toggle
  },
  data() {
    return {
      visible: Boolean(),
      reason: Object(),
      instructions: Array(),
      groups: Array(),
      PatchingName: String(),
      PatchingWeight: Number(1),
      PatchingGroupId: Number(),
      PatchingGroupName: String(),
      PatchingCallRequired: Boolean(),
      creatingInstructionName: String(),
      creatingInstructionContent: String(),
      patchingInstructionId: Number(),
      patchingInstructionName: String(),
      patchingInstructionContent: String(),
    };
  },
  setup() {
    const toast = inject('createToast')
    return { toast }
  },
  mounted() { },
  methods: {
    setPatchingDefaults() {
      this.PatchingName = this.reason.name;
      this.PatchingWeight = this.reason.weight;
      this.PatchingCallRequired = this.reason.call_required;
      this.PatchingGroupId = this.reason.group_id;
      this.PatchingGroupName = this.groups.find(g => g.value == this.PatchingGroupId)?.name;
    },
    getInstructions() {
      this.ax.get(`instructions?reason_id=${this.reason.id}`).then(r => {
        this.instructions = r.data.data.data;
        console.log(this.instructions);
        // this.instructions.forEach(i => i.link = `${this.VITE_APP_URL}${i.link}`);
        // console.log(this.instructions);
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      }).finally(this.$parent.$parent.GetGroups);

      // this.instructions = [
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона.pdf`,
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона1.pdf`,
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона2.pdf`,
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона3.pdf`,
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона4.pdf`,
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона5.pdf`,
      // ];
    },
    AddInstruction() {
      this.ax.post('instructions', {
        name: this.creatingInstructionName,
        content: this.creatingInstructionContent,
        reason_id: this.reason.id,
      }).then(r => {
        const newInstruction = r.data.data;
        this.instructions.push(newInstruction);
        this.creatingInstructionName = '';
        this.creatingInstructionContent = '';
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      });
    },
    PrepareToPatchInstruction(data) {
      this.patchingInstructionId = data.id;
      this.patchingInstructionName = data.name;
      this.patchingInstructionContent = data.content;
    },
    PatchInstruction() {
      if (this.patchingInstructionId == 0) {
        this.toast('Не указана инструкция', 'error');
        return;
      }

      this.ax.patch(`instructions/${this.patchingInstructionId}`, {
        name: this.patchingInstructionName,
        content: this.patchingInstructionContent,
      }).then(r => {
        this.toast(r.data.message, r.data.status ? 'success' : 'error');
        const patchedInstruction = r.data.data;
        const index = this.instructions.findIndex(({ id }) => id == this.patchingInstructionId);
        if (index > -1) {
          this.instructions[index] = patchedInstruction;
        }
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      }).finally(() => this.patchingInstructionId = 0);
    },
    DeleteInstruction() {
      if (this.patchingInstructionId == 0) {
        this.toast('Не указана инструкция', 'error');
        return;
      } else if (!window.confirm("Вы уверены, что хотите удалить инструкцию?")) {
        return;
      }

      this.ax.delete(`instructions/${this.patchingInstructionId}`).then(r => {
        if (!r.data.status) {
          this.toast(r.data.message, 'error');
          return;
        }

        this.toast(r.data.message, r.data.status ? 'success' : 'error');
        const index = this.instructions.findIndex(({ id }) => id == this.patchingInstructionId);
        if (index > -1) {
          this.instructions.splice(index, 1);
        }
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      }).finally(() => this.patchingInstructionId = 0);
    },
    Close() {
      // this.$parent.$parent.GoToNext();
      this.reason = null;
      this.visible = false;
    },
    Save() {
      if (!window.confirm("Вы уверены, что хотите сохранить изменения?")) {
        return;
      }

      this.Close();
    },
    Delete() {
      if (!window.confirm("Вы уверены, что хотите удалить тему?")) {
        return;
      }

      this.Close();
    },
  }
}
</script>

<template>
  <Transition name="modal">
    <div v-if="visible" @click.self="Close" class="modal-mask flex text-gray-900 dark:text-gray-200">
      <div class="modal-container flex flex-col gap-6 relative min-h-fit dark:!bg-gray-800">
        <div class="modal-header mx-10">
          <div class="flex items-center mb-3 text-3xl">
            <p>Тема: <b>{{ reason.name }}</b></p>
          </div>
        </div>

        <div
          class="modal--body flex-1 grid grid-cols-5 justify-items-stretch place-content-between place-items-stretch gap-x-10 px-20">
          <div
            class="col-span-2 block w-full px-6 py-4 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col w-full gap-1">
              <b class="text-xl">Редактирование</b>

              <VueInput v-model="PatchingName" label="Название" />
              <div class="flex flex-row gap-2">
                <VueInput v-model.number="PatchingWeight" type="number" label="Вес" />
                <VueSelect v-model="PatchingGroupId" :options="groups" label="Группа" />
              </div>
              <Toggle v-model="PatchingCallRequired" color="green" label="Через звонок" />

              <div class="flex flex-row gap-2 mt-4">
                <VueButton @click="Save()" color="green"
                  :disabled="PatchingName == reason.name && PatchingWeight == reason.weight && PatchingGroupId == reason.group_id && PatchingCallRequired == reason.call_required">
                  Сохранить
                </VueButton>
                <VueButton
                  v-if="PatchingName != reason.name || PatchingWeight != reason.weight || PatchingGroupId != reason.group_id || PatchingCallRequired != reason.call_required"
                  @click="setPatchingDefaults()" color="dark">
                  Отменить
                </VueButton>
              </div>
            </div>
          </div>

          <!-- instructions -->
          <div
            class="col-span-3 block w-full px-6 py-4 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col w-full gap-1">
              <b class="text-xl">Инструкции</b>

              <div v-for="(i, key) in instructions" :key="key">
                <div v-if="i.id != patchingInstructionId" class="flex content-center gap-1">
                  <p :title="i.content">{{ key + 1 }}. {{ i.name }}</p>
                  <svg @click="PrepareToPatchInstruction(i)" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                    class="w-4 h-4 cursor-pointer hover:text-blue-700">
                    <title>Редактировать</title>
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                  </svg>
                </div>
                <div v-else>
                  <VueInput v-model="patchingInstructionName" label="Название" />
                  <VueInput v-model="patchingInstructionContent" label="Содержимое" />

                  <div class="flex flex-row w-full justify-between mt-4">
                    <div class="flex flex-row gap-2">
                      <VueButton @click="PatchInstruction(i)" color="green"
                        :disabled="patchingInstructionName == i.name && patchingInstructionContent == i.content">
                        Сохранить
                      </VueButton>
                      <VueButton @click="patchingInstructionId = 0" color="dark">
                        Отменить
                      </VueButton>
                    </div>
                    <div>
                      <VueButton @click="DeleteInstruction()" color="red">Удалить</VueButton>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="patchingInstructionId == 0" clas="mt-4">
                <VueInput v-model="creatingInstructionName" label="Название" />
                <VueInput v-model="creatingInstructionContent" label="Содержимое" />

                <div class="flex flex-row gap-2 mt-4">
                  <VueButton @click="AddInstruction()" color="green"
                    :disabled="creatingInstructionName.length == 0 || creatingInstructionContent.length == 0">
                    Добавить
                  </VueButton>
                  <VueButton v-if="creatingInstructionName.length > 0 || creatingInstructionContent.length > 0"
                    @click="creatingInstructionName = ''; creatingInstructionContent = ''" color="dark">
                    Очистить
                  </VueButton>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer mx-10">
          <div class="flex flex-row-reverse gap-2">
            <VueButton @click="Delete()" color="red">Удалить</VueButton>
            <VueButton @click="Close()" color="dark">Закрыть</VueButton>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
