<script>
export default {
  name: 'MessageAttachments',
  props: {
    files: Array(),
    message_id: Number(),
  },
  methods: {
    ShowModal(data) {
      this.$parent.CurrentAttachmentId = data.id
      this.$parent.showModal = true
    }
  }
}
</script>

<template>
  <div class="grid gap-2">
    <div v-if="files.length == 2" class="grid grid-cols-2 gap-2">
      <img @click.prevent="ShowModal(files[0])" :src="files[0]?.link" :alt="files[0]?.name"
        class="h-auto rounded-lg cursor-pointer">
      <img @click.prevent="ShowModal(files[1])" :src="files[1]?.link" :alt="files[1]?.name"
        class="h-auto rounded-lg cursor-pointer">
    </div>
    <div v-else>
      <img @click.prevent="ShowModal(files[0])" :src="files[0]?.link" :alt="files[0]?.name"
        class="h-auto rounded-lg cursor-pointer">
    </div>

    <div v-if="files.length > 2" :class="files.length > 4 ? 'grid-cols-4' : 'grid-cols-' + (files.length - 1) + ''"
      class="grid gap-2">
      <template v-for="(file, key) in files" v-bind:key="file">
        <div v-if="key == 4 && files.length > 5" class="flex bg-white bg-opacity-20 items-center h-auto rounded-lg">
          <p class="text-gray-900">+{{ files.length - key }}</p>
        </div>
        <img v-else-if="key > 0 && key < 5" @click.prevent="ShowModal(file)" :src="file?.link" :alt="file?.name"
          class="h-auto rounded-lg cursor-pointer">
      </template>
    </div>
  </div>
</template>
