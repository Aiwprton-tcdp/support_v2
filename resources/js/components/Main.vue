<script>
import { provide, ref } from 'vue'
import { toast } from 'vue3-toastify'
// import Emitter from 'tiny-emitter'
import Header from '@temps/Header.vue'

export default {
  components: { Header },
  setup() {
    const UserData = ref(window.user)
    // const UnreadMessagesCount = ref(Number())
    // const UnreadMessages = ref(Array())
    // const emitter = ref(new Emitter())

    // const toast = Vue3Toastify()
    const createToast = (text, type) => {
      switch (type) {
        case 'error':
          toast.error(text)
          break
        case 'success':
          toast.success(text)
          break
        case 'warning':
          toast.warn(text)
          break

        default:
          toast.info(text)
          break
      }
    }

    // const NewMessage = function(data) {
    //   UnreadMessagesCount.value++
    //   this.emitter.emit('NewUnreadMessage', data)
    // }

    provide('UserData', UserData)
    // provide('UnreadMessages', UnreadMessages)
    // provide('UnreadMessagesCount', UnreadMessagesCount)
    provide('createToast', createToast)
    // provide('NewMessage', NewMessage)
    // provide('emitter', emitter)

    return {
      UserData,
      // UnreadMessages,
      // UnreadMessagesCount,
      createToast,
      // NewMessage,
      // emitter,
    }
  },
  mounted() {
    BX24.init(() => {
      // console.log('this.UserData')
      console.log(this.UserData)
      const auth = BX24.getAuth()

      let Parameters = {}
      let sURLVariables = window.location.search.substring(1).split('&')
      for (let i = 0; i < sURLVariables.length; i++) {
        let sParameterName = sURLVariables[i].split('=')
        Parameters[sParameterName[0]] = sParameterName[1]
      }

      // console.log(Parameters)
      // console.log(auth)

      this.ax.post('auth/check', {
        auth: auth,
        sid: Parameters
      }).then(r => {
        if (!r.data.status) return

        localStorage.removeItem('support_access')
        localStorage.setItem('support_access', r.data.data.token)
        this.UserData = r.data.data.user

        this.ax.interceptors.request.use(config => {
          const token = localStorage.getItem('support_access')
          config.headers.Authorization = token ? `Bearer ${token}` : ''
          return config
        })
      }).catch(e => {
        this.createToast(e.response.data.message, 'error')
      })
    })
  }
}
</script>

<template>
  <Header />
  <RouterView />
</template>
