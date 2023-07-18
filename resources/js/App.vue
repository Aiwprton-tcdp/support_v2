<script setup>
import { onMounted } from 'vue'
import { initFlowbite } from 'flowbite'
import Main from '@comps/Main.vue'

onMounted(() => {
  initFlowbite()
})
</script>

<template>
  <Main />
</template>
