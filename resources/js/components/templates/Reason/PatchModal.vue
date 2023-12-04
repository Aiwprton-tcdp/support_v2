<script>
import {
  Input as VueInput,
  Button as VueButton,
  Select as VueSelect
} from 'flowbite-vue';

export default {
  name: 'PatchModal',
  components: {
    VueInput, VueButton,
    VueSelect
  },
  data() {
    return {
      visible: Boolean(),
      reason: Object(),
      instructions: Array(),
      PatchingName: String(),
      PatchingWeight: Number(1),
      PatchingGroupId: Number(),
      PatchingCallRequired: Boolean(),
    };
  },
  mounted() {
    this.PatchingName = this.reason.name;
    this.PatchingWeight = this.reason.weight;
    this.PatchingCallRequired = this.reason.call_required;
  },
  methods: {
    getInstructions() {
      console.log(this.reason);
      this.ax.get(`instructions?reason_id=${this.reason.id}`).then(r => {
        this.instructions = r.data.data.data;
        console.log(this.instructions);
        this.instructions.forEach(i => i.link = `${this.VITE_APP_URL}${i.link}`);
        console.log(this.instructions);
      }).catch(e => {
        this.toast(e.response.data.message, 'error');
      });
      // this.instructions = [
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона.pdf`,
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона1.pdf`,
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона2.pdf`,
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона3.pdf`,
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона4.pdf`,
      //   `${this.VITE_APP_URL}/storage/Настройка стационарного телефона5.pdf`,
      // ];
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

        <!-- <template v-else>
          <TableCell>
            <VueInput v-model="PatchingName" placeholder="Введите новое название" />
          </TableCell>
          <TableCell>
            <VueInput v-model.number="PatchingWeight" type="number" placeholder="Укажите вес" />
          </TableCell>
          <TableCell>
            <VueSelect v-model.number="PatchingGroupId" :options="groups" placeholder="Выберите группу" />
          </TableCell>
          <TableCell>
            <Toggle v-model="PatchingCallRequired" color="green" />
          </TableCell>

          <div class="px-6 py-4 space-x-3">
            <VueButton @click="Patch(r.id)" color="green">Сохранить</VueButton>
            <VueButton @click="PrepareForPatch()" color="light">Отменить</VueButton>
          </div>
        </template> -->
<template>
  <Transition name="modal">
    <div v-if="visible" class="modal-mask flex text-gray-900">
      <div class="modal-container flex flex-col gap-6 relative min-h-fit">
        <div class="modal-header mx-10">
          <div class="flex items-center mb-3 text-3xl">
            <p>Тема: <b>{{ reason.name }}</b></p>
          </div>
        </div>

        <div class="modal--body flex-1 mx-10">
          <div class="flex flex-col gap-1">
            <VueInput v-model="PatchingName" placeholder="Введите новое название" />
            <VueInput v-model.number="PatchingWeight" type="number" placeholder="Укажите вес" />
            <VueSelect v-model.number="PatchingGroupId" :options="groups" placeholder="Выберите группу" />
          </div>

          <!-- instructions -->
          <p class="text-xl">Инструкции</p>
          <div class="flex flex-col gap-1">
            <div v-for="i in instructions" :key="i">
              <VueInput v-model="i.link" placeholder="Укажите ссылку или контент" />
            </div>
          </div>
        </div>

        <div class="modal-footer mx-10">
          <div class="flex flex-row-reverse">
            <VueButton @click="Delete()" color="red">Удалить</VueButton>
            <VueButton @click="Save()" color="green">Сохранить</VueButton>
            <VueButton @click="Close()" color="alternative">Отменить</VueButton>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
