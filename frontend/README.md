# SchainsERP Frontend

Skeleton frontend for the SchainsERP web application: Vue 3 + TypeScript + Vite, Tailwind CSS, Vue Router, and Pinia. Mock data only — no backend is wired up yet.

## Setup

```bash
npm install
npm run dev
```

Open the printed local URL. Sign in with any email/password that pass basic validation (there is no real backend — the auth store just stubs the login).

Other scripts:

```bash
npm run build   # type-check + production build
npm run preview # preview the production build locally
```

## Folder structure

| Folder | Purpose |
| --- | --- |
| `src/assets` | Global styles, including the Tailwind entrypoint and theme tokens |
| `src/components/layout` | App shell chrome: `AppShell`, `AppSidebar`, `AppTopbar`, `AuthLayout` |
| `src/components/ui` | Reusable, typed UI primitives (`BaseButton`, `BaseInput`, `BaseCard`, `DataTable`, `PageHeader`) |
| `src/views` | Route-level page components (`LoginView`, `DashboardView`, `ClientsView`, `PagesView`) |
| `src/router` | Route definitions and the auth navigation guard |
| `src/stores` | Pinia stores (`auth`) |
| `src/types` | Shared TypeScript interfaces |
| `src/lib` | Constants and the typed sidebar navigation config |
