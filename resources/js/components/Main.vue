<script>
import { provide, ref, defineAsyncComponent } from 'vue';
import { toast } from 'vue3-toastify';
import Emitter from 'tiny-emitter';

import HeaderComponent from '@temps/Header.vue';

const LoginModal = defineAsyncComponent(() => import('@temps/main/LoginModal.vue'));
const ChangeLogModal = defineAsyncComponent(() => import('@temps/main/ChangeLogModal.vue'));

export default {
  components: {
    HeaderComponent,
    LoginModal,
    ChangeLogModal
  },
  setup() {
    const UserData = ref(window.user);
    const emitter = ref(new Emitter());
    const ActiveTab = ref('tickets');

    const createToast = (text, type) => {
      switch (type) {
        case 'error':
          toast.error(text);
          break;
        case 'success':
          toast.success(text);
          break;
        case 'warning':
          toast.warn(text);
          break;

        default:
          toast.info(text);
          break;
      }
    };

    provide('UserData', UserData);
    provide('createToast', createToast);
    provide('emitter', emitter);
    provide('ActiveTab', ActiveTab);

    return {
      UserData,
      createToast,
      emitter,
      ActiveTab,
    };
  },
  mounted() {
    setTimeout(() => this.authInit(), 1000);

    document.addEventListener('keydown', e => {
      if (e.key !== 'Escape') return;

      this.$refs.ChangeLog.visible = false;
    });
  },
  methods: {
    authInit() {
      try {
        BX24.init(() => {
          const auth = BX24.getAuth();
          this.authCheck(auth);
        });
      } catch (e) {
        this.authCheck();
      }
    },
    authCheck(auth = []) {
      let Parameters = {};
      let sURLVariables = window.location.search.substring(1).split('&');
      for (let i = 0; i < sURLVariables.length; i++) {
        let sParameterName = sURLVariables[i].split('=');
        Parameters[sParameterName[0]] = sParameterName[1];
      }
      if (Parameters?.id != null) {
        window.ticket_id = Parameters.id;
      }

      const accessToken = localStorage.getItem('support_access');
      if (accessToken == null && Object.keys(Parameters).length < 2) {
        this.goToLogin();
        return;
      }

      this.ax.post('auth/check', {
        auth: auth,
        sid: Parameters,
        token: accessToken
      }).then(r => {
        if (!r.data.status) {
          this.goToLogin();
          return;
        }

        if (r.data.data?.token != null) {
          localStorage.removeItem('support_access');
          localStorage.setItem('support_access', r.data.data.token);
        }
        this.UserData = r.data.data.user;

        this.ax.interceptors.request.use(config => {
          const token = localStorage.getItem('support_access');
          config.headers.Authorization = token ? `Bearer ${token}` : '';
          return config;
        })
      }).catch(e => {
        console.log(e?.response?.data?.message ?? e.message);
        // this.createToast(e.response.data.message, 'error');
      });
    },
    goToLogin() {
      this.$refs.Login.visible = true;
    },
    ShowModal() {
      this.$refs.ChangeLog.visible = true;
    },
  }
}
</script>

<template>
  <HeaderComponent />
  <RouterView />

  <div v-if="UserData.is_admin || UserData.role_id == 2" class="fixed bottom-2 right-2 opacity-70">
    <div @click="ShowModal()"
      class="flex items-center justify-center w-8 h-8 p-1 rounded-full cursor-pointer ring-2 ring-gray-300 dark:ring-gray-500 hover:bg-gray-200">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
        class="w-5 h-5 transition duration-150 ease-in-out hover:-translate-y-0.4 hover:scale-110">
        <title>История изменений</title>
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M6 6.878V6a2.25 2.25 0 012.25-2.25h7.5A2.25 2.25 0 0118 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 004.5 9v.878m13.5-3A2.25 2.25 0 0119.5 9v.878m0 0a2.246 2.246 0 00-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0121 12v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6c0-.98.626-1.813 1.5-2.122" />
      </svg>
    </div>
  </div>

  <Teleport to="body">
    <LoginModal ref="Login" />
  </Teleport>

  <Teleport to="body">
    <ChangeLogModal ref="ChangeLog" />
  </Teleport>
</template>
