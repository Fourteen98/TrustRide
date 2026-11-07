/** Pesewas as "GHS 60" or "GHS 62.50", the same way the server writes amounts. */
export function cedis(pesewas: number): string {
  const whole = pesewas % 100 === 0;

  return `GHS ${(pesewas / 100).toLocaleString('en-GH', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: whole ? 0 : 2 })}`;
}
