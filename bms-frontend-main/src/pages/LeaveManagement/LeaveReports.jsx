import { useEffect, useState } from 'react';
import { App, Button, DatePicker, Form, Select } from 'antd';
import { BarChart3 } from 'lucide-react';
import { DataTable } from '../../common/data/index.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import { leaveService } from './leaveService.js';
import { LEAVE_STATUS, LEAVE_TYPES, getLeaveStatusStyle } from './leaveUtils.js';
import '../../styles/common.css';

const BRAND = '#962E32';

const STATUS_OPTIONS = [
  { label: 'All statuses', value: '' },
  { label: LEAVE_STATUS.APPLIED, value: LEAVE_STATUS.APPLIED },
  { label: LEAVE_STATUS.VERIFIED, value: LEAVE_STATUS.VERIFIED },
  { label: LEAVE_STATUS.APPROVED, value: LEAVE_STATUS.APPROVED },
  { label: LEAVE_STATUS.REJECTED, value: LEAVE_STATUS.REJECTED },
];

const LeaveReports = () => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(15);
  const [total, setTotal] = useState(0);
  const [filters, setFilters] = useState({});

  const fetchReport = async (nextPage = 1, nextSize = pageSize, nextFilters = filters) => {
    setLoading(true);
    try {
      const response = await leaveService.listApplications({
        page: nextPage,
        per_page: nextSize,
        ...nextFilters,
      });
      const { items, pagination } = extractArrayFromResponse(response.data || response);
      const paging = updatePaginationFromResponse(
        pagination,
        nextPage,
        nextSize,
        pagination?.total ?? items.length
      );
      setRows(items);
      setPage(paging.current);
      setPageSize(paging.pageSize);
      setTotal(paging.total);
    } catch (error) {
      message.error(error.message || 'Failed to load leave report');
      setRows([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReport(1, pageSize, {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleSearch = async () => {
    const values = await form.validateFields();
    const nextFilters = {
      status: values.status || undefined,
      leave_type: values.leaveType || undefined,
      from_date: values.period?.[0]?.format('YYYY-MM-DD'),
      to_date: values.period?.[1]?.format('YYYY-MM-DD'),
    };
    setFilters(nextFilters);
    fetchReport(1, pageSize, nextFilters);
  };

  const handleReset = () => {
    form.resetFields();
    setFilters({});
    fetchReport(1, pageSize, {});
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
      title: 'Applicant',
      dataIndex: 'applicantName',
      key: 'applicantName',
      searchable: true,
    },
    {
      title: 'Leave Type',
      dataIndex: 'leaveType',
      key: 'leaveType',
      searchable: true,
    },
    {
      title: 'Start Date',
      dataIndex: 'startDate',
      key: 'startDate',
      width: 140,
    },
    {
      title: 'End Date',
      dataIndex: 'endDate',
      key: 'endDate',
      width: 140,
    },
    {
      title: 'Days',
      dataIndex: 'days',
      key: 'days',
      width: 90,
      align: 'center',
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 130,
      align: 'center',
      render: (status) => {
        const style = getLeaveStatusStyle(status);
        return (
          <span
            style={{
              ...style,
              padding: '4px 12px',
              borderRadius: '4px',
              fontSize: '13px',
              fontWeight: 500,
            }}
          >
            {status}
          </span>
        );
      },
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
          Leave Applications Report
        </h4>
        <Form form={form} layout="vertical" requiredMark={false}>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
            <Form.Item
              name="leaveType"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Leave Type</span>}
              className="mb-0"
            >
              <Select
                allowClear
                placeholder="All leave types"
                options={LEAVE_TYPES.map((type) => ({ label: type, value: type }))}
              />
            </Form.Item>
            <Form.Item
              name="status"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Status</span>}
              className="mb-0"
            >
              <Select allowClear placeholder="All statuses" options={STATUS_OPTIONS.filter((item) => item.value)} />
            </Form.Item>
            <Form.Item
              name="period"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Leave Dates</span>}
              className="mb-0"
            >
              <DatePicker.RangePicker className="w-full" format="YYYY-MM-DD" />
            </Form.Item>
          </div>
          <div className="mt-4 flex justify-end gap-2">
            <Button
              type="primary"
              className="btn-standard-primary"
              onClick={handleSearch}
            >
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
            fetchReport(nextPage, nextSize, filters);
          },
        }}
        showSearch
        showRefresh={false}
        searchPlaceholder="Search leave report..."
        rowKey="id"
      />
    </div>
  );
};

export default LeaveReports;
