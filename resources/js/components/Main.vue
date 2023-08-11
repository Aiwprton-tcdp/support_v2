<script>
import { provide, ref } from 'vue'
import { toast } from 'vue3-toastify'
import Emitter from 'tiny-emitter'
import Header from '@temps/Header.vue'

export default {
  components: { Header },
  setup() {
    const UserData = ref(window.user)
    const emitter = ref(new Emitter())
    const ActiveTab = ref('tickets')

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

    provide('UserData', UserData)
    provide('createToast', createToast)
    provide('emitter', emitter)
    provide('ActiveTab', ActiveTab)

    return {
      UserData,
      createToast,
      emitter,
      ActiveTab,
    }
  },
  mounted() {
    BX24.init(() => {
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
  },
}
</script>

<template>
  <Header />
  <RouterView />
</template>
