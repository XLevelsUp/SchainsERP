export interface DataTableColumn<T> {
  key: keyof T
  label: string
}
