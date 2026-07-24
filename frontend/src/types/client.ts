export type ClientStatus = 'active' | 'inactive' | 'pending'

export interface Client {
  id: string
  name: string
  email: string
  status: ClientStatus
  createdAt: string
}
