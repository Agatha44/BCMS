export const STATUS_TAG_CLASS = '!m-0 px-2.5 py-0.5 text-xs font-medium capitalize';

const COLOR_BY_STATUS = {
  prepared: 'default',
  initiated: 'blue',
  examined: 'cyan',
  verified: 'geekblue',
  approved: 'gold',
  posted: 'green',
  processing: 'orange',
  posting: 'orange',
  rejected: 'red',
  returned: 'orange',
  pending: 'gold',
  assigned: 'gold',
  actioned: 'green',
};

const formatStatusText = (value) => {
  const raw = String(value || '').trim();
  if (!raw) return '—';
  return raw.charAt(0).toUpperCase() + raw.slice(1).toLowerCase();
};

export const payrollRunStatusTag = (value) => {
  const v = String(value || '').toLowerCase().trim();
  if (!v) return { color: 'default', text: '—' };

  if (COLOR_BY_STATUS[v]) {
    return { color: COLOR_BY_STATUS[v], text: formatStatusText(value) };
  }

  if (v.includes('reject')) return { color: 'red', text: formatStatusText(value) };
  if (v.includes('return')) return { color: 'orange', text: formatStatusText(value) };
  if (v.includes('pending') || v.includes('assigned')) {
    return { color: 'gold', text: formatStatusText(value) };
  }

  return { color: 'blue', text: formatStatusText(value) };
};
