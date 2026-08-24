import { useState, useEffect } from 'react';
import { Button, Col, Form, Row, Checkbox, InputNumber, Select } from 'antd';
import PropTypes from 'prop-types';
import { apiService } from '../../../services/api.jsx';

const { Option } = Select;

const OvertimeRateForm = ({ onSubmit, onCancel, initialValues }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [educationalLevels, setEducationalLevels] = useState([]);
  const [loadingEducationalLevels, setLoadingEducationalLevels] = useState(false);

  // Fetch active educational levels
  useEffect(() => {
    const fetchEducationalLevels = async () => {
      setLoadingEducationalLevels(true);
      try {
        const response = await apiService.getActiveEducationalLevels();
        if (response.success && response.data) {
          setEducationalLevels(Array.isArray(response.data) ? response.data : []);
        }
      } catch (error) {
        console.error('Error fetching educational levels:', error);
      } finally {
        setLoadingEducationalLevels(false);
      }
    };

    fetchEducationalLevels();
  }, []);

  useEffect(() => {
    if (initialValues && educationalLevels.length > 0) {
      // Find educational level by ID or name
      let educationalLevelId = initialValues.educational_level_id || initialValues.educational_levels_id;
      const educationalLevelName = initialValues.educational_level_name || initialValues.name;
      
      if (!educationalLevelId && educationalLevelName) {
        const foundLevel = educationalLevels.find(
          level => level.level_name === educationalLevelName || level.name === educationalLevelName
        );
        if (foundLevel) {
          educationalLevelId = foundLevel.id;
        }
      }

      form.setFieldsValue({
        educational_level_id: educationalLevelId,
        name: educationalLevelName,
        daily_rate: initialValues.rate || initialValues.daily_rate || initialValues.hourly_rate,
        is_active: initialValues.is_active !== undefined ? initialValues.is_active : true,
      });
    } else if (!initialValues) {
      form.resetFields();
    }
  }, [initialValues, form, educationalLevels]);

  const handleSubmit = async (values) => {
    setLoading(true);
    try {
      // Get the selected educational level name
      const selectedEducationalLevel = educationalLevels.find(
        level => level.id === values.educational_level_id || level.name === values.educational_level_id
      );
      
      const formData = {
        educational_levels_id: values.educational_level_id,
        rate: parseFloat(values.daily_rate) || 0,
        name: selectedEducationalLevel ? (selectedEducationalLevel.level_name || selectedEducationalLevel.name) : values.name,
        is_active: values.is_active !== undefined ? values.is_active : true
      };

      if (onSubmit) {
        await onSubmit(formData);
      }
    } catch (error) {
      console.error('Form submission error:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={handleSubmit}
      initialValues={{ is_active: true, ...initialValues }}
      autoComplete="off"
      style={{ textAlign: 'left' }}
    >
      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="educational_level_id"
            label="Educational Level"
            rules={[
              {
                required: true,
                message: 'Please select educational level'
              }
            ]}
          >
            <Select
              placeholder="Select educational level"
              loading={loadingEducationalLevels}
              showSearch
              filterOption={(input, option) =>
                (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
              }
            >
              {educationalLevels.map(level => (
                <Option key={level.id} value={level.id}>
                  {level.level_name || level.name}
                </Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="daily_rate"
            label="Daily Rate (TSh)"
            rules={[
              {
                required: true,
                message: 'Please enter daily rate'
              },
              {
                type: 'number',
                min: 0.01,
                message: 'Daily rate must be greater than 0',
                transform: (value) => parseFloat(value),
              }
            ]}
          >
            <InputNumber
              style={{ width: '100%' }}
              placeholder="Enter daily rate in TSh"
              min={0}
              step={100}
              formatter={(value) => value ? `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',') : ''}
              parser={(value) => value ? value.replace(/\$\s?|(,*)/g, '') : ''}
            />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="is_active"
            valuePropName="checked"
          >
            <Checkbox>Active</Checkbox>
          </Form.Item>
        </Col>
      </Row>

      {/* Form Actions */}
      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item style={{ marginBottom: 0 }}>
            <div style={{ width: '100%', display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
              {onCancel && (
                <Button onClick={onCancel}>
                  Cancel
                </Button>
              )}
              <Button
                type="primary"
                htmlType="submit"
                loading={loading}
                style={{
                  backgroundColor: '#962E32',
                  borderColor: '#962E32'
                }}
              >
                Submit
              </Button>
            </div>
          </Form.Item>
        </Col>
      </Row>
    </Form>
  );
};

OvertimeRateForm.propTypes = {
  onSubmit: PropTypes.func,
  onCancel: PropTypes.func,
  initialValues: PropTypes.object
};

export default OvertimeRateForm;

