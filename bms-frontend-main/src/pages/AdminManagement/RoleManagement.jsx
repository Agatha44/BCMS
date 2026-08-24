import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Switch } from 'antd';
import { SafetyOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined, ApiOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import RoleCreateForm from '../../common/components/forms/RoleCreateForm.jsx';
import RolePermission from './RolePermission.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const RoleManagement = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isPermissionModalOpen, setIsPermissionModalOpen] = useState(false);
  const [selectedRole, setSelectedRole] = useState(null);
  const [loading, setLoading] = useState(false);
  const [roleData, setRoleData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch roles from API with server-side pagination
  const fetchRoles = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getBmsRoles(params);
      if (response.success && response.data) {
        const { items: roles, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || roles.length;
        
        setRoleData(roles);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
        console.log(response.data);
      } else {
        message.error(response.message || 'Failed to fetch roles');
        setRoleData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching roles:', error);
      message.error('An error occurred while fetching roles');
      setRoleData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRoles(1, pagination.pageSize);
  }, []);

  // Define table columns
  const columns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => {
        // Calculate serial number based on pagination
        const current = pagination.current || 1;
        const pageSize = pagination.pageSize || 15;
        return (current - 1) * pageSize + index + 1;
      },
    },
    {
      title: 'Role Name',
      dataIndex: 'role_name',
      key: 'role_name',
      searchable: true,
      width: 200,
    },
    {
      title: 'Description',
      dataIndex: 'role_description',
      key: 'role_description',
      searchable: true,
      ellipsis: true,
    },
    {
      title: 'Status',
      dataIndex: 'is_active',
      key: 'is_active',
      searchable: false,
      width: 120,
      align: 'center',
      filters: [
        { text: 'Active', value: 1 },
        { text: 'Inactive', value: 0 },
      ],
      onFilter: (value, record) => record.is_active === value,
      render: (is_active, record) => {
        // Handle numeric 1/0, boolean true/false, or string "1"/"0"
        const isActive = is_active === 1 || is_active === true || is_active === '1';
        return (
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px' }}>
            <Switch
              checked={isActive}
              onChange={() => UpdateBmsRolestatus(record)}
              checkedChildren="Active"
              unCheckedChildren="Inactive"
              size="small"
            />
          </div>
        );
      },
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 120,
      align: 'center',
      render: (_, record) => (
        <Button 
          type="primary"
          icon={<EyeOutlined />} 
          size="small"
          onClick={() => handleView(record)}
          className="btn-standard-primary"
        >
          View
        </Button>
      ),
    },
  ];

  const showCreateModal = () => {
    setSelectedRole(null);
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedRole(record);
    setIsViewModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedRole(record);
    setIsEditModalOpen(true);
  };

  const handleMapPermissions = (record) => {
    setSelectedRole(record);
    setIsPermissionModalOpen(true);
  };

  const deleteBmsRole = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this role?',
      content: `This will permanently delete the role "${record.role_name}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteBmsRole(record.id);
          if (response.success) {
            message.success({
              content: `Role "${record.role_name}" has been deleted successfully.`,
              duration: 3,
            });
            fetchRoles(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete role');
          }
        } catch (error) {
          console.error('Error deleting role:', error);
          message.error('An error occurred while deleting role');
        }
      },
    });
  };

  const UpdateBmsRolestatus = async (record) => {
    // Handle numeric 1/0, boolean true/false, or string "1"/"0"
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const newStatus = !currentStatus;
    const action = newStatus ? 'activate' : 'deactivate';
    const actionPast = newStatus ? 'activated' : 'deactivated';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this role?`,
      content: `This will ${action} the role "${record.role_name}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.toggleBmsRoleStatus(record.id);
          if (response.success) {
            message.success({
              content: `Role "${record.role_name}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchRoles(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to update role status');
          }
        } catch (error) {
          console.error('Error toggling role status:', error);
          message.error('An error occurred while updating role status');
        }
      },
    });
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedRole(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.registerBmsRole(formData);
      if (response.success) {
        message.success({
          content: `Role "${formData.role_name}" has been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchRoles();
      } else {
        message.error(response.message || 'Failed to create role');
      }
    } catch (error) {
      console.error('Error creating role:', error);
      message.error('An error occurred while creating role');
    }
  };

  const EditBmsRole = async (formData) => {
    try {
      const response = await apiService.updateBmsRole(selectedRole.id, formData);
      if (response.success) {
        message.success({
          content: `Role "${formData.role_name}" has been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchRoles();
      } else {
        message.error(response.message || 'Failed to update role');
      }
    } catch (error) {
      console.error('Error updating role:', error);
      message.error('An error occurred while updating role');
    }
  };

  return (
    <div className="role-management-container">
      {/* DataTable */}
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
        data={roleData}
        loading={false}
        pagination={pagination}
        pageSize={pagination.pageSize}
        showSearch={true}
        showRefresh={false}
        onChange={(paginationInfo) => {
          if (paginationInfo) {
            const newPage = paginationInfo.current || 1;
            const newPageSize = Number(paginationInfo.pageSize) || 15;
            
            // Only fetch if page or pageSize changed
            if (newPage !== pagination.current || newPageSize !== pagination.pageSize) {
              fetchRoles(newPage, newPageSize);
            }
          }
        }}
        rightAction={
          <Button
            type="primary"
            icon={<SafetyOutlined />}
            onClick={showCreateModal}
            size="large"
            className="btn-standard-primary"
          >
            Create Role
          </Button>
        }
        searchPlaceholder="Search roles..."
        rowKey="id"
        size="middle"
        bordered={true}
      />
      </div>

      {/* Create Role Modal */}
      <Modal
        title="Create Role"
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <RoleCreateForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Role Modal */}
      <Modal
        title="View Role Details"
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={() => {
              if (selectedRole) {
                deleteBmsRole(selectedRole);
                handleModalClose(setIsViewModalOpen);
              }
            }}
            className="btn-standard-primary"
          >
            Delete
          </Button>,
          <Button 
            key="toggle" 
            type="primary"
            icon={<StopOutlined />} 
            onClick={() => {
              if (selectedRole) {
                UpdateBmsRolestatus(selectedRole);
              }
            }}
            className="btn-standard-primary"
          >
            {(selectedRole && (selectedRole.is_active === 1 || selectedRole.is_active === true)) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button 
            key="permissions" 
            type="primary"
            icon={<ApiOutlined />} 
            onClick={() => {
              handleModalClose(setIsViewModalOpen);
              handleMapPermissions(selectedRole);
            }}
            className="btn-standard-primary"
          >
            Map Permissions
          </Button>,
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => {
            handleModalClose(setIsViewModalOpen);
            handleEdit(selectedRole);
          }} className="btn-standard-primary">
            Edit
          </Button>,
          <Button key="close" onClick={() => handleModalClose(setIsViewModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        {selectedRole && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Role Name">
              {selectedRole.role_name}
            </Descriptions.Item>
            <Descriptions.Item label="Description">
              {selectedRole.role_description || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={(selectedRole.is_active === 1 || selectedRole.is_active === true) ? 'green' : 'red'}>
                {(selectedRole.is_active === 1 || selectedRole.is_active === true) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {selectedRole.created_at|| 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedRole.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {selectedRole.modified_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedRole.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Role Modal */}
      <Modal
        title="Edit Role"
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <RoleCreateForm 
          onSubmit={EditBmsRole} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={selectedRole}
        />
      </Modal>

      {/* Role Permission Mapping Modal */}
      <RolePermission
        open={isPermissionModalOpen}
        onClose={() => handleModalClose(setIsPermissionModalOpen)}
        role={selectedRole}
      />
    </div>
  );
};

export default RoleManagement;