// Rupee amount in words, Indian numbering (crore / lakh / thousand), for the
// thermal cash receipt: 150000 -> "Rupees One Lakh Fifty Thousand Only".
// Display only — the backend's print payload carries no words field, and the
// receipt's numeric amount is unchanged.

const ONES = [
  '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
  'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
  'Seventeen', 'Eighteen', 'Nineteen',
]
const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety']

function belowHundred(n: number): string {
  if (n < 20) return ONES[n]
  return [TENS[Math.floor(n / 10)], ONES[n % 10]].filter(Boolean).join(' ')
}

function belowThousand(n: number): string {
  const hundreds = Math.floor(n / 100)
  const rest = n % 100
  return [hundreds ? `${ONES[hundreds]} Hundred` : '', rest ? belowHundred(rest) : '']
    .filter(Boolean)
    .join(' ')
}

// Whole number -> words. Anything at or above a crore recurses on the crore
// count, so 1234,00,00,000 reads "One Thousand Two Hundred Thirty Four Crore".
function integerWords(n: number): string {
  if (n === 0) return 'Zero'
  const crore = Math.floor(n / 10_000_000)
  const lakh = Math.floor((n % 10_000_000) / 100_000)
  const thousand = Math.floor((n % 100_000) / 1000)
  const rest = n % 1000
  return [
    crore ? `${integerWords(crore)} Crore` : '',
    lakh ? `${belowHundred(lakh)} Lakh` : '',
    thousand ? `${belowHundred(thousand)} Thousand` : '',
    rest ? belowThousand(rest) : '',
  ]
    .filter(Boolean)
    .join(' ')
}

/**
 * "Rupees One Lakh Fifty Thousand Only", "Rupees Ten and Fifty Paise Only".
 * Returns null for anything that isn't a plain decimal amount, so the caller
 * can leave the line off rather than print something wrong.
 */
export function amountInWords(value: string | number | null | undefined): string | null {
  if (value === null || value === undefined) return null
  // Parsed from the string rather than via floats: cash amounts arrive as
  // decimal:2 strings ("150000.00"), and 0.29 * 100 isn't exactly 29.
  const text = typeof value === 'number' ? (Number.isFinite(value) ? value.toFixed(2) : '') : value.trim()
  const match = /^(-?)(\d+)(?:\.(\d*))?$/.exec(text)
  if (!match) return null

  const rupees = Number(match[2])
  if (!Number.isSafeInteger(rupees)) return null
  const paise = Number(`${match[3] ?? ''}00`.slice(0, 2))
  const sign = match[1] && (rupees > 0 || paise > 0) ? 'Minus ' : ''

  if (rupees === 0 && paise > 0) return `${sign}${belowHundred(paise)} Paise Only`
  const paisePart = paise > 0 ? ` and ${belowHundred(paise)} Paise` : ''
  return `${sign}Rupees ${integerWords(rupees)}${paisePart} Only`
}
