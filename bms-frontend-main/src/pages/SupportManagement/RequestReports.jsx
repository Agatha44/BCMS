import { useEffect, useState } from 'react';
import { App, Button, DatePicker, Form, Modal, Select, Tabs, Tag } from 'antd';
import { EyeOutlined } from '@ant-design/icons';
import { BarChart3, FileText } from 'lucide-react';
import dayjs from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import { DataTable } from '../../common/data/index.jsx';
import BrandModalHeader from '../CollectionManagement/sod/components/BrandModalHeader.jsx';
import { listSupportRequests } from './supportRequestStore.js';
import '../../styles/common.css';

dayjs.extend(isoWeek);

const BRAND = '#962E32';
const INFRASTRUCTURE_REQUEST_TYPE = 'Infrastructure/hardware';

const PERIOD_TABS = [
  { key: 'weekly', label: 'Weekly' },
  { key: 'monthly', label: 'Monthly' },
  { key: 'yearly', label: 'Yearly' },
];

const REQUEST_TYPE_OPTIONS = [
  { label: 'Infrastructure/hardware', value: 'Infrastructure/hardware' },
  { label: 'System/Software', value: 'System/Software' },
];

const STATUS_OPTIONS = [
  { label: 'Opened', value: 'Opened' },
  { label: 'Verified', value: 'Verified' },
  { label: 'Assigned', value: 'Assigned' },
  { label: 'Reopened', value: 'Reopened' },
  { label: 'Closed', value: 'Closed' },
  { label: 'Resolved', value: 'Resolved' },
  { label: 'On Hold', value: 'On Hold' },
];

const getStatusTag = (status) => {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'resolved') return <Tag color="green">Resolved</Tag>;
  if (normalized === 'assigned') return <Tag color="purple">Assigned</Tag>;
  if (normalized === 'verified') return <Tag color="cyan">Verified</Tag>;
  if (normalized === 'closed') return <Tag color="default">Closed</Tag>;
  if (normalized === 'reopened') return <Tag color="orange">Reopened</Tag>;
  if (normalized === 'opened') return <Tag color="blue">Opened</Tag>;
  return <Tag>{status || 'Opened'}</Tag>;
};

const getPriorityTag = (priority) => {
  const normalized = String(priority || '').toLowerCase();
  if (normalized === 'high') return <Tag color="red">High</Tag>;
  if (normalized === 'medium') return <Tag color="orange">Medium</Tag>;
  if (normalized === 'low') return <Tag color="blue">Low</Tag>;
  return <Tag>{priority || '—'}</Tag>;
};

const Field = ({ label, value }) => (
  <div className="min-w-0 py-1.5">
    <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <div className="mt-0.5 text-sm font-medium text-black">
      {value ?? <span className="text-slate-400">N/A</span>}
    </div>
  </div>
);

const Section = ({ title, children }) => (
  <section className="mb-5 last:mb-0">
    <h4
      className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
      style={{ color: BRAND }}
    >
      {title}
    </h4>
    {children}
  </section>
);

const formatDateTime = (value) => {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const isImageType = (type = '', name = '') =>
  String(type).startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp)$/i.test(name);

const isPdfType = (type = '', name = '') =>
  String(type) === 'application/pdf' || /\.pdf$/i.test(name);

const getPeriodBounds = (periodType, periodDate) => {
  if (!periodDate) return null;
  const date = dayjs(periodDate);
  if (!date.isValid()) return null;
  if (periodType === 'weekly') {
    return { start: date.startOf('isoWeek'), end: date.endOf('isoWeek') };
  }
  if (periodType === 'yearly') {
    return { start: date.startOf('year'), end: date.endOf('year') };
  }
  return { start: date.startOf('month'), end: date.endOf('month') };
};

const formatPeriodLabel = (periodType, periodDate) => {
  const bounds = getPeriodBounds(periodType, periodDate);
  if (!bounds) return '';
  if (periodType === 'weekly') {
    return `${bounds.start.format('DD MMM YYYY')} – ${bounds.end.format('DD MMM YYYY')}`;
  }
  if (periodType === 'yearly') {
    return bounds.start.format('YYYY');
  }
  return bounds.start.format('MMMM YYYY');
};

const getPeriodPickerProps = (periodType) => {
  if (periodType === 'weekly') {
    return { picker: 'week', format: '[Week] w, YYYY', placeholder: 'Select week' };
  }
  if (periodType === 'yearly') {
    return { picker: 'year', format: 'YYYY', placeholder: 'Select year' };
  }
  return { picker: 'month', format: 'MMMM YYYY', placeholder: 'Select month' };
};

const filterReportRows = (requests, { reportPeriod, periodDate, requestType, status }) => {
  const bounds = getPeriodBounds(reportPeriod, periodDate);
  return requests.filter((request) => {
    if (requestType && (request.requestType || request.source) !== requestType) return false;
    if (status && String(request.status || '').toLowerCase() !== String(status).toLowerCase()) {
      return false;
    }
    if (!bounds) return true;
    const created = dayjs(request.created_at);
    if (!created.isValid()) return false;
    return created.valueOf() >= bounds.start.valueOf() && created.valueOf() <= bounds.end.valueOf();
  });
};

const RequestReports = () => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(15);
  const [total, setTotal] = useState(0);
  const [selected, setSelected] = useState(null);
  const [showDocumentPreview, setShowDocumentPreview] = useState(false);
  const [reportPeriod, setReportPeriod] = useState('monthly');
  const [appliedFilters, setAppliedFilters] = useState({
    reportPeriod: 'monthly',
    periodDate: dayjs(),
  });

  const fetchReport = async (nextPage = 1, nextSize = pageSize, nextFilters = appliedFilters) => {
    setLoading(true);
    try {
      const filtered = filterReportRows(listSupportRequests(), nextFilters);
      setRows(filtered);
      setPage(nextPage);
      setPageSize(nextSize);
      setTotal(filtered.length);
    } catch (error) {
      message.error(error?.message || 'Failed to load request report');
      setRows([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    form.setFieldsValue({ periodDate: dayjs() });
    fetchReport(1, pageSize, appliedFilters);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleSearch = async () => {
    const values = await form.validateFields();
    const nextFilters = {
      reportPeriod,
      periodDate: values.periodDate || dayjs(),
      requestType: values.requestType,
      status: values.status,
    };
    setAppliedFilters(nextFilters);
    fetchReport(1, pageSize, nextFilters);
  };

  const handleReset = () => {
    const nextFilters = {
      reportPeriod,
      periodDate: dayjs(),
    };
    form.resetFields();
    form.setFieldsValue({ periodDate: nextFilters.periodDate });
    setAppliedFilters(nextFilters);
    fetchReport(1, pageSize, nextFilters);
  };

  const handlePeriodChange = (nextPeriod) => {
    const nextFilters = {
      ...appliedFilters,
      reportPeriod: nextPeriod,
      periodDate: dayjs(),
    };
    setReportPeriod(nextPeriod);
    form.setFieldsValue({ periodDate: nextFilters.periodDate });
    setAppliedFilters(nextFilters);
    fetchReport(1, pageSize, nextFilters);
  };

  const openView = (record) => {
    setShowDocumentPreview(false);
    setSelected(record);
  };

  const closeView = () => {
    setShowDocumentPreview(false);
    setSelected(null);
  };

  const handleOpenDocument = () => {
    if (!selected?.attachmentUrl) {
      message.error('Document is not available for preview');
      return;
    }
    setShowDocumentPreview(true);
  };

  const periodPickerProps = getPeriodPickerProps(reportPeriod);
  const periodLabel = formatPeriodLabel(appliedFilters.reportPeriod, appliedFilters.periodDate);
  const periodTitle = PERIOD_TABS.find((tab) => tab.key === appliedFilters.reportPeriod)?.label || 'Monthly';

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
      render: (value) => value || '—',
    },
    {
      title: 'Request Type',
      dataIndex: 'requestType',
      key: 'requestType',
      searchable: true,
      render: (value) => value || '—',
    },
    {
      title: 'Support Subject',
      dataIndex: 'subject',
      key: 'subject',
      searchable: true,
      render: (_, record) => record.subject || '—',
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 130,
      render: (value) => getStatusTag(value),
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 110,
      align: 'center',
      render: (_, record) => (
        <Button
          type="primary"
          size="small"
          icon={<EyeOutlined />}
          className="btn-standard-primary"
          onClick={(event) => {
            event.stopPropagation();
            openView(record);
          }}
        >
          View
        </Button>
      ),
    },
  ];

  return (
    <div>
      <Tabs
        activeKey={reportPeriod}
        onChange={handlePeriodChange}
        items={PERIOD_TABS.map((tab) => ({ key: tab.key, label: tab.label }))}
        className="mb-3"
      />

      <div className="mb-4 flex items-center gap-2">
        <BarChart3 size={20} color={BRAND} />
        <h1 className="m-0 text-lg font-semibold text-slate-900">
          {periodTitle} Report
        </h1>
        {periodLabel ? (
          <span className="text-sm font-normal text-slate-500">{periodLabel}</span>
        ) : null}
      </div>

      <div className="mb-4 rounded-lg border border-slate-200 bg-white p-4">
        <h4
          className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
          style={{ color: BRAND }}
        >
          Support Requests Report
        </h4>
        <Form form={form} layout="vertical" requiredMark={false} initialValues={{ periodDate: dayjs() }}>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
            <Form.Item
              name="periodDate"
              label={
                <span className="text-xs font-semibold" style={{ color: BRAND }}>
                  {reportPeriod === 'weekly' ? 'Week' : reportPeriod === 'yearly' ? 'Year' : 'Month'}
                </span>
              }
              className="mb-0"
              rules={[{ required: true, message: 'Select a report period' }]}
            >
              <DatePicker className="w-full" allowClear={false} {...periodPickerProps} />
            </Form.Item>
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
          total,
          showTotal: () => null,
          showQuickJumper: false,
          onChange: (nextPage, nextSize) => {
            fetchReport(nextPage, nextSize, appliedFilters);
          },
        }}
        showSearch
        showRefresh={false}
        searchPlaceholder="Search request report..."
        rowKey={(record, index) => record.id || record.request_no || index}
        onRow={(record) => ({
          onClick: () => openView(record),
          style: { cursor: 'pointer' },
        })}
      />

      <Modal
        open={Boolean(selected)}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        maskClosable={false}
        keyboard={false}
        width={680}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Request Details" onClose={closeView} />
        {selected && (
          <>
            <div className="max-h-[70vh] overflow-y-auto px-6 py-5">
              <div className="mb-5 rounded-md border border-[#ead6d7] bg-[#fff8f8] px-4 py-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <span
                      className="text-[11px] font-semibold uppercase tracking-[0.08em]"
                      style={{ color: BRAND }}
                    >
                      Request Number
                    </span>
                    <div className="mt-0.5 font-mono text-base font-semibold text-black">
                      {selected.request_no || selected.id || 'N/A'}
                    </div>
                    <div className="mt-1 text-xs text-slate-500">
                      {formatDateTime(selected.created_at)
                        ? `Submitted ${formatDateTime(selected.created_at)}`
                        : 'Submission time unavailable'}
                    </div>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    {getStatusTag(selected.status)}
                    {getPriorityTag(selected.priority)}
                  </div>
                </div>
              </div>

              <Section title="Request Information">
                <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                  <Field label="Request Type" value={selected.requestType || selected.source} />
                  <Field label="Priority" value={selected.priority} />
                  {selected.requestType === INFRASTRUCTURE_REQUEST_TYPE ? (
                    <Field label="Infrastructure Support" value={selected.infrastructureSupport} />
                  ) : (
                    <>
                      <Field label="System Name" value={selected.systemName} />
                      <Field label="System Module" value={selected.systemModule} />
                    </>
                  )}
                </div>
              </Section>

              <Section title="Support Details">
                <Field label="Support Subject" value={selected.subject} />
                <div className="mt-2">
                  <span
                    className="mb-1.5 block text-xs font-semibold tracking-[0.01em]"
                    style={{ color: BRAND }}
                  >
                    Support Description
                  </span>
                  <div className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-medium leading-6 text-black">
                    {selected.description || <span className="font-normal text-slate-400">N/A</span>}
                  </div>
                </div>
                <div className="mt-3">
                  <span
                    className="mb-1.5 block text-xs font-semibold tracking-[0.01em]"
                    style={{ color: BRAND }}
                  >
                    Supporting Document
                  </span>
                  {selected.attachmentName ? (
                    <button
                      type="button"
                      onClick={handleOpenDocument}
                      className="inline-flex max-w-full items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-sm font-medium text-black transition hover:border-[#962E32] hover:bg-[#fff8f8]"
                    >
                      <FileText size={16} color={BRAND} />
                      <span className="max-w-[280px] truncate underline decoration-[#962E32]/40 underline-offset-2">
                        {selected.attachmentName}
                      </span>
                    </button>
                  ) : (
                    <div className="text-sm text-slate-400">No document attached</div>
                  )}
                </div>
              </Section>
            </div>
            <div className="flex items-center justify-end border-t border-slate-200 bg-white px-6 py-3">
              <Button onClick={closeView}>Close</Button>
            </div>
          </>
        )}
      </Modal>

      <Modal
        open={showDocumentPreview}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        maskClosable={false}
        keyboard={false}
        zIndex={2000}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 900}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader
          title={selected?.attachmentName || 'Supporting Document'}
          onClose={() => setShowDocumentPreview(false)}
        />
        <div className="bg-slate-50 p-4">
          {selected?.attachmentUrl && isImageType(selected.attachmentType, selected.attachmentName) ? (
            <img
              src={selected.attachmentUrl}
              alt={selected.attachmentName}
              className="mx-auto max-h-[70vh] w-full object-contain"
            />
          ) : selected?.attachmentUrl && isPdfType(selected.attachmentType, selected.attachmentName) ? (
            <iframe
              title={selected?.attachmentName || 'Supporting Document'}
              src={selected.attachmentUrl}
              className="h-[70vh] w-full rounded-md border border-slate-200 bg-white"
            />
          ) : (
            <div className="rounded-md border border-dashed border-slate-200 bg-white px-4 py-12 text-center text-sm text-slate-500">
              Preview is not available for this file type.
            </div>
          )}
        </div>
      </Modal>
    </div>
  );
};

export default RequestReports;
