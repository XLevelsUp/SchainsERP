export interface DataTableColumn<T> {
  key: keyof T
  label: string
  // Opt-in; omitted means left, as every table has always rendered. 'right'
  // is for numeric columns (grams, amounts) so figures line up on the
  // decimal side, header included.
  align?: 'left' | 'right'
}
