import { DatePicker, Form, Input, InputNumber, Select, Switch } from 'antd';
import PropTypes from 'prop-types';
import { useMemo } from 'react';
import { formatEmployeeName } from '../../../common/utils/employeeUtils.jsx';
import { formatMoney } from '../../../common/utils/numberFormat.js';
import { CALCULATED_FIELD_NAMES } from './employeeLoanConstants.js';
import './employee-loan-form.css';

const filterSelectOption = (input, option) =>
  String(option?.label ?? '')
    .toLowerCase()
    .includes(String(input ?? '').toLowerCase());

const buildStaffOption = (employee) => {
  const nationalId = employee?.national_id ?? employee?.nationalId;
  const pfNumber = employee?.pfno;
  const fullName = formatEmployeeName(employee);
  return {
    value: nationalId != null ? String(nationalId) : '',
    label: `${pfNumber || '—'} - ${fullName}`,
  };
};

const SectionHeading = ({ children }) => (
  <h4 className="employee-loan-section-heading">{children}</h4>
);

const CalculatedCard = ({ label, value, highlight = false }) => (
  <div className="employee-loan-calculated-card">
    <span className="employee-loan-calculated-label">{label}</span>
    <span className={`employee-loan-calculated-value${highlight ? ' is-highlight' : ''}`}>
      {value ?? '—'}
    </span>
  </div>
);

const EmployeeLoanFormFields = ({
  disabled = false,
  showAuditFields = false,
  hideReferenceNumber = false,
  showActiveField = true,
  loanTypeOptions = [],
  employees = [],
  employeesLoading = false,
  onLoanTypeChange,
  onPrincipalAmountChange,
  onRepaymentMonthsChange,
  onEffectiveStartDateChange,
}) => {
  const form = Form.useFormInstance();
  const watched = Form.useWatch([], form) || {};

  const employeeSelectOptions = useMemo(
    () => (employees || []).map(buildStaffOption).filter((o) => o.value),
    [employees]
  );

  const fmt = (field) => {
    const value = watched[field];
    if (value === null || value === undefined || value === '') return '—';
    return formatMoney(value);
  };

  return (
    <div className="employee-loan-form">
      <div className="grid grid-cols-1 gap-x-5 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
        <Form.Item
          label="Employee name"
          name="employee_national_id"
          rules={[{ required: true, message: 'Please select an employee' }]}
        >
          <Select
            placeholder="Search employee by PF number or name"
            disabled={disabled}
            loading={employeesLoading}
            showSearch
            allowClear
            optionFilterProp="label"
            options={employeeSelectOptions}
            filterOption={filterSelectOption}
          />
        </Form.Item>

        <Form.Item
          label="Loan type"
          name="loan_type_id"
          rules={[{ required: true, message: 'Loan type is required' }]}
        >
          <Select
            placeholder="Select loan type"
            options={loanTypeOptions}
            disabled={disabled}
            loading={employeesLoading}
            showSearch
            allowClear
            optionFilterProp="label"
            onChange={onLoanTypeChange}
            filterOption={filterSelectOption}
          />
        </Form.Item>

        {!hideReferenceNumber ? (
          <Form.Item label="Loan reference number" name="loan_reference_number">
            <Input disabled={disabled} placeholder="Enter reference…" />
          </Form.Item>
        ) : null}

        <SectionHeading>Loan terms</SectionHeading>

        <Form.Item
          label="Principal amount (TZS)"
          name="principal_amount"
          rules={[{ required: true, message: 'Principal amount is required' }]}
        >
          <InputNumber
            className="w-full"
            min={0}
            step={0.01}
            disabled={disabled}
            onChange={onPrincipalAmountChange}
          />
        </Form.Item>

        <Form.Item
          label="Repayment period (months)"
          name="repayment_period_months"
          rules={[{ required: true, message: 'Repayment period is required' }]}
        >
          <InputNumber
            className="w-full"
            min={1}
            step={1}
            disabled={disabled}
            onChange={onRepaymentMonthsChange}
          />
        </Form.Item>

        <Form.Item label="Loan issue date" name="loan_issue_date">
          <DatePicker className="w-full" disabled={disabled} format="DD/MM/YYYY" />
        </Form.Item>

        <SectionHeading>Calculated amounts</SectionHeading>

        <CalculatedCard label="Total interest" value={fmt('total_interest_amount')} />
        <CalculatedCard label="Monthly principal" value={fmt('monthly_principal_amount')} />
        <CalculatedCard label="Monthly interest" value={fmt('monthly_interest_amount')} />
        <CalculatedCard label="Monthly repayment" value={fmt('monthly_total_repayment_amount')} highlight />
        <CalculatedCard label="Total repaid" value={fmt('total_repaid_amount')} />
        <CalculatedCard label="Outstanding balance" value={fmt('outstanding_balance_amount')} highlight />

        {CALCULATED_FIELD_NAMES.map((name) => (
          <Form.Item key={name} name={name} hidden>
            <InputNumber />
          </Form.Item>
        ))}

        <SectionHeading>{showAuditFields ? 'Schedule & audit' : 'Schedule'}</SectionHeading>

        <Form.Item
          label="Effective start date"
          name="effective_start_date"
          rules={[{ required: true, message: 'Effective start date is required' }]}
        >
          <DatePicker
            className="w-full"
            disabled={disabled}
            format="DD/MM/YYYY"
            onChange={onEffectiveStartDateChange}
          />
        </Form.Item>

        <Form.Item label="Effective end date" name="effective_end_date">
          <DatePicker className="w-full" disabled={disabled} format="DD/MM/YYYY" />
        </Form.Item>

        <Form.Item label="Notes" name="notes" className="employee-loan-notes-item sm:col-span-2 lg:col-span-1">
          <Input.TextArea rows={3} disabled={disabled} placeholder="Optional note…" />
        </Form.Item>

        {showAuditFields ? (
          <>
            <Form.Item label="Created by" name="created_by">
              <Input disabled />
            </Form.Item>
            <Form.Item label="Created at" name="created_at">
              <Input disabled />
            </Form.Item>
          </>
        ) : null}

        {showActiveField ? (
          <div className="employee-loan-active-row col-span-full">
            <Form.Item label="Active" name="is_active" valuePropName="checked" className="employee-loan-active-field">
              <Switch
                checkedChildren="Active"
                unCheckedChildren="Inactive"
                disabled={disabled}
                className="employee-loan-active-switch"
              />
            </Form.Item>
          </div>
        ) : null}
      </div>
    </div>
  );
};

EmployeeLoanFormFields.propTypes = {
  disabled: PropTypes.bool,
  showAuditFields: PropTypes.bool,
  hideReferenceNumber: PropTypes.bool,
  showActiveField: PropTypes.bool,
  loanTypeOptions: PropTypes.arrayOf(
    PropTypes.shape({
      value: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
      label: PropTypes.string.isRequired,
    })
  ),
  employees: PropTypes.arrayOf(PropTypes.object), // eslint-disable-line react/forbid-prop-types
  employeesLoading: PropTypes.bool,
  onLoanTypeChange: PropTypes.func,
  onPrincipalAmountChange: PropTypes.func,
  onRepaymentMonthsChange: PropTypes.func,
  onEffectiveStartDateChange: PropTypes.func,
};

export default EmployeeLoanFormFields;
