import { useState, useEffect } from 'react';
import { Form, Select, Button, Row, Col, App, Input, Checkbox, InputNumber } from 'antd';
import PropTypes from 'prop-types';
import { apiService } from '../../../services/api.jsx';

const { Option } = Select;
const { TextArea } = Input;

const ModuleMenuForm = ({ onSubmit, onCancel, initialValues }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [fetchingModules, setFetchingModules] = useState(false);
  const [modules, setModules] = useState([]);

  // Fetch active modules
  const fetchModules = async () => {
    setFetchingModules(true);
    try {
      const response = await apiService.getActiveBmsModules();
      if (response.success && response.data) {
        const modulesData = Array.isArray(response.data) ? response.data : [];
        setModules(modulesData);
      }
    } catch (error) {
      console.error('Error fetching active modules:', error);
      message.error('Failed to load active modules');
    } finally {
      setFetchingModules(false);
    }
  };

  useEffect(() => {
    fetchModules();
  }, []);

  useEffect(() => {
    if (initialValues) {
      form.setFieldsValue({
        module_id: initialValues.module_id,
        menu_name: initialValues.menu_name,
        menu_path: initialValues.menu_path,
        menu_icon: initialValues.menu_icon,
        menu_description: initialValues.menu_description,
        parent_menu_id: initialValues.parent_menu_id,
        menu_order: initialValues.menu_order,
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
            name="module_id"
            label="Module"
            rules={[
              {
                required: true,
                message: 'Please select a module'
              }
            ]}
          >
            <Select
              placeholder="Select a module"
              loading={fetchingModules}
              showSearch
              filterOption={(input, option) =>
                (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
              }
              disabled={!!initialValues?.module_id} // Disable if editing existing menu
            >
              {modules.map((module) => (
                <Option key={module.id} value={module.id}>
                  {module.title || module.module_name || module.name || `Module ${module.id}`}
                </Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="menu_name"
            label="Menu Name"
            rules={[
              {
                required: true,
                message: 'Please enter menu name'
              }
            ]}
          >
            <Input placeholder="Enter menu name" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="menu_path"
            label="Menu Path"
            rules={[
              {
                required: true,
                message: 'Please enter menu path'
              }
            ]}
          >
            <Input placeholder="Enter menu path (e.g., /admin-management/module-menu)" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={12}>
          <Form.Item
            name="menu_icon"
            label="Menu Icon"
          >
            <Input placeholder="Enter icon name or class" />
          </Form.Item>
        </Col>
        <Col span={12}>
          <Form.Item
            name="menu_order"
            label="Menu Order"
            rules={[
              {
                type: 'number',
                message: 'Please enter a valid number'
              }
            ]}
          >
            <InputNumber 
              placeholder="Enter display order"
              style={{ width: '100%' }}
              min={0}
            />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="menu_description"
            label="Menu Description"
          >
            <TextArea 
              placeholder="Enter menu description" 
              rows={3}
            />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="parent_menu_id"
            label="Parent Menu (Optional)"
          >
            <Select
              placeholder="Select parent menu (leave empty for top-level menu)"
              allowClear
              showSearch
              filterOption={(input, option) =>
                (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
              }
            >
              {/* Parent menus will be loaded dynamically if needed */}
            </Select>
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

ModuleMenuForm.propTypes = {
  onSubmit: PropTypes.func,
  onCancel: PropTypes.func,
  initialValues: PropTypes.object
};

export default ModuleMenuForm;

