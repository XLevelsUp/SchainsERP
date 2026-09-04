import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { setUnauthorizedHandler } from '@/lib/authSession'
import { useAuthStore } from '@/stores/auth'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// api.ts drops the stored session the moment Passport rejects a token. This
// syncs the store with that and sends the operator to the login screen,
// keeping the page they were on as ?redirect so they land back on it.
// Registered after Pinia and the router so both exist when it first fires.
setUnauthorizedHandler(() => {
  useAuthStore().clear()

  const current = router.currentRoute.value
  if (current.name === 'login') return

  router.replace({ name: 'login', query: { redirect: current.fullPath } }).catch(() => {
    // A concurrent navigation superseded this one. The route guard still
    // keeps an unauthenticated user out of the protected routes.
  })
})

app.mount('#app')
