export const REPORT_TYPE_ALIASES = {
  net_pay: 'netpay',
  'net-pay': 'netpay',
  payee: 'paye',
};

export const LOAN_TYPE_ALL_OPTION = { value: '', label: 'All' };

export const REPORT_COLUMNS_CONFIG = {
  netpay: [
    { key: 'pf_number', label: 'PF Number' },
    { key: 'employee_name', label: 'Employee' },
    { key: 'bank_name', label: 'Bank' },
    { key: 'account_number', label: 'Account Number' },
    { key: 'net_pay', label: 'Net Pay', format: 'currency' },
  ],
  paye: [
    { key: 'pf_number', label: 'PF Number' },
    { key: 'employee_name', label: 'Employee' },
    { key: 'gross_pay', label: 'Gross Pay', format: 'currency' },
    { key: 'paye', label: 'PAYE', format: 'currency' },
  ],
  psssf: [
    { key: 'pf_number', label: 'PF Number' },
    { key: 'employee_name', label: 'Employee' },
    { key: 'psssf_contribution', label: 'Employee Contribution', format: 'currency' },
    { key: 'psssf_employer_contribution', label: 'Employer Contribution', format: 'currency' },
  ],
  heslb: [
    { key: 'pf_number', label: 'PF Number' },
    { key: 'employee_name', label: 'Employee' },
    { key: 'heslb', label: 'HESLB', format: 'currency' },
  ],
  other: [
    { key: 'pf_number', label: 'PF Number' },
    { key: 'employee_name', label: 'Employee' },
    { key: 'total', label: 'Total', format: 'currency' },
  ],
  loans: [
    { key: 'pf_number', label: 'PF Number' },
    { key: 'employee_name', label: 'Employee' },
    { key: 'loan_name', label: 'Loan Type' },
    { key: 'principal_amount', label: 'Principal', format: 'currency' },
    { key: 'interest_amount', label: 'Interest', format: 'currency' },
    { key: 'total_repayment', label: 'Total Repayment', format: 'currency' },
  ],
};

const DEFAULT_SUMMARY_FIELDS = [
  { key: 'employee_count', label: 'Employees', format: 'count', useRowsFallback: true },
  { key: 'total_net_pay', label: 'Total net pay', format: 'money' },
  { key: 'total_gross', label: 'Total gross pay', format: 'money' },
  { key: 'total_deductions', label: 'Total deductions', format: 'money' },
  { key: 'total_paye', label: 'Total PAYE', format: 'money' },
  { key: 'total_psssf', label: 'Total PSSSF', format: 'money' },
  { key: 'total_heslb', label: 'Total HESLB', format: 'money' },
];

export const REPORT_SUMMARY_CONFIG = {
  psssf: [
    { key: 'employee_count', label: 'Employees', format: 'count' },
    { key: 'total_employee_contribution', label: 'Employee Contribution', format: 'money' },
    { key: 'total_employer_contribution', label: 'Employer Contribution', format: 'money' },
    { key: 'total_psssf', label: 'Total PSSSF', format: 'money' },
  ],
  loans: [
    { key: 'employee_count', label: 'Employees', format: 'count' },
    { key: 'loan_type_label', label: 'Loan Type', format: 'text', alwaysShow: true },
    { key: 'total_repayment', label: 'Total Repayment', format: 'money' },
  ],
};

export const REPORT_TABLE_CONFIG = {
  netpay: { tableLayout: 'fixed', scroll: undefined },
  default: { tableLayout: undefined, scroll: { x: 'max-content' } },
};

export const resolveReportTypeKey = (reportType) => REPORT_TYPE_ALIASES[reportType] ?? reportType;

export const getReportColumns = (reportType) =>
  REPORT_COLUMNS_CONFIG[resolveReportTypeKey(reportType)] ?? [];

export const getReportTableProps = (reportType) =>
  REPORT_TABLE_CONFIG[resolveReportTypeKey(reportType)] ?? REPORT_TABLE_CONFIG.default;

export const getSummaryFields = (reportType) =>
  REPORT_SUMMARY_CONFIG[resolveReportTypeKey(reportType)] ?? DEFAULT_SUMMARY_FIELDS;

export const buildLoanTypeSelectOptions = (loanTypes) => [
  LOAN_TYPE_ALL_OPTION,
  ...loanTypes.map((o) => ({ value: o.value, label: o.label })),
];
