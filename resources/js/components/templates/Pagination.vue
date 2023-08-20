<script>
export default {
  name: 'PaginationComponent',
  props: ['invoke'],
  data() {
    return {
      page: Number(),
      last_page: Number(),
      available_pages: Array(),
      certain_page: Number(1),
      per_page_internal: Number(10),
      results_info: String(),
      is_not_necessary: Boolean(),
    }
  },
  methods: {
    PrepareAvailablePages(count = 5) {
      if (this.last_page < 5) count = this.last_page

      this.is_not_necessary = count < 2
      if (this.is_not_necessary) return

      const sum_without_current = count - 1
      const left = this.page < count ? 1 : this.page - sum_without_current
      const right = this.page + sum_without_current < this.last_page
        ? this.page + sum_without_current
        : this.last_page

      let start = left

      if (this.last_page < 5) {
        start = 1
      } else if (this.page - left >= 2 && right - this.page >= 2) {
        start = this.page - 2
      } else if (this.last_page - this.page <= 2) {
        start = right - 4
      }

      this.available_pages = Array.from(Array(count), (_, i) => start + i)
    },
    Emit(p = this.page) {
      this.$emit('invoke', p, this.per_page_internal)
    },
    Certain(page_number) {
      if (page_number < 1) page_number = 1
      else if (page_number > this.last_page) page_number = this.last_page

      this.page = page_number
      this.Emit()
    },
    Previous() {
      if (this.page <= 1) return
      this.Emit(--this.page)
    },
    Next() {
      if (this.page >= this.last_page) return
      this.Emit(++this.page)
    },
    First() {
      this.Certain(1)
    },
    Last() {
      this.Certain(this.last_page)
    },
  },
}
</script>

<template>
  <div class="flex flex-col md:flex-row justify-between items-center">
    <label>{{ this.results_info }}</label>

    <div :class="{ invisible: is_not_necessary }" class="inline-flex">
      <input v-model.number="certain_page" type="number" @keyup.enter="Certain(certain_page)"
        class="w-16 p-2 text-sm rounded-lg bg-gray-50 dark:bg-gray-700 dark:placeholder-gray-400 dark:text-white" />

      <button @click="Certain(certain_page)"
        class="px-4 py-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
        Перейти
      </button>
    </div>

    <nav :class="{ invisible: is_not_necessary }">
      <ul class="inline-flex space-x-1 text-sm text-gray-500 bg-white">
        <li v-if="this.page > 1">
          <button @click="Previous()"
            class="inline-flex px-4 py-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
            <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
              xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd"
                d="M7.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z"
                clip-rule="evenodd"></path>
            </svg>
          </button>
        </li>
        <li v-if="!this.available_pages.includes(1)">
          <button @click="First()"
            class="inline-flex px-4 py-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
            1
          </button>
        </li>
        <li v-if="!this.available_pages.includes(1) && !this.available_pages.includes(2)">
          <label class="px-3 py-2 inline-block align-center text-gray-900 dark:bg-gray-800 dark:text-gray-400">
            ...
          </label>
        </li>

        <li v-for="p in available_pages" v-bind:key="p">
          <label v-if="p == this.page"
            class="px-3 py-2 inline-block align-center text-gray-900 dark:bg-gray-800 dark:text-gray-400">{{ p }}</label>
          <button v-else @click="Certain(p)"
            class="px-3 py-2 text-gray-500 border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
            {{ p }}
          </button>
        </li>

        <li v-if="!this.available_pages.includes(this.last_page) && !this.available_pages.includes(this.last_page - 1)">
          <label class="px-3 py-2 inline-block align-center text-gray-900 dark:bg-gray-800 dark:text-gray-400">
            ...
          </label>
        </li>
        <li v-if="!this.available_pages.includes(this.last_page)">
          <button @click="Last()"
            class="inline-flex px-4 py-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
            {{ this.last_page }}
          </button>
        </li>
        <li v-if="this.page < this.last_page">
          <button @click="Next()"
            class="inline-flex px-4 py-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
            <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
              xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd"
                d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z"
                clip-rule="evenodd"></path>
            </svg>
          </button>
        </li>
      </ul>
    </nav>

    <!-- <label for="per_page_select" class="sr-only">На странице</label> -->
    <select v-model="per_page_internal" v-on:change="First()"
      class="block py-2.5 px-0 w-16 text-sm text-gray-500 bg-transparent border-0 border-b-2 border-gray-200 appearance-none dark:text-gray-400 dark:border-gray-700 focus:outline-none focus:ring-0 focus:border-gray-200 peer">
      <option value="10" selected>10</option>
      <option value="50">50</option>
      <option value="100">100</option>
    </select>
  </div>
</template>