import { useState, useEffect } from 'react';
import { Button, App, Tag, Space } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import '../../styles/common.css';
import { apiService } from '../../services/api.jsx';

const IncidentManagement = () => {
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [incidents, setIncidents] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100'],
  });

  useEffect(() => {
    fetchIncidentData(1, pagination.pageSize);
  }, []);

  const fetchIncidentData = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      // Placeholder: Replace with actual API call when available
      // const response = await apiService.getIncidents();
      // if (response.success) {
      //   const incidents = Array.isArray(response.data) ? response.data : [];
      //   setIncidents(incidents);
      //   setPagination(prev => ({ ...prev, current: page, pageSize, total: incidents.length }));
      // }

      // For now, set placeholder data
      setIncidents([]);
      setPagination((prev) => ({ ...prev, current: page, pageSize, total: 0 }));
    } catch (error) {
      console.error('Error fetching incident data:', error);
      message.error('Failed to load incident data');
      setIncidents([]);
      setPagination((prev) => ({ ...prev, current: page, pageSize, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  const getStatusTag = (status) => {
    const normalized = String(status || '').toLowerCase();
    if (normalized.includes('resolved') || normalized.includes('closed')) return <Tag color="green">Resolved</Tag>;
    if (normalized.includes('pending')) return <Tag color="blue">Pending</Tag>;
    if (normalized.includes('open') || normalized.includes('in progress')) return <Tag color="orange">Open</Tag>;
    return <Tag>Unknown</Tag>;
  };

  const columns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => {
        const current = pagination.current || 1;
        const pageSize = pagination.pageSize || 15;
        return (current - 1) * pageSize + index + 1;
      },
    },
    {
      title: 'Incident #',
      dataIndex: 'incident_no',
      key: 'incident_no',
      width: 120,
      searchable: true,
      render: (_, record) => record.incident_no || record.incidentNo || record.id || '—',
    },
    {
      title: 'Title',
      dataIndex: 'title',
      key: 'title',
      searchable: true,
      render: (value) => value || '—',
    },
    {
      title: 'Category',
      dataIndex: 'category',
      key: 'category',
      width: 160,
      searchable: true,
      render: (_, record) => record.category || record.type || '—',
    },
    {
      title: 'Priority',
      dataIndex: 'priority',
      key: 'priority',
      width: 130,
      searchable: true,
      render: (value) => value || '—',
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 130,
      filters: [
        { text: 'Open', value: 'open' },
        { text: 'Pending', value: 'pending' },
        { text: 'Resolved', value: 'resolved' },
      ],
      onFilter: (value, record) => String(record.status || '').toLowerCase().includes(String(value).toLowerCase()),
      render: (value) => getStatusTag(value),
    },
    {
      title: 'Reported On',
      dataIndex: 'reported_at',
      key: 'reported_at',
      width: 180,
      render: (_, record) => {
        const raw = record.reported_at || record.reportedAt || record.created_at || record.createdAt;
        if (!raw) return '—';
        const d = new Date(raw);
        return Number.isNaN(d.getTime()) ? String(raw) : d.toLocaleString();
      },
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 120,
      align: 'center',
      render: () => (
        <Space>
          <Button
            type="primary"
            size="small"
            className="btn-standard-primary"
            onClick={() => message.info('Incident details view coming soon')}
          >
            View
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <div className="incident-management-container">
      <DataTable
        columns={columns}
        data={incidents}
        loading={loading}
        pagination={pagination}
        pageSize={pagination.pageSize}
        showSearch={true}
        showRefresh={false}
        onChange={(paginationInfo) => {
          if (paginationInfo) {
            const newPage = paginationInfo.current || 1;
            const newPageSize = Number(paginationInfo.pageSize) || 15;

            if (newPage !== pagination.current || newPageSize !== pagination.pageSize) {
              fetchIncidentData(newPage, newPageSize);
            }
          }
        }}
        rightAction={
          <Button
            type="primary"
            icon={<PlusOutlined />}
            size="large"
            className="btn-standard-primary"
            onClick={() => message.info('Report incident feature coming soon')}
          >
            Report Incident
          </Button>
        }
        searchPlaceholder="Search incidents..."
        rowKey={(record, idx) => record.id || record.incident_id || record.incidentId || record.incident_no || idx}
        size="middle"
        bordered={true}
      />
    </div>
  );
};

export default IncidentManagement;

