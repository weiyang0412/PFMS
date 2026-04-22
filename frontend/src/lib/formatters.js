export const formatCurrencyMYR = (value, options = {}) => {
  const numeric = Number(value ?? 0);
  const safe = Number.isFinite(numeric) ? numeric : 0;
  return new Intl.NumberFormat('en-MY', {
    style: 'currency',
    currency: 'MYR',
    minimumFractionDigits: options.minimumFractionDigits ?? 2,
    maximumFractionDigits: options.maximumFractionDigits ?? 2,
  }).format(safe);
};

const MALAYSIA_TIMEZONE = 'Asia/Kuala_Lumpur';

const toYmdInTimeZone = (value, timeZone = MALAYSIA_TIMEZONE) => {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(value);
  const year = parts.find((part) => part.type === 'year')?.value;
  const month = parts.find((part) => part.type === 'month')?.value;
  const day = parts.find((part) => part.type === 'day')?.value;
  if (!year || !month || !day) return '';
  return `${year}-${month}-${day}`;
};

export const malaysiaTodayYmd = () => toYmdInTimeZone(new Date());
export const malaysiaCurrentMonthYm = () => malaysiaTodayYmd().slice(0, 7);

export const formatYmdDate = (value, options = {}) => {
  const fallback = options.fallback ?? '—';
  if (typeof value !== 'string' || !value) return fallback;
  const [year, month, day] = value.split('-').map(Number);
  if (!year || !month || !day) return fallback;
  const parsed = new Date(year, month - 1, day);
  if (Number.isNaN(parsed.getTime())) return fallback;
  return new Intl.DateTimeFormat(options.locale ?? 'en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(parsed);
};
