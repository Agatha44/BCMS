import { useState, useEffect } from 'react';
import { Form, Select, Button, Row, Col, App } from 'antd';
import PropTypes from 'prop-types';
import { apiService } from '../../../services/api.jsx';

const { Option } = Select;

const RoleModuleMenuForm = ({ onSubmit, onCancel, initialValues }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [fetchingRoles, setFetchingRoles] = useState(false);
  const [fetchingModuleMenus, setFetchingModuleMenus] = useState(false);
  const [fetchingModules, setFetchingModules] = useState(false);
  const [roles, setRoles] = useState([]);
  const [moduleMenus, setModuleMenus] = useState([]);
  const [modules, setModules] = useState([]);
  const [selectedModuleId, setSelectedModuleId] = useState(null);

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
      // Use active modules endpoint: /bridge-modules/active
      const response = await apiService.getActiveBmsModules();
      if (response.success && response.data) {
        const modulesData = Array.isArray(response.data) ? response.data : [];
        // Only keep active modules
        const activeModules = modulesData.filter(module => {
          const isActive = module.is_active === 1 || module.is_active === true || module.is_active === '1';
          return isActive;
        });
        setModules(activeModules);
      }
    } catch (error) {
      console.error('Error fetching modules:', error);
    } finally {
      setFetchingModules(false);
    }
  };

  // Fetch active module menus for a specific module
  const fetchModuleMenus = async (moduleId) => {
    if (!moduleId) {
      setModuleMenus([]);
      return;
    }
    setFetchingModuleMenus(true);
    try {
      // Use active menus endpoint: /bridge-module-menus/active and filter by module
      const response = await apiService.getActiveBmsModuleMenus({ module_id: moduleId });
      if (response.success && response.data) {
        const menusData = Array.isArray(response.data) ? response.data : [];
        setModuleMenus(menusData);
      }
    } catch (error) {
      console.error('Error fetching module menus:', error);
      message.error('Failed to load module menus');
    } finally {
      setFetchingModuleMenus(false);
    }
  };

  useEffect(() => {
    fetchRoles();
    fetchModules();
  }, []);

  // Refetch menus whenever the selected module changes
  useEffect(() => {
    if (selectedModuleId) {
      fetchModuleMenus(selectedModuleId);
    } else {
      setModuleMenus([]);
    }
  }, [selectedModuleId]);

  useEffect(() => {
    if (initialValues) {
      form.setFieldsValue({
        role_id: initialValues.role_id,
        menu_id: initialValues.menu_id || [],
        module_id: initialValues.module_id || null,
      });

      if (initialValues.module_id) {
        setSelectedModuleId(initialValues.module_id);
      }
    } else {
      // Only reset fields when initialValues changes from a value to null/undefined
      // Don't reset when moduleMenus changes, as that would clear user selections
      form.resetFields();
      setSelectedModuleId(null);
    }
  }, [initialValues, form]);

  // Separate effect to infer module from menu IDs when moduleMenus becomes available (for edit mode)
  useEffect(() => {
    if (initialValues && !initialValues.module_id && initialValues.menu_id && initialValues.menu_id.length > 0 && moduleMenus.length > 0) {
      const firstMenuId = initialValues.menu_id[0];
      const firstMenu = moduleMenus.find(menu => menu.id === firstMenuId);
      if (firstMenu && firstMenu.module_id) {
        setSelectedModuleId(firstMenu.module_id);
        form.setFieldsValue({ module_id: firstMenu.module_id });
      }
    }
  }, [moduleMenus, initialValues, form]);

  const handleSubmit = async (values) => {
    setLoading(true);
    try {
      if (onSubmit) {
        // module_id is only used for filtering menus in the UI – do not send it to the API
        const { module_id, ...payload } = values;
        await onSubmit(payload);
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
      initialValues={{ menu_id: [], ...initialValues }}
      autoComplete="off"
      style={{ textAlign: 'left' }}
    >
      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="role_id"
            label="Role"
            rules={[
              {
                required: true,
                message: 'Please select a role'
              }
            ]}
          >
            <Select
              placeholder="Select a role"
              loading={fetchingRoles}
              showSearch
              filterOption={(input, option) =>
                (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
              }
              disabled={!!initialValues?.role_id} // Disable if editing existing assignment
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
              allowClear
              onChange={(value) => {
                setSelectedModuleId(value || null);
                // Clear previously selected menus whenever module changes
                form.setFieldsValue({ menu_id: [] });
              }}
              value={selectedModuleId}
              filterOption={(input, option) =>
                (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
              }
            >
              {modules.map((module) => (
                <Option key={module.id} value={module.id}>
                  {module.title}
                </Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Form.Item
            name="menu_id"
            label="Module Menus"
            rules={[
              {
                required: true,
                message: 'Please select at least one module menu'
              },
              {
                type: 'array',
                min: 1,
                message: 'Please select at least one module menu'
              }
            ]}
          >
            <Select
              mode="multiple"
              placeholder="Select module menus (one role can have multiple module menus)"
              loading={fetchingModuleMenus}
              disabled={!selectedModuleId}
              showSearch
              filterOption={(input, option) =>
                (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
              }
            >
              {moduleMenus
                .filter((menu) => !selectedModuleId || menu.module_id === selectedModuleId)
                .map((menu) => {
                  const module = modules.find(m => m.id === menu.module_id);
                  const moduleName = module ? (module.title) : `Module ${menu.module_id}`;
                  return (
                    <Option key={menu.id} value={menu.id}>
                      {menu.menu_name || menu.name} {menu.module_id ? `(${moduleName})` : ''}
                    </Option>
                  );
                })}
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

RoleModuleMenuForm.propTypes = {
  onSubmit: PropTypes.func,
  onCancel: PropTypes.func,
  initialValues: PropTypes.object
};

export default RoleModuleMenuForm;

