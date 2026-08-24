import { useState, useEffect } from 'react';
import { Button, Col, Form, Row, Input, DatePicker, Select, Checkbox, App } from 'antd';
import { CalendarOutlined } from '@ant-design/icons';
import PropTypes from 'prop-types';
import dayjs from 'dayjs';
import { apiService } from '../../../services/api.jsx';

const { Option } = Select;
const { TextArea } = Input;

const PublicHolidayForm = ({ onSubmit, onCancel, initialValues }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [checkingDate, setCheckingDate] = useState(false);
  const [dateExists, setDateExists] = useState(false);
  const [existingHoliday, setExistingHoliday] = useState(null);

  useEffect(() => {
    if (initialValues) {
      const formValues = {
        holiday_name: initialValues.holiday_name || initialValues.holidayName,
        holiday_date: initialValues.holiday_date || initialValues.holidayDate 
          ? dayjs(initialValues.holiday_date || initialValues.holidayDate) 
          : null,
        holiday_type: initialValues.holiday_type || initialValues.holidayType || null,
        description: initialValues.description || null,
        is_active: initialValues.is_active !== undefined 
          ? initialValues.is_active 
          : (initialValues.isActive !== undefined ? initialValues.isActive : null),
      };
      
      form.setFieldsValue(formValues);
    } else {
      form.resetFields();
    }
  }, [initialValues, form]);

  const checkDateExists = async (date) => {
    if (!date) {
      setDateExists(false);
      setExistingHoliday(null);
      return;
    }

    // Don't check if editing the same holiday
    if (initialValues && initialValues.id) {
      const initialDate = dayjs(initialValues.holiday_date || initialValues.holidayDate);
      if (dayjs(date).isSame(initialDate, 'day')) {
        setDateExists(false);
        setExistingHoliday(null);
        return;
      }
    }

    setCheckingDate(true);
    try {
      const dateString = date.format('YYYY-MM-DD');
      const response = await apiService.checkPublicHolidayDate({ date: dateString });
      
      if (response.success && response.data && response.data.exists) {
        setDateExists(true);
        setExistingHoliday(response.data.holiday);
        form.setFields([
          {
            name: 'holiday_date',
            errors: [`A holiday "${response.data.holiday?.holiday_name || response.data.holiday?.holidayName}" already exists for this date`],
          },
        ]);
      } else {
        setDateExists(false);
        setExistingHoliday(null);
        form.setFields([
          {
            name: 'holiday_date',
            errors: [],
          },
        ]);
      }
    } catch (error) {
      console.error('Error checking date:', error);
      // Don't block user if check fails
      setDateExists(false);
      setExistingHoliday(null);
    } finally {
      setCheckingDate(false);
    }
  };

  const handleSubmit = async (values) => {
    setLoading(true);
    try {
      // Build formData according to backend validation rules
      const formData = {
        holiday_name: values.holiday_name, // required|string|max:255
        holiday_date: values.holiday_date.format('YYYY-MM-DD'), // required|date|unique
      };

      // Add nullable fields - send null if not provided (to match backend nullable validation)
      // holiday_type: nullable|string|max:50
      formData.holiday_type = values.holiday_type || null;

      // description: nullable|string
      formData.description = values.description || null;

      // is_active: nullable|boolean
      // Send true if checked, null if unchecked (to match nullable boolean)
      formData.is_active = values.is_active === true ? true : null;

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
            name="holiday_name"
            label="Holiday Name"
            rules={[
              {
                required: true,
                message: 'Please enter holiday name',
              },
              {
                max: 255,
                message: 'Holiday name must not exceed 255 characters',
              },
            ]}
          >
            <Input 
              placeholder="e.g., Independence Day" 
              prefix={<CalendarOutlined />}
              maxLength={255}
              showCount
            />
          </Form.Item>
        </Col>

        <Col xs={24} sm={24} md={12} lg={12}>
          <Form.Item
            name="holiday_date"
            label="Holiday Date"
            rules={[
              {
                required: true,
                message: 'Please select holiday date',
              },
            ]}
            validateStatus={dateExists ? 'error' : checkingDate ? 'validating' : ''}
            help={dateExists && existingHoliday 
              ? `A holiday "${existingHoliday.holiday_name || existingHoliday.holidayName}" already exists for this date. Please edit the existing holiday instead.`
              : null
            }
          >
            <DatePicker
              style={{ width: '100%' }}
              placeholder="Select holiday date"
              format="YYYY-MM-DD"
              onChange={(date) => {
                if (date) {
                  checkDateExists(date);
                } else {
                  setDateExists(false);
                  setExistingHoliday(null);
                }
              }}
              disabledDate={(current) => {
                // Allow past dates for historical records
                // Can add restriction if needed: return current && current < dayjs().startOf('day');
                return false;
              }}
            />
          </Form.Item>
        </Col>

        <Col xs={24} sm={24} md={12} lg={12}>
          <Form.Item
            name="holiday_type"
            label="Holiday Type"
            rules={[
              {
                max: 50,
                message: 'Holiday type must not exceed 50 characters',
              },
            ]}
          >
            <Select placeholder="Select holiday type (optional)" allowClear>
              <Option value="National">National</Option>
              <Option value="Regional">Regional</Option>
              <Option value="Religious">Religious</Option>
              <Option value="Cultural">Cultural</Option>
              <Option value="Other">Other</Option>
            </Select>
          </Form.Item>
        </Col>

        <Col span={24}>
          <Form.Item
            name="description"
            label="Description"
          >
            <TextArea 
              placeholder="Additional details about the holiday (optional)" 
              rows={4}
            />
          </Form.Item>
        </Col>

        <Col span={24}>
          <Form.Item
            name="is_active"
            valuePropName="checked"
          >
            <Checkbox>
              Active Holiday
              <span style={{ marginLeft: 8, color: '#666', fontSize: '12px' }}>
                (Only active holidays affect overtime calculations)
              </span>
            </Checkbox>
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={16} style={{ marginTop: 24 }}>
        <Col span={24}>
          <Form.Item style={{ marginBottom: 0 }}>
            <div style={{ width: '100%', display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
              <Button onClick={onCancel}>
                Cancel
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={loading}
                disabled={dateExists}
                style={{
                  backgroundColor: '#962E32',
                  borderColor: '#962E32',
                }}
              >
                {initialValues ? 'Update' : 'Save'}
              </Button>
            </div>
          </Form.Item>
        </Col>
      </Row>
    </Form>
  );
};

PublicHolidayForm.propTypes = {
  onSubmit: PropTypes.func.isRequired,
  onCancel: PropTypes.func.isRequired,
  initialValues: PropTypes.object,
};

export default PublicHolidayForm;

