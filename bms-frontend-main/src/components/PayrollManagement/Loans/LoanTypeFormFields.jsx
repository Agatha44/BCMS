import { DatePicker, Form, Input, InputNumber, Select, Switch } from 'antd';
import PropTypes from 'prop-types';
import { useMemo } from 'react';

const interestMethodOptions = [
  { value: 'flat', label: 'Flat' },
  { value: 'reducing_balance', label: 'Reducing Balance' },
];

const LoanTypeFormFields = ({
  disabled = false,
  hideActiveToggle = false,
  employmentTypes = [],
  employmentTypesLoading = false,
  departments = [],
  departmentsLoading = false,
}) => {
  const hasInterest = Form.useWatch('has_interest');
  const minAmount = Form.useWatch('minimum_loan_amount');
  const maxAmount = Form.useWatch('maximum_loan_amount');
  const minMonths = Form.useWatch('minimum_repayment_months');
  const maxMonths = Form.useWatch('maximum_repayment_months');

  const interestEnabled = useMemo(() => hasInterest === true || hasInterest === 1 || hasInterest === '1', [hasInterest]);

  const validateMinMax = (minFieldLabel, maxFieldLabel, getMin, getMax) => async () => {
    const minV = Number(getMin());
    const maxV = Number(getMax());
    const minOk = Number.isFinite(minV);
    const maxOk = Number.isFinite(maxV);
    if (!minOk || !maxOk) return;
    if (minV > maxV) throw new Error(`${minFieldLabel} must be less than or equal to ${maxFieldLabel}`);
  };

  const validateMinMaxAmounts = validateMinMax('Minimum loan amount', 'Maximum loan amount', () => minAmount, () => maxAmount);
  const validateMinMaxMonths = validateMinMax(
    'Minimum repayment months',
    'Maximum repayment months',
    () => minMonths,
    () => maxMonths
  );

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-3">
      <Form.Item label="Loan Name" name="loan_name" rules={[{ required: true, message: 'Loan name is required' }]}>
        <Input placeholder="e.g. Staff Loan" disabled={disabled} />
      </Form.Item>

      <Form.Item label="Loan Code" name="loan_code" rules={[{ required: true, message: 'Loan code is required' }]}>
        <Input placeholder="e.g. STAFF_LOAN" disabled={disabled} />
      </Form.Item>

      <Form.Item label="Priority" name="priority">
        <InputNumber className="w-full" min={0} step={1} disabled={disabled} />
      </Form.Item>

      <Form.Item label="Has Interest" name="has_interest" valuePropName="checked">
        <Switch disabled={disabled} />
      </Form.Item>

      {interestEnabled ? (
        <>
          <Form.Item
            label="Interest %"
            name="interest_percentage"
            rules={[{ required: true, message: 'Interest percentage is required' }]}
          >
            <InputNumber className="w-full" min={0} max={100} step={0.01} disabled={disabled} />
          </Form.Item>

          <Form.Item
            label="Interest Calculation Method"
            name="interest_calculation_method"
            rules={[{ required: true, message: 'Interest calculation method is required' }]}
          >
            <Select options={interestMethodOptions} disabled={disabled} />
          </Form.Item>
        </>
      ) : null}

      <Form.Item
        label="Minimum Loan Amount"
        name="minimum_loan_amount"
        dependencies={['maximum_loan_amount']}
        rules={[
          { required: true, message: 'Minimum loan amount is required' },
          { validator: validateMinMaxAmounts },
        ]}
      >
        <InputNumber className="w-full" min={0} step={0.01} disabled={disabled} />
      </Form.Item>

      <Form.Item
        label="Maximum Loan Amount"
        name="maximum_loan_amount"
        dependencies={['minimum_loan_amount']}
        rules={[
          { required: true, message: 'Maximum loan amount is required' },
          { validator: validateMinMaxAmounts },
        ]}
      >
        <InputNumber className="w-full" min={0} step={0.01} disabled={disabled} />
      </Form.Item>

      <Form.Item
        label="Minimum Repayment Months"
        name="minimum_repayment_months"
        dependencies={['maximum_repayment_months']}
        rules={[
          { required: true, message: 'Minimum repayment months is required' },
          { validator: validateMinMaxMonths },
        ]}
      >
        <InputNumber className="w-full" min={1} step={1} disabled={disabled} />
      </Form.Item>

      <Form.Item
        label="Maximum Repayment Months"
        name="maximum_repayment_months"
        dependencies={['minimum_repayment_months']}
        rules={[
          { required: true, message: 'Maximum repayment months is required' },
          { validator: validateMinMaxMonths },
        ]}
      >
        <InputNumber className="w-full" min={1} step={1} disabled={disabled} />
      </Form.Item>

      <Form.Item label="Contract Type" name="contract_type">
        <Select
          placeholder="Select contract type"
          disabled={disabled}
          loading={employmentTypesLoading}
          allowClear
          showSearch
          optionFilterProp="label"
          options={(employmentTypes || [])
            .filter((t) => t && (t.emptype_id ?? t.id) != null)
            .map((t) => ({
              value: Number(t.emptype_id ?? t.id),
              label: t.emptype_name,
            }))}
        />
      </Form.Item>

      <Form.Item label="Department Section" name="department_section">
        <Select
          placeholder="Select department section"
          disabled={disabled}
          loading={departmentsLoading}
          allowClear
          showSearch
          optionFilterProp="label"
          options={(departments || [])
            .filter((d) => d && (d.department_id ?? d.id) != null)
            .map((d) => ({
              value: Number(d.department_id ?? d.id),
              label: d.department_name ?? d.name,
            }))}
        />
      </Form.Item>

      <Form.Item label="Job Title Position" name="job_title_position">
        <Input placeholder="Optional" disabled={disabled} />
      </Form.Item>

      <Form.Item label="Start Date" name="start_date">
        <DatePicker className="w-full" disabled={disabled} />
      </Form.Item>

      <Form.Item label="End Date" name="end_date">
        <DatePicker className="w-full" disabled={disabled} />
      </Form.Item>

      {!hideActiveToggle ? (
        <Form.Item label="Active" name="is_active" valuePropName="checked">
          <Switch disabled={disabled} />
        </Form.Item>
      ) : null}
    </div>
  );
};

LoanTypeFormFields.propTypes = {
  disabled: PropTypes.bool,
  hideActiveToggle: PropTypes.bool,
  employmentTypes: PropTypes.array,
  employmentTypesLoading: PropTypes.bool,
  departments: PropTypes.array,
  departmentsLoading: PropTypes.bool,
};

export default LoanTypeFormFields;

