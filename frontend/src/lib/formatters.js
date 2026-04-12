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
