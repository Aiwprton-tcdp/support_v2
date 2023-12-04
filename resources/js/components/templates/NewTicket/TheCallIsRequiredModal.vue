<script>
import { inject } from 'vue';
import { Button as VueButton } from 'flowbite-vue';

export default {
  name: 'TheCallIsRequiredModal',
  components: { VueButton },
  data() {
    return {
      visible: Boolean(),
    }
  },
  setup() {
    const UserData = inject('UserData')
    return { UserData }
  },
  mounted() { },
  methods: {
    Close() {
      this.$parent.$parent.GoToNext();
      this.visible = false;
    },
  }
}
</script>

<template>
  <Transition name="modal">
    <div v-if="visible" class="modal-mask flex text-gray-900">
      <div class="modal-container flex flex-col gap-6 relative min-h-fit !w-fit">
        <div class="modal-header mx-10">
          <div class="flex items-center justify-center mb-3 text-3xl">
            <b>Внимание !!!</b>
          </div>
        </div>

        <div class="modal--body flex-1 mx-10">
          <div class="flex flex-col gap-4">
            <!-- <div class="flex gap-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-6 h-6 cursor-help text-sky-500">
                <title>Совет</title>
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
              </svg>

              <p class="flex-1">По данной теме можно позвонить менеджеру ТП по внутренней связи на номер
                <b>112</b>.
              </p>
            </div>
            <div class="flex gap-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-6 h-6 cursor-help text-sky-500">
                <title>Совет</title>
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
              </svg>

              <p class="flex-1">Также, при необходимости видеосвязи, можно позвонить по WhatsApp на номер
                <b>+79068562075</b>.
              </p>
            </div>
            <div class="flex gap-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-6 h-6 cursor-help text-yellow-400">
                <title>Предупреждение</title>
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
              </svg>

              <p class="flex-1">Сотрудник ТП в свою очередь для связи с Вами будет пытаться дозвониться на номера,
                указанные у Вас в
                профиле. Имеет смысл проверить свой профиль на наличие номера, на который зарегистрирован аккаунт
                WhatsApp.</p>
            </div> -->



            <div class="flex flex-col ml-3">
              <p>В течение 15 минут <b>после завершения создания тикета</b> с Вами свяжется менеджер Техподдержки.</p>
              <p>Связь может осуществляться через:</p>
              <ul class="!list-disc">
                <li>аудиозвонок по внутренней связи CRM</li>
                <li>аудиозвонок на Ваш рабочий номер (или иные), указанный(е) в профиле</li>
                <li>аудиозвонок/видеозвонок через WhatsApp с номера +79068562075</li>
              </ul>
            </div>
            <div class="flex flex-col ml-3">
              <p>Если Вам не позвонили, свяжитесь с менеджером Техподдержки, <b>ответственным за данный тикет</b>.</p>
              <p>В Вашем случае связь может осуществляться через:</p>
              <ul class="!list-disc">
                <li>аудиозвонок по внутренней связи CRM</li>
                <li>аудиозвонок через данное приложение (из тикета по кнопке)</li>
                <li>аудиозвонок на рабочий номер (или иные), указанный(е) в профиле</li>
                <li>аудиозвонок/видеозвонок через WhatsApp на номер +79068562075</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="modal-footer mx-10">
          <div class="flex flex-row-reverse justify-between">
            <VueButton @click="Close" color="default">
              {{ UserData.gender == 'ж' ? 'Ознакомлена' : 'Ознакомлен' }}
            </VueButton>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
