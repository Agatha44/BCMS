import { useCallback, useEffect, useState } from 'react';
import { App, Button, Card, Form, Input, Modal, Switch, Tag, Tooltip } from 'antd';
import { PlusOutlined, SettingOutlined } from '@ant-design/icons';
import DataTable from '../../common/data/DataTable.jsx';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import BrandModalHeader from '../CollectionManagement/sod/components/BrandModalHeader.jsx';
import { apiService } from '../../services/api.jsx';
import ReportEngineManageModal from '../../modules/report-engine/components/ReportEngineManageModal.jsx';
import '../../styles/common.css';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

function FormLabel({ children }) {
  return (
    <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {children}
    </span>
  );
}

const ReportEngineManagement = () => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [registrations, setRegistrations] = useState([]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [manageOpen, setManageOpen] = useState(false);
  const [managingRecord, setManagingRecord] = useState(null);

  const fetchRegistrations = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiService.getReportEngineRegistrations();
      if (response?.success) {
        setRegistrations(Array.isArray(response.data) ? response.data : []);
      } else {
        message.error(response?.message || 'Failed to load registrations');
      }
    } catch (err) {
      message.error(err?.message || 'Failed to load registrations');
    } finally {
      setLoading(false);
    }
  }, [message]);

  useEffect(() => {
    fetchRegistrations();
  }, [fetchRegistrations]);

  const openCreate = () => {
    form.resetFields();
    form.setFieldsValue({ menu_label: 'Reports', is_active: true });
    setCreateOpen(true);
  };

  const handleCreate = async (values) => {
    setSubmitting(true);
    try {
      const response = await apiService.createReportEngineRegistration(values);
      if (response?.success) {
        message.success('Module registration created');
        setCreateOpen(false);
        fetchRegistrations();
      } else {
        message.error(response?.message || 'Create failed');
      }
    } catch (err) {
      message.error(err?.message || 'Create failed');
    } finally {
      setSubmitting(false);
    }
  };

  const columns = [
    {
      title: 'S/N',
      key: 'sn',
      width: 56,
      align: 'center',
      render: (_, __, index) => index + 1,
    },
    {
      title: 'Menu Label',
      dataIndex: 'menu_label',
      key: 'menu_label',
    },
    {
      title: 'Route Slug',
      dataIndex: 'route_slug',
      key: 'route_slug',
      render: (slug) => <code className="text-xs">{slug}</code>,
    },
    {
      title: 'Path Prefix',
      dataIndex: 'module_path_prefix',
      key: 'module_path_prefix',
      ellipsis: true,
      render: (path) => (
        <Tooltip title={path}>
          <span className="text-xs text-slate-600">{path}</span>
        </Tooltip>
      ),
    },
    {
      title: 'Reports',
      dataIndex: 'reports_count',
      key: 'reports_count',
      width: 90,
      align: 'center',
    },
    {
      title: 'Status',
      dataIndex: 'is_active',
      key: 'is_active',
      width: 90,
      render: (active) => (active ? <Tag color="success">Active</Tag> : <Tag>Inactive</Tag>),
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 120,
      render: (_, record) => (
        <Button
          size="small"
          icon={<SettingOutlined />}
          onClick={() => {
            setManagingRecord(record);
            setManageOpen(true);
          }}
        >
          Manage
        </Button>
      ),
    },
  ];

  return (
    <Card className="report-engine-admin-container">
      <div className="relative">
        {loading ? (
          <div
            className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
            style={{ top: '4.75rem' }}
          >
            <CollectionLoader />
          </div>
        ) : null}
      <DataTable
        columns={columns}
        data={registrations}
        loading={false}
        showSearch
        showRefresh
        onRefresh={fetchRegistrations}
        rowKey="id"
        rightAction={
          <Button
            type="primary"
            icon={<PlusOutlined />}
            onClick={openCreate}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
          >
            Register Module
          </Button>
        }
        pagination={{
          pageSize: 15,
          showTotal: () => null,
          showQuickJumper: false,
        }}
      />
      </div>

      <Modal
        open={createOpen}
        onCancel={() => setCreateOpen(false)}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        width={560}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Register Report Module" onClose={() => setCreateOpen(false)} />
        <Form form={form} layout="vertical" onFinish={handleCreate} className="px-6 py-4">
          <Form.Item name="menu_label" label={<FormLabel>Menu Label</FormLabel>} rules={[{ required: true }]}>
            <Input placeholder="Reports" />
          </Form.Item>
          <Form.Item name="route_slug" label={<FormLabel>Route Slug</FormLabel>} rules={[{ required: true }]}>
            <Input placeholder="collection-management" />
          </Form.Item>
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
            <Switch defaultChecked />
          </Form.Item>
        </Form>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3 dark:border-slate-600 dark:bg-slate-900">
          <Button
            type="primary"
            loading={submitting}
            onClick={() => form.submit()}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => {
              e.currentTarget.style.backgroundColor = BRAND_DARK;
              e.currentTarget.style.borderColor = BRAND_DARK;
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
              e.currentTarget.style.borderColor = BRAND;
            }}
          >
            Create
          </Button>
          <Button onClick={() => setCreateOpen(false)}>Close</Button>
        </div>
      </Modal>

      <ReportEngineManageModal
        open={manageOpen}
        registration={managingRecord}
        onClose={() => {
          setManageOpen(false);
          setManagingRecord(null);
        }}
        onUpdated={fetchRegistrations}
      />
    </Card>
  );
};

export default ReportEngineManagement;
