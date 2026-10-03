/** Accepts 0241234567, 233241234567 and +233241234567. The server does the real check. */
export function looksLikeGhanaPhone(input: string): boolean {
  const digits = input.replace(/\D+/g, '');

  return (digits.startsWith('233') && digits.length === 12) || (digits.startsWith('0') && digits.length === 10);
}
