import { useEffect, useMemo, useState } from 'react';
import { useSelector } from 'react-redux';
import { App, Button, DatePicker, Form, Input, Modal, Select, Upload } from 'antd';
import { CheckCircleOutlined, CloseCircleOutlined, EyeOutlined, PlusOutlined, UploadOutlined } from '@ant-design/icons';
import { ClipboardList } from 'lucide-react';
import { DataTable } from '../../common/data/index.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import LeaveModalHeader from './LeaveModalHeader.jsx';
import { leaveService } from './leaveService.js';
import {
  LEAVE_STATUS,
  LEAVE_TYPES,
  countLeaveDays,
  getLeaveStatusStyle,
  isLeaveAdministrator,
  isLeaveApprover,
  isLeaveEmployee,
} from './leaveUtils.js';
import '../../styles/common.css';

const BRAND = '#962E32';
const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;
const ACCEPTED_DOCUMENT_TYPES = '.pdf,.jpg,.jpeg,.png,.doc,.docx';

const normFileList = (event) => {
  if (Array.isArray(event)) return event;
  return event?.fileList ?? [];
};

const Field = ({ label, value }) => (
  <div className="min-w-0 py-1.5">
    <span
      className="block text-xs font-semibold tracking-[0.01em]"
      style={{ color: BRAND }}
    >
      {label}
    </span>
    <div className="mt-0.5 text-sm font-medium text-black">
      {value ?? <span className="text-slate-400">N/A</span>}
    </div>
  </div>
);

const LeaveApplication = () => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [rejectForm] = Form.useForm();
  const selectedRole = useSelector((state) => state.app.selectedRole);
  const canApply = isLeaveEmployee(selectedRole);
  const canVerify = isLeaveAdministrator(selectedRole);
  const canApprove = isLeaveApprover(selectedRole);

  const [applications, setApplications] = useState([]);
  const [loading, setLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [isApplyOpen, setIsApplyOpen] = useState(false);
  const [isViewOpen, setIsViewOpen] = useState(false);
  const [isRejectOpen, setIsRejectOpen] = useState(false);
  const [selected, setSelected] = useState(null);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(15);
  const [total, setTotal] = useState(0);

  const watchedRange = Form.useWatch('dates', form);
  const calculatedDays = useMemo(
    () => countLeaveDays(watchedRange?.[0], watchedRange?.[1]),
    [watchedRange]
  );

  const fetchApplications = async (nextPage = page, nextSize = pageSize) => {
    setLoading(true);
    try {
      const response = await leaveService.listApplications({
        page: nextPage,
        per_page: nextSize,
      });
      const { items, pagination } = extractArrayFromResponse(response.data || response);
      setApplications(items);
      const paging = updatePaginationFromResponse(pagination, nextPage, nextSize, pagination?.total ?? items.length);
      setPage(paging.current);
      setPageSize(paging.pageSize);
      setTotal(paging.total);
    } catch (error) {
      message.error(error.message || 'Failed to load leave applications');
      setApplications([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchApplications(1, pageSize);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedRole]);

  const openApply = () => {
    form.resetFields();
    setIsApplyOpen(true);
  };

  const closeApply = () => {
    setIsApplyOpen(false);
    form.resetFields();
  };

  const openView = (record) => {
    setSelected(record);
    setIsViewOpen(true);
  };

  const closeView = () => {
    setIsViewOpen(false);
    setSelected(null);
  };

  const beforeUploadDocument = (file) => {
    if (file.size > MAX_DOCUMENT_BYTES) {
      message.error('Supportive document must be 10MB or smaller');
      return Upload.LIST_IGNORE;
    }
    return false;
  };

  const handleApply = async () => {
    try {
      const values = await form.validateFields();
      const [startDate, endDate] = values.dates || [];
      const uploadedFile = values.supportiveDocument?.[0]?.originFileObj;
      await leaveService.createApplication({
        leaveType: values.leaveType,
        startDate: startDate.format('YYYY-MM-DD'),
        endDate: endDate.format('YYYY-MM-DD'),
        reason: values.reason?.trim() || '',
        file: uploadedFile,
      });
      closeApply();
      message.success('Leave application submitted for verification');
      fetchApplications(1, pageSize);
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error.message || 'Unable to submit leave application');
    }
  };

  const handleVerify = async () => {
    if (!selected?.id) return;
    setActionLoading(true);
    try {
      await leaveService.verifyApplication(selected.id);
      message.success('Leave application verified');
      closeView();
      fetchApplications(page, pageSize);
    } catch (error) {
      message.error(error.message || 'Unable to verify leave application');
    } finally {
      setActionLoading(false);
    }
  };

  const handleApprove = async () => {
    if (!selected?.id) return;
    setActionLoading(true);
    try {
      await leaveService.approveApplication(selected.id);
      message.success('Leave application approved');
      closeView();
      fetchApplications(page, pageSize);
    } catch (error) {
      message.error(error.message || 'Unable to approve leave application');
    } finally {
      setActionLoading(false);
    }
  };

  const openReject = () => {
    rejectForm.resetFields();
    setIsRejectOpen(true);
  };

  const closeReject = () => {
    setIsRejectOpen(false);
    rejectForm.resetFields();
  };

  const handleReject = async () => {
    try {
      const values = await rejectForm.validateFields();
      setActionLoading(true);
      await leaveService.rejectApplication(selected.id, values.reason.trim());
      message.success('Leave application rejected');
      closeReject();
      closeView();
      fetchApplications(page, pageSize);
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error.message || 'Unable to reject leave application');
    } finally {
      setActionLoading(false);
    }
  };

  const showVerify = canVerify && selected?.status === LEAVE_STATUS.APPLIED;
  const showApprove = canApprove && selected?.status === LEAVE_STATUS.VERIFIED;
  const showReject = showVerify || showApprove;

  const columns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => (page - 1) * pageSize + index + 1,
    },
    ...((canVerify || canApprove) ? [{
      title: 'Applicant',
      dataIndex: 'applicantName',
      key: 'applicantName',
      searchable: true,
    }] : []),
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
      <div className="mb-4 flex items-center gap-2">
        <ClipboardList size={20} color={BRAND} />
        <h1 className="m-0 text-lg font-semibold text-slate-900">Manage Application</h1>
      </div>

      <DataTable
        columns={columns}
        data={applications}
        loading={loading}
        pagination={{
          current: page,
          pageSize,
          total,
          showTotal: () => null,
          showQuickJumper: false,
          onChange: (nextPage, nextSize) => {
            fetchApplications(nextPage, nextSize);
          },
        }}
        showSearch
        showRefresh={false}
        rightAction={
          canApply ? (
            <Button
              type="primary"
              icon={<PlusOutlined />}
              className="btn-standard-primary"
              onClick={openApply}
            >
              Apply
            </Button>
          ) : null
        }
        searchPlaceholder="Search leave applications..."
        rowKey="id"
        onRow={(record) => ({
          onClick: () => openView(record),
          style: { cursor: 'pointer' },
        })}
      />

      <Modal
        open={isApplyOpen}
        onCancel={closeApply}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <LeaveModalHeader title="Apply Leave" onClose={closeApply} />
        <div className="px-6 py-5">
          <Form form={form} layout="vertical" requiredMark={false}>
            <Form.Item
              name="leaveType"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Leave Type</span>}
              rules={[{ required: true, message: 'Select a leave type' }]}
            >
              <Select
                placeholder="Select leave type"
                options={LEAVE_TYPES.map((type) => ({ label: type, value: type }))}
              />
            </Form.Item>
            <Form.Item
              name="dates"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Leave Dates</span>}
              rules={[{ required: true, message: 'Select start and end dates' }]}
            >
              <DatePicker.RangePicker className="w-full" format="YYYY-MM-DD" />
            </Form.Item>
            <div className="mb-4 text-sm font-medium text-black">
              Days: {calculatedDays || <span className="text-slate-400">N/A</span>}
            </div>
            <Form.Item
              name="reason"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Reason</span>}
              rules={[{ required: true, message: 'Enter a reason' }]}
            >
              <Input.TextArea rows={4} maxLength={500} showCount placeholder="Reason for leave" />
            </Form.Item>
            <Form.Item
              name="supportiveDocument"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Supportive Document</span>}
              valuePropName="fileList"
              getValueFromEvent={normFileList}
              extra={<span className="text-xs text-slate-500">PDF, Word, or image. Max 10MB.</span>}
            >
              <Upload
                accept={ACCEPTED_DOCUMENT_TYPES}
                maxCount={1}
                beforeUpload={beforeUploadDocument}
              >
                <Button icon={<UploadOutlined />}>Select file</Button>
              </Upload>
            </Form.Item>
          </Form>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            onClick={handleApply}
            style={{ background: BRAND, borderColor: BRAND }}
            className="hover:!bg-[#7A2326] hover:!border-[#7A2326]"
          >
            Submit
          </Button>
          <Button onClick={closeApply}>Close</Button>
        </div>
      </Modal>

      <Modal
        open={isViewOpen}
        onCancel={closeView}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <LeaveModalHeader title="Leave Application" onClose={closeView} />
        <div className="px-6 py-5">
          <h4
            className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
            style={{ color: BRAND }}
          >
            Application Details
          </h4>
          <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
            <Field label="Applicant" value={selected?.applicantName} />
            <Field label="Status" value={selected?.status} />
            <Field label="Leave Type" value={selected?.leaveType} />
            <Field label="Days" value={selected?.days} />
            <Field label="Start Date" value={selected?.startDate} />
            <Field label="End Date" value={selected?.endDate} />
            <Field label="Applied On" value={selected?.appliedOn} />
            <Field label="Supportive Document" value={selected?.supportiveDocumentName} />
          </div>
          <Field label="Reason" value={selected?.reason} />
          {selected?.rejectionReason ? (
            <Field label="Rejection Reason" value={selected.rejectionReason} />
          ) : null}
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          {showVerify ? (
            <Button
              type="primary"
              icon={<CheckCircleOutlined />}
              loading={actionLoading}
              onClick={handleVerify}
              style={{ background: BRAND, borderColor: BRAND }}
              className="hover:!bg-[#7A2326] hover:!border-[#7A2326]"
            >
              Verify
            </Button>
          ) : null}
          {showApprove ? (
            <Button
              type="primary"
              icon={<CheckCircleOutlined />}
              loading={actionLoading}
              onClick={handleApprove}
              style={{ background: BRAND, borderColor: BRAND }}
              className="hover:!bg-[#7A2326] hover:!border-[#7A2326]"
            >
              Approve
            </Button>
          ) : null}
          {showReject ? (
            <Button
              icon={<CloseCircleOutlined />}
              loading={actionLoading}
              onClick={openReject}
            >
              Reject
            </Button>
          ) : null}
          <Button onClick={closeView}>Close</Button>
        </div>
      </Modal>

      <Modal
        open={isRejectOpen}
        onCancel={closeReject}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <LeaveModalHeader title="Reject Leave" onClose={closeReject} />
        <div className="px-6 py-5">
          <Form form={rejectForm} layout="vertical" requiredMark={false}>
            <Form.Item
              name="reason"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Rejection Reason</span>}
              rules={[{ required: true, message: 'Enter a rejection reason' }]}
            >
              <Input.TextArea rows={4} maxLength={500} showCount placeholder="Reason for rejection" />
            </Form.Item>
          </Form>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            icon={<CloseCircleOutlined />}
            loading={actionLoading}
            onClick={handleReject}
            style={{ background: BRAND, borderColor: BRAND }}
            className="hover:!bg-[#7A2326] hover:!border-[#7A2326]"
          >
            Reject
          </Button>
          <Button onClick={closeReject}>Close</Button>
        </div>
      </Modal>
    </div>
  );
};

export default LeaveApplication;
