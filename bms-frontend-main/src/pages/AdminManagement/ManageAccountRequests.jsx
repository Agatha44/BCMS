import { useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { App, Button, DatePicker, Form, Input, Select, Tag } from 'antd';
import dayjs from 'dayjs';
import { EyeOutlined, PlusOutlined } from '@ant-design/icons';
import { ClipboardList } from 'lucide-react';
import { DataTable } from '../../common/data/index.jsx';
import { getStoredUser } from '../../modules/auth/authSession.js';
import {
  BrandModalHeader,
  Field,
  FormLabel,
  GrantRoleModal,
  ModalFooter,
  SectionHeading,
} from './grantRole/grantRoleComponents.jsx';
import { BRAND, DATE_FORMAT, normalizeBridgeUser } from './grantRole/grantRoleUtils.js';
import {
  addAccountRequest,
  listAccountRequests,
  nextAccountRequestId,
  overlappingAssignedRoles,
  updateAccountRequest,
} from './accountRequestStore.js';
import {
  notifyAccountRequestDecision,
  notifyRequestorRoleAlreadyAssigned,
} from './accountRequestNotifications.js';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const REQUEST_TYPE_OPTIONS = [
  { label: 'Staff', value: 'Staff' },
  { label: 'Non-staff', value: 'Non-staff' },
];

const STAFF_REQUEST_TYPE = 'Staff';
const NON_STAFF_REQUEST_TYPE = 'Non-staff';

const SYSTEM_NAME_OPTIONS = [
  { label: 'Bridge Management System', value: 'Bridge Management System' },
];

const MODULE_NAME_OPTIONS = [
  'Account Management Module',
  'Administration Management Module',
  'Allowance Management Module',
  'Employee Management Module',
  'Incident Management Module',
  'Leave Management Module',
  'Toll Management Module',
  'Notification Management Module',
  'Payroll Management Module',
  'Support Management Module',
].map((name) => ({ label: name, value: name }));

const ROLE_OPTIONS = [
  'Toll Registrar',
  'Toll Collector',
  'Toll Supervisor',
  'Toll Approver',
  'Toll Reviewer',
  'Toll Auditor',
  'Toll Administrator',
  'Toll Accountant',
  'Employee Approver',
  'Employee Registrar',
  'Employee Administrator',
  'Support Administrator',
  'ICT Officer',
  'Technical Officer',
].map((name) => ({ label: name, value: name }));

const REQUESTOR_ROLES = ['Toll Administrator', 'Employee Approver'];
const APPROVER_ROLES = ['Toll Reviewer'];
const ACCESS_ROLES = ['Toll Approver', 'Toll Reviewer', 'Employee Approver'];

const getStatusTag = (status) => {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'granted') return <Tag color="green">Granted</Tag>;
  if (normalized === 'revoked') return <Tag color="orange">Revoked</Tag>;
  if (normalized === 'approved') return <Tag color="cyan">Approved</Tag>;
  if (normalized === 'rejected') return <Tag color="red">Rejected</Tag>;
  if (normalized === 'under review') return <Tag color="gold">Under Review</Tag>;
  return <Tag color="blue">{status || 'Submitted'}</Tag>;
};

const formatDate = (value) => {
  if (!value) return null;
  const date = dayjs(value);
  return date.isValid() ? date.format('D MMM YYYY') : String(value);
};

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

const displayUserName = (user) => {
  if (!user) return '—';
  if (user.full_name && String(user.full_name).trim()) return String(user.full_name).trim();
  const fromParts = [user.first_name, user.surname].filter(Boolean).join(' ').trim();
  return fromParts || user.username || user.email || '—';
};

const hasAnyRole = (roleName, allowedRoles) =>
  allowedRoles.some((role) => role.toLowerCase() === String(roleName || '').trim().toLowerCase());

const formatRequestedRoles = (roles) => {
  if (Array.isArray(roles)) return roles.filter(Boolean).join(', ');
  return roles || '';
};

const uniqueRoles = (roles) => {
  const seen = new Set();
  return (Array.isArray(roles) ? roles : [roles])
    .map((role) => String(role || '').trim())
    .filter((role) => {
      const key = role.toLowerCase();
      if (!role || seen.has(key)) return false;
      seen.add(key);
      return true;
    });
};

const fetchAssignedRolesFromUsers = async (accountName) => {
  const name = String(accountName || '').trim().toLowerCase();
  if (!name) return [];
  try {
    const response = await apiService.getBridgeUsers({ search: accountName, per_page: 50, page: 1 });
    if (!response?.success || !response.data) return [];
    const { items } = extractArrayFromResponse(response.data);
    return (items || [])
      .map((item) => normalizeBridgeUser(item))
      .filter((user) => String(user?.full_name || '').trim().toLowerCase() === name)
      .flatMap((user) => (user.roles || []).map((role) => role.name).filter(Boolean));
  } catch {
    return [];
  }
};

const findAlreadyAssignedRoles = async (accountName, requestedRoles) => {
  const fromRequests = overlappingAssignedRoles(accountName, requestedRoles);
  const fromUsers = await fetchAssignedRolesFromUsers(accountName);
  const assignedLookup = new Set(fromUsers.map((role) => String(role).trim().toLowerCase()));
  const fromApi = (Array.isArray(requestedRoles) ? requestedRoles : [requestedRoles])
    .map((role) => String(role || '').trim())
    .filter((role) => role && assignedLookup.has(role.toLowerCase()));
  return uniqueRoles([...fromRequests, ...fromApi]);
};

const ACTION_COMMENT_CONFIG = {
  approve: {
    title: 'Approve Account Request',
    label: 'Approval Comment',
    placeholder: 'Enter a comment before approving this request',
    submitLabel: 'Approve',
    successMessage: 'Account request approved',
    danger: false,
  },
  reject: {
    title: 'Reject Account Request',
    label: 'Rejection Comment',
    placeholder: 'Enter a comment before rejecting this request',
    submitLabel: 'Reject Request',
    successMessage: 'Account request rejected',
    danger: true,
  },
  grant: {
    title: 'Grant Access',
    label: 'Grant Comment',
    placeholder: 'Enter a comment before granting access',
    submitLabel: 'Grant Access',
    successMessage: 'Access granted for the requested roles and module',
    danger: false,
  },
  revoke: {
    title: 'Revoke Access',
    label: 'Revoke Comment',
    placeholder: 'Enter a comment before revoking access',
    submitLabel: 'Revoke Access',
    successMessage: 'Access revoked for the requested roles and module',
    danger: true,
  },
};

const ManageAccountRequests = () => {
  const { message, notification } = App.useApp();
  const selectedRole = useSelector((state) => state.app.selectedRole);
  const currentUser = getStoredUser();
  const [form] = Form.useForm();
  const [actionForm] = Form.useForm();
  const requestType = Form.useWatch('requestType', form);
  const isStaffRequest = requestType === STAFF_REQUEST_TYPE;
  const isNonStaffRequest = requestType === NON_STAFF_REQUEST_TYPE;
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(15);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [pendingAction, setPendingAction] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [selected, setSelected] = useState(null);

  const canRequest = hasAnyRole(selectedRole, REQUESTOR_ROLES);
  const canApprove = hasAnyRole(selectedRole, APPROVER_ROLES);
  const canManageAccess = hasAnyRole(selectedRole, ACCESS_ROLES);
  const selectedStatus = String(selected?.status || '').toLowerCase();
  const canApproveOrReject = canApprove && ['submitted', 'under review'].includes(selectedStatus);
  const canGrantAccess = canManageAccess && ['approved', 'revoked'].includes(selectedStatus);
  const canRevokeAccess = canManageAccess && ['approved', 'granted'].includes(selectedStatus);

  const fetchRequests = (nextPage = 1, nextSize = pageSize) => {
    setLoading(true);
    const all = listAccountRequests();
    setRequests(all);
    setPage(nextPage);
    setPageSize(nextSize);
    setLoading(false);
  };

  useEffect(() => {
    fetchRequests(1, pageSize);
  }, []);

  const openCreate = () => {
    if (!canRequest) {
      message.error('Only Toll Administrator and Employee Approver can submit account requests');
      return;
    }
    form.resetFields();
    setIsCreateOpen(true);
  };

  const closeCreate = () => {
    form.resetFields();
    setIsCreateOpen(false);
  };

  const handleCreate = async () => {
    if (!canRequest) {
      message.error('Only Toll Administrator and Employee Approver can submit account requests');
      return;
    }
    try {
      const values = await form.validateFields();
      setSubmitting(true);
      const nextId = nextAccountRequestId();
      const isStaff = values.requestType === STAFF_REQUEST_TYPE;
      const requestedRole = Array.isArray(values.requestedRole)
        ? values.requestedRole
        : [values.requestedRole].filter(Boolean);
      const accountName = isStaff
        ? String(values.staffName || '').trim()
        : String(values.name || '').trim();
      const alreadyAssigned = await findAlreadyAssignedRoles(accountName, requestedRole);
      const created = {
        id: nextId,
        request_no: `AR-${String(nextId).padStart(4, '0')}`,
        accountName,
        staffName: isStaff ? values.staffName : undefined,
        name: isStaff ? undefined : values.name,
        organization: values.organization,
        section: isStaff ? values.section : undefined,
        systemName: isStaff ? values.systemName : undefined,
        moduleName: values.moduleName,
        startDate: isStaff ? undefined : values.startDate?.format?.(DATE_FORMAT) || values.startDate,
        endDate: isStaff ? undefined : values.endDate?.format?.(DATE_FORMAT) || values.endDate,
        requestType: values.requestType,
        requestedRole,
        justification: values.justification,
        status: 'Submitted',
        requestedBy: displayUserName(currentUser),
        requestedByUserId: currentUser?.id ?? currentUser?.user_id ?? null,
        requestedByUsername: currentUser?.username || null,
        created_at: new Date().toISOString(),
      };
      setRequests(addAccountRequest(created));
      setPage(1);
      closeCreate();
      if (alreadyAssigned.length) {
        notifyRequestorOfAssignedRoles(created, alreadyAssigned);
      }
      message.success('Account request submitted');
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error?.message || 'Unable to submit account request');
    } finally {
      setSubmitting(false);
    }
  };

  const notifyRequestorOfAssignedRoles = (request, assignedRoles) => {
    if (!assignedRoles.length) return;
    const notice = notifyRequestorRoleAlreadyAssigned({
      requestorName: request.requestedBy,
      requestorUserId: request.requestedByUserId,
      requestorUsername: request.requestedByUsername,
      accountName: request.accountName,
      assignedRoles,
      requestNo: request.request_no,
    });
    notification.warning({
      message: notice.title,
      description: notice.message,
      placement: 'topRight',
      duration: 8,
    });
  };

  const persistSelected = (id, patch) => {
    const next = updateAccountRequest(id, patch);
    setRequests(next);
    const updated = next.find((item) => String(item.id) === String(id));
    setSelected(updated || null);
    return updated;
  };

  const closeActionComment = () => {
    setPendingAction(null);
    actionForm.resetFields();
  };

  const openActionComment = (action) => {
    if (!selected) return;
    if (action === 'approve' && !canApproveOrReject) {
      message.error('Only Toll Reviewer can approve account requests');
      return;
    }
    if (action === 'reject' && !canApproveOrReject) {
      message.error('Only Toll Reviewer can reject account requests');
      return;
    }
    if (action === 'grant' && !canGrantAccess) {
      message.error('Only Toll Approver, Toll Reviewer, and Employee Approver can grant access');
      return;
    }
    if (action === 'revoke' && !canRevokeAccess) {
      message.error('Only Toll Approver, Toll Reviewer, and Employee Approver can revoke access');
      return;
    }
    actionForm.resetFields();
    setPendingAction(action);
  };

  const handleConfirmAction = async () => {
    if (!selected || !pendingAction) return;
    const config = ACTION_COMMENT_CONFIG[pendingAction];
    try {
      const values = await actionForm.validateFields();
      const comment = String(values.comment || '').trim();
      if (!comment) {
        message.error('A comment is required before this action');
        return;
      }
      const actor = displayUserName(currentUser);
      const now = new Date().toISOString();
      const nextComment = {
        action: config.submitLabel,
        comment,
        by: actor,
        role: selectedRole,
        at: now,
      };
      const comments = [...(selected.comments || []), nextComment];
      const patch = { comments };
      if (pendingAction === 'approve') {
        Object.assign(patch, {
          status: 'Approved',
          reviewedBy: actor,
          decided_at: now,
          decisionComment: comment,
        });
      } else if (pendingAction === 'reject') {
        Object.assign(patch, {
          status: 'Rejected',
          reviewedBy: actor,
          decided_at: now,
          decisionComment: comment,
        });
      } else if (pendingAction === 'grant') {
        const alreadyAssigned = await findAlreadyAssignedRoles(
          selected.accountName,
          selected.requestedRole
        );
        if (alreadyAssigned.length) {
          notifyRequestorOfAssignedRoles(selected, alreadyAssigned);
        }
        Object.assign(patch, {
          status: 'Granted',
          accessActionBy: actor,
          accessActionAt: now,
          accessComment: comment,
        });
      } else if (pendingAction === 'revoke') {
        Object.assign(patch, {
          status: 'Revoked',
          accessActionBy: actor,
          accessActionAt: now,
          accessComment: comment,
        });
      }
      const updated = persistSelected(selected.id, patch);
      if (pendingAction === 'grant' || pendingAction === 'revoke') {
        const sent = notifyAccountRequestDecision({
          request: updated || { ...selected, ...patch },
          action: pendingAction,
          actor,
          comment,
        });
        sent.forEach((item) => {
          notification.open({
            type: pendingAction === 'grant' ? 'success' : 'warning',
            message: item.title,
            description: item.message,
            placement: 'topRight',
            duration: 8,
          });
        });
      }
      closeActionComment();
      message.success(config.successMessage);
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error?.message || 'Unable to complete this action');
    }
  };

  const columns = [
    {
      title: 'Request No',
      dataIndex: 'request_no',
      key: 'request_no',
      searchable: true,
      width: 130,
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
      width: 180,
    },
    {
      title: 'Requested Role',
      dataIndex: 'requestedRole',
      key: 'requestedRole',
      searchable: true,
      width: 220,
      render: (roles) => formatRequestedRoles(roles) || '—',
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
      width: 150,
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 100,
      align: 'center',
      render: (_, record) => (
        <Button
          type="primary"
          icon={<EyeOutlined />}
          size="small"
          className="btn-standard-primary"
          onClick={(event) => {
            event.stopPropagation();
            setSelected(record);
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
        <h1 className="m-0 text-lg font-semibold text-slate-900">Manage Account-requests</h1>
      </div>

      <DataTable
        columns={columns}
        data={requests}
        loading={loading}
        pagination={{
          current: page,
          pageSize,
          total: requests.length,
          showTotal: () => null,
          showQuickJumper: false,
          onChange: (nextPage, nextSize) => {
            fetchRequests(nextPage, nextSize);
          },
        }}
        showSearch
        showRefresh={false}
        rightAction={
          canRequest ? (
            <Button
              type="primary"
              icon={<PlusOutlined />}
              className="btn-standard-primary"
              onClick={openCreate}
            >
              New Account Request
            </Button>
          ) : null
        }
        searchPlaceholder="Search account requests..."
        rowKey={(record, index) => record.id || record.request_no || index}
        onRow={(record) => ({
          onClick: () => setSelected(record),
          style: { cursor: 'pointer' },
        })}
      />

      <GrantRoleModal
        open={isCreateOpen}
        onCancel={closeCreate}
        width={680}
        maskClosable={false}
        keyboard={false}
      >
        <BrandModalHeader title="New Account Request" onClose={closeCreate} />
        <div className="px-6 py-5">
          <Form form={form} layout="vertical" requiredMark={false}>
            <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
              <Form.Item
                name="requestType"
                label={<FormLabel>Request Type</FormLabel>}
                rules={[{ required: true, message: 'Select a request type' }]}
              >
                <Select
                  placeholder="Select a request type"
                  options={REQUEST_TYPE_OPTIONS}
                  onChange={() => {
                    form.setFieldsValue({
                      staffName: undefined,
                      name: undefined,
                      organization: undefined,
                      section: undefined,
                      systemName: undefined,
                      moduleName: undefined,
                      startDate: undefined,
                      endDate: undefined,
                    });
                  }}
                />
              </Form.Item>
              <Form.Item
                name="requestedRole"
                label={<FormLabel>Requested Role</FormLabel>}
                rules={[{ required: true, type: 'array', min: 1, message: 'Select at least one requested role' }]}
              >
                <Select
                  mode="multiple"
                  allowClear
                  placeholder="Select one or more roles"
                  options={ROLE_OPTIONS}
                  maxTagCount="responsive"
                />
              </Form.Item>
              {isStaffRequest ? (
                <>
                  <Form.Item
                    name="staffName"
                    label={<FormLabel>Staff Name</FormLabel>}
                    rules={[{ required: true, message: 'Enter staff name' }]}
                  >
                    <Input placeholder="Enter staff name" maxLength={120} />
                  </Form.Item>
                  <Form.Item
                    name="organization"
                    label={<FormLabel>Organization</FormLabel>}
                    rules={[{ required: true, message: 'Enter organization' }]}
                  >
                    <Input placeholder="Enter organization" maxLength={120} />
                  </Form.Item>
                  <Form.Item
                    name="section"
                    label={<FormLabel>Section</FormLabel>}
                    rules={[{ required: true, message: 'Enter section' }]}
                  >
                    <Input placeholder="Enter section" maxLength={120} />
                  </Form.Item>
                  <Form.Item
                    name="systemName"
                    label={<FormLabel>System Name</FormLabel>}
                    rules={[{ required: true, message: 'Select a system name' }]}
                  >
                    <Select placeholder="Select a system name" options={SYSTEM_NAME_OPTIONS} />
                  </Form.Item>
                  <Form.Item
                    name="moduleName"
                    label={<FormLabel>Module Name</FormLabel>}
                    rules={[{ required: true, message: 'Select a module name' }]}
                  >
                    <Select placeholder="Select a module name" options={MODULE_NAME_OPTIONS} />
                  </Form.Item>
                </>
              ) : null}
              {isNonStaffRequest ? (
                <>
                  <Form.Item
                    name="name"
                    label={<FormLabel>Name</FormLabel>}
                    rules={[{ required: true, message: 'Enter name' }]}
                  >
                    <Input placeholder="Enter name" maxLength={120} />
                  </Form.Item>
                  <Form.Item
                    name="organization"
                    label={<FormLabel>Organization</FormLabel>}
                    rules={[{ required: true, message: 'Enter organization' }]}
                  >
                    <Input placeholder="Enter organization" maxLength={120} />
                  </Form.Item>
                  <Form.Item
                    name="startDate"
                    label={<FormLabel>Start Date</FormLabel>}
                    rules={[{ required: true, message: 'Select a start date' }]}
                  >
                    <DatePicker className="w-full" format={DATE_FORMAT} placeholder="Select start date" />
                  </Form.Item>
                  <Form.Item
                    name="endDate"
                    label={<FormLabel>End Date</FormLabel>}
                    dependencies={['startDate']}
                    rules={[
                      { required: true, message: 'Select an end date' },
                      ({ getFieldValue }) => ({
                        validator(_, value) {
                          const startDate = getFieldValue('startDate');
                          if (!value || !startDate || !value.isBefore(startDate, 'day')) {
                            return Promise.resolve();
                          }
                          return Promise.reject(new Error('End date must be on or after the start date'));
                        },
                      }),
                    ]}
                  >
                    <DatePicker className="w-full" format={DATE_FORMAT} placeholder="Select end date" />
                  </Form.Item>
                  <Form.Item
                    name="moduleName"
                    label={<FormLabel>Module Name</FormLabel>}
                    rules={[{ required: true, message: 'Select a module name' }]}
                  >
                    <Select placeholder="Select a module name" options={MODULE_NAME_OPTIONS} />
                  </Form.Item>
                </>
              ) : null}
            </div>
            <Form.Item
              name="justification"
              label={<FormLabel>Justification</FormLabel>}
              rules={[{ required: true, message: 'Enter a justification' }]}
            >
              <Input.TextArea
                rows={4}
                maxLength={500}
                showCount
                placeholder="Explain why this account request is needed"
              />
            </Form.Item>
          </Form>
        </div>
        <ModalFooter>
          <Button onClick={closeCreate}>Cancel</Button>
          <Button
            type="primary"
            loading={submitting}
            onClick={handleCreate}
            className="btn-standard-primary"
          >
            Submit Request
          </Button>
        </ModalFooter>
      </GrantRoleModal>

      <GrantRoleModal
        open={Boolean(selected)}
        onCancel={() => setSelected(null)}
        width={760}
        maskClosable={false}
        keyboard={false}
      >
        <BrandModalHeader title="Account Request Details" onClose={() => setSelected(null)} />
        {selected && (
          <div className="flex max-h-[85vh] flex-col">
            <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
              <div className="mb-5 rounded-md border border-[#ead6d7] bg-[#fff8f8] px-4 py-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <span
                      className="text-[11px] font-semibold uppercase tracking-[0.08em]"
                      style={{ color: BRAND }}
                    >
                      Account Request Number
                    </span>
                    <div className="mt-0.5 font-mono text-base font-semibold text-black">
                      {selected.request_no}
                    </div>
                    <div className="mt-1 text-xs text-slate-500">
                      {formatDateTime(selected.created_at)
                        ? `Submitted ${formatDateTime(selected.created_at)}`
                        : 'Submission time unavailable'}
                    </div>
                  </div>
                  {getStatusTag(selected.status)}
                </div>
              </div>

              <SectionHeading>Account Holder</SectionHeading>
              <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                <Field label="Account Name" value={selected.accountName} />
                <Field label="Requested Role" value={formatRequestedRoles(selected.requestedRole)} />
                {selected.requestType === STAFF_REQUEST_TYPE ? (
                  <>
                    <Field label="Staff Name" value={selected.staffName} />
                    <Field label="Organization" value={selected.organization} />
                    <Field label="Section" value={selected.section} />
                    <Field label="System Name" value={selected.systemName} />
                    <Field label="Module Name" value={selected.moduleName} />
                  </>
                ) : null}
                {selected.requestType === NON_STAFF_REQUEST_TYPE ? (
                  <>
                    <Field label="Name" value={selected.name} />
                    <Field label="Organization" value={selected.organization} />
                    <Field label="Module Name" value={selected.moduleName} />
                    <Field label="Start Date" value={formatDate(selected.startDate)} />
                    <Field label="End Date" value={formatDate(selected.endDate)} />
                  </>
                ) : null}
              </div>

              <SectionHeading>Request Information</SectionHeading>
              <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                <Field label="Request Type" value={selected.requestType} />
                <Field label="Requested By" value={selected.requestedBy} />
                <Field label="Reviewed By" value={selected.reviewedBy} />
                <Field label="Decision Date" value={formatDateTime(selected.decided_at)} />
                <Field label="Access Action By" value={selected.accessActionBy} />
                <Field label="Access Action Date" value={formatDateTime(selected.accessActionAt)} />
              </div>
              <div className="mt-2">
                <Field label="Justification" value={selected.justification} />
              </div>
              {(selected.comments || []).length > 0 ? (
                <div className="mt-4">
                  <SectionHeading>Action Comments</SectionHeading>
                  <div className="space-y-3">
                    {selected.comments.map((entry, index) => (
                      <div key={`${entry.at}-${index}`} className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <span className="text-sm font-semibold text-slate-900">{entry.action}</span>
                          <span className="text-xs text-slate-500">{formatDateTime(entry.at)}</span>
                        </div>
                        <div className="mt-1 text-xs text-slate-500">
                          {entry.by}
                          {entry.role ? ` · ${entry.role}` : ''}
                        </div>
                        <div className="mt-1.5 text-sm text-black">{entry.comment}</div>
                      </div>
                    ))}
                  </div>
                </div>
              ) : null}
            </div>
            <ModalFooter>
              <Button onClick={() => setSelected(null)}>Close</Button>
              {canApproveOrReject ? (
                <>
                  <Button danger onClick={() => openActionComment('reject')}>
                    Reject
                  </Button>
                  <Button type="primary" className="btn-standard-primary" onClick={() => openActionComment('approve')}>
                    Approve
                  </Button>
                </>
              ) : null}
              {canRevokeAccess ? (
                <Button danger onClick={() => openActionComment('revoke')}>
                  Revoke Access
                </Button>
              ) : null}
              {canGrantAccess ? (
                <Button type="primary" className="btn-standard-primary" onClick={() => openActionComment('grant')}>
                  Grant Access
                </Button>
              ) : null}
            </ModalFooter>
          </div>
        )}
      </GrantRoleModal>

      <GrantRoleModal
        open={Boolean(pendingAction)}
        onCancel={closeActionComment}
        width={480}
        maskClosable={false}
        keyboard={false}
      >
        <BrandModalHeader
          title={ACTION_COMMENT_CONFIG[pendingAction]?.title || 'Comment'}
          onClose={closeActionComment}
        />
        <div className="px-6 py-5">
          <Form form={actionForm} layout="vertical" requiredMark={false}>
            <Form.Item
              name="comment"
              label={<FormLabel>{ACTION_COMMENT_CONFIG[pendingAction]?.label || 'Comment'}</FormLabel>}
              rules={[{ required: true, message: 'Enter a comment before this action' }]}
            >
              <Input.TextArea
                rows={4}
                maxLength={400}
                showCount
                placeholder={ACTION_COMMENT_CONFIG[pendingAction]?.placeholder || 'Enter a comment'}
              />
            </Form.Item>
          </Form>
        </div>
        <ModalFooter>
          <Button onClick={closeActionComment}>Cancel</Button>
          <Button
            type="primary"
            danger={Boolean(ACTION_COMMENT_CONFIG[pendingAction]?.danger)}
            className={ACTION_COMMENT_CONFIG[pendingAction]?.danger ? undefined : 'btn-standard-primary'}
            onClick={handleConfirmAction}
          >
            {ACTION_COMMENT_CONFIG[pendingAction]?.submitLabel || 'Submit'}
          </Button>
        </ModalFooter>
      </GrantRoleModal>
    </div>
  );
};

export default ManageAccountRequests;
