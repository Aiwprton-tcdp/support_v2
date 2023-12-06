<script>
import { inject } from 'vue';
import { Button as VueButton } from 'flowbite-vue';

export default {
  name: 'InstructionsModal',
  components: { VueButton },
  data() {
    return {
      visible: Boolean(),
      instructions: Array(),
    };
  },
  setup() {
    const UserData = inject('UserData');
    return { UserData };
  },
  mounted() {
    document.addEventListener('click', e => {
      if (e.target.nodeName == 'A') {
        this.CheckInstructionLinkUsed(e.target.href);
      }
    });
    document.addEventListener('auxclick', e => {
      if (e.button == 1 && e.target.nodeName == 'A') {
        this.CheckInstructionLinkUsed(e.target.href);
      }
    });
  },
  methods: {
    get(id, reason_id) {
      this.ax.get(`instructions?ticket_id=${id}&reason_id=${reason_id}`).then(r => {
        this.instructions = r?.data?.data?.data ?? [];
        console.log(this.instructions);
      });
    },
    close() {
      this.instructions = [];
      this.visible = false;
    },
  }
}
</script>

<template>
  <Transition name="modal">
    <div v-if="visible" @click.self="close()" class="modal-mask flex text-gray-900 dark:text-gray-200">
      <div class="modal-container flex flex-col gap-6 relative min-h-fit dark:!bg-gray-800">
        <div class="modal-header mx-20">
          <div class="flex items-center mb-3 text-3xl">
            <b>Инструкции</b>
          </div>
        </div>

        <div class="modal--body flex-1 flex flex-col px-20">
          <div v-if="instructions.length == 0">
            <p>По данной теме пока нет инструкций.</p>
          </div>
          <template v-else v-for="(i, key) in instructions" :key="key">
            <div :class="!i.ciid && UserData.role_id == 2 ? 'bg-red-200' : 'bg-white'"
              class="block w-full px-6 py-4  border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
              <p>{{ key + 1 }}. {{ i.name }}</p>
              <p>{{ i.content }}</p>
            </div>
          </template>
        </div>

        <div class="modal-footer flex flex-row-reverse mx-10">
          <VueButton @click="close()" color="dark">Закрыть</VueButton>
        </div>
      </div>
    </div>
  </Transition>
</template>
