/// <reference types="vite/client" />

interface ImportMetaEnv {
  /**
   * Absolute base URL for the Laravel API, including the `/api/v1` prefix —
   * e.g. https://api.lensnstories.com/api/v1. Leave unset in local dev, where
   * Vite's `/api` proxy makes the relative default correct.
   */
  readonly VITE_API_BASE_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<Record<string, never>, Record<string, never>, unknown>
  export default component
}