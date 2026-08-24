export const fmt = (n) => Number(n ?? 0).toLocaleString();

export const mapKpisFromApi = (data) => {
  if (!data) return null;

  return {
    totalPassages: {
      value: fmt(data.total_passages?.value),
      change: data.total_passages?.change ?? '+0%',
    },
    bundlePassages: {
      value: fmt(data.bundle_passages?.value),
      change: data.bundle_passages?.change ?? '+0%',
    },
    prepaymentPassages: {
      value: fmt(data.prepayment_passages?.value),
      change: data.prepayment_passages?.change ?? '+0%',
    },
    cashPassages: {
      value: fmt(data.cash_passages?.value),
      change: data.cash_passages?.change ?? '+0%',
    },
    periodLabel: data.period_label || 'last month',
  };
};

export const mapBodyTypeItems = (response) => {
  if (!response?.success) return null;

  const items = response?.data?.items;
  if (!Array.isArray(items)) return [];

  return items
    .map((row) => ({
      name: String(row.name ?? '').trim(),
      value: Number(row.value) || 0,
    }))
    .filter((row) => row.name && row.value > 0);
};

export const mapTollTrendEntry = (data) => {
  if (!data?.fy_label) return null;

  return {
    label: data.fy_label,
    entry: {
      passages: {
        data: data.passages?.data ?? [],
        headline: data.passages?.headline ?? '0',
        delta: data.passages?.delta ?? '+0%',
      },
      revenue: {
        data: data.revenue?.data ?? [],
        headline: data.revenue?.headline ?? 'TZS 0',
        delta: data.revenue?.delta ?? '+0%',
      },
    },
  };
};

export const mergeBodyTypePeriods = (todayRes, weekRes, monthRes) => {
  const today = mapBodyTypeItems(todayRes);
  const week = mapBodyTypeItems(weekRes);
  const month = mapBodyTypeItems(monthRes);

  if (today === null && week === null && month === null) {
    return null;
  }

  return {
    ...(today !== null ? { today } : {}),
    ...(week !== null ? { week } : {}),
    ...(month !== null ? { month } : {}),
  };
};

export const mapFinancialYearsFromApi = (data) => {
  const years = data?.years;
  if (!Array.isArray(years) || years.length === 0) return null;

  return {
    labels: years.map((y) => y.label),
    startYearByLabel: years.reduce((acc, y) => {
      if (y.label && y.start_year) acc[y.label] = y.start_year;
      return acc;
    }, {}),
  };
};
