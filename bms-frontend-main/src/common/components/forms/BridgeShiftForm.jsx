import { useEffect, useState } from 'react';
import { Form, Input, TimePicker, Switch, Button, Row, Col, App } from 'antd';
import PropTypes from 'prop-types';
import dayjs from 'dayjs';

const BridgeShiftForm = ({ onSubmit, onCancel, initialValues }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (initialValues) {
      form.setFieldsValue({
        shift_name: initialValues.shift_name,
        start_time: initialValues.start_time ? dayjs(initialValues.start_time, 'HH:mm') : null,
        end_time: initialValues.end_time ? dayjs(initialValues.end_time, 'HH:mm') : null,
        is_active:
          initialValues.is_active === 1 ||
          initialValues.is_active === true ||
          initialValues.is_active === '1',
      });
    } else {
      form.resetFields();
    }
  }, [initialValues, form]);

  const handleSubmit = async (values) => {
    setLoading(true);
    try {
      const payload = {
        shift_name: values.shift_name,
        start_time: values.start_time ? values.start_time.format('HH:mm') : null,
        end_time: values.end_time ? values.end_time.format('HH:mm') : null,
        is_active: values.is_active ? 1 : 0,
      };

      if (onSubmit) {
        await onSubmit(payload);
      }
    } catch (error) {
      console.error('BridgeShiftForm submission error:', error);
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
            name="shift_name"
            label="Shift Name"
            rules={[
              {
                required: true,
                message: 'Please enter shift name',
              },
            ]}
          >
            <Input placeholder="Enter shift name" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={12}>
          <Form.Item
            name="start_time"
            label="Start Time"
            rules={[
              {
                required: true,
                message: 'Please select start time',
              },
            ]}
          >
            <TimePicker style={{ width: '100%' }} format="HH:mm" />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            name="end_time"
            label="End Time"
            rules={[
              {
                required: true,
                message: 'Please select end time',
              },
            ]}
          >
            <TimePicker style={{ width: '100%' }} format="HH:mm" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="is_active"
            label="Status"
            valuePropName="checked"
            initialValue={true}
          >
            <Switch checkedChildren="Active" unCheckedChildren="Inactive" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item style={{ marginBottom: 0 }}>
            <div
              style={{
                width: '100%',
                display: 'flex',
                justifyContent: 'flex-end',
                gap: '8px',
              }}
            >
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
                  borderColor: '#962E32',
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

BridgeShiftForm.propTypes = {
  onSubmit: PropTypes.func,
  onCancel: PropTypes.func,
  initialValues: PropTypes.object,
};

export default BridgeShiftForm;


