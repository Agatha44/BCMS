import { useState, useEffect } from 'react';
import { Button, Col, Form, Input, Row, Select, Switch } from 'antd';
import PropTypes from 'prop-types';

const { TextArea } = Input;
const { Option } = Select;

const NotificationForm = ({ onSubmit, onCancel, initialValues }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (initialValues) {
      form.setFieldsValue({
        title: initialValues.title || initialValues.notification_title,
        message: initialValues.message || initialValues.notification_message,
        type: initialValues.type || initialValues.notification_type || 'info',
        is_active: initialValues.is_active !== undefined ? initialValues.is_active : true,
      });
    } else {
      form.resetFields();
      form.setFieldsValue({
        type: 'info',
        is_active: true,
      });
    }
  }, [initialValues, form]);

  const handleSubmit = async (values) => {
    setLoading(true);
    try {
      const formData = {
        title: values.title,
        message: values.message,
        type: values.type || 'info',
        is_active: values.is_active !== undefined ? values.is_active : true,
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
      initialValues={{ 
        type: 'info',
        is_active: true,
        ...initialValues 
      }}
      autoComplete="off"
      style={{ textAlign: 'left' }}
    >
      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="title"
            label="Title"
            rules={[
              {
                required: true,
                message: 'Please enter notification title'
              },
              {
                max: 200,
                message: 'Title cannot exceed 200 characters'
              }
            ]}
          >
            <Input placeholder="Enter notification title" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="message"
            label="Message"
            rules={[
              {
                required: true,
                message: 'Please enter notification message'
              },
              {
                max: 1000,
                message: 'Message cannot exceed 1000 characters'
              }
            ]}
          >
            <TextArea 
              rows={6}
              placeholder="Enter notification message"
              showCount
              maxLength={1000}
            />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="type"
            label="Type"
            rules={[
              {
                required: true,
                message: 'Please select notification type'
              }
            ]}
          >
            <Select placeholder="Select notification type">
              <Option value="info">Info</Option>
              <Option value="warning">Warning</Option>
              <Option value="error">Error</Option>
              <Option value="success">Success</Option>
            </Select>
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="is_active"
            valuePropName="checked"
            label="Status"
            initialValue={true}
          >
            <Switch 
              checkedChildren="Active" 
              unCheckedChildren="Inactive"
            />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24} style={{ textAlign: 'right', marginTop: 16 }}>
          <Button 
            onClick={onCancel} 
            style={{ marginRight: 8 }}
          >
            Cancel
          </Button>
          <Button 
            type="primary" 
            htmlType="submit" 
            loading={loading}
            className="btn-standard-primary"
          >
            {initialValues ? 'Update' : 'Create'}
          </Button>
        </Col>
      </Row>
    </Form>
  );
};

NotificationForm.propTypes = {
  onSubmit: PropTypes.func.isRequired,
  onCancel: PropTypes.func.isRequired,
  initialValues: PropTypes.object,
};

export default NotificationForm;

