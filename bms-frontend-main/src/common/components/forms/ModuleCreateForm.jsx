import { useState, useEffect } from 'react';
import { Button, Col, Form, Input, Row, Checkbox } from 'antd';
import PropTypes from 'prop-types';

const { TextArea } = Input;

const ModuleCreateForm = ({ onSubmit, onCancel, initialValues }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (initialValues) {
      form.setFieldsValue({
        title: initialValues.title || initialValues.module_name,
        icon: initialValues.icon,
        description: initialValues.description || initialValues.module_description,
        module_id: initialValues.module_id,
        is_active: initialValues.is_active !== undefined ? initialValues.is_active : true,
      });
    } else {
      form.resetFields();
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
      initialValues={{ is_active: true, ...initialValues }}
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
                message: 'Please enter module title'
              }
            ]}
          >
            <Input placeholder="Enter module title" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={12}>
          <Form.Item
            name="icon"
            label="Icon"
          >
            <Input placeholder="Enter icon name or class" />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            name="module_id"
            label="Module ID"
            rules={[
              {
                required: true,
                message: 'Please enter module ID'
              }
            ]}
          >
            <Input placeholder="Enter module ID" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="description"
            label="Description"
          >
            <TextArea 
              placeholder="Enter module description" 
              rows={4}
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
            <div style={{ width: '100%', display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
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

ModuleCreateForm.propTypes = {
  onSubmit: PropTypes.func,
  onCancel: PropTypes.func,
  initialValues: PropTypes.object
};

export default ModuleCreateForm;

