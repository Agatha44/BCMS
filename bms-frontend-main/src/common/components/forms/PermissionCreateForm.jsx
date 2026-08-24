import { useState, useEffect } from 'react';
import { Button, Col, Form, Input, Row, Checkbox } from 'antd';
import PropTypes from 'prop-types';

const PermissionCreateForm = ({ onSubmit, onCancel, initialValues }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (initialValues) {
      form.setFieldsValue({
        name: initialValues.name,
        controller: initialValues.controller,
        permission: initialValues.permission,
        route: initialValues.route,
        is_active: initialValues.is_active !== undefined ? (initialValues.is_active === 1 || initialValues.is_active === true || initialValues.is_active === '1') : true,
      });
    } else {
      form.resetFields();
      form.setFieldsValue({ is_active: true });
    }
  }, [initialValues, form]);

  const handleSubmit = async (values) => {
    setLoading(true);
    try {
      const formData = {
        ...values,
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
      initialValues={initialValues ? { is_active: true, ...initialValues } : { is_active: true }}
      autoComplete="off"
      style={{ textAlign: 'left' }}
    >
      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="name"
            label="Name"
            rules={[
              {
                required: true,
                message: 'Please enter permission name'
              }
            ]}
          >
            <Input placeholder="Enter permission name" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col xs={24} sm={24} md={12} lg={12}>
          <Form.Item
            name="controller"
            label="Controller"
            rules={[
              {
                required: true,
                message: 'Please enter controller'
              }
            ]}
          >
            <Input placeholder="Enter controller" />
          </Form.Item>
        </Col>
        <Col xs={24} sm={24} md={12} lg={12}>
          <Form.Item
            name="permission"
            label="Permission"
            rules={[
              {
                required: true,
                message: 'Please enter permission'
              }
            ]}
          >
            <Input placeholder="Enter permission" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="route"
            label="Route"
            rules={[
              {
                required: true,
                message: 'Please enter route'
              }
            ]}
          >
            <Input placeholder="Enter route (e.g., /api/users)" />
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
            <div style={{ width: '100%', display: 'flex', justifyContent: 'flex-end' }}>
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

PermissionCreateForm.propTypes = {
  onSubmit: PropTypes.func,
  onCancel: PropTypes.func,
  initialValues: PropTypes.object
};

export default PermissionCreateForm;

