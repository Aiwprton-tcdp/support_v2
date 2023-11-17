<script>
export default {
  name: 'MessageAttachments',
  props: {
    images: Array(),
    files: Array(),
    domain: String(),
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
  <!-- images -->
  <div v-if="images.length > 0" class="grid gap-2">
    <div v-if="images.length == 2" class="grid grid-cols-2 gap-2">
      <img @click.prevent="ShowModal(images[0])" :src="domain + images[0]?.link" :alt="images[0]?.name"
        class="h-auto rounded-lg cursor-pointer">
      <img @click.prevent="ShowModal(images[1])" :src="domain + images[1]?.link" :alt="images[1]?.name"
        class="h-auto rounded-lg cursor-pointer">
    </div>
    <div v-else>
      <img @click.prevent="ShowModal(images[0])" :src="domain + images[0]?.link" :alt="images[0]?.name"
        class="h-auto rounded-lg cursor-pointer">
    </div>

    <div v-if="images.length > 2" :class="images.length > 4 ? 'grid-cols-4' : 'grid-cols-' + (images.length - 1) + ''"
      class="grid gap-2">
      <template v-for="(image, key) in images" v-bind:key="image">
        <div v-if="key == 4 && images.length > 5" class="flex bg-white bg-opacity-20 items-center h-auto rounded-lg">
          <p class="text-gray-900">+{{ images.length - key }}</p>
        </div>
        <img v-else-if="key > 0 && key < 5" @click.prevent="ShowModal(image)" :src="domain + image?.link"
          :alt="image?.name" class="h-auto rounded-lg cursor-pointer">
      </template>
    </div>
  </div>

  <!-- files -->
  <div v-if="files.length > 0" class="flex flex-col gap-2">
    <template v-for="file in files" v-bind:key="file">
      <a :href="domain + file?.link" target="_blank"
        class="flex gap-2 items-center justify-center py-1 px-2 cursor-pointer transition duration-150 ease-in-out hover:-skew-x-6 hover:scale-[1.02]">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
          class="flex-none w-6 h-6">
          <title>{{ file?.name }}</title>
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
        <p class="flex-initial max-w-80 truncate text-gray-900" :title="file?.name">{{ file?.name }}</p>
      </a>
    </template>
  </div>
</template>
