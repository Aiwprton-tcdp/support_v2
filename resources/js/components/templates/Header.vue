<script>
import { useRoute, useRouter } from 'vue-router'
import { watchEffect, ref, onMounted, watch, computed } from 'vue'
import { Tabs, Tab } from 'flowbite-vue'

export default {
  components: { Tabs, Tab },
  // data() {
  //   return {
  //     ActiveTab: String(),
  //   }
  // },
  setup() {
    const route = useRoute()
    const router = useRouter()
    const path = computed(() => route.name)
    let ActiveTab = ref()
    // ActiveTab = path

    // watchEffect(() => {
    //   console.log('watchEffect')
    //   console.log(ActiveTab.value)
    // })

    // onMounted(() => {
    //   router.isReady().then(() => {
    //     ActiveTab = route?.name// ?? 'tickets'
    //     console.log('onMounted')
    //     console.log(ActiveTab)
    //     // this.$router.push({ name: this.ActiveTab })
    //   })
    // })

    return { ActiveTab }
  },
  methods: {
    ChangeRoute() {
      // console.log('ChangeRoute')
      this.$router.push({ name: this.ActiveTab })
    }
  },
  // watch: {
  //   ActiveTab: function(data) {
  //     console.log('ActiveTab')
  //     console.log(data)
  //     this.$router.push({ name: data })
  //   }
  // }
}
</script>

<template>
  <Tabs variant="underline" v-model="ActiveTab" @click:pane="ChangeRoute()">
    <Tab name="tickets" title="Тикеты" />
    <Tab name="details" title="Детализация" />
    <Tab name="dashboard" title="Статистика" />
    <Tab name="coupons" title="Купоны" />
    <Tab name="reasons" title="Темы" />
    <Tab name="users" title="Пользователи" />
    <Tab name="groups" title="Группы" />
    <!-- <Tab name="roles" title="Роли" /> -->
  </Tabs>
</template>
