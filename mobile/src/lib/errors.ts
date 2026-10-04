/** The sentence to show a person when something throws. The API layer already makes errors readable. */
export function messageOf(error: unknown, fallback = 'Something went wrong. Try again.'): string {
  return error instanceof Error && error.message ? error.message : fallback;
}
