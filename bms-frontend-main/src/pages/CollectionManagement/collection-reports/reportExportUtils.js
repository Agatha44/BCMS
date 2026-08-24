import { FILTER_LABELS } from './reportCatalog.js';
import { sortReportFilters } from './reportFilterUtils.js';

export function formatReportGeneratorName(user) {
  if (!user) return 'Unknown user';

  const firstName = user.first_name || user.firstName || user.firstname || '';
  const lastName = user.surname || user.last_name || user.lastName || user.lastname || '';
  const fullName = [firstName, lastName].filter(Boolean).join(' ');

  return fullName || user.username || 'Unknown user';
}

const COLLECTION_TYPE_LABELS = {
  cash: 'Cash',
  cashless: 'Cashless',
};

const OPTIONS_LABELS = {
  1: 'Summary',
  2: 'Detail',
};

const TRANS_TYPE_LABELS = {
  CASH: 'Cash',
  CASHLESS: 'Cashless',
};

/**
 * Build human-readable filter lines for PDF/print headers.
 */
export function buildReportFilterDisplayLines(filters, report, context = {}) {
  const {
    shiftOptions = [],
    laneOptions = [],
    bodyTypeOptions = [],
    selectedOperators = {},
  } = context;

  const keys = sortReportFilters(report?.filters ?? []);
  const lines = [];

  keys.forEach((key) => {
    const val = filters?.[key];
    if (val === undefined || val === null || val === '') return;

    const label = FILTER_LABELS[key] || key.replace(/_/g, ' ');
    let display = String(val);

    if (key === 'shift_id') {
      display = shiftOptions.find((o) => String(o.value) === String(val))?.label ?? display;
    } else if (key === 'lane' || key === 'lane_id') {
      display = laneOptions.find((o) => String(o.value) === String(val))?.label ?? display;
    } else if (key === 'body_type' || key === 'body_type_id') {
      display =
        bodyTypeOptions.find((o) => String(o.value) === String(val))?.label ??
        bodyTypeOptions.find((o) => String(o.id) === String(val))?.label ??
        display;
    } else if (key === 'operator' || key === 'user_id') {
      display = selectedOperators[key]?.label ?? selectedOperators.user_id?.label ?? display;
    } else if (key === 'collection_type') {
      display = COLLECTION_TYPE_LABELS[val] ?? display;
    } else if (key === 'trans_type') {
      display = TRANS_TYPE_LABELS[val] ?? display;
    } else if (key === 'options') {
      display = OPTIONS_LABELS[val] ?? display;
    }

    lines.push({ label, value: display });
  });

  return lines;
}
