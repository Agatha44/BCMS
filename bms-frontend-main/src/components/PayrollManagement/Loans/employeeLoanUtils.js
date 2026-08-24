import dayjs from 'dayjs';
import { round2 } from '../../../common/utils/numberFormat.js';

export const parseActiveLoanTypeOptions = (data) =>
  (Array.isArray(data) ? data : [])
    .filter((t) => t?.value != null)
    .map((t) => ({
      value: t.value,
      label: String(t.label ?? ''),
    }));

export const isEmployeeLoanActive = (record) =>
  record?.is_active === 1 || record?.is_active === true || record?.is_active === '1';

export const getEmployeeLoanId = (record) =>
  record?.employee_loan_id ?? record?.id ?? null;

const toDayjsOrNull = (v) => {
  if (!v) return null;
  const d = dayjs(v);
  return d.isValid() ? d : null;
};

const numForm = (v) => {
  if (v === null || v === undefined || v === '') return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
};

const interestEnabled = (loanType) =>
  Number(loanType?.has_interest) === 1 || loanType?.has_interest === true || loanType?.has_interest === '1';

const calcFlatInterest = (loanAmount, interestPct) => round2((loanAmount * interestPct) / 100);

const calcLoanAmounts = ({ loanAmount, repaymentMonths, loanType }) => {
  const principal = parseFloat(loanAmount) || 0;
  const months = parseInt(repaymentMonths, 10) || 0;

  if (!principal || !months) {
    return {
      total_interest_amount: null,
      monthly_principal_amount: null,
      monthly_interest_amount: null,
      monthly_total_repayment_amount: null,
      outstanding_balance_amount: null,
    };
  }

  let interestAmount = 0;

  if (loanType && interestEnabled(loanType)) {
    const method = String(loanType.interest_calculation_method || 'flat').toLowerCase().trim();
    const interestPct = parseFloat(loanType.interest_percentage) || 0;

    if (method === 'flat') {
      interestAmount = calcFlatInterest(principal, interestPct);
    } else if (method === 'reducing_balance' || method === 'reducing') {
      const monthlyRate = interestPct / 100 / 12;
      if (monthlyRate > 0) {
        const factor = Math.pow(1 + monthlyRate, months);
        const totalRepayment = round2((principal * monthlyRate * factor) / (factor - 1)) * months;
        interestAmount = round2(totalRepayment - principal);
      }
    }
  }

  const totalRepayment = round2(principal + interestAmount);
  const monthlyTotal = round2(totalRepayment / months);

  return {
    total_interest_amount: interestAmount,
    monthly_principal_amount: round2(principal / months),
    monthly_interest_amount: round2(interestAmount / months),
    monthly_total_repayment_amount: monthlyTotal,
    outstanding_balance_amount: totalRepayment,
  };
};

export const applyLoanTypeDefaults = (loanType) => {
  if (!loanType) return {};

  const minMonths = parseInt(loanType.minimum_repayment_months, 10) || null;
  const minAmount = parseFloat(loanType.minimum_loan_amount) || null;

  return {
    principal_amount: minAmount,
    repayment_period_months: minMonths,
    total_repaid_amount: 0,
    ...calcLoanAmounts({
      loanAmount: minAmount,
      repaymentMonths: minMonths,
      loanType,
    }),
  };
};

const calcEffectiveEndDate = (startDate, repaymentMonths) => {
  const start = toDayjsOrNull(startDate);
  const months = parseInt(repaymentMonths, 10);
  if (!start || !months) return null;
  return start.add(months, 'month').subtract(1, 'day');
};

export const recalcLoanFormAmounts = (form, loanType, { preserveTotalRepaid = false } = {}) => {
  const loanAmount = form.getFieldValue('principal_amount');
  const repaymentMonths = form.getFieldValue('repayment_period_months');
  const startDate = form.getFieldValue('effective_start_date');

  const amounts = calcLoanAmounts({
    loanAmount,
    repaymentMonths,
    loanType,
  });

  const patch = {
    ...amounts,
    effective_end_date: calcEffectiveEndDate(startDate, repaymentMonths),
  };

  if (preserveTotalRepaid) {
    const totalRepaid = parseFloat(form.getFieldValue('total_repaid_amount')) || 0;
    const months = parseInt(repaymentMonths, 10) || 0;
    const totalRepayment =
      amounts.monthly_total_repayment_amount && months
        ? round2(amounts.monthly_total_repayment_amount * months)
        : amounts.outstanding_balance_amount ?? 0;
    patch.outstanding_balance_amount = round2(Math.max(0, totalRepayment - totalRepaid));
  } else {
    patch.total_repaid_amount = 0;
  }

  form.setFieldsValue(patch);
};

export const mapEmployeeLoanToFormValues = (record) => {
  if (!record) return null;

  return {
    employee_national_id:
      record.employee_national_id != null ? String(record.employee_national_id) : undefined,
    loan_type_id: record.loan_type_id,
    loan_reference_number: record.loan_reference_number ?? undefined,
    loan_issue_date: toDayjsOrNull(record.loan_issue_date),
    effective_start_date: toDayjsOrNull(record.effective_start_date ?? record.repayment_start_date),
    principal_amount: numForm(record.principal_amount),
    repayment_period_months: numForm(record.repayment_period_months),
    total_interest_amount: numForm(record.total_interest_amount),
    monthly_principal_amount: numForm(record.monthly_principal_amount),
    monthly_interest_amount: numForm(record.monthly_interest_amount),
    monthly_total_repayment_amount: numForm(record.monthly_total_repayment_amount),
    total_repaid_amount: numForm(record.total_repaid_amount),
    outstanding_balance_amount: numForm(record.outstanding_balance_amount),
    effective_end_date: toDayjsOrNull(record.effective_end_date),
    is_active: isEmployeeLoanActive(record),
    notes: record.notes ?? undefined,
    created_by: record.created_by ?? undefined,
    created_at: record.created_at ?? undefined,
  };
};

const toApiDate = (d) => {
  if (!d) return null;
  if (typeof d.format === 'function') return d.format('YYYY-MM-DD');
  return null;
};

const numOrNull = (v) => {
  if (v === undefined || v === null || v === '') return null;
  return String(v);
};

export const buildEmployeeLoanPayload = (values, { isActive = 1 } = {}) => ({
  employee_national_id: values.employee_national_id,
  loan_type_id: values.loan_type_id,
  loan_reference_number: values.loan_reference_number ?? null,
  principal_amount: numOrNull(values.principal_amount),
  repayment_period_months: values.repayment_period_months ?? null,
  repayment_start_date: toApiDate(values.effective_start_date),
  effective_start_date: toApiDate(values.effective_start_date),
  loan_issue_date: toApiDate(values.loan_issue_date),
  effective_end_date: toApiDate(values.effective_end_date),
  total_interest_amount: numOrNull(values.total_interest_amount),
  monthly_principal_amount: numOrNull(values.monthly_principal_amount),
  monthly_interest_amount: numOrNull(values.monthly_interest_amount),
  monthly_total_repayment_amount: numOrNull(values.monthly_total_repayment_amount),
  total_repaid_amount: numOrNull(values.total_repaid_amount),
  outstanding_balance_amount: numOrNull(values.outstanding_balance_amount),
  is_active: isActive,
  notes: values.notes ?? null,
});
