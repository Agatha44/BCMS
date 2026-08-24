import { DatePicker, Form, Input, InputNumber, Select, Switch } from 'antd';
import PropTypes from 'prop-types';

const calcTypeOptions = [
  { value: 'fixed', label: 'Fixed' },
  { value: 'percentage', label: 'Percentage' },
];

const BenefitTypeFormFields = ({
  disabled = false,
  hideActiveToggle = false,
  employmentTypes = [],
  employmentTypesLoading = false,
  departments = [],
  departmentsLoading = false,
}) => {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-3">
      <Form.Item
        label="Benefit Name"
        name="benefit_name"
        rules={[{ required: true, message: 'Benefit name is required' }]}
      >
        <Input placeholder="e.g. House Allowance" disabled={disabled} />
      </Form.Item>

      <Form.Item
        label="Benefit Code"
        name="benefit_code"
        rules={[{ required: true, message: 'Benefit code is required' }]}
      >
        <Input placeholder="e.g. HRA" disabled={disabled} />
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
              // Normalize to number to match form values like "1" vs 1
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
              // Normalize to number to match form values like "7" vs 7
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

BenefitTypeFormFields.propTypes = {
  disabled: PropTypes.bool,
  hideActiveToggle: PropTypes.bool,
  employmentTypes: PropTypes.array,
  employmentTypesLoading: PropTypes.bool,
  departments: PropTypes.array,
  departmentsLoading: PropTypes.bool,
};

export default BenefitTypeFormFields;

