import { useState, useEffect } from 'react';
import { Button, Col, Form, Input, Row, Checkbox } from 'antd';
import PropTypes from 'prop-types';

const BankForm = ({ onSubmit, initialValues }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (initialValues) {
      form.setFieldsValue({
        bank_name: initialValues.bank_name,
        short_name: initialValues.short_name,
        sort_code: initialValues.sort_code,
        bank_code: initialValues.bank_code,
        bi_code: initialValues.bi_code,
        erp_bc: initialValues.erp_bc,
        erp_br: initialValues.erp_br,
        citi_code: initialValues.citi_code,
        swift_code: initialValues.swift_code,
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
            name="bank_name"
            label="Bank Name"
            rules={[
              {
                required: true,
                message: 'Please enter bank name'
              }
            ]}
          >
            <Input placeholder="Enter bank name" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="short_name"
            label="Short Name"
            rules={[
              {
                required: true,
                message: 'Please enter short name'
              }
            ]}
          >
            <Input placeholder="Enter short name" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={12}>
          <Form.Item
            name="sort_code"
            label="Sort Code"
          >
            <Input placeholder="Enter sort code" />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            name="bank_code"
            label="Bank Code"
          >
            <Input placeholder="Enter bank code" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={12}>
          <Form.Item
            name="bi_code"
            label="BI Code"
          >
            <Input placeholder="Enter BI code" />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            name="erp_bc"
            label="ERP BC"
          >
            <Input placeholder="Enter ERP BC" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={12}>
          <Form.Item
            name="erp_br"
            label="ERP BR"
          >
            <Input placeholder="Enter ERP BR" />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            name="citi_code"
            label="Citi Code"
          >
            <Input placeholder="Enter Citi code" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="swift_code"
            label="SWIFT Code"
          >
            <Input placeholder="Enter SWIFT code" />
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

BankForm.propTypes = {
  onSubmit: PropTypes.func,
  onCancel: PropTypes.func,
  initialValues: PropTypes.object
};

export default BankForm;

