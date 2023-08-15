<script>
import { inject } from 'vue'
import { Tabs, Tab } from 'flowbite-vue'

export default {
  name: 'Header',
  components: { Tabs, Tab },
  setup() {
    const ActiveTab = inject('ActiveTab')
    const UserData = inject('UserData')

    return { ActiveTab, UserData }
  },
  methods: {
    ChangeRoute() {
      this.$router.push({ name: this.ActiveTab })
    }
  },
}
</script>

<template>
  <Tabs variant="underline" v-model="ActiveTab" @click:pane="ChangeRoute()">
    <Tab name="tickets" title="Тикеты" />
    <Tab name="archive" title="Архив" />

    <template v-if="UserData.is_admin || [2, 3, 4].includes(UserData?.role_id)">
      <!-- <Tab name="details" title="Детализация" /> -->
      <Tab name="dashboard" title="Статистика" />
      <!-- <Tab name="coupons" title="Купоны" /> -->
      <template v-if="UserData.is_admin || UserData?.role_id != 4">
        <Tab name="reasons" title="Темы" />
        <Tab name="groups" title="Группы" />
        <Tab name="users" title="Пользователи" />
        <!-- <Tab name="roles" title="Роли" /> -->
      </template>
    </template>
  </Tabs>
</template>
