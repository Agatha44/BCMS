import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Switch } from 'antd';
import { SafetyOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import PermissionCreateForm from '../../common/components/forms/PermissionCreateForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const PermissionManagement = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedPermission, setSelectedPermission] = useState(null);
  const [loading, setLoading] = useState(false);
  const [permissionData, setPermissionData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch permissions from API with server-side pagination
  const fetchPermissions = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getBmsPermissions(params);
      if (response.success && response.data) {
        const { items: permissions, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || permissions.length;
        
        setPermissionData(permissions);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch permissions');
        setPermissionData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching permissions:', error);
      message.error('An error occurred while fetching permissions');
      setPermissionData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPermissions(1, pagination.pageSize);
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
      title: 'Name',
      dataIndex: 'name',
      key: 'name',
      searchable: true,
      width: 200,
    },
    {
      title: 'Permission',
      dataIndex: 'permission',
      key: 'permission',
      searchable: true,
      width: 180,
    },
    {
      title: 'Route',
      dataIndex: 'route',
      key: 'route',
      searchable: true,
      ellipsis: true,
    },
    {
      title: 'Status',
      dataIndex: 'is_active',
      key: 'is_active',
      searchable: false,
      width: 120,
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
              onChange={() => handleToggleStatus(record)}
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
    setSelectedPermission(null);
    setIsCreateModalOpen(true);
  };

  const handleView = async (record) => {
    try {
      setLoading(true);
      const response = await apiService.getBmsPermissionById(record.id);
      if (response.success && response.data) {
        setSelectedPermission(response.data);
        console.log(response.data);
        setIsViewModalOpen(true);
      } else {
        message.error(response.message || 'Failed to fetch permission details');
      }
    } catch (error) {
      console.error('Error fetching permission details:', error);
      message.error('An error occurred while fetching permission details');
    } finally {
      setLoading(false);
    }
  };

  const handleEdit = async (record) => {
    try {
      setLoading(true);
      const response = await apiService.getBmsPermissionById(record.id);
      if (response.success && response.data) {
        setSelectedPermission(response.data);
        setIsEditModalOpen(true);
      } else {
        message.error(response.message || 'Failed to fetch permission details');
      }
    } catch (error) {
      console.error('Error fetching permission details:', error);
      message.error('An error occurred while fetching permission details');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this permission?',
      content: `This will permanently delete the permission "${record.name}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteBmsPermission(record.id);
          if (response.success) {
            message.success({
              content: `Permission "${record.name}" has been deleted successfully.`,
              duration: 3,
            });
            fetchPermissions(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete permission');
          }
        } catch (error) {
          console.error('Error deleting permission:', error);
          message.error('An error occurred while deleting permission');
        }
      },
    });
  };

  const handleToggleStatus = (record) => {
    // Handle numeric 1/0, boolean true/false, or string "1"/"0"
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const action = currentStatus ? 'deactivate' : 'activate';
    const actionPast = currentStatus ? 'deactivated' : 'activated';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this permission?`,
      content: `This will ${action} the permission "${record.name}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: !currentStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.toggleBmsPermissionStatus(record.id);
          if (response.success) {
            message.success({
              content: `Permission "${record.name}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchPermissions(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to update permission status');
          }
        } catch (error) {
          console.error('Error toggling permission status:', error);
          message.error('An error occurred while updating permission status');
        }
      },
    });
  };

  const handleCreateCancel = () => {
    setIsCreateModalOpen(false);
    setSelectedPermission(null);
  };

  const handleViewCancel = () => {
    setIsViewModalOpen(false);
    setSelectedPermission(null);
  };

  const handleEditCancel = () => {
    setIsEditModalOpen(false);
    setSelectedPermission(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.registerBmsPermission(formData);
      if (response.success) {
        message.success({
          content: `Permission "${formData.name}" has been created successfully.`,
          duration: 3,
        });
        setIsCreateModalOpen(false);
        setSelectedPermission(null);
        fetchPermissions();
      } else {
        message.error(response.message || 'Failed to create permission');
      }
    } catch (error) {
      console.error('Error creating permission:', error);
      message.error('An error occurred while creating permission');
    }
  };

  const handleEditSubmit = async (formData) => {
    try {
      const response = await apiService.updateBmsPermission(selectedPermission.id, formData);
      if (response.success) {
        message.success({
          content: `Permission "${formData.name}" has been updated successfully.`,
          duration: 3,
        });
        setIsEditModalOpen(false);
        setSelectedPermission(null);
        fetchPermissions();
      } else {
        message.error(response.message || 'Failed to update permission');
      }
    } catch (error) {
      console.error('Error updating permission:', error);
      message.error('An error occurred while updating permission');
    }
  };

  return (
    <div className="permission-management-container">
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
        data={permissionData}
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
              fetchPermissions(newPage, newPageSize);
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
            Create Permission
          </Button>
        }
        searchPlaceholder="Search permissions..."
        rowKey="id"
        size="middle"
        bordered={true}
      />
      </div>

      {/* Create Permission Modal */}
      <Modal
        title="Create Permission"
        open={isCreateModalOpen}
        onCancel={handleCreateCancel}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <PermissionCreateForm 
          onSubmit={handleCreateSubmit} 
          onCancel={handleCreateCancel} 
        />
      </Modal>

      {/* View Permission Modal */}
      <Modal
        title="View Permission Details"
        open={isViewModalOpen}
        onCancel={handleViewCancel}
        footer={[
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={() => {
              if (selectedPermission) {
                handleDelete(selectedPermission);
                handleViewCancel();
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
              if (selectedPermission) {
                handleToggleStatus(selectedPermission);
              }
            }}
            className="btn-standard-primary"
          >
            {(selectedPermission && (selectedPermission.is_active === 1 || selectedPermission.is_active === true)) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => {
            handleViewCancel();
            handleEdit(selectedPermission);
          }} className="btn-standard-primary">
            Edit
          </Button>,
          <Button key="close" onClick={handleViewCancel}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        {selectedPermission && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Name">
              {selectedPermission.name}
            </Descriptions.Item>
            <Descriptions.Item label="Permission">
              {selectedPermission.permission}
            </Descriptions.Item>
            <Descriptions.Item label="Route">
              {selectedPermission.route}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={(selectedPermission.is_active === 1 || selectedPermission.is_active === true) ? 'green' : 'red'}>
                {(selectedPermission.is_active === 1 || selectedPermission.is_active === true) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {selectedPermission.created_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedPermission.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {selectedPermission.modified_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedPermission.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Permission Modal */}
      <Modal
        title="Edit Permission"
        open={isEditModalOpen}
        onCancel={handleEditCancel}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <PermissionCreateForm 
          onSubmit={handleEditSubmit} 
          onCancel={handleEditCancel}
          initialValues={selectedPermission}
        />
      </Modal>
    </div>
  );
};

export default PermissionManagement;

