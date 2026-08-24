import { useState, useEffect } from 'react';
import { Button, Modal, Descriptions, Tag, App, Select, Switch } from 'antd';
import { EyeOutlined, EditOutlined, DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import BridgeModuleRoleForm from '../../common/components/forms/BridgeModuleRoleForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const { Option } = Select;

const BridgeModuleRole = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedRecord, setSelectedRecord] = useState(null);
  const [loading, setLoading] = useState(false);
  const [moduleRoleData, setModuleRoleData] = useState([]);
  const [roles, setRoles] = useState([]);
  const [modules, setModules] = useState([]);
  const [filterRoleId, setFilterRoleId] = useState(null);
  const [filterModuleId, setFilterModuleId] = useState(null);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch bridge-module-roles with server-side pagination
  const fetchModuleRoles = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      // Add filters if selected
      if (filterRoleId) {
        params.role_id = filterRoleId;
      }
      if (filterModuleId) {
        params.module_id = filterModuleId;
      }
      
      const response = await apiService.getBridgeModuleRoles(params);
      if (response.success && response.data) {
        const { items: records, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || records.length;
        
        setModuleRoleData(records);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch module-role assignments');
        setModuleRoleData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching module-role assignments:', error);
      message.error('An error occurred while fetching module-role assignments');
      setModuleRoleData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  // Fetch roles for filter dropdown
  const fetchRoles = async () => {
    try {
      const response = await apiService.getBmsRoles();
      if (response.success && response.data) {
        const rolesData = Array.isArray(response.data) ? response.data : [];
        setRoles(rolesData);
      }
    } catch (error) {
      console.error('Error fetching roles:', error);
    }
  };

  // Fetch modules for filter dropdown
  const fetchModules = async () => {
    try {
      const response = await apiService.getActiveBmsModules();
      if (response.success && response.data) {
        const modulesData = Array.isArray(response.data) ? response.data : [];
        setModules(modulesData);
      }
    } catch (error) {
      console.error('Error fetching modules:', error);
    }
  };

  useEffect(() => {
    // Reset to page 1 when filters change
    const currentPageSize = pagination.pageSize || 15;
    setPagination(prev => ({ ...prev, current: 1 }));
    fetchModuleRoles(1, currentPageSize);
    fetchRoles();
    fetchModules();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filterRoleId, filterModuleId]);

  // Helper function to get role name by ID
  const getRoleName = (roleId) => {
    const role = roles.find(r => r.id === roleId);
    return role ? (role.role_name || role.name) : `Role ID: ${roleId}`;
  };

  // Helper function to get module name by ID
  const getModuleName = (moduleId) => {
    const module = modules.find(m => m.id === moduleId);
    return module ? (module.title || module.module_name || module.name) : `Module ID: ${moduleId}`;
  };

  // Helper function to check if active
  const checkIsActive = (value) => {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value === 1;
    if (typeof value === 'string') return value === '1' || value === 'true';
    return false;
  };

  // Define table columns
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
      }
    },
    {
      title: 'Module',
      dataIndex: 'module_name',
      key: 'module_name',
      searchable: true,
      width: 200,
      render: (moduleId) => getModuleName(moduleId)
    },
    {
      title: 'Role',
      dataIndex: 'role_name',
      key: 'role_name',
      searchable: true,
      width: 200,
      render: (roleId) => getRoleName(roleId)
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
        { text: 'Inactive', value: 0 }
      ],
      onFilter: (value, record) => record.is_active === value,
      render: (is_active, record) => {
        const active = checkIsActive(is_active);
        return (
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px' }}>
            <Switch
              checked={active}
              onChange={() => handleToggleStatus(record)}
              checkedChildren="Active"
              unCheckedChildren="Inactive"
              size="small"
            />
          </div>
        );
      }
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 120,
      align: 'center',
      render: (_, record) => (
        <Button type="primary" icon={<EyeOutlined />} size="small" onClick={() => handleView(record)} className="btn-standard-primary">
          View
        </Button>
      )
    }
  ];

  const showCreateModal = () => {
    setSelectedRecord(null);
    setIsCreateModalOpen(true);
  };

  const handleToggleStatus = async (record) => {
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const newStatus = !currentStatus;
    const action = newStatus ? 'activate' : 'deactivate';
    const actionPast = newStatus ? 'activated' : 'deactivated';

    modal.confirm({
      title: `Are you sure you want to ${action} this module-role assignment?`,
      content: `This will ${action} the assignment for role "${getRoleName(record.role_id)}" and module "${getModuleName(record.module_id)}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.toggleBridgeModuleRoleStatus(record.id);
          if (response.success) {
            message.success({
              content: `Module-role assignment has been ${actionPast} successfully.`,
              duration: 3,
            });
            const currentPage = pagination.current || 1;
            const currentPageSize = pagination.pageSize || 15;
            fetchModuleRoles(currentPage, currentPageSize);
          } else {
            message.error(response.message || 'Failed to update assignment status');
          }
        } catch (error) {
          console.error('Error toggling assignment status:', error);
          message.error('An error occurred while updating assignment status');
        }
      },
    });
  };

  const handleView = (record) => {
    setSelectedRecord(record);
    setIsViewModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedRecord(record);
    setIsEditModalOpen(true);
  };

  const handleDelete = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this module-role assignment?',
      content: `This will remove the assignment for role "${getRoleName(record.role_id)}" and module "${getModuleName(record.module_id)}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteBridgeModuleRole(record.id);
          if (response.success) {
            message.success({
              content: 'Module-role assignment has been deleted successfully.',
              duration: 3,
            });
            const currentPage = pagination.current || 1;
            const currentPageSize = pagination.pageSize || 15;
            fetchModuleRoles(currentPage, currentPageSize);
          } else {
            message.error(response.message || 'Failed to delete assignment');
          }
        } catch (error) {
          console.error('Error deleting assignment:', error);
          message.error('An error occurred while deleting assignment');
        }
      },
    });
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedRecord(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      // Use bulkAssignModules for multiple role assignments
      const bulkData = {
        module_id: formData.module_id,
        role_ids: formData.role_ids || []
      };
      
      const response = await apiService.bulkAssignModules(bulkData);
      if (response.success) {
        message.success({
          content: `Module-role assignment${formData.role_ids?.length > 1 ? 's have' : ' has'} been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        const currentPage = pagination.current || 1;
        const currentPageSize = pagination.pageSize || 15;
        fetchModuleRoles(currentPage, currentPageSize);
      } else {
        message.error(response.message || 'Failed to create assignment');
      }
    } catch (error) {
      console.error('Error creating assignment:', error);
      message.error('An error occurred while creating assignment');
    }
  };

  const handleEditSubmit = async (formData) => {
    try {
      // For edit, we'll delete the old record and create new ones with bulk assign
      // First delete the existing record
      const deleteResponse = await apiService.deleteBridgeModuleRole(selectedRecord.id);
      
      if (!deleteResponse.success) {
        message.error(deleteResponse.message || 'Failed to delete old assignment');
        return;
      }

      // Then create new assignments using bulk assign
      const bulkData = {
        module_id: formData.module_id,
        role_ids: formData.role_ids || []
      };
      
      const response = await apiService.bulkAssignModules(bulkData);
      if (response.success) {
        message.success({
          content: `Module-role assignment${formData.role_ids?.length > 1 ? 's have' : ' has'} been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        const currentPage = pagination.current || 1;
        const currentPageSize = pagination.pageSize || 15;
        fetchModuleRoles(currentPage, currentPageSize);
      } else {
        message.error(response.message || 'Failed to update assignment');
      }
    } catch (error) {
      console.error('Error updating assignment:', error);
      message.error('An error occurred while updating assignment');
    }
  };

  // Prepare initial values for edit
  const getEditInitialValues = () => {
    if (!selectedRecord) return null;
    return {
      role_ids: selectedRecord.role_ids || (selectedRecord.role_id ? [selectedRecord.role_id] : []),
      module_id: selectedRecord.module_id,
    };
  };

  return (
    <div className="bridge-module-role-container">
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
        data={moduleRoleData}
        loading={false}
        pagination={pagination}
        pageSize={pagination.pageSize}
        showSearch={true}
        showRefresh={false}
        onChange={(paginationInfo) => {
          if (paginationInfo) {
            const newPage = paginationInfo.current || 1;
            const newPageSize = paginationInfo.pageSize || 15;
            fetchModuleRoles(newPage, newPageSize);
          }
        }}
        rightAction={
          <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
            <Select
              placeholder="Filter by Role"
              allowClear
              style={{ width: 200 }}
              value={filterRoleId}
              onChange={setFilterRoleId}
            >
              {roles.map((role) => (
                <Option key={role.id} value={role.id}>
                  {role.role_name || role.name}
                </Option>
              ))}
            </Select>
            <Select
              placeholder="Filter by Module"
              allowClear
              style={{ width: 200 }}
              value={filterModuleId}
              onChange={setFilterModuleId}
            >
              {modules.map((module) => (
                <Option key={module.id} value={module.id}>
                  {module.title || module.module_name || module.name}
                </Option>
              ))}
            </Select>
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={showCreateModal}
              size="large"
              className="btn-standard-primary"
            >
              Create Module-Role Assignment
            </Button>
          </div>
        }
        searchPlaceholder="Search assignments..."
        rowKey="id"
        size="middle"
        bordered={true}
      />
      </div>

      {/* Create Assignment Modal */}
      <Modal
        title="Create Module-Role Assignment"
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <BridgeModuleRoleForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Assignment Modal */}
      <Modal
        title="View Module-Role Assignment"
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={() => {
              if (selectedRecord) {
                handleDelete(selectedRecord);
              }
            }}
            className="btn-standard-primary"
          >
            Delete
          </Button>,
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => {
            setIsViewModalOpen(false);
            setIsEditModalOpen(true);
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
        {selectedRecord && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Module">
              {getModuleName(selectedRecord.module_id)}
            </Descriptions.Item>
            <Descriptions.Item label="Role(s)">
              {selectedRecord.role_ids && Array.isArray(selectedRecord.role_ids) ? (
                <div>
                  {selectedRecord.role_ids.map((roleId) => (
                    <Tag key={roleId} color="#962E32" style={{ marginBottom: 4 }}>
                      {getRoleName(roleId)}
                    </Tag>
                  ))}
                </div>
              ) : selectedRecord.role_id ? (
                <Tag color="#962E32">{getRoleName(selectedRecord.role_id)}</Tag>
              ) : (
                'N/A'
              )}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={checkIsActive(selectedRecord.is_active) ? 'green' : 'red'}>
                {checkIsActive(selectedRecord.is_active) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {selectedRecord.created_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedRecord.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {selectedRecord.modified_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedRecord.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Assignment Modal */}
      <Modal
        title="Edit Module-Role Assignment"
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <BridgeModuleRoleForm 
          onSubmit={handleEditSubmit} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={getEditInitialValues()}
        />
      </Modal>
    </div>
  );
};

export default BridgeModuleRole;

