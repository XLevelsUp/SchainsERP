import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    // Which session a route belongs to: the operational app (default) or the
    // admin console. Each has its own sign-in, token and layout. Set on the
    // parent record; children inherit it through the merged meta.
    realm?: 'app' | 'admin'
    requiresAuth?: boolean
    // Sign-in screens: someone already signed in to this realm is sent on
    // to its home page instead.
    guestOnly?: boolean
  }
}
