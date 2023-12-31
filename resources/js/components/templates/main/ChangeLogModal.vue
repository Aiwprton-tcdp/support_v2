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
    this.changes = [
      {
        date: '01.11.2023',
        version: '0.2.1',
        additions: [
          'инструкция по подготовке и настройке стационарного телефона в чате тикета',
          'функционал для автоматического распределения тикетов с менеджера ТП при удалении роли',
          'возможность закреплять тикеты в начале списка',
          'список изменений (Changelog)',
        ],
        fixations: [
          'отображение шаблонных сообщений',
          'шрифты',
        ],
        deletions: [],
      }, {
        date: '09.11.2023',
        version: '0.2.2',
        additions: [
          'возможность прикреплять изображения из буфера',
        ],
        fixations: [
          'отображение времени решения тикетов',
          'поведение при смене темы или ответственного',
          'отображение диаграмм по упавшим и решённым тикетам по менеджерам и темам',
          'отображение изображений в модальном окне',
        ],
        deletions: [],
      }, {
        date: '17.11.2023',
        version: '0.2.3',
        additions: [
          'возможность отправлять в сообщениях документы',
          'возможность авторизации вне CRM',
          'индикаторы активности менеджеров (в разделе "Пользователи")',
          'возможность редактировать темы в завершённых тикетах',
          'отображение инструкции при создании обращения',
          'возможность фильтровать активные тикеты по теме и автоматически применять этот фильтр из раздела "Активные тикеты"',
        ],
        fixations: [
          'отображение данных в диаграмме "Общее количество тикетов в разрезе сотрудника"',
        ],
        deletions: [],
      }, {
        date: '04.12.2023',
        version: '0.3.0',
        additions: [
          'возможность скрывать/раскрывать блок информации о тикете',
          'таблица отображения количества созданных тикетов по подразделениям',
          'возможность закреплять шаблонные сообщения',
          'параметр "Через звонок" для тем и функционал для таких тем',
          'возможность совершать звонки',
          'возможность указывать тип проблемы в тикете',
        ],
        fixations: [
          'отображение шаблонных сообщений',
          'отображение идентификаторов пользователей из разных CRM',
          'отображение таблиц в статистике',
        ],
        deletions: [],
      }, {
        date: '06.12.2023',
        version: '0.3.1',
        additions: [
          'форма для управления темами и связанными инструкциями',
          'окно информирования о необходимости решения тикета через аудио- или видеосвязь',
          'новый формат распределения тикетов',
        ],
        fixations: [
          'редактирование тем',
          'загрузка тикета по абсолютной ссылке',
          'управление типами проблем в завершённых тикетах',
        ],
        deletions: [],
      }
    ];
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
    <div v-if="visible" @click.self="Close" class="modal-mask flex text-gray-900">
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
                <p class="font-semibold underline">Добавлено:</p>
                <ul class="list-disc">
                  <li v-for="add in c.additions" v-bind:key="add">{{ add }}</li>
                </ul>
              </template>

              <template v-if="c.fixations.length > 0">
                <p class="font-semibold underline mt-3">Исправлено:</p>
                <ul class="list-disc">
                  <li v-for="fix in c.fixations" v-bind:key="fix">{{ fix }}</li>
                </ul>
              </template>

              <template v-if="c.deletions.length > 0">
                <p class="font-semibold underline mt-3">Удалено:</p>
                <ul class="list-disc">
                  <li v-for="del in c.deletions" v-bind:key="del">{{ del }}</li>
                </ul>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style>
.scrollbar-thumb-blue::-webkit-scrollbar-thumb {
  background-color: #d5d8df;
}
</style>
