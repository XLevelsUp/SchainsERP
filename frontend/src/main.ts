import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { setSessionChangedHandler, setUnauthorizedHandler } from '@/lib/authSession'
import { useAuthStore } from '@/stores/auth'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// A superseded navigation rejects here. The route guard still keeps an
// unauthenticated operator out of the protected routes, so swallowing it is
// safe in both directions.
const ignoreNavigationFailure = () => {}

function sendToLogin() {
  const current = router.currentRoute.value
  if (current.name === 'login') return

  router
    .replace({ name: 'login', query: { redirect: current.fullPath } })
    .catch(ignoreNavigationFailure)
}

// api.ts drops the stored session the moment Passport rejects a token. This
// syncs the store with that and sends the operator to the login screen,
// keeping the page they were on as ?redirect so they land back on it.
// Registered after Pinia and the router so both exist when it first fires.
setUnauthorizedHandler(() => {
  useAuthStore().clear()
  sendToLogin()
})

// Another tab signed in or out. authSession has already updated its own
// mirror by this point, so the store only needs bringing in line — and then
// this tab follows the same way it would have if the change had happened
// here: out to /login on sign-out, on to the app on sign-in.
setSessionChangedHandler((session) => {
  useAuthStore().adoptExternalSession(session)

  if (!session) {
    sendToLogin()
    return
  }

  const current = router.currentRoute.value
  if (current.name !== 'login') return

  const redirect = current.query.redirect
  router
    .replace(typeof redirect === 'string' && redirect ? redirect : { name: 'dashboard' })
    .catch(ignoreNavigationFailure)
})

app.mount('#app')
