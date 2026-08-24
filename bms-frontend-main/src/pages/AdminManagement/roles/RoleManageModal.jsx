import { useCallback, useEffect, useState } from 'react';
import { Alert, App, Button, Form, Input, Modal, Switch } from 'antd';
import { SaveOutlined } from '@ant-design/icons';

import { apiService } from '../../../services/api.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import RoleModulesPanel from './RoleModulesPanel.jsx';
import { apiMessage, BRAND, BRAND_DARK, isRoleActive, normalizeRoleRecord } from './roleUtils.js';

const BrandModalHeader = ({ title, onClose }) => (
  <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
    <h2 className="m-0 text-sm font-semibold leading-none text-white">{title}</h2>
    <button
      type="button"
      aria-label="Close"
      onClick={onClose}
      className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
      </svg>
    </button>
  </div>
);

const SectionHeading = ({ children }) => (
  <h4
    className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
    style={{ color: BRAND }}
  >
    {children}
  </h4>
);

const RoleManageModal = ({ open, roleId, onClose, onSaved }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const isCreate = !roleId;

  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState(null);
  const [role, setRole] = useState(null);
  const [activeRoleId, setActiveRoleId] = useState(roleId ?? null);

  const resetState = useCallback(() => {
    form.resetFields();
    setRole(null);
    setActiveRoleId(null);
    setLoadError(null);
    setLoading(false);
    setSaving(false);
  }, [form]);

  const loadRole = useCallback(async (id) => {
    if (!id) return;
    setLoading(true);
    setLoadError(null);
    try {
      const response = await apiService.getRoleById(id);
      if (response.success && response.data) {
        const record = normalizeRoleRecord(response.data);
        setRole(record);
        setActiveRoleId(record.id);
        form.setFieldsValue({
          name: record.name || '',
          description: record.description || '',
          is_active: isRoleActive(record.is_active),
        });
      } else {
        setLoadError(apiMessage(response, 'Failed to load role'));
      }
    } catch (err) {
      console.error('Error loading role:', err);
      setLoadError('An error occurred while loading the role');
    } finally {
      setLoading(false);
    }
  }, [form]);

  useEffect(() => {
    if (!open) {
      resetState();
      return;
    }
    if (roleId) {
      loadRole(roleId);
    } else {
      resetState();
      form.setFieldsValue({ name: '', description: '', is_active: true });
    }
  }, [open, roleId, loadRole, resetState, form]);

  const handleClose = () => {
    if (saving) return;
    onClose();
  };

  const handleSubmit = async (values) => {
    setSaving(true);
    const payload = {
      name: String(values.name || '').trim(),
      description: String(values.description || '').trim(),
      is_active: values.is_active !== false,
    };

    try {
      if (isCreate && !activeRoleId) {
        const response = await apiService.createRole(payload);
        if (response.success && response.data) {
          const created = normalizeRoleRecord(response.data);
          setRole(created);
          setActiveRoleId(created.id);
          message.success(apiMessage(response, 'Role created successfully'));
          onSaved?.(created);
        } else {
          message.error(apiMessage(response, 'Failed to create role'));
        }
      } else {
        const id = activeRoleId || roleId;
        const response = await apiService.updateRole(id, payload);
        if (response.success) {
          message.success(apiMessage(response, 'Role updated successfully'));
          if (response.data) {
            const updated = normalizeRoleRecord(response.data);
            setRole(updated);
            onSaved?.(updated);
          } else {
            await loadRole(id);
            onSaved?.();
          }
        } else {
          message.error(apiMessage(response, 'Failed to update role'));
        }
      }
    } catch (err) {
      console.error('Error saving role:', err);
      message.error('An error occurred while saving the role');
    } finally {
      setSaving(false);
    }
  };

  const modalTitle = isCreate && !activeRoleId
    ? 'Create Role'
    : `Manage Role${role?.name ? `: ${role.name}` : ''}`;

  return (
    <Modal
      open={open}
      onCancel={handleClose}
      footer={null}
      width={920}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      maskClosable={!saving}
      keyboard={!saving}
      styles={{
        body: { padding: 0, maxHeight: '85vh', overflowY: 'auto' },
        content: { padding: 0, overflow: 'hidden' },
      }}
    >
      <BrandModalHeader title={modalTitle} onClose={handleClose} />

      {loading ? (
        <CollectionLoader size={64} compact />
      ) : (
        <>
          <div className="px-6 py-5">
            {loadError ? (
              <Alert type="error" showIcon message={loadError} className="mb-4" />
            ) : null}

            <SectionHeading>Role Details</SectionHeading>
            <Form
              form={form}
              layout="vertical"
              initialValues={{ is_active: true }}
              onFinish={handleSubmit}
              disabled={saving}
            >
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Form.Item
                  name="name"
                  label={
                    <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                      Name
                    </span>
                  }
                  rules={[
                    { required: true, message: 'Name is required' },
                    { max: 50, message: 'Maximum 50 characters' },
                  ]}
                >
                  <Input placeholder="e.g. Supervisor" maxLength={50} />
                </Form.Item>
                <Form.Item
                  name="is_active"
                  label={
                    <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                      Active
                    </span>
                  }
                  valuePropName="checked"
                >
                  <Switch checkedChildren="Active" unCheckedChildren="Inactive" />
                </Form.Item>
              </div>
              <Form.Item
                name="description"
                label={
                  <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Description
                  </span>
                }
                rules={[{ required: true, message: 'Description is required' }]}
              >
                <Input.TextArea rows={3} placeholder="What this role is for" />
              </Form.Item>
            </Form>

            {activeRoleId ? (
              <RoleModulesPanel
                embedded
                roleId={activeRoleId}
                roleName={role?.name || form.getFieldValue('name')}
              />
            ) : (
              <p className="mt-4 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                Save the role first, then assign bridge modules below.
              </p>
            )}
          </div>

          <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
            <Button
              type="primary"
              icon={<SaveOutlined />}
              loading={saving}
              onClick={() => form.submit()}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
              onMouseEnter={(e) => {
                if (!saving) {
                  e.currentTarget.style.backgroundColor = BRAND_DARK;
                  e.currentTarget.style.borderColor = BRAND_DARK;
                }
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
                e.currentTarget.style.borderColor = BRAND;
              }}
            >
              {isCreate && !activeRoleId ? 'Create Role' : 'Save Role'}
            </Button>
            <Button onClick={handleClose} disabled={saving}>
              Close
            </Button>
          </div>
        </>
      )}
    </Modal>
  );
};

export default RoleManageModal;
