import { useCallback, useEffect, useMemo, useState } from 'react';
import { App, Button, Form, Input, Modal, Segmented, Switch, Tag, Tooltip } from 'antd';
import { EditOutlined, EyeOutlined, PlusOutlined, SettingOutlined } from '@ant-design/icons';
import DataTable from '../../../common/data/DataTable.jsx';
import BrandModalHeader from '../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import { apiService } from '../../../services/api.jsx';
import ReportDefinitionModal from './ReportDefinitionModal.jsx';
import ReportDefinitionViewModal from './ReportDefinitionViewModal.jsx';
import {
  REPORT_ENGINE_MANAGE_MODAL_CSS,
  REPORT_ENGINE_MANAGE_MODAL_WIDTH,
  REPORT_ENGINE_MODAL_FOOTER_STYLE,
} from './reportEngineModal.styles.js';
import { REPORT_CATEGORY_OPTIONS } from '../constants.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

function FormLabel({ children }) {
  return (
    <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {children}
    </span>
  );
}

export default function ReportEngineManageModal({ open, registration, onClose, onUpdated }) {
  const { message, modal } = App.useApp();
  const [form] = Form.useForm();
  const [activeTab, setActiveTab] = useState('reports');
  const [definitions, setDefinitions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [definitionModalOpen, setDefinitionModalOpen] = useState(false);
  const [editingDefinition, setEditingDefinition] = useState(null);
  const [loadingDefinitionId, setLoadingDefinitionId] = useState(null);
  const [viewModalOpen, setViewModalOpen] = useState(false);
  const [viewingDefinition, setViewingDefinition] = useState(null);
  const [loadingViewId, setLoadingViewId] = useState(null);

  const categoryLabel = useMemo(() => {
    const map = Object.fromEntries(REPORT_CATEGORY_OPTIONS.map((o) => [o.value, o.label]));
    return (key) => map[key] ?? key;
  }, []);

  const fetchDefinitions = useCallback(async () => {
    if (!registration?.id) return;
    setLoading(true);
    try {
      const response = await apiService.getReportEngineDefinitions(registration.id);
      if (response?.success) {
        setDefinitions(Array.isArray(response.data) ? response.data : []);
      } else {
        message.error(response?.message || 'Failed to load report definitions');
      }
    } catch (err) {
      message.error(err?.message || 'Failed to load report definitions');
    } finally {
      setLoading(false);
    }
  }, [registration?.id, message]);

  useEffect(() => {
    if (!open || !registration) return;
    form.setFieldsValue({
      menu_label: registration.menu_label,
      route_slug: registration.route_slug,
      module_path_prefix: registration.module_path_prefix,
      description: registration.description,
      is_active: registration.is_active !== false,
    });
    setActiveTab('reports');
    fetchDefinitions();
  }, [open, registration, form, fetchDefinitions]);

  const handleSaveRegistration = async (values) => {
    if (!registration?.id) return;
    setSubmitting(true);
    try {
      const response = await apiService.updateReportEngineRegistration(registration.id, values);
      if (response?.success) {
        message.success('Module registration updated');
        onUpdated?.();
      } else {
        message.error(response?.message || 'Update failed');
      }
    } catch (err) {
      message.error(err?.message || 'Update failed');
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveDefinition = async (payload) => {
    setSubmitting(true);
    try {
      const response = editingDefinition?.definition_id
        ? await apiService.updateReportEngineDefinition(editingDefinition.definition_id, payload)
        : await apiService.createReportEngineDefinition(registration.id, payload);
      if (response?.success) {
        message.success(editingDefinition ? 'Report updated' : 'Report created');
        setDefinitionModalOpen(false);
        setEditingDefinition(null);
        fetchDefinitions();
        onUpdated?.();
      } else {
        message.error(response?.message || 'Save failed');
      }
    } catch (err) {
      message.error(err?.message || 'Save failed');
    } finally {
      setSubmitting(false);
    }
  };

  const fetchDefinitionById = async (definitionId) => {
    const response = await apiService.getReportEngineDefinition(definitionId);
    if (response?.success) {
      return response.data;
    }
    throw new Error(response?.message || 'Failed to load report definition');
  };

  const openDefinitionForEdit = async (record) => {
    if (!record?.definition_id) return;
    setLoadingDefinitionId(record.definition_id);
    try {
      const data = await fetchDefinitionById(record.definition_id);
      setEditingDefinition(data);
      setDefinitionModalOpen(true);
    } catch (err) {
      message.error(err?.message || 'Failed to load report definition');
    } finally {
      setLoadingDefinitionId(null);
    }
  };

  const openDefinitionForView = async (record) => {
    if (!record?.definition_id) return;
    setViewModalOpen(true);
    setViewingDefinition(null);
    setLoadingViewId(record.definition_id);
    try {
      const data = await fetchDefinitionById(record.definition_id);
      setViewingDefinition(data);
    } catch (err) {
      message.error(err?.message || 'Failed to load report definition');
      setViewModalOpen(false);
    } finally {
      setLoadingViewId(null);
    }
  };

  const handleEditFromView = (definition) => {
    setViewModalOpen(false);
    setViewingDefinition(null);
    setEditingDefinition(definition);
    setDefinitionModalOpen(true);
  };

  const handleDeleteDefinition = (record) => {
    modal.confirm({
      title: 'Delete report definition?',
      content: `Remove "${record.name ?? record.label}" from the catalog?`,
      okText: 'Delete',
      okButtonProps: { danger: true },
      onOk: async () => {
        const response = await apiService.deleteReportEngineDefinition(record.definition_id);
        if (response?.success) {
          message.success('Report deleted');
          fetchDefinitions();
          onUpdated?.();
        } else {
          message.error(response?.message || 'Delete failed');
        }
      },
    });
  };

  const definitionColumns = [
    {
      title: 'S/N',
      key: 'sn',
      width: 56,
      align: 'center',
      render: (_, __, index) => index + 1,
    },
    {
      title: 'Report Name',
      dataIndex: 'name',
      key: 'name',
      render: (name, record) => (
        <Tooltip title={record.description}>
          <span>{name ?? record.label}</span>
        </Tooltip>
      ),
    },
    {
      title: 'Category',
      dataIndex: 'category_key',
      key: 'category_key',
      width: 120,
      render: (key) => <Tag>{categoryLabel(key)}</Tag>,
    },
    {
      title: 'Script',
      dataIndex: 'script',
      key: 'script',
      width: 200,
      ellipsis: true,
      render: (script) => (
        <Tag style={{ fontFamily: 'monospace', fontSize: 12 }}>{script ?? '—'}</Tag>
      ),
    },
    {
      title: 'Status',
      key: 'status',
      width: 90,
      render: (_, record) =>
        record.deleted_at ? (
          <Tag color="default">Deleted</Tag>
        ) : record.is_active ? (
          <Tag color="success">Active</Tag>
        ) : (
          <Tag color="warning">Inactive</Tag>
        ),
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 220,
      render: (_, record) => (
        <div className="flex flex-wrap gap-1">
          <Button
            size="small"
            icon={<EyeOutlined />}
            loading={loadingViewId === record.definition_id}
            onClick={() => openDefinitionForView(record)}
          >
            View
          </Button>
          <Button
            size="small"
            icon={<EditOutlined />}
            loading={loadingDefinitionId === record.definition_id}
            onClick={() => openDefinitionForEdit(record)}
          >
            Edit
          </Button>
          {record.deleted_at ? (
            <Button
              size="small"
              onClick={async () => {
                const response = await apiService.restoreReportEngineDefinition(record.definition_id);
                if (response?.success) {
                  message.success('Report restored');
                  fetchDefinitions();
                }
              }}
            >
              Restore
            </Button>
          ) : (
            <Button size="small" danger onClick={() => handleDeleteDefinition(record)}>
              Delete
            </Button>
          )}
        </div>
      ),
    },
  ];

  const modalFooter = (
    <div className="report-engine-modal-footer" style={REPORT_ENGINE_MODAL_FOOTER_STYLE}>
      {activeTab === 'settings' ? (
        <Button
          type="primary"
          loading={submitting}
          icon={<SettingOutlined />}
          style={{ backgroundColor: BRAND, borderColor: BRAND }}
          onMouseEnter={(e) => {
            e.currentTarget.style.backgroundColor = BRAND_DARK;
            e.currentTarget.style.borderColor = BRAND_DARK;
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.backgroundColor = BRAND;
            e.currentTarget.style.borderColor = BRAND;
          }}
          onClick={() => form.submit()}
        >
          Save Settings
        </Button>
      ) : (
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingDefinition(null);
            setDefinitionModalOpen(true);
          }}
          style={{ backgroundColor: BRAND, borderColor: BRAND }}
        >
          Add Report
        </Button>
      )}
      <Button onClick={onClose}>Close</Button>
    </div>
  );

  return (
    <>
      <style>{REPORT_ENGINE_MANAGE_MODAL_CSS}</style>
      <Modal
        open={open}
        onCancel={onClose}
        footer={modalFooter}
        centered
        destroyOnClose
        title={null}
        closable={false}
        width={REPORT_ENGINE_MANAGE_MODAL_WIDTH}
        style={{ maxWidth: '96vw', top: 24 }}
        className="brand-modal report-engine-manage-modal"
        styles={{
          body: { padding: 0, overflow: 'hidden' },
          content: { padding: 0, overflow: 'hidden' },
          footer: {
            margin: 0,
            padding: '12px 24px',
            backgroundColor: '#ffffff',
            borderTop: '1px solid #e2e8f0',
          },
        }}
      >
        <BrandModalHeader
          title={`Manage Module — ${registration?.menu_label ?? registration?.route_slug ?? ''}`}
          onClose={onClose}
        />

        <div className="px-6 py-4">
          <Segmented
            value={activeTab}
            onChange={setActiveTab}
            options={[
              { label: 'Reports', value: 'reports' },
              { label: 'Settings', value: 'settings' },
            ]}
            className="mb-4"
          />

          {activeTab === 'settings' ? (
            <Form form={form} layout="vertical" onFinish={handleSaveRegistration}>
              <div className="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <Form.Item name="menu_label" label={<FormLabel>Menu Label</FormLabel>} rules={[{ required: true }]}>
                  <Input />
                </Form.Item>
                <Form.Item name="route_slug" label={<FormLabel>Route Slug</FormLabel>} rules={[{ required: true }]}>
                  <Input />
                </Form.Item>
              </div>
              <Form.Item
                name="module_path_prefix"
                label={<FormLabel>Module Path Prefix</FormLabel>}
                rules={[{ required: true }]}
              >
                <Input placeholder="/collection-management/collection-reports" />
              </Form.Item>
              <Form.Item name="description" label={<FormLabel>Description</FormLabel>}>
                <Input.TextArea rows={2} />
              </Form.Item>
              <Form.Item name="is_active" label={<FormLabel>Active</FormLabel>} valuePropName="checked">
                <Switch />
              </Form.Item>
            </Form>
          ) : (
            <DataTable
              columns={definitionColumns}
              data={definitions}
              loading={loading}
              showSearch
              showRefresh
              onRefresh={fetchDefinitions}
              rowKey={(row) => row.definition_id ?? row.id ?? row.script}
              pagination={{
                pageSize: 10,
                showTotal: () => null,
                showQuickJumper: false,
              }}
            />
          )}
        </div>
      </Modal>

      <ReportDefinitionViewModal
        open={viewModalOpen}
        onClose={() => {
          setViewModalOpen(false);
          setViewingDefinition(null);
        }}
        definition={viewingDefinition}
        loading={Boolean(loadingViewId)}
        onEdit={handleEditFromView}
        moduleSlug={registration?.route_slug ?? 'collection-management'}
      />

      <ReportDefinitionModal
        open={definitionModalOpen}
        onClose={() => {
          setDefinitionModalOpen(false);
          setEditingDefinition(null);
        }}
        onSubmit={handleSaveDefinition}
        submitting={submitting}
        initialValues={editingDefinition}
      />
    </>
  );
}
