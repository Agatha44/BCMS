import dayjs from 'dayjs';
import { formatCount, formatPayrollMoney } from '../../../common/utils/numberFormat.js';
import { getReportColumns, getSummaryFields } from './payrollReportConfig.js';

export const unwrapPayload = (payload) => payload?.data ?? payload ?? {};

export const parsePeriod = (value) => {
  const d = dayjs(value, ['YYYY-MM-DD', 'YYYY-MM'], true);
  return d.isValid() ? d : null;
};

const parseNamedFilter = (filters = [], name) => {
  const filter = filters?.find((f) => f?.name === name);
  if (!filter?.options?.length) return null;
  return {
    label: filter.label ?? name,
    options: filter.options.map((o) => ({
      value: String(o?.value ?? ''),
      label: o?.label ?? '',
    })),
  };
};

export const mapReportTypes = (payload) => {
  const root = unwrapPayload(payload);
  const list = Array.isArray(root) ? root : root.types ?? root.report_types ?? root.items ?? [];

  return list
    .map((item) => {
      const id = String(item?.id ?? item?.key ?? item?.report_type ?? item?.code ?? '').trim();
      if (!id) return null;
      return {
        id,
        label: item?.label ?? item?.name ?? item?.title ?? item?.report_name ?? id,
        sortOrder: Number(item?.sort_order ?? 0),
        bankFilter: parseNamedFilter(item.filters, 'bank'),
      };
    })
    .filter(Boolean)
    .sort((a, b) => a.sortOrder - b.sortOrder);
};

export const buildReportPayload = (filters, { search = '', page = 1, pageSize = 15, forDownload = false } = {}) => {
  const payload = {
    report_type: filters.reportType,
    month: parsePeriod(filters.period)?.format('YYYY-MM') ?? '',
    ...(filters.bank ? { bank: filters.bank } : {}),
    ...(filters.loan_type_id ? { loan_type_id: filters.loan_type_id } : {}),
  };

  if (forDownload) return payload;

  return {
    ...payload,
    ...(search && { search }),
    page,
    per_page: pageSize,
  };
};

export const filtersFromForm = (values) => ({
  reportType: values.reportType,
  period: values.period ?? '',
  ...(values.bank ? { bank: String(values.bank) } : {}),
  ...(values.loan_type_id ? { loan_type_id: String(values.loan_type_id) } : {}),
});

const SUMMARY_FORMATTERS = {
  count: (value, rows) => formatCount(value ?? rows.length),
  money: (value) => formatPayrollMoney(value),
  text: (value) => value ?? 'N/A',
};

const resolveLoanTypeLabel = (summary) => {
  const loanType = summary?.loan_type;
  if (loanType && typeof loanType === 'object') {
    return loanType.loan_type ?? loanType.loan_name ?? 'N/A';
  }
  return 'All Loan Types';
};

const summaryValue = (summary, field) => {
  if (field.key === 'loan_type_label') return resolveLoanTypeLabel(summary);
  return summary[field.key];
};

export const buildSummaryCards = (summary, rows, reportType) => {
  if (!summary) return [];
  if (summary.cards?.length) return summary.cards;

  return getSummaryFields(reportType)
    .map((field) => {
      const raw = summaryValue(summary, field);
      if (raw == null && !field.alwaysShow) return null;
      const format = SUMMARY_FORMATTERS[field.format];
      const value = field.useRowsFallback ? format(raw, rows) : format(raw);
      return { label: field.label, value };
    })
    .filter(Boolean);
};

export const buildTableColumns = (reportType, { current = 1, pageSize = 15 } = {}) => [
  {
    title: 'S/N',
    key: 'sn',
    width: 70,
    align: 'center',
    render: (_, __, index) => (current - 1) * pageSize + index + 1,
  },
  ...getReportColumns(reportType).map((col) => {
    const isMoney = col.format === 'currency';
    return {
      title: col.label,
      dataIndex: col.key,
      key: col.key,
      ellipsis: !isMoney,
      align: isMoney ? 'right' : undefined,
      money: isMoney || undefined,
    };
  }),
];

export const reportRowKey = (row) =>
  String(
    row?.id
    ?? `${row?.pf_number ?? row?.employee_id ?? row?.employee_name ?? 'row'}-${row?.loan_name ?? row?.loan_type_id ?? row?.net_pay ?? row?.gross_pay ?? ''}`
  );
