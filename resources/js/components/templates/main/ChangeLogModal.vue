<script>
export default {
  name: 'ChangeLogModal',
  data() {
    return {
      visible: Boolean(),
      changes: Array(),
    }
  },
  mounted() {
    this.changes = [{
      date: '01.11.2023',
      version: '0.2.1',
      additions: [
        'Добавлена инструкция по подготовке и настройке стационарного телефона в чате тикета',
        'Добавлен функционал для автоматического распределения тикетов с менеджера ТП при удалении роли',
        'Добавлена возможность закреплять тикеты в начале списка',
        'Добавлен список изменений (Changelog)',
      ],
      fixations: [
        'Исправлено отображение шаблонных сообщений',
        'Исправлены шрифты',
      ],
      deletions: [],
    }];
    console.log('this.changes');
    console.log(this.changes);
  },
  methods: {
    Close() {
      this.visible = false
    },
  }
}
</script>

<template>
  <Transition name="modal">
    <div v-if="visible" @click.self="Close" class="modal-mask">
      <div
        class="modal-container h-[80vh] overflow-y-auto overscroll-none scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2 scrolling-touch">
        <div class="modal-header mx-14">
          <div class="flex items-center mb-3 text-3xl">
            <b>Changelog</b>
          </div>
        </div>

        <div class="modal--body mx-14">
          <div class="flex flex-col gap-8">
            <div v-for="c in changes" v-bind:key="c">
              <b class="mt-3">[v{{ c.version }}] - {{ c.date }}</b>

              <template v-if="c.additions.length > 0">
                <p class="font-semibold underline">Нововведения:</p>
                <ul class="list-disc">
                  <li v-for="add in c.additions" v-bind:key="add">{{ add }}</li>
                </ul>
              </template>

              <template v-if="c.fixations.length > 0">
                <p class="font-semibold underline mt-3">Изменения:</p>
                <ul class="list-disc">
                  <li v-for="fix in c.fixations" v-bind:key="fix">{{ fix }}</li>
                </ul>
              </template>

              <template v-if="c.deletions.length > 0">
                <p class="font-semibold underline mt-3">Удаления:</p>
                <ul class="list-disc">
                  <li v-for="del in c.deletions" v-bind:key="del">{{ del }}</li>
                </ul>
              </template>
            </div>
          </div>
        </div>

        <!-- <div class="modal-footer mx-14">
          <div class="flex flex-row-reverse justify-between">
            <VueButton @click="Close" color="alternative">
              Закрыть
            </VueButton>
          </div>
        </div> -->
      </div>
    </div>
  </Transition>
</template>

<style>
.scrollbar-thumb-blue::-webkit-scrollbar-thumb {
  background-color: #d5d8df;
}
</style>
