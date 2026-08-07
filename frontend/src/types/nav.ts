import type { Component } from 'vue'

export interface NavLink {
  type: 'link'
  label: string
  to: string
  icon: Component
}

export interface NavGroup {
  type: 'group'
  label: string
  icon: Component
  children: NavLink[]
}

export type NavItem = NavLink | NavGroup
