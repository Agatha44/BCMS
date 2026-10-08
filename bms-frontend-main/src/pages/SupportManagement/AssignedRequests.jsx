import { useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { App, Button, Form, Input, Modal, Tag } from 'antd';
import { EyeOutlined } from '@ant-design/icons';
import { ChevronRight, FileText, Monitor, UserCheck, Wrench } from 'lucide-react';
import { DataTable } from '../../common/data/index.jsx';
import BrandModalHeader from '../CollectionManagement/sod/components/BrandModalHeader.jsx';
import { listSupportRequests, saveSupportRequests, updateSupportRequest } from './supportRequestStore.js';
import SupportRequestMinutes from './SupportRequestMinutes.jsx';
import { appendSupportMinute, ensureSupportMinutes, getSupportUsername } from './supportRequestMinutes.js';
import '../../styles/common.css';

const BRAND = '#962E32';
const INFRASTRUCTURE_REQUEST_TYPE = 'Infrastructure/hardware';
const SYSTEM_REQUEST_TYPE = 'System/Software';

const REQUEST_CARDS = [
  {
    key: 'hardware',
    title: 'Hardware Requests',
    description: 'Infrastructure and hardware support requests',
    requestType: INFRASTRUCTURE_REQUEST_TYPE,
    icon: Wrench,
  },
  {
    key: 'system',
    title: 'System Requests',
    description: 'System and software support requests',
    requestType: SYSTEM_REQUEST_TYPE,
    icon: Monitor,
  },
];

const ICT_OFFICER = 'ICT Officer';
const TECHNICAL_OFFICER = 'Technical Officer';

const STATUS_OPTIONS = [
  { label: 'Opened', value: 'Opened' },
  { label: 'Verified', value: 'Verified' },
  { label: 'Assigned', value: 'Assigned' },
  { label: 'Reopened', value: 'Reopened' },
  { label: 'On Hold', value: 'On Hold' },
  { label: 'Closed', value: 'Closed' },
  { label: 'Resolved', value: 'Resolved' },
  { label: 'Cancelled', value: 'Cancelled' },
];
const HOLDABLE_STATUSES = ['opened', 'assigned', 'verified', 'reopened'];
const CLOSEABLE_STATUSES = ['opened', 'assigned', 'verified', 'reopened', 'on hold'];

const isSystemRequestType = (requestType) =>
  String(requestType || '').toLowerCase() === SYSTEM_REQUEST_TYPE.toLowerCase();

const isHardwareRequestType = (requestType) =>
  String(requestType || '').toLowerCase() === INFRASTRUCTURE_REQUEST_TYPE.toLowerCase();

const canImplementRequestType = (roleName, requestType) => {
  const normalizedRole = String(roleName || '').trim().toLowerCase();
  if (normalizedRole === ICT_OFFICER.toLowerCase()) return isSystemRequestType(requestType);
  if (normalizedRole === TECHNICAL_OFFICER.toLowerCase()) return isHardwareRequestType(requestType);
  return false;
};

const canHoldRequest = (roleName, status, requestType) =>
  canImplementRequestType(roleName, requestType) &&
  HOLDABLE_STATUSES.includes(String(status || '').toLowerCase());

const canCloseRequest = (roleName, status, requestType) =>
  canImplementRequestType(roleName, requestType) &&
  CLOSEABLE_STATUSES.includes(String(status || '').toLowerCase());

const implementorActionMessage = (requestType, action) =>
  isSystemRequestType(requestType)
    ? `Only an ICT Officer can ${action} a System/Software request`
    : `Only a Technical Officer can ${action} an Infrastructure/hardware request`;

const SUPERVISOR_REVIEWER_ROLES = ['Toll Supervisor', 'Toll Reviewer'];
const SUPERVISOR_REVIEWER_LABEL = 'Toll Supervisor and Toll Reviewer';
const ASSIGNABLE_STATUSES = ['opened', 'verified', 'reopened', 'on hold'];
const VERIFIABLE_STATUSES = ['opened', 'reopened'];
const CANCELABLE_STATUSES = ['opened', 'verified', 'assigned', 'reopened', 'on hold'];

const hasSupervisorReviewerRole = (roleName) => {
  const normalizedRole = String(roleName || '').trim().toLowerCase();
  return SUPERVISOR_REVIEWER_ROLES.some((role) => role.toLowerCase() === normalizedRole);
};

const canAssignRequest = (roleName, status) =>
  hasSupervisorReviewerRole(roleName) && ASSIGNABLE_STATUSES.includes(String(status || '').toLowerCase());

const canVerifyRequest = (roleName, status) =>
  hasSupervisorReviewerRole(roleName) && VERIFIABLE_STATUSES.includes(String(status || '').toLowerCase());

const canCancelRequest = (roleName, status) =>
  hasSupervisorReviewerRole(roleName) && CANCELABLE_STATUSES.includes(String(status || '').toLowerCase());

const getStatusTag = (status) => {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'resolved') return <Tag color="green">Resolved</Tag>;
  if (normalized === 'assigned') return <Tag color="purple">Assigned</Tag>;
  if (normalized === 'verified') return <Tag color="cyan">Verified</Tag>;
  if (normalized === 'on hold') return <Tag color="gold">On Hold</Tag>;
  if (normalized === 'cancelled' || normalized === 'canceled') return <Tag color="red">Cancelled</Tag>;
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

const AssignedRequests = () => {
  const { message } = App.useApp();
  const selectedRole = useSelector((state) => state.app.selectedRole);
  const currentUser = useSelector((state) => state.auth.user);
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(false);
  const [selected, setSelected] = useState(null);
  const [showDocumentPreview, setShowDocumentPreview] = useState(false);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(15);
  const [total, setTotal] = useState(0);
  const [selectedCard, setSelectedCard] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [isCancelOpen, setIsCancelOpen] = useState(false);
  const [cancelForm] = Form.useForm();
  const [isHoldOpen, setIsHoldOpen] = useState(false);
  const [holdForm] = Form.useForm();

  const visibleRequests = selectedCard
    ? requests.filter((request) => request.requestType === selectedCard.requestType)
    : [];

  const fetchRequests = async (nextPage = page, nextSize = pageSize, card = selectedCard) => {
    setLoading(true);
    try {
      const stored = listSupportRequests().map((request) => ({
        ...request,
        minutes: ensureSupportMinutes(request, getSupportUsername(currentUser) || request.requestedBy, selectedRole),
      }));
      saveSupportRequests(stored);
      const filtered = card
        ? stored.filter((request) => request.requestType === card.requestType)
        : [];
      setRequests(stored);
      setPage(nextPage);
      setPageSize(nextSize);
      setTotal(filtered.length);
    } catch (error) {
      message.error(error?.message || 'Failed to load assign requests');
    } finally {
      setLoading(false);
    }
  };

  const handleSelectCard = (card) => {
    setSelectedCard(card);
    fetchRequests(1, pageSize, card);
  };

  useEffect(() => {
    fetchRequests(1, pageSize, selectedCard);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const openView = (record) => {
    setShowDocumentPreview(false);
    const username = getSupportUsername(currentUser) || record?.requestedBy;
    setSelected({
      ...record,
      minutes: ensureSupportMinutes(record, username, selectedRole),
    });
  };

  const closeView = () => {
    setShowDocumentPreview(false);
    setIsCancelOpen(false);
    cancelForm.resetFields();
    setIsHoldOpen(false);
    holdForm.resetFields();
    setSelected(null);
  };

  const handleOpenDocument = () => {
    if (!selected?.attachmentUrl) {
      message.error('Document is not available for preview');
      return;
    }
    setShowDocumentPreview(true);
  };

  const applyUpdate = (id, patch, successMessage) => {
    setActionLoading(true);
    try {
      const nextStatusValue = patch.status || selected?.status;
      const nextRequests = updateSupportRequest(id, {
        ...patch,
        minutes: appendSupportMinute(selected, nextStatusValue, getSupportUsername(currentUser), selectedRole, patch.comment),
      });
      setRequests(nextRequests);
      const updated = nextRequests.find((request) => String(request.id) === String(id));
      if (updated) setSelected(updated);
      setTotal(
        selectedCard
          ? nextRequests.filter((request) => request.requestType === selectedCard.requestType).length
          : 0
      );
      message.success(successMessage);
    } catch (error) {
      message.error(error?.message || 'Unable to update the request');
    } finally {
      setActionLoading(false);
    }
  };

  const handleVerify = () => {
    if (!selected) return;
    if (!canVerifyRequest(selectedRole, selected.status)) {
      message.error(`Only ${SUPERVISOR_REVIEWER_LABEL} can verify a request`);
      return;
    }
    applyUpdate(
      selected.id,
      {
        status: 'Verified',
        verified_at: new Date().toISOString(),
        verified_by: selectedRole,
      },
      'Request verified'
    );
  };

  const handleAssign = (implementor) => {
    if (!selected || !implementor) return;
    if (!canAssignRequest(selectedRole, selected.status)) {
      message.error(`Only ${SUPERVISOR_REVIEWER_LABEL} can assign a request`);
      return;
    }
    const now = new Date().toISOString();
    applyUpdate(
      selected.id,
      {
        status: 'Assigned',
        assignedTo: implementor,
        assigned_at: now,
        assigned_by: selectedRole,
        verified_at: selected.verified_at || now,
        verified_by: selected.verified_by || selectedRole,
      },
      `Request assigned to ${implementor}`
    );
  };

  const openHold = () => {
    if (!selected) return;
    if (!canHoldRequest(selectedRole, selected.status, selected.requestType)) {
      message.error(implementorActionMessage(selected.requestType, 'place on hold'));
      return;
    }
    holdForm.resetFields();
    setIsHoldOpen(true);
  };

  const closeHold = () => {
    setIsHoldOpen(false);
    holdForm.resetFields();
  };

  const handleHold = async () => {
    if (!selected) return;
    if (!canHoldRequest(selectedRole, selected.status, selected.requestType)) {
      message.error(implementorActionMessage(selected.requestType, 'place on hold'));
      return;
    }
    try {
      const values = await holdForm.validateFields();
      const comment = String(values.comment || '').trim();
      if (!comment) {
        message.error('A comment is required to place a request on hold');
        return;
      }
      applyUpdate(
        selected.id,
        {
          status: 'On Hold',
          on_hold_at: new Date().toISOString(),
          on_hold_by: selectedRole,
          hold_comment: comment,
          comment,
        },
        'Request placed on hold'
      );
      closeHold();
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error?.message || 'Unable to place the request on hold');
    }
  };

  const handleCloseRequest = () => {
    if (!selected) return;
    if (!canCloseRequest(selectedRole, selected.status, selected.requestType)) {
      message.error(implementorActionMessage(selected.requestType, 'close'));
      return;
    }
    applyUpdate(
      selected.id,
      {
        status: 'Closed',
        closed_at: new Date().toISOString(),
        closed_by: selectedRole,
      },
      'Request closed'
    );
  };

  const openCancel = () => {
    if (!selected) return;
    if (!canCancelRequest(selectedRole, selected.status)) {
      message.error(`Only ${SUPERVISOR_REVIEWER_LABEL} can cancel a request`);
      return;
    }
    cancelForm.resetFields();
    setIsCancelOpen(true);
  };

  const closeCancel = () => {
    setIsCancelOpen(false);
    cancelForm.resetFields();
  };

  const handleCancelRequest = async () => {
    if (!selected) return;
    if (!canCancelRequest(selectedRole, selected.status)) {
      message.error(`Only ${SUPERVISOR_REVIEWER_LABEL} can cancel a request`);
      return;
    }
    try {
      const values = await cancelForm.validateFields();
      const comment = String(values.comment || '').trim();
      if (!comment) {
        message.error('A comment is required to cancel a request');
        return;
      }
      applyUpdate(
        selected.id,
        {
          status: 'Cancelled',
          cancelled_at: new Date().toISOString(),
          cancelled_by: selectedRole,
          cancel_comment: comment,
          comment,
        },
        'Request cancelled'
      );
      closeCancel();
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error?.message || 'Unable to cancel the request');
    }
  };

  const isHardwareRequest = selected?.requestType === INFRASTRUCTURE_REQUEST_TYPE;
  const isSystemRequest = selected?.requestType === SYSTEM_REQUEST_TYPE;

  const columns = [
    {
      title: 'Request No',
      dataIndex: 'request_no',
      key: 'request_no',
      width: 140,
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
      render: (value) => value || '—',
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 130,
      filters: STATUS_OPTIONS.map((option) => ({ text: option.label, value: option.value })),
      onFilter: (value, record) =>
        String(record.status || '').toLowerCase() === String(value).toLowerCase(),
      render: (value) => getStatusTag(value),
    },
    {
      title: 'Action',
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
        <UserCheck size={20} color={BRAND} />
        <h1 className="m-0 text-lg font-semibold text-slate-900">Assign Requests</h1>
      </div>

      <div className="mb-5 grid grid-cols-1 gap-4 md:grid-cols-2">
        {REQUEST_CARDS.map((card) => {
          const Icon = card.icon;
          const count = requests.filter((request) => request.requestType === card.requestType).length;
          const isActive = selectedCard?.key === card.key;

          return (
            <button
              key={card.key}
              type="button"
              onClick={() => handleSelectCard(card)}
              className={`flex h-full items-start gap-4 rounded-2xl border p-5 text-left shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#962E32]/25 ${
                isActive
                  ? 'border-[#962E32] bg-[#fff8f8] shadow-md'
                  : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-[#ead6d7] hover:shadow-md'
              }`}
            >
              <span
                className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
                style={{ backgroundColor: '#fff5f5', color: BRAND }}
              >
                <Icon size={22} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="flex items-center justify-between gap-3">
                  <span className="text-base font-semibold text-slate-900">{card.title}</span>
                  <span
                    className="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                    style={{
                      backgroundColor: isActive ? '#ead6d7' : '#f1f5f9',
                      color: isActive ? BRAND : '#475569',
                    }}
                  >
                    {count}
                  </span>
                </span>
                <span className="mt-1 block text-sm text-slate-500">{card.description}</span>
                <span className="mt-3 inline-flex items-center gap-1 text-xs font-semibold" style={{ color: BRAND }}>
                  {isActive ? 'Showing requests' : 'View requests'}
                  <ChevronRight size={14} />
                </span>
              </span>
            </button>
          );
        })}
      </div>

      {selectedCard ? (
        <DataTable
          columns={columns}
          data={visibleRequests}
          loading={loading}
          pagination={{
            current: page,
            pageSize,
            total,
            showTotal: () => null,
            showQuickJumper: false,
            onChange: (nextPage, nextSize) => {
              fetchRequests(nextPage, nextSize, selectedCard);
            },
          }}
          showSearch
          showRefresh={false}
          searchPlaceholder={`Search ${selectedCard.title.toLowerCase()}...`}
          rowKey={(record, index) => record.id || record.request_no || index}
          onRow={(record) => ({
            onClick: () => openView(record),
            style: { cursor: 'pointer' },
          })}
        />
      ) : null}

      <Modal
        open={Boolean(selected)}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        maskClosable={false}
        keyboard={false}
        width={980}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Assign Details" onClose={closeView} />
        {selected && (
          <div className="flex max-h-[85vh] flex-col">
            <div className="grid min-h-0 flex-1 grid-cols-1 overflow-hidden sm:grid-cols-[minmax(0,1fr)_280px]">
            <div className="min-h-0 overflow-y-auto px-6 py-5">
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
                  <Field label="Assigned To" value={selected.assignedTo} />
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
              <SupportRequestMinutes
                minutes={selected.minutes}
                requestNumber={selected.request_no || selected.id}
                currentStatus={selected.status}
                username={getSupportUsername(currentUser) || selected.requestedBy}
                roleName={selectedRole}
              />
            </div>
            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
              <Button onClick={closeView}>Close</Button>
              {canHoldRequest(selectedRole, selected.status, selected.requestType) ? (
                <Button
                  loading={actionLoading}
                  onClick={openHold}
                >
                  On Hold
                </Button>
              ) : null}
              {canCloseRequest(selectedRole, selected.status, selected.requestType) ? (
                <Button
                  type="primary"
                  loading={actionLoading}
                  className="btn-standard-primary"
                  onClick={handleCloseRequest}
                >
                  Close Request
                </Button>
              ) : null}
              {canCancelRequest(selectedRole, selected.status) ? (
                <Button
                  danger
                  loading={actionLoading}
                  onClick={openCancel}
                >
                  Cancel Request
                </Button>
              ) : null}
              {canVerifyRequest(selectedRole, selected.status) ? (
                <Button
                  type="primary"
                  loading={actionLoading}
                  className="btn-standard-primary"
                  onClick={handleVerify}
                >
                  Verify
                </Button>
              ) : null}
              {canAssignRequest(selectedRole, selected.status) && isSystemRequest ? (
                <Button
                  type="primary"
                  loading={actionLoading}
                  className="btn-standard-primary"
                  onClick={() => handleAssign(ICT_OFFICER)}
                >
                  Assign to ICT Officer
                </Button>
              ) : null}
              {canAssignRequest(selectedRole, selected.status) && isHardwareRequest ? (
                <Button
                  type="primary"
                  loading={actionLoading}
                  className="btn-standard-primary"
                  onClick={() => handleAssign(TECHNICAL_OFFICER)}
                >
                  Assign to Technical Officer
                </Button>
              ) : null}
            </div>
          </div>
        )}
      </Modal>

      <Modal
        open={isHoldOpen}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        maskClosable={false}
        keyboard={false}
        zIndex={1100}
        width={480}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="On Hold" onClose={closeHold} />
        <div className="px-6 py-5">
          <p className="mb-3 text-sm text-slate-600">
            Add a comment to place this request on hold.
          </p>
          <Form form={holdForm} layout="vertical" requiredMark={false}>
            <Form.Item
              name="comment"
              label="Comment"
              rules={[{ required: true, whitespace: true, message: 'Comment is required to place a request on hold' }]}
            >
              <Input.TextArea
                rows={4}
                maxLength={500}
                showCount
                placeholder="Enter the reason for placing this request on hold"
              />
            </Form.Item>
          </Form>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button onClick={closeHold}>Back</Button>
          <Button
            type="primary"
            loading={actionLoading}
            className="btn-standard-primary"
            onClick={handleHold}
          >
            On Hold
          </Button>
        </div>
      </Modal>

      <Modal
        open={isCancelOpen}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        maskClosable={false}
        keyboard={false}
        zIndex={1100}
        width={480}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Cancel Request" onClose={closeCancel} />
        <div className="px-6 py-5">
          <p className="mb-3 text-sm text-slate-600">
            Add a comment to cancel this request.
          </p>
          <Form form={cancelForm} layout="vertical" requiredMark={false}>
            <Form.Item
              name="comment"
              label="Comment"
              rules={[{ required: true, whitespace: true, message: 'Comment is required to cancel a request' }]}
            >
              <Input.TextArea
                rows={4}
                maxLength={500}
                showCount
                placeholder="Enter the reason for cancelling this request"
              />
            </Form.Item>
          </Form>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button onClick={closeCancel}>Back</Button>
          <Button
            danger
            loading={actionLoading}
            onClick={handleCancelRequest}
          >
            Cancel Request
          </Button>
        </div>
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

export default AssignedRequests;
