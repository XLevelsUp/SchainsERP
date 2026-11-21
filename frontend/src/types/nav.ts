import type { Component } from 'vue'
import type { AppPermission } from './auth'

export interface NavLink {
  type: 'link'
  label: string
  to: string
  icon: Component
  // Shown only to a user with this permission (lib/appPermissions.ts).
  permission?: AppPermission
}

export interface NavGroup {
  type: 'group'
  label: string
  icon: Component
  children: NavLink[]
}

export type NavItem = NavLink | NavGroup
