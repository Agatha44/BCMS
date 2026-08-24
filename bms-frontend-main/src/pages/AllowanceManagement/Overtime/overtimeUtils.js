// Reusable helper to format amounts with commas (no currency symbol)
export const formatAmount = (amount) =>
  new Intl.NumberFormat('en-TZ', {
    minimumFractionDigits: 0,
  }).format(amount || 0);

