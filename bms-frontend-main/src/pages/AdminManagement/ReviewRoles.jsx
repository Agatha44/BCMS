import { useEffect, useMemo, useState } from 'react';
import { useSelector } from 'react-redux';
import { Alert, App, Button, Checkbox, DatePicker, Form, Input, Select, Tag } from 'antd';
import { CheckOutlined, EyeOutlined, StopOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { UserCheck } from 'lucide-react';
import { DataTable } from '../../common/data/index.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse } from '../../common/utils/employeeUtils.jsx';
import { getStoredUser } from '../../modules/auth/authSession.js';
import {
  BrandModalHeader,
  Field,
  FormLabel,
  GrantRoleModal,
  ModalFooter,
  SectionHeading,
} from './grantRole/grantRoleComponents.jsx';
import {
  BRAND,
  buildDisplayName,
  getTableRowKey,
  getUserNida,
  normalizeBridgeUser,
  roleRowKey,
} from './grantRole/grantRoleUtils.js';
import {
  getMonthReviews,
  getUserReview,
  markUserReviewed,
  monthKeyFromDate,
  recordUserReviewAction,
  userReviewKey,
} from './roleReviewStore.js';
import { notifyRoleReviewDecision } from './accountRequestNotifications.js';
import '../../styles/common.css';

const REVIEW_FILTERS = [
  { label: 'All usernames', value: 'all' },
  { label: 'Not reviewed this month', value: 'pending' },
  { label: 'Reviewed this month', value: 'reviewed' },
  { label: 'Enforced', value: 'enforced' },
];

const REVIEWER_ROLES = ['Toll Reviewer'];

const ACTION_CONFIG = {
  approve: {
    title: 'Approve Assigned Roles',
    label: 'Justification',
    placeholder: 'Enter a justification for approving these assigned roles',
    submitLabel: 'Approve Roles',
    successMessage: 'Assigned roles approved',
    danger: false,
  },
  revoke: {
    title: 'Revoke Assigned Roles',
    label: 'Justification',
    placeholder: 'Enter a justification for revoking these assigned roles',
    submitLabel: 'Revoke Roles',
    successMessage: 'Assigned roles revoked',
    danger: true,
  },
};

const displayUserName = (user) => {
  if (!user) return '—';
  return buildDisplayName(user) || user.username || user.email || '—';
};

const usernameOf = (user) =>
  String(user?.username || user?.user_name || user?.pf_number || '').trim();

const assignedRoleObjectsOf = (user) =>
  (Array.isArray(user?.roles) ? user.roles : []).filter((role) => role?.id || role?.name);

const assignedRolesOf = (user) =>
  assignedRoleObjectsOf(user)
    .map((role) => String(role?.name || role?.role_name || '').trim())
    .filter(Boolean);

const roleNameOf = (role) => String(role?.name || role?.role_name || '').trim();

const hasAnyRole = (roleName, allowedRoles) =>
  allowedRoles.some((role) => role.toLowerCase() === String(roleName || '').trim().toLowerCase());

const formatDateTime = (value) => {
  if (!value) return '—';
  const date = dayjs(value);
  return date.isValid() ? date.format('D MMM YYYY HH:mm') : String(value);
};

const formatDate = (value) => {
  if (!value) return '—';
  const date = dayjs(value);
  return date.isValid() ? date.format('D MMM YYYY') : String(value);
};

const getEnforceDate = (reviewMonth) => reviewMonth.endOf('month').add(3, 'day').startOf('day');

const isEnforcementStarted = (reviewMonth, today = dayjs()) =>
  !today.startOf('day').isBefore(getEnforceDate(reviewMonth));

const latestRoleDecision = (review, roleName) => {
  const name = String(roleName || '').trim().toLowerCase();
  const actions = Array.isArray(review?.actions) ? review.actions : [];
  for (let index = actions.length - 1; index >= 0; index -= 1) {
    const action = actions[index];
    const matched = (action.roles || []).some((role) => String(role).trim().toLowerCase() === name);
    if (matched) return action;
  }
  return null;
};

const ReviewRoles = () => {
  const { message } = App.useApp();
  const selectedRole = useSelector((state) => state.app.selectedRole);
  const currentUser = getStoredUser();
  const [form] = Form.useForm();
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [acting, setActing] = useState(false);
  const [reviewMonth, setReviewMonth] = useState(dayjs());
  const [reviewFilter, setReviewFilter] = useState('all');
  const [usernameFilter, setUsernameFilter] = useState('');
  const [reviews, setReviews] = useState({});
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(15);
  const [selected, setSelected] = useState(null);
  const [selectedRoleKeys, setSelectedRoleKeys] = useState([]);
  const [pendingAction, setPendingAction] = useState(null);

  const monthKey = monthKeyFromDate(reviewMonth);
  const enforceDate = getEnforceDate(reviewMonth);
  const enforcementStarted = isEnforcementStarted(reviewMonth);
  const canDecideRoles = hasAnyRole(selectedRole, REVIEWER_ROLES);
  const selectedRoles = selected
    ? assignedRoleObjectsOf(selected).filter((role) => selectedRoleKeys.includes(String(roleRowKey(role))))
    : [];

  const refreshReviews = (nextMonthKey = monthKey) => {
    setReviews(getMonthReviews(nextMonthKey));
  };

  const fetchAllUsers = async () => {
    setLoading(true);
    try {
      const collected = [];
      let pageNumber = 1;
      const perPage = 100;
      let lastPage = 1;

      do {
        const response = await apiService.getBridgeUsers({ page: pageNumber, per_page: perPage });
        if (!response?.success) {
          throw new Error(response?.message || 'Failed to fetch users');
        }
        const { items, pagination } = extractArrayFromResponse(response.data || {});
        const batch = (items || []).map((item) => normalizeBridgeUser(item)).filter(Boolean);
        collected.push(...batch);
        lastPage = Number(pagination?.last_page || pagination?.total_pages || pageNumber);
        if (!batch.length || batch.length < perPage) break;
        pageNumber += 1;
      } while (pageNumber <= lastPage && pageNumber <= 50);

      const unique = [];
      const seen = new Set();
      collected.forEach((user) => {
        const key = getTableRowKey(user);
        if (seen.has(key)) return;
        seen.add(key);
        unique.push(user);
      });
      unique.sort((a, b) => usernameOf(a).localeCompare(usernameOf(b), undefined, { sensitivity: 'base' }));
      setUsers(unique);
    } catch (error) {
      console.error('Error fetching users:', error);
      message.error(error.message || 'Unable to load usernames and assigned roles');
      setUsers([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAllUsers();
    refreshReviews(monthKey);
  }, []);

  const reviewStatusOf = (user) => {
    const review = reviews[userReviewKey(user)] || getUserReview(monthKey, user);
    if (review) return 'reviewed';
    if (enforcementStarted) return 'enforced';
    return 'pending';
  };

  const usernameOptions = useMemo(
    () =>
      users
        .map((user) => usernameOf(user))
        .filter(Boolean)
        .map((username) => ({ label: username, value: username })),
    [users]
  );

  const rows = useMemo(() => {
    return users.filter((user) => {
      const username = usernameOf(user);
      if (usernameFilter && username !== usernameFilter) return false;
      const status = reviewStatusOf(user);
      if (reviewFilter === 'pending') return status === 'pending';
      if (reviewFilter === 'reviewed') return status === 'reviewed';
      if (reviewFilter === 'enforced') return status === 'enforced';
      return true;
    });
  }, [users, reviews, reviewFilter, usernameFilter, monthKey, enforcementStarted]);

  const reviewedCount = users.filter((user) => reviewStatusOf(user) === 'reviewed').length;
  const enforcedCount = users.filter((user) => reviewStatusOf(user) === 'enforced').length;

  const applyUserUpdate = (user, nextRoles) => {
    const updated = { ...user, roles: nextRoles };
    setUsers((current) =>
      current.map((item) => (getTableRowKey(item) === getTableRowKey(user) ? { ...item, ...updated } : item))
    );
    setSelected((current) => (current && getTableRowKey(current) === getTableRowKey(user) ? updated : current));
    return updated;
  };

  const openUser = (user) => {
    setSelected(user);
    setSelectedRoleKeys(assignedRoleObjectsOf(user).map((role) => String(roleRowKey(role))));
  };

  const closeUser = () => {
    setSelected(null);
    setSelectedRoleKeys([]);
    setPendingAction(null);
    form.resetFields();
  };

  const handleMarkReviewed = (user) => {
    markUserReviewed(monthKey, user, {
      username: usernameOf(user),
      accountName: displayUserName(user),
      assignedRoles: assignedRolesOf(user),
      reviewedBy: displayUserName(currentUser),
    });
    refreshReviews();
    setSelected((current) => (current && getTableRowKey(current) === getTableRowKey(user) ? { ...user } : current));
    message.success(`${usernameOf(user) || displayUserName(user)} marked as reviewed for ${reviewMonth.format('MMMM YYYY')}`);
  };

  const openDecisionFromModal = (action) => {
    if (!selected) return;
    if (!canDecideRoles) {
      message.error('Only Toll Reviewer can approve or revoke assigned roles');
      return;
    }
    if (!selectedRoles.length) {
      message.error('Select at least one assigned role for this username');
      return;
    }
    form.resetFields();
    setPendingAction(action);
  };

  const closeDecision = () => {
    setPendingAction(null);
    form.resetFields();
  };

  const handleConfirmDecision = async () => {
    if (!selected || !pendingAction) return;
    const config = ACTION_CONFIG[pendingAction];
    try {
      const values = await form.validateFields();
      const justification = String(values.justification || '').trim();
      if (!justification) {
        message.error('A justification is required before this action');
        return;
      }
      const rolesToAct = selectedRoles.length ? selectedRoles : assignedRoleObjectsOf(selected);
      if (!rolesToAct.length) {
        message.error('Select at least one assigned role for this username');
        return;
      }

      setActing(true);
      let remainingRoles = assignedRoleObjectsOf(selected);
      let notifiedRoles = rolesToAct.map((role) => roleNameOf(role)).filter(Boolean);
      if (pendingAction === 'revoke') {
        const nida = getUserNida(selected);
        if (!nida) {
          message.error('This username is missing a national ID, so assigned roles cannot be revoked');
          return;
        }
        const results = await Promise.all(
          rolesToAct.map(async (role) => {
            if (!role.id) {
              return { success: false, role: roleNameOf(role), message: 'Role id is missing' };
            }
            const response = await apiService.revokeRoleFromBridgeUsers(nida, role.id);
            return {
              success: Boolean(response?.success),
              role: roleNameOf(role),
              message: response?.message,
            };
          })
        );
        const failed = results.filter((result) => !result.success);
        const revokedNames = new Set(results.filter((result) => result.success).map((result) => result.role.toLowerCase()));
        remainingRoles = remainingRoles.filter((role) => !revokedNames.has(roleNameOf(role).toLowerCase()));
        notifiedRoles = results.filter((result) => result.success).map((result) => result.role).filter(Boolean);
        applyUserUpdate(selected, remainingRoles);
        setSelectedRoleKeys(remainingRoles.map((role) => String(roleRowKey(role))));
        if (failed.length === results.length) {
          message.error(failed[0]?.message || 'Unable to revoke the selected roles');
          return;
        }
        if (failed.length) {
          message.warning(`Some roles could not be revoked: ${failed.map((item) => item.role).join(', ')}`);
        }
      }

      recordUserReviewAction(monthKey, selected, {
        type: pendingAction,
        roles: notifiedRoles,
        justification,
        by: displayUserName(currentUser),
        role: selectedRole,
        username: usernameOf(selected),
        accountName: displayUserName(selected),
        assignedRoles: remainingRoles.map((role) => roleNameOf(role)).filter(Boolean),
      });
      if (notifiedRoles.length) {
        notifyRoleReviewDecision({
          user: selected,
          username: usernameOf(selected),
          action: pendingAction,
          roles: notifiedRoles,
          actor: displayUserName(currentUser),
          justification,
        });
      }
      refreshReviews();
      closeDecision();
      message.success(config.successMessage);
    } catch (error) {
      if (error?.errorFields) return;
      message.error(error?.message || 'Unable to complete this action');
    } finally {
      setActing(false);
    }
  };

  const statusTag = (user) => {
    const status = reviewStatusOf(user);
    if (status === 'reviewed') return <Tag color="green">Reviewed</Tag>;
    if (status === 'enforced') return <Tag color="red">Enforced</Tag>;
    return <Tag color="gold">Pending</Tag>;
  };

  const roleDecisionTag = (user, roleName) => {
    const decision = latestRoleDecision(reviews[userReviewKey(user)], roleName);
    if (!decision) return null;
    if (decision.type === 'revoke') return <Tag color="orange">Revoked</Tag>;
    if (decision.type === 'approve') return <Tag color="cyan">Approved</Tag>;
    return null;
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
      title: 'Username',
      dataIndex: 'username',
      key: 'username',
      searchable: true,
      width: 180,
      render: (_, record) => usernameOf(record) || '—',
    },
    {
      title: 'Account Name',
      dataIndex: 'full_name',
      key: 'accountName',
      searchable: true,
      width: 200,
      render: (_, record) => displayUserName(record),
    },
    {
      title: 'Assigned Roles',
      key: 'roles',
      render: (_, record) => {
        const roles = assignedRolesOf(record);
        if (!roles.length) return <Tag>No roles assigned</Tag>;
        return (
          <div className="flex flex-wrap gap-1">
            {roles.map((role) => (
              <span key={`${getTableRowKey(record)}-${role}`} className="inline-flex items-center gap-1">
                <Tag color={BRAND}>{role}</Tag>
                {roleDecisionTag(record, role)}
              </span>
            ))}
          </div>
        );
      },
    },
    {
      title: 'Monthly Review',
      key: 'review',
      width: 140,
      render: (_, record) => statusTag(record),
    },
    {
      title: 'Reviewed By',
      key: 'reviewedBy',
      width: 160,
      render: (_, record) => reviews[userReviewKey(record)]?.reviewedBy || '—',
    },
    {
      title: 'Reviewed On',
      key: 'reviewedAt',
      width: 170,
      render: (_, record) => formatDateTime(reviews[userReviewKey(record)]?.reviewedAt),
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 110,
      align: 'center',
      render: (_, record) => (
        <div className="flex justify-center">
          <Button
            size="small"
            icon={<EyeOutlined />}
            onClick={(event) => {
              event.stopPropagation();
              openUser(record);
            }}
          >
            View
          </Button>
        </div>
      ),
    },
  ];

  const selectedReview = selected ? reviews[userReviewKey(selected)] : null;
  const selectedActions = selectedReview?.actions || [];

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <UserCheck size={20} color={BRAND} />
          <h1 className="m-0 text-lg font-semibold text-slate-900">Review Roles</h1>
        </div>
        <div className="text-sm text-slate-500">
          {reviewedCount} of {users.length} usernames reviewed in {reviewMonth.format('MMMM YYYY')}
        </div>
      </div>

      {enforcementStarted ? (
        <Alert
          className="mb-4"
          type="error"
          showIcon
          message="Monthly role review enforcement is active"
          description={`${enforcedCount} username${enforcedCount === 1 ? '' : 's'} ${
            enforcedCount === 1 ? 'was' : 'were'
          } not reviewed for ${reviewMonth.format('MMMM YYYY')}. Enforcement started on ${formatDate(
            enforceDate
          )}, 3 days after the month ended.`}
        />
      ) : (
        <Alert
          className="mb-4"
          type="info"
          showIcon
          message="Monthly role review"
          description={
            canDecideRoles
              ? `Review assigned roles for every username this month. As Toll Reviewer, approve or revoke roles for a username with a justification. If ${reviewMonth.format(
                  'MMMM YYYY'
                )} ends without a review, enforcement starts on ${formatDate(enforceDate)}.`
              : `Review assigned roles for every username this month. If ${reviewMonth.format(
                  'MMMM YYYY'
                )} ends without a review, enforcement starts on ${formatDate(enforceDate)}.`
          }
        />
      )}

      <div className="mb-4 flex flex-wrap items-end gap-3">
        <div>
          <div className="mb-1 text-xs font-semibold" style={{ color: BRAND }}>
            Review Month
          </div>
          <DatePicker
            picker="month"
            allowClear={false}
            value={reviewMonth}
            format="MMMM YYYY"
            onChange={(value) => {
              const nextMonth = value || dayjs();
              setReviewMonth(nextMonth);
              setReviews(getMonthReviews(monthKeyFromDate(nextMonth)));
              setPage(1);
            }}
          />
        </div>
        <div>
          <div className="mb-1 text-xs font-semibold" style={{ color: BRAND }}>
            Username
          </div>
          <Select
            className="w-64"
            allowClear
            showSearch
            placeholder="Search all usernames"
            optionFilterProp="label"
            value={usernameFilter || undefined}
            options={usernameOptions}
            onChange={(value) => {
              setUsernameFilter(value || '');
              setPage(1);
            }}
          />
        </div>
        <div>
          <div className="mb-1 text-xs font-semibold" style={{ color: BRAND }}>
            Review Status
          </div>
          <Select
            className="w-56"
            value={reviewFilter}
            options={REVIEW_FILTERS}
            onChange={(value) => {
              setReviewFilter(value);
              setPage(1);
            }}
          />
        </div>
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
            setPage(nextPage);
            setPageSize(nextSize);
          },
        }}
        showSearch
        showRefresh={false}
        searchPlaceholder="Search all available usernames..."
        rowKey={(record) => getTableRowKey(record)}
        onRow={(record) => ({
          onClick: () => openUser(record),
          style: { cursor: 'pointer' },
        })}
      />

      <GrantRoleModal
        open={Boolean(selected)}
        onCancel={closeUser}
        width={720}
        maskClosable={false}
        keyboard={false}
      >
        <BrandModalHeader title="Review Assigned Roles" onClose={closeUser} />
        {selected ? (
          <div className="flex max-h-[85vh] flex-col">
            <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
              {reviewStatusOf(selected) === 'enforced' ? (
                <Alert
                  className="mb-4"
                  type="error"
                  showIcon
                  message="Enforced"
                  description={`A month passed without this username being reviewed. Enforcement started on ${formatDate(
                    enforceDate
                  )}.`}
                />
              ) : null}
              <SectionHeading>Username</SectionHeading>
              <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                <Field label="Username" value={usernameOf(selected)} />
                <Field label="Account Name" value={displayUserName(selected)} />
                <Field label="Email" value={selected.email} />
                <Field label="Review Status" value={statusTag(selected)} />
              </div>
              <SectionHeading>Assigned Roles</SectionHeading>
              {assignedRoleObjectsOf(selected).length ? (
                <div className="mb-5 space-y-2">
                  {canDecideRoles ? (
                    <div className="mb-2 text-xs text-slate-500">
                      Select the roles to approve or revoke for this username. A justification is required.
                    </div>
                  ) : null}
                  {assignedRoleObjectsOf(selected).map((role) => {
                    const key = String(roleRowKey(role));
                    const name = roleNameOf(role);
                    return (
                      <div
                        key={key}
                        className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-slate-200 px-3 py-2"
                      >
                        <div className="flex items-center gap-2">
                          {canDecideRoles ? (
                            <Checkbox
                              checked={selectedRoleKeys.includes(key)}
                              onChange={(event) => {
                                const checked = event.target.checked;
                                setSelectedRoleKeys((current) =>
                                  checked ? [...current, key] : current.filter((item) => item !== key)
                                );
                              }}
                            />
                          ) : null}
                          <Tag color={BRAND}>{name || '—'}</Tag>
                          {roleDecisionTag(selected, name)}
                        </div>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <div className="mb-5">
                  <Tag>No roles assigned</Tag>
                </div>
              )}
              <SectionHeading>Monthly Review</SectionHeading>
              <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                <Field label="Review Month" value={reviewMonth.format('MMMM YYYY')} />
                <Field label="Enforcement Date" value={formatDate(enforceDate)} />
                <Field label="Reviewed By" value={selectedReview?.reviewedBy} />
                <Field label="Reviewed On" value={formatDateTime(selectedReview?.reviewedAt)} />
              </div>
              {selectedActions.length ? (
                <>
                  <SectionHeading>Role Decisions</SectionHeading>
                  <div className="space-y-3">
                    {selectedActions.map((entry, index) => (
                      <div key={`${entry.at}-${index}`} className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <span className="text-sm font-semibold text-slate-900">
                            {entry.type === 'revoke' ? 'Revoked' : 'Approved'}
                          </span>
                          <span className="text-xs text-slate-500">{formatDateTime(entry.at)}</span>
                        </div>
                        <div className="mt-1 text-xs text-slate-500">
                          {entry.by}
                          {entry.role ? ` · ${entry.role}` : ''}
                        </div>
                        <div className="mt-1.5 flex flex-wrap gap-1">
                          {(entry.roles || []).map((role) => (
                            <Tag key={`${entry.at}-${role}`}>{role}</Tag>
                          ))}
                        </div>
                        <div className="mt-1.5 text-sm text-black">{entry.justification}</div>
                      </div>
                    ))}
                  </div>
                </>
              ) : null}
            </div>
            <ModalFooter>
              <Button onClick={closeUser}>Close</Button>
              {canDecideRoles ? (
                <>
                  <Button
                    danger
                    icon={<StopOutlined />}
                    disabled={!assignedRoleObjectsOf(selected).length}
                    onClick={() => openDecisionFromModal('revoke')}
                  >
                    Revoke
                  </Button>
                  <Button
                    type="primary"
                    icon={<CheckOutlined />}
                    className="btn-standard-primary"
                    disabled={!assignedRoleObjectsOf(selected).length}
                    onClick={() => openDecisionFromModal('approve')}
                  >
                    Approve
                  </Button>
                </>
              ) : null}
              <Button
                type={canDecideRoles ? 'default' : 'primary'}
                icon={<CheckOutlined />}
                className={canDecideRoles ? undefined : 'btn-standard-primary'}
                disabled={Boolean(selectedReview)}
                onClick={() => handleMarkReviewed(selected)}
              >
                {selectedReview ? 'Reviewed' : 'Mark Reviewed'}
              </Button>
            </ModalFooter>
          </div>
        ) : null}
      </GrantRoleModal>

      <GrantRoleModal
        open={Boolean(pendingAction)}
        onCancel={closeDecision}
        width={520}
        maskClosable={false}
        keyboard={false}
      >
        <BrandModalHeader
          title={ACTION_CONFIG[pendingAction]?.title || 'Justify Action'}
          onClose={closeDecision}
        />
        <div className="px-6 py-5">
          <div className="mb-3 text-sm text-slate-600">
            {pendingAction === 'revoke' ? 'Revoke' : 'Approve'} assigned roles for{' '}
            <span className="font-semibold text-slate-900">
              {usernameOf(selected) || displayUserName(selected)}
            </span>
            .
          </div>
          <div className="mb-4 flex flex-wrap gap-1">
            {selectedRoles.map((role) => (
              <Tag key={roleRowKey(role)} color={BRAND}>
                {roleNameOf(role)}
              </Tag>
            ))}
          </div>
          <Form form={form} layout="vertical" requiredMark={false}>
            <Form.Item
              name="justification"
              label={<FormLabel>{ACTION_CONFIG[pendingAction]?.label || 'Justification'}</FormLabel>}
              rules={[{ required: true, message: 'Enter a justification before this action' }]}
            >
              <Input.TextArea
                rows={4}
                maxLength={400}
                showCount
                placeholder={ACTION_CONFIG[pendingAction]?.placeholder || 'Enter a justification'}
              />
            </Form.Item>
          </Form>
        </div>
        <ModalFooter>
          <Button onClick={closeDecision} disabled={acting}>
            Cancel
          </Button>
          <Button
            type="primary"
            danger={Boolean(ACTION_CONFIG[pendingAction]?.danger)}
            className={ACTION_CONFIG[pendingAction]?.danger ? undefined : 'btn-standard-primary'}
            loading={acting}
            onClick={handleConfirmDecision}
          >
            {ACTION_CONFIG[pendingAction]?.submitLabel || 'Submit'}
          </Button>
        </ModalFooter>
      </GrantRoleModal>
    </div>
  );
};

export default ReviewRoles;
