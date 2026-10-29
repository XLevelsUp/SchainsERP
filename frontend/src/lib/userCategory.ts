import type { CategoryName } from '@/types'

/*
| user_details.category_name — which balance a party is kept in: GRAMS,
| PURITY or BOTH. The backend calculates and returns both figures for every
| user and never reads this field itself, so it is purely a display rule:
| wherever a selected party's own balance is shown, the measure they are not
| kept in reads 0. A missing category shows both.
|
| Display only — never apply it to entry fields, payloads or the OB/CB maths.
*/

// No default anywhere: the head picks one when creating a user.
export const userCategoryOptions: { value: CategoryName; label: string }[] = [
  { value: 'GRAMS', label: 'Grams' },
  { value: 'PURITY', label: 'Purity' },
  { value: 'BOTH', label: 'Both' },
]

export function categoryShowsGrams(category?: CategoryName | null): boolean {
  return category !== 'PURITY'
}

export function categoryShowsPurity(category?: CategoryName | null): boolean {
  return category !== 'GRAMS'
}
