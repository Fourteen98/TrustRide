const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const LONG_DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** "2026-10-08" as a local date, with no timezone shifting. */
function parse(date: string): Date {
  const [year, month, day] = date.split('-').map(Number);

  return new Date(year, month - 1, day);
}

/** "Thu 8 Oct" */
export function shortDate(date: string): string {
  const d = parse(date);

  return `${DAYS[d.getDay()]} ${d.getDate()} ${MONTHS[d.getMonth()]}`;
}

/** "Thursday 8 October" style, short month */
export function longDate(date: string): string {
  const d = parse(date);

  return `${LONG_DAYS[d.getDay()]} ${d.getDate()} ${MONTHS[d.getMonth()]}`;
}

/** "06:30" becomes "6:30 am" */
export function clock(time: string | null | undefined): string {
  if (!time) {
    return '';
  }

  const [hours, minutes] = time.split(':').map(Number);
  const suffix = hours < 12 ? 'am' : 'pm';
  const hour = hours % 12 === 0 ? 12 : hours % 12;

  return `${hour}:${String(minutes).padStart(2, '0')} ${suffix}`;
}

/** An ISO moment as "Wed 7 Oct, 6:30 pm" in the phone's own time. */
export function moment(iso: string): string {
  const d = new Date(iso);
  const time = clock(`${d.getHours()}:${d.getMinutes()}`);

  return `${DAYS[d.getDay()]} ${d.getDate()} ${MONTHS[d.getMonth()]}, ${time}`;
}

/** ISO weekdays, 1 for Monday to 7 for Sunday, as "Mon to Fri" or "Mon, Wed, Fri". */
export function weekdays(days: number[]): string {
  const sorted = [...days].sort((a, b) => a - b);
  const names = sorted.map((day) => DAYS[day % 7]);

  if (sorted.length === 7) {
    return 'Every day';
  }

  const consecutive = sorted.every((day, index) => index === 0 || day === sorted[index - 1] + 1);

  return consecutive && sorted.length > 2 ? `${names[0]} to ${names[names.length - 1]}` : names.join(', ');
}
