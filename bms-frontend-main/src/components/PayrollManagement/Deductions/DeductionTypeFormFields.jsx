import { DatePicker, Form, Input, InputNumber, Select, Switch } from 'antd';
import PropTypes from 'prop-types';
import { useMemo } from 'react';

const calcTypeOptions = [
  { value: 'fixed', label: 'Fixed' },
  { value: 'percentage', label: 'Percentage' },
];

const DeductionTypeFormFields = ({
  disabled = false,
  hideActiveToggle = false,
  employmentTypes = [],
  employmentTypesLoading = false,
}) => {
  const calculationType = Form.useWatch('calculation_type');
  const calculationValue = Form.useWatch('calculation_value');
  const employeeContribution = Form.useWatch('employee_contribution_percentage');
  const employerContribution = Form.useWatch('employer_contribution_percentage');
  const isPercentageCalc = useMemo(
    () => String(calculationType || '').toLowerCase().trim() === 'percentage',
    [calculationType]
  );

  const totalContribution = useMemo(() => {
    const emp = Number(employeeContribution ?? 0) || 0;
    const er = Number(employerContribution ?? 0) || 0;
    return emp + er;
  }, [employeeContribution, employerContribution]);

  const validateTotalPercentage = async () => {
    if (!isPercentageCalc) return;
    const cap = Number(calculationValue);
    const hasCap = Number.isFinite(cap) && cap > 0;

    if (totalContribution > 100) throw new Error('Total contribution percentage must not exceed 100%');
    if (hasCap && totalContribution > cap) {
      throw new Error(`Total contribution percentage must not exceed the calculation value (${cap}%)`);
    }
  };

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-3">
      <Form.Item
        label="Deduction Name"
        name="deduction_name"
        rules={[{ required: true, message: 'Deduction name is required' }]}
      >
        <Input placeholder="e.g. Union Fee" disabled={disabled} />
      </Form.Item>

      <Form.Item
        label="Deduction Code"
        name="deduction_code"
        rules={[{ required: true, message: 'Deduction code is required' }]}
      >
        <Input placeholder="e.g. UNION" disabled={disabled} />
      </Form.Item>

      <Form.Item
        label="Calculation Type"
        name="calculation_type"
        rules={[{ required: true, message: 'Calculation type is required' }]}
      >
        <Select options={calcTypeOptions} disabled={disabled} />
      </Form.Item>

      <Form.Item
        label="Calculation Value"
        name="calculation_value"
        rules={[{ required: true, message: 'Calculation value is required' }]}
      >
        <InputNumber className="w-full" min={0} step={0.01} disabled={disabled} />
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
              value: t.emptype_id ?? t.id,
              label: t.emptype_name,
            }))}
        />
      </Form.Item>

      {isPercentageCalc ? (
        <>
          <Form.Item
            label="Employee Contribution %"
            name="employee_contribution_percentage"
            dependencies={['employer_contribution_percentage', 'calculation_type', 'calculation_value']}
            rules={[
              { required: true, message: 'Employee contribution percentage is required' },
              { validator: validateTotalPercentage },
            ]}
          >
            <InputNumber className="w-full" min={0} max={100} step={0.01} disabled={disabled} />
          </Form.Item>

          <Form.Item
            label="Employer Contribution %"
            name="employer_contribution_percentage"
            dependencies={['employee_contribution_percentage', 'calculation_type', 'calculation_value']}
            rules={[
              { required: true, message: 'Employer contribution percentage is required' },
              { validator: validateTotalPercentage },
            ]}
          >
            <InputNumber className="w-full" min={0} max={100} step={0.01} disabled={disabled} />
          </Form.Item>
        </>
      ) : null}

      <Form.Item label="Start Date" name="start_date">
        <DatePicker className="w-full" disabled={disabled} />
      </Form.Item>

      <Form.Item label="End Date" name="end_date">
        <DatePicker className="w-full" disabled={disabled} />
      </Form.Item>

      <Form.Item label="Mandatory" name="is_mandatory" valuePropName="checked">
        <Switch disabled={disabled} />
      </Form.Item>

      {!hideActiveToggle ? (
        <Form.Item label="Active" name="is_active" valuePropName="checked">
          <Switch disabled={disabled} />
        </Form.Item>
      ) : null}
    </div>
  );
};

DeductionTypeFormFields.propTypes = {
  disabled: PropTypes.bool,
  hideActiveToggle: PropTypes.bool,
  employmentTypes: PropTypes.array,
  employmentTypesLoading: PropTypes.bool,
};

export default DeductionTypeFormFields;

