import { useEffect, useRef, useState } from 'react';
import { useSelector } from 'react-redux';
import { App, Button, Form, Input, Modal, Select, Tag, Upload } from 'antd';
import { CloseOutlined, EyeOutlined, PlusOutlined, UploadOutlined } from '@ant-design/icons';
import { FileText, Headset } from 'lucide-react';
import { DataTable } from '../../common/data/index.jsx';
import BrandModalHeader from '../CollectionManagement/sod/components/BrandModalHeader.jsx';
import { addSupportRequest, listSupportRequests, nextSupportRequestId, saveSupportRequests, updateSupportRequest } from './supportRequestStore.js';
import { getStoredUser } from '../../modules/auth/authSession.js';
import SupportRequestMinutes from './SupportRequestMinutes.jsx';
import { appendSupportMinute, buildSupportMinute, ensureSupportMinutes, getSupportUsername } from './supportRequestMinutes.js';
import '../../styles/common.css';

const BRAND = '#962E32';
const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

const REQUEST_TYPE_OPTIONS = [
  { label: 'Infrastructure/hardware', value: 'Infrastructure/hardware' },
  { label: 'System/Software', value: 'System/Software' },
];

const PRIORITY_OPTIONS = [
  { label: 'Low', value: 'Low' },
  { label: 'Medium', value: 'Medium' },
  { label: 'High', value: 'High' },
];

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

const INFRASTRUCTURE_REQUEST_TYPE = 'Infrastructure/hardware';

const SYSTEM_NAME_OPTIONS = [
  { label: 'Bridge Management System', value: 'Bridge Management System' },
];

const SYSTEM_MODULE_OPTIONS = [
  { label: 'Toll Collection Module', value: 'Toll Collection Module' },
  { label: 'Incident Module', value: 'Incident Module' },
  { label: 'Event Module', value: 'Event Module' },
  { label: 'Overload Module', value: 'Overload Module' },
  { label: 'Advertisement Module', value: 'Advertisement Module' },
];

const INFRASTRUCTURE_SUPPORT_OPTIONS = [
  { label: 'Camera Malfunction', value: 'Camera Malfunction' },
  { label: 'Gate Malfunction', value: 'Gate Malfunction' },
  { label: 'AC Malfunction', value: 'AC Malfunction' },
  { label: 'Printers', value: 'Printers' },
  { label: 'Monitors', value: 'Monitors' },
  { label: 'UPS Malfunction', value: 'UPS Malfunction' },
  
];

const SUPPORT_SUBJECT_OPTIONS_BY_INFRASTRUCTURE = {
  'Gate Malfunction': [
    { label: 'Damaged Gate', value: 'Damaged Gate' },
    { label: 'Unresponding gate to bms', value: 'Unresponding gate to bms' },
  ],
  'Camera Malfunction': [
    { label: 'Dark Images', value: 'Dark Images' },
    { label: 'No Camera', value: 'No Camera' },
    { label: 'other', value: 'other' },
  ],
  'AC Malfunction': [
    { label: 'Water linking', value: 'Water linking' },
    { label: 'Unresponsive', value: 'Unresponsive' },
  ],
  'Printers': [
    { label: 'No ink', value: 'No ink' },
    { label: 'Unresponding printer to bms', value: 'Unresponding printer to bms' },
  ],
  'Monitors': [
    { label: 'Not responding', value: 'Not responding' },
    { label: 'other', value: 'other' },
  ],
  'UPS Malfunction': [
    { label: 'Bipping Sound', value: 'Bipping Sound' },
    { label: 'other', value: 'other' },
  ],
  
};

const SUPPORT_SUBJECT_OPTIONS_BY_MODULE = {
  'Toll Collection Module': [
    { label: 'Duplicate Payment', value: 'Duplicate Payment' },
    { label: 'Delayed Image capture', value: 'Delayed Image capture' },
    { label: 'Incorrect Plate Number capture', value: 'Incorrect Plate Number capture' },
    { label: 'Unrecognized Bundle Payment', value: 'Unrecognized Bundle Payment' },
  ],
  'Incident Module': [
    { label: 'Printer Malfunction', value: 'Printer Malfunction' },
    { label: 'Monitor Malfunction', value: 'Monitor Malfunction' },
    { label: 'Network Issue', value: 'Network Issue' },
    { label: 'Gate Malfunction', value: 'Gate Malfunction' },
    { label: 'UPS Malfunction', value: 'UPS Malfunction' },
    { label: 'Accident', value: 'Accident' },
  ],
  'Event Module': [
    { label: 'Control Number Issue', value: 'Control Number Issue' },
    { label: 'GEPG Response Issue', value: 'GEPG Response Issue' },
  ],
  'Overload Module': [
    { label: 'Control Number Issue', value: 'Control Number Issue' },
    { label: 'GEPG Response Issue', value: 'GEPG Response Issue' },
  ],
  'Advertisement Module': [
    { label: 'Control Number Issue', value: 'Control Number Issue' },
    { label: 'GEPG Response Issue', value: 'GEPG Response Issue' },
  ],
};

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

const getUploadedFile = (item) => item?.originFileObj || (item instanceof Blob ? item : null);

const fileToDataUrl = (file) =>
  new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => reject(reader.error || new Error('Failed to read file'));
    reader.readAsDataURL(file);
  });

const SUPPORT_REQUESTOR_ROLES = ['Toll Registrar', 'Toll Supervisor', 'Toll Accountant', 'Toll Reviewer'];
const REOPENABLE_STATUSES = ['closed', 'resolved'];
const REQUESTOR_ROLES_LABEL = 'Toll Registrar, Toll Supervisor, Toll Accountant, and Toll Reviewer';

const isSupportRequestor = (roleName) => {
  const normalized = String(roleName || '').trim().toLowerCase();
  return SUPPORT_REQUESTOR_ROLES.some((role) => role.toLowerCase() === normalized);
};

const canReopenRequest = (roleName, status) =>
  isSupportRequestor(roleName) && REOPENABLE_STATUSES.includes(String(status || '').toLowerCase());

const ICT_OFFICER = 'ICT Officer';
const TECHNICAL_OFFICER = 'Technical Officer';
const SYSTEM_REQUEST_TYPE = 'System/Software';
const HOLDABLE_STATUSES = ['opened', 'assigned', 'verified', 'reopened'];

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

const SUPERVISOR_REVIEWER_ROLES = ['Toll Supervisor', 'Toll Reviewer'];
const SUPERVISOR_REVIEWER_LABEL = 'Toll Supervisor and Toll Reviewer';
const CANCELABLE_STATUSES = ['opened', 'verified', 'assigned', 'reopened', 'on hold'];

const canCancelRequest = (roleName, status) => {
  const normalizedRole = String(roleName || '').trim().toLowerCase();
  return (
    SUPERVISOR_REVIEWER_ROLES.some((role) => role.toLowerCase() === normalizedRole) &&
    CANCELABLE_STATUSES.includes(String(status || '').toLowerCase())
  );
};

const CLOSEABLE_STATUSES = ['opened', 'assigned', 'verified', 'reopened', 'on hold'];

const canCloseRequest = (roleName, status, requestType) =>
  canImplementRequestType(roleName, requestType) &&
  CLOSEABLE_STATUSES.includes(String(status || '').toLowerCase());

const implementorActionMessage = (requestType, action) =>
  isSystemRequestType(requestType)
    ? `Only an ICT Officer can ${action} a System/Software request`
    : `Only a Technical Officer can ${action} an Infrastructure/hardware request`;

const isImageType = (type = '', name = '') =>
  String(type).startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp)$/i.test(name);

const isPdfType = (type = '', name = '') =>
  String(type) === 'application/pdf' || /\.pdf$/i.test(name);

const getRequesterUsername = (user) => {
  const source = user || {};
  const nested = source.user || source.employee || source.auth_user || {};
  return (
    source.username ||
    source.user_name ||
    source.userName ||
    nested.username ||
    nested.user_name ||
    source.login ||
    ''
  ).trim();
};

const displayRequestedBy = (value, user) => {
  const username = getRequesterUsername(user) || getRequesterUsername(getStoredUser());
  const stored = String(value || '').trim();
  if (username) return username;
  if (stored && !/\s/.test(stored)) return stored;
  return stored || '—';
};

const ManageRequests = () => {
  const { message } = App.useApp();
  const selectedRole = useSelector((state) => state.app.selectedRole);
  const currentUser = useSelector((state) => state.auth.user);
  const canCreate = isSupportRequestor(selectedRole);
  const [form] = Form.useForm();
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(false);
  const [selected, setSelected] = useState(null);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [showDocumentPreview, setShowDocumentPreview] = useState(false);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(15);
  const [total, setTotal] = useState(0);
  const [reopening, setReopening] = useState(false);
  const [isReopenOpen, setIsReopenOpen] = useState(false);
  const [reopenForm] = Form.useForm();
  const [isCancelOpen, setIsCancelOpen] = useState(false);
  const [cancelForm] = Form.useForm();
  const [cancelling, setCancelling] = useState(false);
  const [isHoldOpen, setIsHoldOpen] = useState(false);
  const [holdForm] = Form.useForm();
  const [holding, setHolding] = useState(false);
  const [closing, setClosing] = useState(false);
  const pendingFileRef = useRef(null);
  const selectedRequestType = Form.useWatch('requestType', form);
  const selectedSystemModule = Form.useWatch('systemModule', form);
  const selectedInfrastructureSupport = Form.useWatch('infrastructureSupport', form);
  const isInfrastructureRequest = selectedRequestType === INFRASTRUCTURE_REQUEST_TYPE;
  const supportSubjectOptions = isInfrastructureRequest
    ? (SUPPORT_SUBJECT_OPTIONS_BY_INFRASTRUCTURE[selectedInfrastructureSupport] || [])
    : (SUPPORT_SUBJECT_OPTIONS_BY_MODULE[selectedSystemModule] || []);
  const hasSubjectOptions = supportSubjectOptions.length > 0;
  const subjectSelectKey = isInfrastructureRequest
    ? selectedInfrastructureSupport
    : selectedSystemModule;

  const fetchRequests = async (nextPage = page, nextSize = pageSize) => {
    setLoading(true);
    try {
      const stored = listSupportRequests();
      const withOwner = stored.map((request) => {
        const requestedBy = displayRequestedBy(request.requestedBy, currentUser);
        return {
          ...request,
          requestedBy,
          minutes: ensureSupportMinutes(request, getSupportUsername(currentUser) || requestedBy, selectedRole),
        };
      });
      saveSupportRequests(withOwner);
      setRequests(withOwner);
      setPage(nextPage);
      setPageSize(nextSize);
      setTotal(stored.length);
    } catch (error) {
      message.error(error?.message || 'Failed to load support requests');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRequests(1, pageSize);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentUser]);

  useEffect(() => {
    if (!canCreate) {
      setIsCreateOpen(false);
    }
  }, [canCreate]);

  const openCreate = () => {
    if (!canCreate) {
      message.error(`Only ${REQUESTOR_ROLES_LABEL} can create a request`);
      return;
    }
    form.resetFields();
    setIsCreateOpen(true);
  };

  const closeCreate = () => {
    setIsCreateOpen(false);
    form.resetFields();
    pendingFileRef.current = null;
  };

  const beforeUploadAttachment = (file) => {
    if (file.size > MAX_ATTACHMENT_BYTES) {
      message.error('Supporting document must be 10MB or smaller');
      return Upload.LIST_IGNORE;
    }
    pendingFileRef.current = file;
    return false;
  };

  const handleCreate = async () => {
    if (!canCreate) {
      message.error(`Only ${REQUESTOR_ROLES_LABEL} can create a request`);
      return;
    }
    try {
      const values = await form.validateFields();
      setSubmitting(true);
      const nextId = nextSupportRequestId();
      const attachment = values.attachment?.[0];
      const uploadedFile = pendingFileRef.current || getUploadedFile(attachment);
      let attachmentUrl = null;
      if (uploadedFile) {
        attachmentUrl = await fileToDataUrl(uploadedFile);
      }
      const isInfrastructure = values.requestType === INFRASTRUCTURE_REQUEST_TYPE;
      const createdAt = new Date().toISOString();
      const created = {
        id: nextId,
        request_no: `SR-${String(nextId).padStart(4, '0')}`,
        requestType: values.requestType,
        priority: values.priority,
        systemName: isInfrastructure ? undefined : values.systemName,
        systemModule: isInfrastructure ? undefined : values.systemModule,
        infrastructureSupport: isInfrastructure ? values.infrastructureSupport : undefined,
        subject: typeof values.subject === 'string' ? values.subject.trim() : values.subject,
        description: values.description?.trim(),
        attachmentName: uploadedFile?.name || attachment?.name,
        attachmentUrl,
        attachmentType: uploadedFile?.type || '',
        status: 'Opened',
        requestedBy: displayRequestedBy('', currentUser),
        created_at: createdAt,
        minutes: [buildSupportMinute('Opened', getSupportUsername(currentUser), selectedRole)],
      };
      const nextRequests = addSupportRequest(created);
      setRequests(nextRequests);
      setTotal(nextRequests.length);
      setPage(1);
      pendingFileRef.current = null;
      closeCreate();
      message.success('Support request created');
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error?.message || 'Unable to create support request');
    } finally {
      setSubmitting(false);
    }
  };

  const openView = (record) => {
    setShowDocumentPreview(false);
    const username = getSupportUsername(currentUser) || displayRequestedBy(record?.requestedBy, currentUser);
    setSelected({
      ...record,
      minutes: ensureSupportMinutes(record, username, selectedRole),
    });
  };

  const closeView = () => {
    setShowDocumentPreview(false);
    setIsReopenOpen(false);
    reopenForm.resetFields();
    setIsCancelOpen(false);
    cancelForm.resetFields();
    setIsHoldOpen(false);
    holdForm.resetFields();
    setSelected(null);
  };

  const closeDocumentPreview = () => {
    setShowDocumentPreview(false);
  };

  const handleOpenDocument = () => {
    if (!selected?.attachmentUrl) {
      message.error('Document is not available for preview');
      return;
    }
    setShowDocumentPreview(true);
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
      setHolding(true);
      const nextRequests = updateSupportRequest(selected.id, {
        status: 'On Hold',
        on_hold_at: new Date().toISOString(),
        on_hold_by: selectedRole,
        hold_comment: comment,
        minutes: appendSupportMinute(selected, 'On Hold', getSupportUsername(currentUser), selectedRole, comment),
      });
      setRequests(nextRequests);
      setTotal(nextRequests.length);
      const updated = nextRequests.find((request) => String(request.id) === String(selected.id));
      if (updated) setSelected(updated);
      closeHold();
      message.success('Request placed on hold');
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error?.message || 'Unable to place the request on hold');
    } finally {
      setHolding(false);
    }
  };

  const handleCloseRequest = () => {
    if (!selected) return;
    if (!canCloseRequest(selectedRole, selected.status, selected.requestType)) {
      message.error(implementorActionMessage(selected.requestType, 'close'));
      return;
    }
    setClosing(true);
    try {
      const nextRequests = updateSupportRequest(selected.id, {
        status: 'Closed',
        closed_at: new Date().toISOString(),
        closed_by: selectedRole,
        minutes: appendSupportMinute(selected, 'Closed', getSupportUsername(currentUser), selectedRole),
      });
      setRequests(nextRequests);
      setTotal(nextRequests.length);
      const updated = nextRequests.find((request) => String(request.id) === String(selected.id));
      if (updated) setSelected(updated);
      message.success('Request closed');
    } catch (error) {
      message.error(error?.message || 'Unable to close the request');
    } finally {
      setClosing(false);
    }
  };

  const openReopen = () => {
    if (!selected) return;
    if (!canReopenRequest(selectedRole, selected.status)) {
      message.error(`Only ${REQUESTOR_ROLES_LABEL} can reopen a closed request`);
      return;
    }
    reopenForm.resetFields();
    setIsReopenOpen(true);
  };

  const closeReopen = () => {
    setIsReopenOpen(false);
    reopenForm.resetFields();
  };

  const handleReopen = async () => {
    if (!selected) return;
    if (!canReopenRequest(selectedRole, selected.status)) {
      message.error(`Only ${REQUESTOR_ROLES_LABEL} can reopen a closed request`);
      return;
    }
    try {
      const values = await reopenForm.validateFields();
      const comment = String(values.comment || '').trim();
      if (!comment) {
        message.error('A comment is required to reopen a closed request');
        return;
      }
      setReopening(true);
      const nextRequests = updateSupportRequest(selected.id, {
        status: 'Reopened',
        reopened_at: new Date().toISOString(),
        reopened_by: selectedRole,
        reopen_comment: comment,
        minutes: appendSupportMinute(selected, 'Reopened', getSupportUsername(currentUser), selectedRole, comment),
      });
      setRequests(nextRequests);
      setTotal(nextRequests.length);
      const updated = nextRequests.find((request) => String(request.id) === String(selected.id));
      if (updated) setSelected(updated);
      closeReopen();
      message.success('Request reopened');
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error?.message || 'Unable to reopen the request');
    } finally {
      setReopening(false);
    }
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
      setCancelling(true);
      const nextRequests = updateSupportRequest(selected.id, {
        status: 'Cancelled',
        cancelled_at: new Date().toISOString(),
        cancelled_by: selectedRole,
        cancel_comment: comment,
        minutes: appendSupportMinute(selected, 'Cancelled', getSupportUsername(currentUser), selectedRole, comment),
      });
      setRequests(nextRequests);
      setTotal(nextRequests.length);
      const updated = nextRequests.find((request) => String(request.id) === String(selected.id));
      if (updated) setSelected(updated);
      closeCancel();
      message.success('Request cancelled');
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error?.message || 'Unable to cancel the request');
    } finally {
      setCancelling(false);
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
      width: 140,
      searchable: true,
      render: (value) => value || '—',
    },
    {
      title: 'Requested By',
      dataIndex: 'requestedBy',
      key: 'requestedBy',
      width: 160,
      render: (value) => displayRequestedBy(value, currentUser),
    },
    {
      title: 'Request Type',
      dataIndex: 'requestType',
      key: 'requestType',
      searchable: true,
      render: (_, record) => record.requestType || record.source || '—',
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
      filters: STATUS_OPTIONS.map((option) => ({ text: option.label, value: option.value })),
      onFilter: (value, record) =>
        String(record.status || '').toLowerCase() === String(value).toLowerCase(),
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
      <div className="mb-4 flex items-center gap-2">
        <Headset size={20} color={BRAND} />
        <h1 className="m-0 text-lg font-semibold text-slate-900">Manage Requests</h1>
      </div>

      <DataTable
        columns={columns}
        data={requests}
        loading={loading}
        pagination={{
          current: page,
          pageSize,
          total,
          showTotal: () => null,
          showQuickJumper: false,
          onChange: (nextPage, nextSize) => {
            fetchRequests(nextPage, nextSize);
          },
        }}
        showSearch
        showRefresh={false}
        rightAction={
          canCreate ? (
            <Button
              type="primary"
              icon={<PlusOutlined />}
              className="btn-standard-primary"
              onClick={openCreate}
            >
              Create
            </Button>
          ) : null
        }
        searchPlaceholder="Search support requests..."
        rowKey={(record, index) => record.id || record.request_no || index}
        onRow={(record) => ({
          onClick: () => openView(record),
          style: { cursor: 'pointer' },
        })}
      />

      <Modal
        open={isCreateOpen}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        maskClosable={false}
        keyboard={false}
        width={680}
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <div
          className="flex items-center justify-between px-6 py-3"
          style={{ background: BRAND }}
        >
          <h2 className="m-0 text-base font-semibold text-white">Request</h2>
          <button
            type="button"
            aria-label="Close"
            onClick={closeCreate}
            className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
          >
            <CloseOutlined className="text-sm" />
          </button>
        </div>
        <div className="px-6 py-5">
          <Form form={form} layout="vertical" requiredMark={false}>
            <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
              <Form.Item
                name="requestType"
                label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Request Type</span>}
                rules={[{ required: true, message: 'Select a request type' }]}
              >
                <Select
                  placeholder="Select a request type"
                  options={REQUEST_TYPE_OPTIONS}
                  onChange={() => {
                    form.setFieldsValue({
                      systemName: undefined,
                      systemModule: undefined,
                      infrastructureSupport: undefined,
                      subject: undefined,
                    });
                  }}
                />
              </Form.Item>
              <Form.Item
                name="priority"
                label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Priority</span>}
                rules={[{ required: true, message: 'Select a priority' }]}
              >
                <Select placeholder="Select a priority" options={PRIORITY_OPTIONS} />
              </Form.Item>
              {isInfrastructureRequest ? (
                <Form.Item
                  name="infrastructureSupport"
                  label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Infrastructure Support</span>}
                  rules={[{ required: true, message: 'Select infrastructure support' }]}
                  className="sm:col-span-2"
                >
                  <Select
                    placeholder="Select infrastructure support"
                    options={INFRASTRUCTURE_SUPPORT_OPTIONS}
                    onChange={() => form.setFieldValue('subject', undefined)}
                  />
                </Form.Item>
              ) : (
                <>
                  <Form.Item
                    name="systemName"
                    label={<span className="text-xs font-semibold" style={{ color: BRAND }}>System Name</span>}
                    rules={[{ required: true, message: 'Select a system name' }]}
                  >
                    <Select placeholder="Select a system name" options={SYSTEM_NAME_OPTIONS} />
                  </Form.Item>
                  <Form.Item
                    name="systemModule"
                    label={<span className="text-xs font-semibold" style={{ color: BRAND }}>System Module</span>}
                    rules={[{ required: true, message: 'Select a system module' }]}
                  >
                    <Select
                      placeholder="Select a system module"
                      options={SYSTEM_MODULE_OPTIONS}
                      onChange={() => form.setFieldValue('subject', undefined)}
                    />
                  </Form.Item>
                </>
              )}
            </div>
            <Form.Item
              name="subject"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Support Subject</span>}
              rules={[{ required: true, message: hasSubjectOptions ? 'Select a support subject' : 'Enter a support subject' }]}
            >
              {hasSubjectOptions ? (
                <Select
                  key={subjectSelectKey}
                  placeholder="Select a support subject"
                  options={supportSubjectOptions}
                />
              ) : (
                <Input key={subjectSelectKey || 'subject-text'} placeholder="Enter support subject" maxLength={150} />
              )}
            </Form.Item>
            <Form.Item
              name="description"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Support Description</span>}
              rules={[{ required: true, message: 'Enter a support description' }]}
            >
              <Input.TextArea rows={4} maxLength={1000} showCount placeholder="Describe the support request" />
            </Form.Item>
            <Form.Item
              name="attachment"
              label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Supporting Document</span>}
              valuePropName="fileList"
              getValueFromEvent={(event) => (Array.isArray(event) ? event : event?.fileList ?? [])}
            >
              <Upload
                beforeUpload={beforeUploadAttachment}
                maxCount={1}
                onRemove={() => {
                  pendingFileRef.current = null;
                }}
              >
                <Button icon={<UploadOutlined />}>Select File</Button>
              </Upload>
            </Form.Item>
          </Form>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button onClick={closeCreate}>Cancel</Button>
          <Button
            type="primary"
            loading={submitting}
            onClick={handleCreate}
            className="btn-standard-primary"
          >
            Create
          </Button>
        </div>
      </Modal>

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
        <BrandModalHeader title="Request Details" onClose={closeView} />
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
                  <Field label="Requested By" value={displayRequestedBy(selected.requestedBy, currentUser)} />
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
                username={getSupportUsername(currentUser) || displayRequestedBy(selected.requestedBy, currentUser)}
                roleName={selectedRole}
              />
            </div>
            <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
              <Button onClick={closeView}>Close</Button>
              {canHoldRequest(selectedRole, selected.status, selected.requestType) ? (
                <Button
                  loading={holding}
                  onClick={openHold}
                >
                  On Hold
                </Button>
              ) : null}
              {canCloseRequest(selectedRole, selected.status, selected.requestType) ? (
                <Button
                  type="primary"
                  loading={closing}
                  className="btn-standard-primary"
                  onClick={handleCloseRequest}
                >
                  Close Request
                </Button>
              ) : null}
              {canCancelRequest(selectedRole, selected.status) ? (
                <Button
                  danger
                  loading={cancelling}
                  onClick={openCancel}
                >
                  Cancel Request
                </Button>
              ) : null}
              {canReopenRequest(selectedRole, selected.status) ? (
                <Button
                  type="primary"
                  loading={reopening}
                  className="btn-standard-primary"
                  onClick={openReopen}
                >
                  Reopen
                </Button>
              ) : null}
            </div>
          </div>
        )}
      </Modal>

      <Modal
        open={isReopenOpen}
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
        <BrandModalHeader title="Reopen Request" onClose={closeReopen} />
        <div className="px-6 py-5">
          <p className="mb-3 text-sm text-slate-600">
            Add a comment to reopen this closed request.
          </p>
          <Form form={reopenForm} layout="vertical" requiredMark={false}>
            <Form.Item
              name="comment"
              label="Comment"
              rules={[{ required: true, whitespace: true, message: 'Comment is required to reopen a closed request' }]}
            >
              <Input.TextArea
                rows={4}
                maxLength={500}
                showCount
                placeholder="Enter the reason for reopening this request"
              />
            </Form.Item>
          </Form>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button onClick={closeReopen}>Cancel</Button>
          <Button
            type="primary"
            loading={reopening}
            className="btn-standard-primary"
            onClick={handleReopen}
          >
            Reopen
          </Button>
        </div>
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
            loading={holding}
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
            loading={cancelling}
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
          onClose={closeDocumentPreview}
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

export default ManageRequests;
