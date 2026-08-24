import { useState, useEffect, useMemo } from 'react';
import { Button, Col, Form, Row, Input, DatePicker, Select, Checkbox, App } from 'antd';
import { FileTextOutlined } from '@ant-design/icons';
import PropTypes from 'prop-types';
import dayjs from 'dayjs';
import { apiService } from '../../../services/api.jsx';
import { extractArrayFromResponse, formatEmployeeName } from '../../../common/utils/employeeUtils.jsx';
import '../../../styles/common.css';

const { Option } = Select;
const { TextArea } = Input;

const buildStaffOption = (employee) => {
  const pfNumber = employee?.pfno || employee?.pf_number || employee?.pfNumber || '—';
  const fullName = formatEmployeeName(employee);
  return {
    value: pfNumber,
    label: `${pfNumber} - ${fullName}`,
  };
};

const SpecialTaskForm = ({ onSubmit, onCancel, initialValues, isApplication = false }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [employees, setEmployees] = useState([]);
  const [employeesLoading, setEmployeesLoading] = useState(false);
  const [overtimeRule, setOvertimeRule] = useState('standard');

  const employeeSelectOptions = useMemo(
    () =>
      (employees || [])
        .map((emp) => buildStaffOption(emp))
        .filter((o) => o.value && o.value !== '—'),
    [employees],
  );

  useEffect(() => {
    if (!isApplication) {
      fetchEmployees();
    }

    if (initialValues) {
      const formValues = {
        task_name: initialValues.task_name || initialValues.taskName,
        description: initialValues.description,
        pf_number: initialValues.pf_number || initialValues.employeePfNumber || initialValues.employee_pf_number,
        start_date: initialValues.start_date || initialValues.startDate ? dayjs(initialValues.start_date || initialValues.startDate) : null,
        end_date: initialValues.end_date || initialValues.endDate ? dayjs(initialValues.end_date || initialValues.endDate) : null,
        overtime_rule: initialValues.overtime_rule || initialValues.overtimeRule || 'standard',
        custom_overtime_threshold: initialValues.custom_overtime_threshold || initialValues.customOvertimeThreshold,
        is_pre_approved: initialValues.is_pre_approved !== undefined ? initialValues.is_pre_approved : (initialValues.isPreApproved || false),
      };

      form.setFieldsValue(formValues);
      setOvertimeRule(formValues.overtime_rule || 'standard');
    } else {
      form.resetFields();
      setOvertimeRule('standard');
    }
  }, [initialValues, isApplication, form]);

  const fetchEmployees = async () => {
    setEmployeesLoading(true);
    try {
      const response = await apiService.getBridgeEmployees({ per_page: 1000 });
      if (response.success && response.data) {
        const { items: employeesData } = extractArrayFromResponse(response.data);
        setEmployees(employeesData || []);
      }
    } catch (error) {
      console.error('Error fetching employees:', error);
      message.error('Failed to load employees');
    } finally {
      setEmployeesLoading(false);
    }
  };

  const handleSubmit = async (values) => {
    setLoading(true);
    try {
      const formData = {
        task_name: values.task_name,
        start_date: values.start_date.format('YYYY-MM-DD'),
        end_date: values.end_date.format('YYYY-MM-DD'),
        overtime_rule: values.overtime_rule,
      };

      if (values.description) {
        formData.description = values.description;
      }

      if (values.overtime_rule === 'custom' && values.custom_overtime_threshold) {
        formData.custom_overtime_threshold = parseFloat(values.custom_overtime_threshold);
      }

      if (values.is_pre_approved !== undefined) {
        formData.is_pre_approved = values.is_pre_approved || false;
      }

      if (!isApplication && values.pf_number) {
        formData.pf_number = values.pf_number;
      }

      if (isApplication && values.justification) {
        formData.justification = values.justification;
      }

      if (onSubmit) {
        await onSubmit(formData);
      }
    } catch (error) {
      console.error('Form submission error:', error);
      message.error('Failed to submit form. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={handleSubmit}
      autoComplete="off"
      style={{ textAlign: 'left' }}
    >
      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="task_name"
            label="Task Name"
            rules={[{ required: true, message: 'Please enter task name' }]}
          >
            <Input placeholder="Enter task name" />
          </Form.Item>
        </Col>

        <Col span={24}>
          <Form.Item
            name="description"
            label="Description"
            rules={[{ required: true, message: 'Please enter description' }]}
          >
            <TextArea placeholder="Enter task description" rows={4} showCount maxLength={500} />
          </Form.Item>
        </Col>

        {!isApplication && (
          <Col span={24}>
            <Form.Item
              name="pf_number"
              label="Employee"
              rules={[{ required: true, message: 'Please select employee' }]}
            >
              <Select
                placeholder="Search employee by PF number or name"
                showSearch
                loading={employeesLoading}
                allowClear
                optionFilterProp="label"
                options={employeeSelectOptions}
                filterOption={(input, option) =>
                  String(option?.label ?? '')
                    .toLowerCase()
                    .includes(String(input ?? '').toLowerCase())
                }
              />
            </Form.Item>
          </Col>
        )}

        <Col xs={24} sm={12}>
          <Form.Item
            name="start_date"
            label="Start Date"
            rules={[{ required: true, message: 'Please select start date' }]}
          >
            <DatePicker
              style={{ width: '100%' }}
              placeholder="Select start date"
              format="YYYY-MM-DD"
              disabledDate={(current) => {
                if (!initialValues && current && current < dayjs().startOf('day')) return true;
                const endDate = form.getFieldValue('end_date');
                if (endDate && current && current > endDate) return true;
                return false;
              }}
            />
          </Form.Item>
        </Col>

        <Col xs={24} sm={12}>
          <Form.Item
            name="end_date"
            label="End Date"
            rules={[
              { required: true, message: 'Please select end date' },
              ({ getFieldValue }) => ({
                validator(_, value) {
                  const startDate = getFieldValue('start_date');
                  if (!value || !startDate || !dayjs(value).isBefore(dayjs(startDate), 'day')) {
                    return Promise.resolve();
                  }
                  return Promise.reject(new Error('End date must be on or after start date'));
                },
              }),
            ]}
          >
            <DatePicker
              style={{ width: '100%' }}
              placeholder="Select end date"
              format="YYYY-MM-DD"
              disabledDate={(current) => {
                if (!initialValues && current && current < dayjs().startOf('day')) return true;
                const startDate = form.getFieldValue('start_date');
                if (startDate && current && current < startDate) return true;
                return false;
              }}
            />
          </Form.Item>
        </Col>

        <Col xs={24} sm={overtimeRule === 'custom' ? 12 : 24}>
          <Form.Item
            name="overtime_rule"
            label="Overtime Rule"
            rules={[{ required: true, message: 'Please select overtime rule' }]}
          >
            <Select
              placeholder="Select rule"
              onChange={(value) => {
                setOvertimeRule(value);
                form.setFieldValue('overtime_rule', value);
              }}
            >
              <Option value="all_hours">All Hours</Option>
              <Option value="standard">Standard (9-hour rule)</Option>
              <Option value="custom">Custom Threshold</Option>
            </Select>
          </Form.Item>
        </Col>

        {overtimeRule === 'custom' && (
          <Col xs={24} sm={12}>
            <Form.Item
              name="custom_overtime_threshold"
              label="Threshold (Hours)"
              rules={[
                { required: true, message: 'Please enter threshold' },
                {
                  type: 'number',
                  min: 0,
                  message: 'Must be a positive number',
                  transform: (value) => parseFloat(value),
                },
              ]}
            >
              <Input type="number" placeholder="Hours" min={0} step={0.5} />
            </Form.Item>
          </Col>
        )}

        {!isApplication && (
          <Col span={24}>
            <Form.Item name="is_pre_approved" valuePropName="checked">
              <Checkbox>Pre-approve overtime</Checkbox>
            </Form.Item>
          </Col>
        )}

        {isApplication && (
          <Col span={24}>
            <Form.Item
              name="justification"
              label="Justification"
              rules={[{ required: true, message: 'Please provide justification' }]}
            >
              <TextArea placeholder="Explain why this task is needed" rows={4} showCount maxLength={500} />
            </Form.Item>
          </Col>
        )}
      </Row>

      <Row gutter={16} style={{ marginTop: 24 }}>
        <Col span={24}>
          <Form.Item style={{ marginBottom: 0 }}>
            <div style={{ width: '100%', display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
              <Button onClick={onCancel}>
                {initialValues ? 'Close' : 'Cancel'}
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={loading}
                className="btn-standard-primary"
              >
                {initialValues ? 'Update' : 'Submit'}
              </Button>
            </div>
          </Form.Item>
        </Col>
      </Row>
    </Form>
  );
};

SpecialTaskForm.propTypes = {
  onSubmit: PropTypes.func.isRequired,
  onCancel: PropTypes.func.isRequired,
  initialValues: PropTypes.object,
  isApplication: PropTypes.bool,
};

export default SpecialTaskForm;
