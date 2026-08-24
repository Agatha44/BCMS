import { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert, App, Card, Input, Modal, Select, Tabs, Tag, Button, DatePicker } from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import { Eye, Loader2, UserPlus } from 'lucide-react';
import dayjs from 'dayjs';
import { useSearchParams } from 'react-router-dom';

import DataTable from '../../common/data/DataTable.jsx';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../services/api.jsx';
import GrantRole from './GrantRole.jsx';
import '../../styles/common.css';

export const USER_TAB_KEYS = {
  COLLECTION: 'collection-users',
  BMS: 'bms-users',
};

const TAB_KEYS = Object.values(USER_TAB_KEYS);

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';
const DATE_FORMAT = 'YYYY-MM-DD';

const buildDisplayName = (user) => {
  if (!user) return '';
  if (user.full_name && String(user.full_name).trim()) return String(user.full_name).trim();
  return [user.first_name, user.middle_name, user.surname]
    .map((p) => (p == null ? '' : String(p).trim()))
    .filter(Boolean)
    .join(' ');
};

const isUserActive = (status) => status === 1 || status === '1' || status === true;

const normalizeUserPayload = (data) => (data?.user != null ? data.user : data);

const roleTagKey = (role) => role.user_role_id ?? role.id ?? role.name;

const formatDisplayDate = (value) => {
  if (!value) return null;
  const d = dayjs(value);
  return d.isValid() ? d.format('D MMM YYYY') : null;
};

const formatRoleDateRange = (role) => {
  const start = formatDisplayDate(role.start_date);
  const end = formatDisplayDate(role.end_date);
  if (start && end) return `${start} – ${end}`;
  if (start) return `From ${start}`;
  if (end) return `Until ${end}`;
  return null;
};

/** Badge: Active | Scheduled | Expired (uses API flag + date fallbacks). */
const getRoleAssignmentBadge = (role) => {
  if (role.is_currently_effective === true) {
    return { label: 'Active', color: 'green' };
  }

  const today = dayjs().startOf('day');
  const start = role.start_date ? dayjs(role.start_date).startOf('day') : null;
  const end = role.end_date ? dayjs(role.end_date).startOf('day') : null;

  if (start?.isValid() && start.isAfter(today)) {
    return { label: 'Scheduled', color: 'blue' };
  }
  if (end?.isValid() && end.isBefore(today)) {
    return { label: 'Expired', color: 'default' };
  }

  return null;
};

const RoleChip = ({ role, compact = false }) => {
  const badge = getRoleAssignmentBadge(role);
  const range = formatRoleDateRange(role);

  if (compact) {
    return (
      <span
        className="inline-flex max-w-full flex-col gap-0.5 rounded-md border border-[#ead6d7] bg-[#fff5f5] px-2 py-1"
        title={range || role.name}
      >
        <span className="flex flex-wrap items-center gap-1">
          <span className="text-xs font-medium" style={{ color: BRAND }}>
            {role.name}
          </span>
          {badge ? (
            <Tag color={badge.color} className="!m-0 px-1.5 py-0 text-[10px] leading-tight">
              {badge.label}
            </Tag>
          ) : null}
        </span>
        {range ? <span className="text-[10px] text-slate-500">{range}</span> : null}
      </span>
    );
  }

  return (
    <div className="inline-flex min-w-[140px] max-w-full flex-col gap-1 rounded-lg border border-[#ead6d7] bg-[#fffafa] px-2.5 py-2">
      <div className="flex flex-wrap items-center gap-1.5">
        <span className="text-xs font-semibold" style={{ color: BRAND }}>
          {role.name}
        </span>
        {badge ? (
          <Tag color={badge.color} className="!m-0 px-2 py-0 text-[10px] font-semibold uppercase">
            {badge.label}
          </Tag>
        ) : null}
        {role.assignment_is_active === false ? (
          <Tag className="!m-0 px-2 py-0 text-[10px]">Inactive assignment</Tag>
        ) : null}
      </div>
      {range ? <span className="text-[11px] text-slate-600">{range}</span> : null}
      {role.description ? (
        <span className="text-[10px] text-slate-400">{role.description}</span>
      ) : null}
    </div>
  );
};

const RoleChipsList = ({ roles, compact = false }) => {
  const list = Array.isArray(roles) ? roles : [];
  if (!list.length) {
    return <span className="text-sm text-slate-400">{EMPTY_VALUE}</span>;
  }
  return (
    <div className={`flex flex-wrap gap-1.5 ${compact ? 'max-w-[320px]' : ''}`}>
      {list.map((role) => (
        <RoleChip key={roleTagKey(role)} role={role} compact={compact} />
      ))}
    </div>
  );
};

const AssignRoleFields = ({
  assignRoleId,
  onRoleChange,
  assignStartDate,
  onStartChange,
  assignEndDate,
  onEndChange,
  roleOptions,
  loadingRoles,
  disabled,
}) => (
  <div className="space-y-3">
    <div>
      <span className="mb-1 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
        Role
      </span>
      <Select
        placeholder="Select role"
        showSearch
        optionFilterProp="label"
        loading={loadingRoles}
        value={assignRoleId}
        onChange={onRoleChange}
        options={roleOptions}
        className="w-full"
        disabled={disabled}
      />
    </div>
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <div>
        <span className="mb-1 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
          Start Date
        </span>
        <DatePicker
          className="w-full"
          format={DATE_FORMAT}
          value={assignStartDate}
          onChange={(d) => {
            onStartChange(d);
            if (d && assignEndDate && assignEndDate.isBefore(d, 'day')) {
              onEndChange(null);
            }
          }}
          allowClear
          disabled={disabled}
          placeholder="Optional"
        />
      </div>
      <div>
        <span className="mb-1 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
          End Date
        </span>
        <DatePicker
          className="w-full"
          format={DATE_FORMAT}
          value={assignEndDate}
          onChange={onEndChange}
          allowClear
          disabled={disabled}
          placeholder="Optional"
          disabledDate={(current) => {
            if (!assignStartDate || !current) return false;
            return current.isBefore(assignStartDate.startOf('day'));
          }}
        />
      </div>
    </div>
    <p className="m-0 text-[11px] text-slate-500">
      Optional assignment window. End date must be on or after start date when both are set.
    </p>
  </div>
);

const BrandModalHeader = ({ title, onClose }) => (
  <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
    <h2 className="m-0 text-sm font-semibold leading-none text-white">{title}</h2>
    <button
      type="button"
      aria-label="Close"
      onClick={onClose}
      className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
      </svg>
    </button>
  </div>
);

const Field = ({ label, value }) => (
  <div className="min-w-0 py-1.5">
    <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <div className="mt-0.5 text-sm font-medium text-black">
      {value != null && value !== '' ? value : <span className="text-slate-400">{EMPTY_VALUE}</span>}
    </div>
  </div>
);

const CollectionUsersTab = () => {
  const { message } = App.useApp();
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isInitialLoad, setIsInitialLoad] = useState(true);

  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  });

  const [filters, setFilters] = useState({
    search: '',
    status: undefined,
    role: undefined,
    sort_by: 'id',
    sort_order: 'asc',
    per_page: 15,
    page: 1,
  });

  const [roleOptions, setRoleOptions] = useState([]);
  const [loadingRoles, setLoadingRoles] = useState(false);

  const [showViewModal, setShowViewModal] = useState(false);
  const [viewUser, setViewUser] = useState(null);
  const [loadingViewId, setLoadingViewId] = useState(null);
  const [loadingView, setLoadingView] = useState(false);
  const [viewError, setViewError] = useState(null);

  const [showAssignModal, setShowAssignModal] = useState(false);
  const [assignTargetUser, setAssignTargetUser] = useState(null);
  const [assignRoleId, setAssignRoleId] = useState(undefined);
  const [assignStartDate, setAssignStartDate] = useState(null);
  const [assignEndDate, setAssignEndDate] = useState(null);
  const [assigning, setAssigning] = useState(false);
  const [assigningUserId, setAssigningUserId] = useState(null);

  const resetAssignForm = useCallback(() => {
    setAssignRoleId(undefined);
    setAssignStartDate(null);
    setAssignEndDate(null);
  }, []);

  const applyUserUpdate = useCallback((updatedUser) => {
    if (!updatedUser?.id) return;
    setUsers((prev) =>
      prev.map((u) => (u.id === updatedUser.id ? { ...u, ...updatedUser } : u))
    );
    setViewUser((prev) => (prev?.id === updatedUser.id ? { ...prev, ...updatedUser } : prev));
    setAssignTargetUser((prev) => (prev?.id === updatedUser.id ? { ...prev, ...updatedUser } : prev));
  }, []);

  const fetchUsers = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        page: filters.page,
        per_page: filters.per_page,
        sort_by: filters.sort_by,
        sort_order: filters.sort_order,
      };
      if (filters.search?.trim()) params.search = filters.search.trim();
      if (filters.status !== undefined && filters.status !== null && filters.status !== '') {
        params.status = filters.status;
      }
      if (filters.role !== undefined && filters.role !== null && filters.role !== '') {
        params.role = filters.role;
      }

      const response = await apiService.getUsers(params);
      if (response.success && response.data?.users && response.data?.pagination) {
        setUsers(response.data.users);
        setPagination(response.data.pagination);
      } else {
        setUsers([]);
        setPagination((prev) => ({ ...prev, total: 0 }));
        setError(response.message || 'Failed to load users');
      }
    } catch (err) {
      setUsers([]);
      setError('An error occurred while fetching users');
      console.error('Error fetching users:', err);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  const fetchRoleOptions = useCallback(async () => {
    setLoadingRoles(true);
    try {
      const response = await apiService.getRoles();
      if (response.success) {
        const list = Array.isArray(response.data)
          ? response.data
          : Array.isArray(response.data?.roles)
            ? response.data.roles
            : [];
        setRoleOptions(
          list.map((r) => ({
            value: r.id,
            label: r.name || r.role_name || `Role ${r.id}`,
          }))
        );
      }
    } catch (err) {
      console.error('Error fetching roles for filter:', err);
    } finally {
      setLoadingRoles(false);
    }
  }, []);

  useEffect(() => {
    fetchUsers();
    fetchRoleOptions();
    setIsInitialLoad(false);
  }, []);

  useEffect(() => {
    if (!isInitialLoad) fetchUsers();
  }, [filters.page, isInitialLoad, fetchUsers]);

  useEffect(() => {
    if (!isInitialLoad && (filters.status !== undefined || filters.role !== undefined || filters.per_page !== 15)) {
      fetchUsers();
    }
  }, [filters.per_page, filters.status, filters.role, isInitialLoad, fetchUsers]);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (!isInitialLoad) fetchUsers();
    }, 500);
    return () => clearTimeout(timeoutId);
  }, [filters.search, isInitialLoad, fetchUsers]);

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({
      ...prev,
      [key]: value,
      page: key === 'page' ? value : 1,
    }));
  };

  const handleSearch = (searchTerm) => handleFilterChange('search', searchTerm);

  const openAssignModal = (user) => {
    setAssignTargetUser(user);
    resetAssignForm();
    setShowAssignModal(true);
  };

  const closeAssignModal = () => {
    if (assigning) return;
    setShowAssignModal(false);
    setAssignTargetUser(null);
    resetAssignForm();
  };

  const handleAssignRole = async ({ user: userOverride, closeModalOnSuccess } = {}) => {
    const user = userOverride ?? assignTargetUser;

    if (!user?.id) {
      message.error('User information is missing');
      return;
    }
    if (assignRoleId == null || assignRoleId === '') {
      message.warning('Please select a role');
      return;
    }

    const startDate = assignStartDate ? assignStartDate.format(DATE_FORMAT) : null;
    const endDate = assignEndDate ? assignEndDate.format(DATE_FORMAT) : null;

    if (startDate && endDate && dayjs(endDate).isBefore(dayjs(startDate), 'day')) {
      message.error('End date must be on or after start date');
      return;
    }

    setAssigning(true);
    setAssigningUserId(user.id);
    try {
      const response = await apiService.assignBcmsUserRole(user.id, {
        roleId: assignRoleId,
        startDate,
        endDate,
      });
      if (response.success && response.data) {
        const updatedUser = normalizeUserPayload(response.data);
        applyUserUpdate(updatedUser);
        const apiMessage = response.message || 'Role assigned successfully';
        if (apiMessage.toLowerCase().includes('already assigned')) {
          message.info(apiMessage);
        } else {
          message.success(apiMessage);
        }
        resetAssignForm();
        if (closeModalOnSuccess ?? showAssignModal) {
          closeAssignModal();
        }
      } else {
        message.error(response.message || 'Failed to assign role');
      }
    } catch (err) {
      console.error('Error assigning role:', err);
      message.error('An error occurred while assigning the role');
    } finally {
      setAssigning(false);
      setAssigningUserId(null);
    }
  };

  const openViewModal = async (user) => {
    setLoadingViewId(user.id);
    setLoadingView(true);
    setViewError(null);
    setViewUser(null);
    resetAssignForm();
    try {
      const response = await apiService.getUserById(user.id);
      if (response.success && response.data) {
        setViewUser(normalizeUserPayload(response.data));
        setShowViewModal(true);
      } else {
        setViewError(response.message || 'Failed to load user details');
        setShowViewModal(true);
      }
    } catch (err) {
      setViewError('An error occurred while loading user details');
      setShowViewModal(true);
      console.error('Error loading user:', err);
    } finally {
      setLoadingView(false);
      setLoadingViewId(null);
    }
  };

  const columns = useMemo(
    () => [
      {
        title: '#',
        key: 'serial',
        width: 70,
        align: 'center',
        render: (_, user) => {
          const currentPage = pagination?.current_page || 1;
          const perPage = filters.per_page || 15;
          const idx = users.findIndex((u) => u.id === user.id);
          const serialNumber = idx >= 0 ? (currentPage - 1) * perPage + idx + 1 : 0;
          return <span className="text-sm font-medium text-slate-600">{serialNumber}</span>;
        },
      },
      {
        title: 'Name',
        key: 'name',
        render: (_, user) => (
          <span className="block min-w-[160px] text-sm font-medium text-black">
            {buildDisplayName(user) || EMPTY_VALUE}
          </span>
        ),
      },
      {
        title: 'Username',
        dataIndex: 'username',
        key: 'username',
        render: (v) => (
          <span className="block min-w-[120px] font-mono text-sm text-black">{v || EMPTY_VALUE}</span>
        ),
      },
      {
        title: 'Email',
        dataIndex: 'email',
        key: 'email',
        render: (v) => (
          <span className="block min-w-[180px] text-sm text-black" title={v || ''}>
            {v || EMPTY_VALUE}
          </span>
        ),
      },
      {
        title: 'Phone',
        dataIndex: 'phone',
        key: 'phone',
        render: (v) => (
          <span className="block min-w-[120px] text-sm text-black">{v || EMPTY_VALUE}</span>
        ),
      },
      {
        title: 'Status',
        key: 'status',
        align: 'center',
        width: 120,
        render: (_, user) => {
          const active = isUserActive(user.status);
          return (
            <div className="flex justify-center">
              <span
                className={`inline-flex min-w-[84px] items-center justify-center rounded-full px-2.5 py-1 text-xs font-semibold ${
                  active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                }`}
              >
                {user.status_text || (active ? 'Active' : 'Inactive')}
              </span>
            </div>
          );
        },
      },
      {
        title: 'Roles',
        key: 'roles',
        render: (_, user) => <RoleChipsList roles={user.roles} compact />,
      },
      {
        title: 'Actions',
        key: 'actions',
        align: 'center',
        width: 220,
        render: (_, user) => (
          <div className="flex items-center justify-center gap-2">
            <button
              type="button"
              onClick={() => openAssignModal(user)}
              disabled={assigningUserId === user.id}
              className="inline-flex items-center space-x-1 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-700 transition hover:border-[#ead6d7] hover:bg-[#fff5f5] disabled:cursor-not-allowed disabled:opacity-50"
              title="Assign role"
            >
              {assigningUserId === user.id ? (
                <Loader2 size={14} className="animate-spin" style={{ color: BRAND }} />
              ) : (
                <UserPlus size={15} style={{ color: BRAND }} />
              )}
              <span>Assign</span>
            </button>
            <button
              type="button"
              onClick={() => openViewModal(user)}
              disabled={loadingViewId === user.id}
              className="inline-flex items-center space-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
              title="View user details"
            >
              {loadingViewId === user.id ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <Eye size={16} />
              )}
              <span>View</span>
            </button>
          </div>
        ),
      },
    ],
    [users, filters.per_page, loadingViewId, pagination, assigningUserId]
  );

  const paginationConfig = useMemo(
    () => ({
      current: pagination.current_page,
      pageSize: pagination.per_page,
      total: pagination.total,
      showTotal: () => null,
      showQuickJumper: false,
      onChange: (page, pageSize) => {
        if (pageSize !== filters.per_page) handleFilterChange('per_page', pageSize);
        handleFilterChange('page', page);
      },
    }),
    [filters.per_page, pagination]
  );

  const detailUser = viewUser;
  const detailRoles = Array.isArray(detailUser?.roles) ? detailUser.roles : [];

  const assignFieldsProps = {
    assignRoleId,
    onRoleChange: setAssignRoleId,
    assignStartDate,
    onStartChange: setAssignStartDate,
    assignEndDate,
    onEndChange: setAssignEndDate,
    roleOptions,
    loadingRoles,
    disabled: assigning,
  };

  return (
    <div className="space-y-4">
      {error && (
        <Alert type="error" showIcon message={error} closable onClose={() => setError(null)} />
      )}

      <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Input
            placeholder="Search by name, username, email, phone..."
            prefix={<SearchOutlined />}
            value={filters.search}
            onChange={(e) => handleSearch(e.target.value)}
            allowClear
          />
          <Select
            placeholder="Filter by status"
            allowClear
            value={filters.status}
            onChange={(v) => handleFilterChange('status', v)}
            options={[
              { value: 1, label: 'Active' },
              { value: 0, label: 'Inactive' },
            ]}
            className="w-full"
          />
          <Select
            placeholder="Filter by role"
            allowClear
            showSearch
            optionFilterProp="label"
            loading={loadingRoles}
            value={filters.role}
            onChange={(v) => handleFilterChange('role', v)}
            options={roleOptions}
            className="w-full"
          />
          <Select
            placeholder="Sort by"
            value={filters.sort_by}
            onChange={(v) => handleFilterChange('sort_by', v)}
            options={[
              { value: 'id', label: 'ID' },
              { value: 'username', label: 'Username' },
              { value: 'email', label: 'Email' },
              { value: 'created_at', label: 'Created at' },
            ]}
            className="w-full"
          />
        </div>

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
          data={users}
          loading={false}
          pagination={paginationConfig}
          onSearchChange={handleSearch}
          showSearch={false}
          showRefresh={true}
          onRefresh={fetchUsers}
          rowKey={(record) => record.id}
        />
        </div>
      </div>

      <Modal
        open={showViewModal}
        onCancel={() => setShowViewModal(false)}
        footer={null}
        width={760}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="User Details" onClose={() => setShowViewModal(false)} />

        {loadingView ? (
          <CollectionLoader size={64} compact />
        ) : viewError ? (
          <div className="px-6 py-5">
            <Alert type="error" showIcon message={viewError} />
          </div>
        ) : detailUser ? (
          <div className="px-6 py-5">
            <h4
              className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
              style={{ color: BRAND }}
            >
              Profile
            </h4>
            <div className="grid grid-cols-1 gap-x-6 md:grid-cols-2">
              <Field label="Full Name" value={buildDisplayName(detailUser)} />
              <Field label="Username" value={detailUser.username} />
              <Field label="Email" value={detailUser.email} />
              <Field label="Phone" value={detailUser.phone} />
              <Field
                label="Status"
                value={detailUser.status_text || (isUserActive(detailUser.status) ? 'Active' : 'Inactive')}
              />
              <Field label="NIDA" value={detailUser.nida} />
            </div>

            <h4
              className="mb-3 mt-6 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
              style={{ color: BRAND }}
            >
              Roles
            </h4>
            <RoleChipsList roles={detailRoles} />

            <div className="mt-4 rounded-lg border border-slate-100 bg-slate-50/80 p-4">
              <h4
                className="mb-3 text-xs font-semibold uppercase tracking-[0.12em]"
                style={{ color: BRAND }}
              >
                Assign Role
              </h4>
              <AssignRoleFields {...assignFieldsProps} />
              <div className="mt-3 flex justify-end">
                <Button
                  type="primary"
                  icon={<UserPlus size={14} />}
                  loading={assigning}
                  onClick={() => handleAssignRole({ user: detailUser, closeModalOnSuccess: false })}
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
                  Assign Role
                </Button>
              </div>
            </div>

            <h4
              className="mb-3 mt-6 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
              style={{ color: BRAND }}
            >
              Timestamps
            </h4>
            <div className="grid grid-cols-1 gap-x-6 md:grid-cols-2">
              <Field label="Created At" value={detailUser.created_at} />
              <Field label="Updated At" value={detailUser.updated_at} />
            </div>
          </div>
        ) : null}

        {!loadingView && (
          <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
            {detailUser ? (
              <Button
                type="primary"
                icon={<UserPlus size={14} />}
                onClick={() => openAssignModal(detailUser)}
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
                Assign Role
              </Button>
            ) : null}
            <Button onClick={() => setShowViewModal(false)}>Close</Button>
          </div>
        )}
      </Modal>

      <Modal
        open={showAssignModal}
        onCancel={closeAssignModal}
        footer={null}
        width={520}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!assigning}
        keyboard={!assigning}
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Assign Role to User" onClose={closeAssignModal} />

        <div className="px-6 py-5">
          <p className="m-0 text-sm text-slate-600">
            User:{' '}
            <span className="font-semibold text-black">
              {assignTargetUser ? buildDisplayName(assignTargetUser) || assignTargetUser.username : EMPTY_VALUE}
            </span>
          </p>
          {assignTargetUser?.roles?.length ? (
            <div className="mt-3">
              <span className="mb-2 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                Current Roles
              </span>
              <RoleChipsList roles={assignTargetUser.roles} compact />
            </div>
          ) : null}
          <div className="mt-4">
            <AssignRoleFields {...assignFieldsProps} />
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            icon={<UserPlus size={14} />}
            loading={assigning}
            onClick={() => handleAssignRole({ closeModalOnSuccess: true })}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => {
              if (!assigning) {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
                e.currentTarget.style.borderColor = BRAND_DARK;
              }
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
              e.currentTarget.style.borderColor = BRAND;
            }}
          >
            Assign Role
          </Button>
          <Button onClick={closeAssignModal} disabled={assigning}>
            Close
          </Button>
        </div>
      </Modal>
    </div>
  );
};

const ManageUsers = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const tabFromUrl = searchParams.get('tab');
  const [activeTab, setActiveTab] = useState(
    TAB_KEYS.includes(tabFromUrl) ? tabFromUrl : USER_TAB_KEYS.COLLECTION
  );

  useEffect(() => {
    if (tabFromUrl && TAB_KEYS.includes(tabFromUrl) && tabFromUrl !== activeTab) {
      setActiveTab(tabFromUrl);
    }
  }, [tabFromUrl, activeTab]);

  const handleTabChange = (key) => {
    setActiveTab(key);
    setSearchParams({ tab: key }, { replace: true });
  };

  return (
    <Card className="manage-users-container">
      <Tabs
        activeKey={activeTab}
        onChange={handleTabChange}
        items={[
          {
            key: USER_TAB_KEYS.COLLECTION,
            label: 'Collection Users',
            children: <CollectionUsersTab />,
          },
          {
            key: USER_TAB_KEYS.BMS,
            label: 'BMS Users',
            children: <GrantRole />,
          },
        ]}
      />
    </Card>
  );
};

export default ManageUsers;
