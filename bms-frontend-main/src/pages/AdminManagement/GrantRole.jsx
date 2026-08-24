import { useEffect, useMemo, useState } from 'react';
import { Alert, App, Button, Form, Switch, Table, Tag } from 'antd';
import { EyeOutlined } from '@ant-design/icons';
import { Pencil, UserPlus } from 'lucide-react';
import dayjs from 'dayjs';

import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import {
  AssignRoleFields,
  BrandModalHeader,
  BrandPrimaryButton,
  BridgeUserFormFields,
  Field,
  FormFooter,
  GrantRoleModal,
  LoadingOverlay,
  LoadingState,
  ModalFooter,
  renderRoleDateCell,
  SectionHeading,
} from './grantRole/grantRoleComponents.jsx';
import {
  BRAND,
  buildDisplayName,
  buildUserPayload,
  DATE_FORMAT,
  EMPTY_VALUE,
  getRoleAssignmentBadge,
  getTableRowKey,
  getUserNida,
  isPastDate,
  isRoleToggleOn,
  isUserActive,
  mapUserToEditFormValues,
  normalizeBridgeUser,
  normalizeBridgeUserFromResponse,
  normalizeRoleList,
  roleRowKey,
} from './grantRole/grantRoleUtils.js';
import '../../styles/common.css';

const GrantRole = () => {
  const { message, modal: antdModal } = App.useApp();
  const [createUserForm] = Form.useForm();
  const [editUserForm] = Form.useForm();

  const [isUserDetailsModalOpen, setIsUserDetailsModalOpen] = useState(false);
  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
  const [isCreateUserModalOpen, setIsCreateUserModalOpen] = useState(false);
  const [isEditUserModalOpen, setIsEditUserModalOpen] = useState(false);

  const [creatingUser, setCreatingUser] = useState(false);
  const [updatingUser, setUpdatingUser] = useState(false);
  const [loadingEditUser, setLoadingEditUser] = useState(false);
  const [selectedUser, setSelectedUser] = useState(null);
  const [loadingUserDetails, setLoadingUserDetails] = useState(false);
  const [viewError, setViewError] = useState(null);

  const [loading, setLoading] = useState(false);
  const [userData, setUserData] = useState([]);
  const [rolesData, setRolesData] = useState([]);
  const [loadingRoles, setLoadingRoles] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [isInitialLoad, setIsInitialLoad] = useState(true);

  const [assignRoleIds, setAssignRoleIds] = useState([]);
  const [assignStartDate, setAssignStartDate] = useState(null);
  const [assignEndDate, setAssignEndDate] = useState(null);
  const [assigning, setAssigning] = useState(false);
  const [togglingRoleKey, setTogglingRoleKey] = useState(null);

  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100'],
  });

  const roleOptions = useMemo(
    () =>
      rolesData.map((role) => ({
        value: Number(role.id),
        label: role.role_name || role.name || `Role ${role.id}`,
      })),
    [rolesData]
  );

  const detailRoles = useMemo(
    () => (Array.isArray(selectedUser?.roles) ? selectedUser.roles : []),
    [selectedUser]
  );

  const resetAssignForm = () => {
    setAssignRoleIds([]);
    setAssignStartDate(null);
    setAssignEndDate(null);
  };

  const applyUserUpdate = (updatedUser) => {
    if (!updatedUser) return;
    const normalized = normalizeBridgeUser(updatedUser);
    setSelectedUser(normalized);
    setUserData((prev) =>
      prev.map((user) => {
        const sameUser =
          (normalized.id != null && user.id === normalized.id) ||
          getUserNida(user) === getUserNida(normalized);
        return sameUser ? { ...user, ...normalized } : user;
      })
    );
  };

  const fetchBridgeUsers = async (page = 1, pageSize = 15, search = searchQuery) => {
    setLoading(true);
    try {
      const params = { page, per_page: pageSize };
      if (search?.trim()) params.search = search.trim();

      const response = await apiService.getBridgeUsers(params);
      if (response.success && response.data) {
        const { items: bridgeUsers, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || bridgeUsers.length;

        setUserData(bridgeUsers.map(normalizeBridgeUser));
        setPagination((prev) => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount),
        }));
      } else {
        message.error(response.message || 'Failed to fetch bridge users');
        setUserData([]);
        setPagination((prev) => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching users:', error);
      message.error('An error occurred while fetching users');
      setUserData([]);
      setPagination((prev) => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  const refreshUserList = () =>
    fetchBridgeUsers(pagination.current || 1, pagination.pageSize || 15, searchQuery);

  const fetchBridgeUserByNida = async (nida) => {
    const response = await apiService.getBridgeUserById(nida);
    if (response.success && response.data) {
      return normalizeBridgeUserFromResponse(response.data);
    }
    throw new Error(response.message || 'Failed to load user details');
  };

  const refreshSelectedUser = async (nida) => {
    if (!nida) return null;
    try {
      const user = await fetchBridgeUserByNida(nida);
      applyUserUpdate(user);
      return user;
    } catch (error) {
      console.error('Error refreshing user details:', error);
      return null;
    }
  };

  const fetchRoles = async () => {
    setLoadingRoles(true);
    try {
      const response = await apiService.getBmsRoles();
      if (response.success && response.data) {
        const roles = Array.isArray(response.data) ? response.data : response.data.roles || [];
        setRolesData(
          roles.filter((role) => role.is_active === 1 || role.is_active === true || role.is_active === '1')
        );
      } else {
        setRolesData([]);
      }
    } catch (error) {
      console.error('Error fetching roles:', error);
      setRolesData([]);
    } finally {
      setLoadingRoles(false);
    }
  };

  useEffect(() => {
    fetchBridgeUsers(1, pagination.pageSize);
    fetchRoles();
    setIsInitialLoad(false);
  }, []);

  useEffect(() => {
    if (isInitialLoad) return undefined;
    const timeoutId = setTimeout(() => {
      fetchBridgeUsers(1, pagination.pageSize, searchQuery);
    }, 500);
    return () => clearTimeout(timeoutId);
  }, [searchQuery, isInitialLoad]);

  const handleToggleUserStatus = async (record) => {
    const currentStatus = isUserActive(record.status || record.is_active);
    const action = currentStatus ? 'deactivate' : 'activate';
    const actionPast = currentStatus ? 'deactivated' : 'activated';
    const userName = record.full_name || buildDisplayName(record) || record.username || 'this user';

    antdModal.confirm({
      title: `Are you sure you want to ${action} this user?`,
      content: `This will ${action} the user "${userName}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: !currentStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const nida = getUserNida(record);
          if (!nida) {
            message.error('User NIDA is missing');
            return;
          }

          const response = await apiService.toggleBridgeUserStatus(nida);
          if (response.success) {
            message.success(`User "${userName}" has been ${actionPast} successfully.`);
            refreshUserList();
            if (selectedUser && getUserNida(selectedUser) === nida) {
              setSelectedUser({
                ...selectedUser,
                status: currentStatus ? 0 : 1,
                is_active: currentStatus ? 0 : 1,
              });
            }
          } else {
            message.error(response.message || 'Failed to update user status');
          }
        } catch (error) {
          console.error('Error toggling user status:', error);
          message.error('An error occurred while updating user status');
        }
      },
    });
  };

  const openUserDetailsModal = async (record) => {
    const nida = getUserNida(record);
    if (!record || !nida) {
      message.error('User information is missing');
      return;
    }

    setLoadingUserDetails(true);
    setViewError(null);
    setSelectedUser(normalizeBridgeUser(record));
    setIsUserDetailsModalOpen(true);

    try {
      setSelectedUser(await fetchBridgeUserByNida(nida));
    } catch (error) {
      console.error('Error loading user details:', error);
      setViewError(error.message || 'An error occurred while loading user details');
    } finally {
      setLoadingUserDetails(false);
    }
  };

  const closeUserDetailsModal = () => {
    setIsUserDetailsModalOpen(false);
    setSelectedUser(null);
    setViewError(null);
    resetAssignForm();
  };

  const openAssignModal = () => {
    resetAssignForm();
    setIsAssignModalOpen(true);
  };

  const closeAssignModal = () => {
    if (assigning) return;
    setIsAssignModalOpen(false);
    resetAssignForm();
  };

  const openCreateUserModal = () => {
    createUserForm.resetFields();
    setIsCreateUserModalOpen(true);
  };

  const closeCreateUserModal = () => {
    if (creatingUser) return;
    setIsCreateUserModalOpen(false);
    createUserForm.resetFields();
  };

  const openEditUserModal = async () => {
    const nida = getUserNida(selectedUser);
    if (!selectedUser || !nida) {
      message.error('User information is missing');
      return;
    }

    setIsEditUserModalOpen(true);
    setLoadingEditUser(true);
    editUserForm.resetFields();

    try {
      const user = await fetchBridgeUserByNida(nida);
      applyUserUpdate(user);
      editUserForm.setFieldsValue(mapUserToEditFormValues(user));
    } catch (error) {
      console.error('Error loading user for edit:', error);
      message.error(error.message || 'An error occurred while loading user details');
      editUserForm.setFieldsValue(mapUserToEditFormValues(selectedUser));
    } finally {
      setLoadingEditUser(false);
    }
  };

  const closeEditUserModal = () => {
    if (updatingUser || loadingEditUser) return;
    setIsEditUserModalOpen(false);
    editUserForm.resetFields();
  };

  const handleUpdateUser = async (values) => {
    const nida = getUserNida(selectedUser);
    if (!selectedUser || !nida) {
      message.error('User information is missing');
      return;
    }

    setUpdatingUser(true);
    try {
      const response = await apiService.updateBridgeUser(nida, buildUserPayload(values));
      if (response.success) {
        message.success(response.message || 'User updated successfully');
        setIsEditUserModalOpen(false);
        editUserForm.resetFields();
        await refreshSelectedUser(nida);
        await refreshUserList();
      } else {
        message.error(response.message || 'Failed to update user');
      }
    } catch (error) {
      console.error('Error updating user:', error);
      message.error('An error occurred while updating the user');
    } finally {
      setUpdatingUser(false);
    }
  };

  const handleCreateUser = async (values) => {
    setCreatingUser(true);
    try {
      const response = await apiService.createBridgeUser(buildUserPayload(values, { includeNida: true }));
      if (response.success) {
        message.success(response.message || 'User created successfully');
        setIsCreateUserModalOpen(false);
        createUserForm.resetFields();
        await refreshUserList();

        const createdUser = normalizeBridgeUserFromResponse(response.data);
        if (createdUser) {
          setSelectedUser(createdUser);
          resetAssignForm();
          setIsAssignModalOpen(true);
        }
      } else {
        message.error(response.message || 'Failed to create user');
      }
    } catch (error) {
      console.error('Error creating user:', error);
      message.error('An error occurred while creating the user');
    } finally {
      setCreatingUser(false);
    }
  };

  const handleAssignRole = async () => {
    const nida = getUserNida(selectedUser);
    if (!selectedUser || !nida) {
      message.error('User information is missing');
      return;
    }
    if (!Array.isArray(assignRoleIds) || assignRoleIds.length === 0) {
      message.warning('Please select at least one role');
      return;
    }

    const startDate = assignStartDate ? assignStartDate.format(DATE_FORMAT) : null;
    const endDate = assignEndDate ? assignEndDate.format(DATE_FORMAT) : null;

    if (isPastDate(assignStartDate)) {
      message.error('Start date cannot be in the past');
      return;
    }
    if (isPastDate(assignEndDate)) {
      message.error('End date cannot be in the past');
      return;
    }
    if (startDate && endDate && dayjs(endDate).isBefore(dayjs(startDate), 'day')) {
      message.error('End date must be on or after start date');
      return;
    }

    const currentRoleIds = detailRoles.map((role) => Number(role.id)).filter(Number.isFinite);
    const rolesToAssign = assignRoleIds
      .map((id) => Number(id))
      .filter((id) => Number.isFinite(id) && !currentRoleIds.includes(id));

    if (rolesToAssign.length === 0) {
      message.info('All selected roles are already assigned to this user');
      return;
    }

    setAssigning(true);
    try {
      const results = await Promise.all(
        rolesToAssign.map((roleId) =>
          apiService.assignRoleToBridgeUsers(nida, roleId, {
            from_date: startDate,
            to_date: endDate,
          })
        )
      );

      const allSuccess = results.every((result) => result.success);
      const skippedCount = assignRoleIds.length - rolesToAssign.length;

      if (allSuccess) {
        const assignedCount = rolesToAssign.length;
        const apiMessage = results.find((result) => result.message)?.message;
        message.success(
          skippedCount > 0
            ? `${assignedCount} role(s) assigned. ${skippedCount} already assigned role(s) skipped.`
            : apiMessage || `${assignedCount} role(s) assigned successfully`
        );
        await refreshSelectedUser(nida);
        await refreshUserList();
        closeAssignModal();
      } else {
        const failed = results.find((result) => !result.success);
        message.error(failed?.message || 'Some roles could not be assigned');
        await refreshSelectedUser(nida);
        await refreshUserList();
      }
    } catch (error) {
      console.error('Error assigning roles:', error);
      message.error('An error occurred while assigning roles');
    } finally {
      setAssigning(false);
    }
  };

  const handleToggleRoleStatus = (role, nextChecked) => {
    const nida = getUserNida(selectedUser);
    const roleId = role.id;
    const roleName = role.name || `Role ${roleId}`;

    if (!selectedUser || !nida || !roleId) {
      message.error('Role information is missing');
      return;
    }

    antdModal.confirm({
      title: nextChecked ? 'Enable role?' : 'Revoke role?',
      content: nextChecked
        ? `This will enable the "${roleName}" role for ${buildDisplayName(selectedUser) || selectedUser.username}.`
        : `This will revoke the "${roleName}" role from ${buildDisplayName(selectedUser) || selectedUser.username}.`,
      okText: nextChecked ? 'Enable' : 'Revoke',
      okType: nextChecked ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        const toggleKey = roleRowKey(role);
        setTogglingRoleKey(toggleKey);
        try {
          const response = nextChecked
            ? await apiService.assignRoleToBridgeUsers(nida, roleId)
            : await apiService.revokeRoleFromBridgeUsers(nida, roleId);

          if (response.success) {
            message.success(
              response.message ||
                `Role "${roleName}" ${nextChecked ? 'enabled' : 'revoked'} successfully`
            );
            await refreshSelectedUser(nida);
            await refreshUserList();
          } else {
            message.error(response.message || `Failed to ${nextChecked ? 'enable' : 'revoke'} role`);
          }
        } catch (error) {
          console.error('Error toggling role status:', error);
          message.error('An error occurred while updating the role');
        } finally {
          setTogglingRoleKey(null);
        }
      },
    });
  };

  const roleTableColumns = useMemo(
    () => [
      {
        title: 'Role Name',
        key: 'name',
        render: (_, role) => (
          <span className="text-sm font-medium text-black">{role.name || EMPTY_VALUE}</span>
        ),
      },
      {
        title: 'Status',
        key: 'status',
        width: 120,
        render: (_, role) => {
          const badge = getRoleAssignmentBadge(role);
          return (
            <Tag color={badge.color} className="!m-0 font-semibold uppercase">
              {badge.label}
            </Tag>
          );
        },
      },
      {
        title: 'Start Date',
        key: 'start_date',
        width: 130,
        render: (_, role) => renderRoleDateCell(role.start_date),
      },
      {
        title: 'End Date',
        key: 'end_date',
        width: 130,
        render: (_, role) => renderRoleDateCell(role.end_date),
      },
      {
        title: 'Action',
        key: 'toggle',
        width: 120,
        align: 'center',
        render: (_, role) => {
          const key = roleRowKey(role);
          return (
            <Switch
              checked={isRoleToggleOn(role)}
              loading={togglingRoleKey === key}
              onChange={(checked) => handleToggleRoleStatus(role, checked)}
              checkedChildren="Revoke"
              unCheckedChildren="Enable"
              size="small"
            />
          );
        },
      },
    ],
    [togglingRoleKey, selectedUser]
  );

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
      title: 'Name',
      dataIndex: 'full_name',
      key: 'full_name',
      searchable: true,
      width: 200,
      render: (text, record) => text || buildDisplayName(record) || EMPTY_VALUE,
    },
    {
      title: 'Username',
      dataIndex: 'username',
      key: 'username',
      searchable: true,
      width: 200,
      render: (text) => text || EMPTY_VALUE,
    },
    {
      title: 'Email',
      dataIndex: 'email',
      key: 'email',
      searchable: true,
      width: 200,
    },
    {
      title: 'Current Roles',
      key: 'roles',
      width: 250,
      render: (_, record) => {
        const roles = normalizeRoleList(record.roles || record.role || []);
        if (roles.length === 0) {
          return <Tag color="default">No roles assigned</Tag>;
        }
        return (
          <div className="flex flex-wrap gap-1">
            {roles.map((role) => (
              <Tag key={roleRowKey(role)} color={BRAND}>
                {role.name}
              </Tag>
            ))}
          </div>
        );
      },
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 150,
      align: 'center',
      render: (status, record) => (
        <Switch
          checked={isUserActive(status || record.is_active)}
          onChange={() => handleToggleUserStatus(record)}
          checkedChildren="Active"
          unCheckedChildren="Inactive"
          size="small"
        />
      ),
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 120,
      fixed: 'right',
      render: (_, record) => (
        <Button
          type="primary"
          icon={<EyeOutlined />}
          onClick={() => openUserDetailsModal(record)}
          size="small"
          className="btn-standard-primary"
        >
          View
        </Button>
      ),
    },
  ];

  const assignFieldsProps = {
    assignRoleIds,
    onRoleChange: setAssignRoleIds,
    assignStartDate,
    onStartChange: setAssignStartDate,
    assignEndDate,
    onEndChange: setAssignEndDate,
    roleOptions,
    loadingRoles,
    disabled: assigning,
  };

  return (
    <div className="grant-role-container">
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
        data={userData}
        loading={false}
        pagination={pagination}
        pageSize={pagination.pageSize}
        showSearch={true}
        serverSideSearch={true}
        showRefresh={false}
        rightAction={
          <Button
            type="primary"
            icon={<UserPlus size={16} />}
            onClick={openCreateUserModal}
            className="btn-standard-primary"
          >
            Add User
          </Button>
        }
        onSearchChange={(value) => {
          setSearchQuery(value);
          setPagination((prev) => ({ ...prev, current: 1 }));
        }}
        onChange={(paginationInfo) => {
          if (paginationInfo) {
            const newPage = paginationInfo.current || 1;
            const newPageSize = Number(paginationInfo.pageSize) || 15;
            if (newPage !== pagination.current || newPageSize !== pagination.pageSize) {
              fetchBridgeUsers(newPage, newPageSize, searchQuery);
            }
          }
        }}
        searchPlaceholder="Search by name, username, email, phone..."
        rowKey={getTableRowKey}
        size="middle"
        bordered={true}
      />
      </div>

      <GrantRoleModal open={isUserDetailsModalOpen} onCancel={closeUserDetailsModal} width={860}>
        <BrandModalHeader title="User Details" onClose={closeUserDetailsModal} />

        {loadingUserDetails ? (
          <LoadingState message="Loading user details…" />
        ) : viewError ? (
          <div className="px-6 py-5">
            <Alert type="error" showIcon message={viewError} />
          </div>
        ) : selectedUser ? (
          <div className="px-6 py-5">
            <SectionHeading>Profile</SectionHeading>
            <div className="grid grid-cols-1 gap-x-6 md:grid-cols-2">
              <Field label="Full Name" value={buildDisplayName(selectedUser)} />
              <Field label="Username" value={selectedUser.username} />
              <Field label="Email" value={selectedUser.email} />
              <Field label="Phone" value={selectedUser.phone} />
              <Field
                label="Status"
                value={isUserActive(selectedUser.status || selectedUser.is_active) ? 'Active' : 'Inactive'}
              />
              <Field label="NIDA" value={getUserNida(selectedUser)} />
            </div>

            <SectionHeading className="mb-3 mt-6">Roles</SectionHeading>
            <Table
              columns={roleTableColumns}
              dataSource={detailRoles}
              rowKey={roleRowKey}
              pagination={false}
              size="small"
              bordered
              scroll={{ x: 720 }}
              locale={{ emptyText: 'No roles assigned' }}
            />

            {(selectedUser.created_at || selectedUser.updated_at) && (
              <>
                <SectionHeading className="mb-3 mt-6">Timestamps</SectionHeading>
                <div className="grid grid-cols-1 gap-x-6 md:grid-cols-2">
                  <Field label="Created At" value={selectedUser.created_at} />
                  <Field label="Updated At" value={selectedUser.updated_at} />
                </div>
              </>
            )}
          </div>
        ) : null}

        {!loadingUserDetails && (
          <ModalFooter>
            {selectedUser && !viewError ? (
              <>
                <Button icon={<Pencil size={14} />} onClick={openEditUserModal}>
                  Edit User
                </Button>
                <BrandPrimaryButton icon={<UserPlus size={14} />} onClick={openAssignModal}>
                  Assign Role
                </BrandPrimaryButton>
              </>
            ) : null}
            <Button onClick={closeUserDetailsModal}>Close</Button>
          </ModalFooter>
        )}
      </GrantRoleModal>

      <GrantRoleModal
        open={isAssignModalOpen}
        onCancel={closeAssignModal}
        width={560}
        maskClosable={!assigning}
        keyboard={!assigning}
      >
        <BrandModalHeader title="Assign Role to User" onClose={closeAssignModal} />

        <div className="px-6 py-5">
          <p className="m-0 text-sm text-slate-600">
            User:{' '}
            <span className="font-semibold text-black">
              {selectedUser ? buildDisplayName(selectedUser) || selectedUser.username : EMPTY_VALUE}
            </span>
          </p>
          {detailRoles.length > 0 ? (
            <div className="mt-3">
              <span className="mb-2 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                Current Roles
              </span>
              <div className="flex flex-wrap gap-1.5">
                {detailRoles.map((role) => (
                  <Tag key={roleRowKey(role)} color={BRAND}>
                    {role.name}
                  </Tag>
                ))}
              </div>
            </div>
          ) : null}
          <div className="mt-4">
            <AssignRoleFields {...assignFieldsProps} />
          </div>
        </div>

        <ModalFooter>
          <BrandPrimaryButton
            icon={<UserPlus size={14} />}
            loading={assigning}
            onClick={handleAssignRole}
          >
            Assign Roles
          </BrandPrimaryButton>
          <Button onClick={closeAssignModal} disabled={assigning}>
            Close
          </Button>
        </ModalFooter>
      </GrantRoleModal>

      <GrantRoleModal
        open={isCreateUserModalOpen}
        onCancel={closeCreateUserModal}
        width={640}
        maskClosable={!creatingUser}
        keyboard={!creatingUser}
      >
        <BrandModalHeader title="Add Bridge User" onClose={closeCreateUserModal} />

        <Form
          form={createUserForm}
          layout="vertical"
          onFinish={handleCreateUser}
          preserve={false}
          className="px-6 py-5"
        >
          <BridgeUserFormFields mode="create" disabled={creatingUser} />
          <FormFooter>
            <BrandPrimaryButton
              htmlType="submit"
              icon={<UserPlus size={14} />}
              loading={creatingUser}
            >
              Create User
            </BrandPrimaryButton>
            <Button onClick={closeCreateUserModal} disabled={creatingUser}>
              Close
            </Button>
          </FormFooter>
        </Form>
      </GrantRoleModal>

      <GrantRoleModal
        open={isEditUserModalOpen}
        onCancel={closeEditUserModal}
        width={640}
        maskClosable={!updatingUser && !loadingEditUser}
        keyboard={!updatingUser && !loadingEditUser}
        afterOpenChange={(open) => {
          if (!open) {
            editUserForm.resetFields();
            setLoadingEditUser(false);
          }
        }}
      >
        <BrandModalHeader title="Edit Bridge User" onClose={closeEditUserModal} />

        <div className="relative">
          {loadingEditUser ? <LoadingOverlay message="Loading user details…" /> : null}
          <Form
            form={editUserForm}
            layout="vertical"
            onFinish={handleUpdateUser}
            className="px-6 py-5"
          >
            <BridgeUserFormFields mode="edit" disabled={updatingUser} />
            <FormFooter>
              <BrandPrimaryButton
                htmlType="submit"
                icon={<Pencil size={14} />}
                loading={updatingUser}
              >
                Update User
              </BrandPrimaryButton>
              <Button onClick={closeEditUserModal} disabled={updatingUser}>
                Close
              </Button>
            </FormFooter>
          </Form>
        </div>
      </GrantRoleModal>
    </div>
  );
};

export default GrantRole;
