import { useState, useEffect } from 'react';
import { Form, Select, Button, Row, Col, App } from 'antd';
import PropTypes from 'prop-types';
import { apiService } from '../../../services/api.jsx';

const { Option } = Select;

const BridgeModuleRoleForm = ({ onSubmit, onCancel, initialValues }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [fetchingRoles, setFetchingRoles] = useState(false);
  const [fetchingModules, setFetchingModules] = useState(false);
  const [roles, setRoles] = useState([]);
  const [modules, setModules] = useState([]);

  // Fetch roles
  const fetchRoles = async () => {
    setFetchingRoles(true);
    try {
      const response = await apiService.getBmsRoles();
      if (response.success && response.data) {
        const rolesData = Array.isArray(response.data) ? response.data : [];
        // Filter only active roles
        const activeRoles = rolesData.filter(role => {
          const isActive = role.is_active === 1 || role.is_active === true || role.is_active === '1';
          return isActive;
        });
        setRoles(activeRoles);
      }
    } catch (error) {
      console.error('Error fetching roles:', error);
      message.error('Failed to load roles');
    } finally {
      setFetchingRoles(false);
    }
  };

  // Fetch active modules
  const fetchModules = async () => {
    setFetchingModules(true);
    try {
      const response = await apiService.getActiveBmsModules();
      if (response.success && response.data) {
        const modulesData = Array.isArray(response.data) ? response.data : [];
        // Filter only active modules
        const activeModules = modulesData.filter(module => {
          const isActive = module.is_active === 1 || module.is_active === true || module.is_active === '1';
          return isActive;
        });
        setModules(activeModules);
      }
    } catch (error) {
      console.error('Error fetching modules:', error);
      message.error('Failed to load modules');
    } finally {
      setFetchingModules(false);
    }
  };

  useEffect(() => {
    fetchRoles();
    fetchModules();
  }, []);

  useEffect(() => {
    if (initialValues) {
      form.setFieldsValue({
        role_ids: initialValues.role_ids || (initialValues.role_id ? [initialValues.role_id] : []),
        module_id: initialValues.module_id,
      });
    } else {
      form.resetFields();
    }
  }, [initialValues, form]);

  const handleSubmit = async (values) => {
    setLoading(true);
    try {
      if (onSubmit) {
        await onSubmit(values);
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
      initialValues={{ role_ids: [], ...initialValues }}
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
            >
              {modules.map((module) => (
                <Option key={module.id} value={module.id}>
                  {module.title || module.module_name || module.name || module.id}
                </Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="role_ids"
            label="Roles"
            rules={[
              {
                required: true,
                message: 'Please select at least one role'
              },
              {
                type: 'array',
                min: 1,
                message: 'Please select at least one role'
              }
            ]}
          >
            <Select
              mode="multiple"
              placeholder="Select roles (one module can have multiple roles)"
              loading={fetchingRoles}
              showSearch
              filterOption={(input, option) =>
                (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
              }
            >
              {roles.map((role) => (
                <Option key={role.id} value={role.id}>
                  {role.role_name || role.name}
                </Option>
              ))}
            </Select>
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

BridgeModuleRoleForm.propTypes = {
  onSubmit: PropTypes.func,
  onCancel: PropTypes.func,
  initialValues: PropTypes.object
};

export default BridgeModuleRoleForm;

