import { DatePicker, Form, Input, Select, Switch } from 'antd';
import PropTypes from 'prop-types';

const taxableOptions = [
  { value: 1, label: 'Taxable' },
  { value: 0, label: 'Not Taxable' },
];

const ArrearsReasonFormFields = ({ disabled = false, hideActiveToggle = false }) => {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-3">
      <Form.Item
        label="Reason Name"
        name="reason_name"
        rules={[{ required: true, message: 'Reason name is required' }]}
      >
        <Input placeholder="e.g. Overtime" disabled={disabled} />
      </Form.Item>

      <Form.Item
        label="Reason Code"
        name="reason_code"
        rules={[{ required: true, message: 'Reason code is required' }]}
      >
        <Input placeholder="e.g. OT" disabled={disabled} />
      </Form.Item>

      <Form.Item
        label="Taxable"
        name="is_taxable"
        rules={[{ required: true, message: 'Please select taxable option' }]}
      >
        <Select options={taxableOptions} disabled={disabled} />
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

ArrearsReasonFormFields.propTypes = {
  disabled: PropTypes.bool,
  hideActiveToggle: PropTypes.bool,
};

export default ArrearsReasonFormFields;

