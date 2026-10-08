import { useEffect, useState } from 'react';
import { App, Button, DatePicker, Form, Select, Tag } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
import { BarChart3 } from 'lucide-react';
import { DataTable } from '../../common/data/index.jsx';
import { listAccountRequests } from './accountRequestStore.js';
import '../../styles/common.css';

const BRAND = '#962E32';

const REQUEST_TYPE_OPTIONS = [
  { label: 'Staff', value: 'Staff' },
  { label: 'Non-staff', value: 'Non-staff' },
];

const STATUS_OPTIONS = [
  { label: 'Submitted', value: 'Submitted' },
  { label: 'Approved', value: 'Approved' },
  { label: 'Rejected', value: 'Rejected' },
  { label: 'Granted', value: 'Granted' },
  { label: 'Revoked', value: 'Revoked' },
];

const getStatusTag = (status) => {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'granted') return <Tag color="green">Granted</Tag>;
  if (normalized === 'revoked') return <Tag color="orange">Revoked</Tag>;
  if (normalized === 'approved') return <Tag color="cyan">Approved</Tag>;
  if (normalized === 'rejected') return <Tag color="red">Rejected</Tag>;
  return <Tag color="blue">{status || 'Submitted'}</Tag>;
};

const formatRequestedRoles = (roles) => {
  if (Array.isArray(roles)) return roles.filter(Boolean).join(', ');
  return roles || '—';
};

const formatDate = (value) => {
  if (!value) return '—';
  const date = dayjs(value);
  return date.isValid() ? date.format('D MMM YYYY') : String(value);
};

const AccountReports = () => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(15);
  const [filters, setFilters] = useState({});

  const fetchReport = (nextPage = 1, nextSize = pageSize, nextFilters = filters) => {
    setLoading(true);
    const all = listAccountRequests().filter((request) => {
      if (nextFilters.requestType && request.requestType !== nextFilters.requestType) return false;
      if (nextFilters.status && String(request.status || '').toLowerCase() !== String(nextFilters.status).toLowerCase()) {
        return false;
      }
      if (nextFilters.fromDate || nextFilters.toDate) {
        const created = dayjs(request.created_at);
        if (!created.isValid()) return false;
        if (nextFilters.fromDate && created.isBefore(dayjs(nextFilters.fromDate), 'day')) return false;
        if (nextFilters.toDate && created.isAfter(dayjs(nextFilters.toDate), 'day')) return false;
      }
      return true;
    });
    setRows(all);
    setPage(nextPage);
    setPageSize(nextSize);
    setLoading(false);
  };

  useEffect(() => {
    fetchReport(1, pageSize, {});
  }, []);

  const handleSearch = async () => {
    const values = await form.validateFields();
    const nextFilters = {
      requestType: values.requestType,
      status: values.status,
      fromDate: values.period?.[0]?.format('YYYY-MM-DD'),
      toDate: values.period?.[1]?.format('YYYY-MM-DD'),
    };
    setFilters(nextFilters);
    fetchReport(1, pageSize, nextFilters);
  };

  const handleReset = () => {
    form.resetFields();
    setFilters({});
    fetchReport(1, pageSize, {});
  };

  const handleExport = () => {
    if (!rows.length) {
      message.warning('No report data to export');
      return;
    }
    setExporting(true);
    try {
      const exportData = rows.map((request, index) => ({
        'S.No': index + 1,
        'Request No': request.request_no || '',
        'Account Name': request.accountName || '',
        'Request Type': request.requestType || '',
        'Requested Role': formatRequestedRoles(request.requestedRole),
        'Module Name': request.moduleName || '',
        Status: request.status || '',
        'Requested By': request.requestedBy || '',
        Date: formatDate(request.created_at),
        Organization: request.organization || '',
      }));
      const workbook = XLSX.utils.book_new();
      const worksheet = XLSX.utils.json_to_sheet(exportData);
      XLSX.utils.book_append_sheet(workbook, worksheet, 'Account Requests');
      const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
      const filename = `account_requests_report_${timestamp}.xlsx`;
      const excelBuffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
      saveAs(
        new Blob([excelBuffer], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        }),
        filename
      );
      message.success('Report exported');
    } catch (error) {
      console.error('Export error:', error);
      message.error('Unable to export the report');
    } finally {
      setExporting(false);
    }
  };

  const columns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => (page - 1) * pageSize + index + 1,
    },
    {
      title: 'Request No',
      dataIndex: 'request_no',
      key: 'request_no',
      searchable: true,
    },
    {
      title: 'Account Name',
      dataIndex: 'accountName',
      key: 'accountName',
      searchable: true,
    },
    {
      title: 'Request Type',
      dataIndex: 'requestType',
      key: 'requestType',
      searchable: true,
      width: 140,
    },
    {
      title: 'Requested Role',
      dataIndex: 'requestedRole',
      key: 'requestedRole',
      searchable: true,
      render: (roles) => formatRequestedRoles(roles),
    },
    {
      title: 'Module Name',
      dataIndex: 'moduleName',
      key: 'moduleName',
      searchable: true,
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 130,
      render: (status) => getStatusTag(status),
    },
    {
      title: 'Requested By',
      dataIndex: 'requestedBy',
      key: 'requestedBy',
      searchable: true,
    },
    {
      title: 'Date',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 140,
      render: (value) => formatDate(value),
    },
  ];

  return (
    <div>
      <div className="mb-4 flex items-center gap-2">
        <BarChart3 size={20} color={BRAND} />
        <h1 className="m-0 text-lg font-semibold text-slate-900">Manage Reports</h1>
      </div>

      <div className="mb-4 rounded-lg border border-slate-200 bg-white p-4">
        <h4
          className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
          style={{ color: BRAND }}
        >
          Account Requests Report
        </h4>
        <Form form={form} layout="vertical" requiredMark={false}>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
            <Form.Item
              name="requestType"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Request Type</span>}
              className="mb-0"
            >
              <Select allowClear placeholder="All request types" options={REQUEST_TYPE_OPTIONS} />
            </Form.Item>
            <Form.Item
              name="status"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Status</span>}
              className="mb-0"
            >
              <Select allowClear placeholder="All statuses" options={STATUS_OPTIONS} />
            </Form.Item>
            <Form.Item
              name="period"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Request Dates</span>}
              className="mb-0"
            >
              <DatePicker.RangePicker className="w-full" format="YYYY-MM-DD" />
            </Form.Item>
          </div>
          <div className="mt-4 flex justify-end gap-2">
            <Button type="primary" className="btn-standard-primary" onClick={handleSearch}>
              Generate
            </Button>
            <Button onClick={handleReset}>Reset</Button>
          </div>
        </Form>
      </div>

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        pagination={{
          current: page,
          pageSize,
          total: rows.length,
          showTotal: () => null,
          showQuickJumper: false,
          onChange: (nextPage, nextSize) => {
            fetchReport(nextPage, nextSize, filters);
          },
        }}
        showSearch
        showRefresh={false}
        searchPlaceholder="Search account reports..."
        rowKey={(record, index) => record.id || record.request_no || index}
        rightAction={
          <Button
            icon={<DownloadOutlined />}
            loading={exporting}
            disabled={!rows.length}
            onClick={handleExport}
          >
            Export
          </Button>
        }
      />
    </div>
  );
};

export default AccountReports;
