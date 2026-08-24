import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Select, Switch } from 'antd';
import { EyeOutlined, EditOutlined, DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import ModuleRoleForm from '../../common/components/forms/ModuleRoleForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const { Option } = Select;

const ModuleRole = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedAssignment, setSelectedAssignment] = useState(null);
  const [loading, setLoading] = useState(false);
  const [assignmentData, setAssignmentData] = useState([]);
  const [roles, setRoles] = useState([]);
  const [modules, setModules] = useState([]);
  const [filterRoleId, setFilterRoleId] = useState(null);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch role-module assignments with server-side pagination
  const fetchAssignments = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      // Add role filter if selected
      if (filterRoleId) {
        params.role_id = filterRoleId;
      }
      
      const response = await apiService.getBmsRoleModules(params);
      if (response.success && response.data) {
        // Extract assignments and pagination data from response
        const { items: assignments, pagination: paginationData } = extractArrayFromResponse(response.data);
        
        // Get total count from response (could be in different places)
        const totalCount = response.data.count || response.data.total || paginationData?.total || assignments.length;
        
        setAssignmentData(assignments);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
        console.log('Fetched assignments:', assignments, 'Pagination:', paginationData);
      } else {
        message.error(response.message || 'Failed to fetch role-module assignments');
        setAssignmentData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching assignments:', error);
      message.error('An error occurred while fetching role-module assignments');
      setAssignmentData([]);
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

  // Fetch modules for display
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
    // Reset to page 1 when filter changes
    const currentPageSize = pagination.pageSize || 15;
    setPagination(prev => ({ ...prev, current: 1 }));
    fetchAssignments(1, currentPageSize);
    fetchRoles();
    fetchModules();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filterRoleId]);

  // Helper function to get role name by ID
  const getRoleName = (roleId) => {
    const role = roles.find(r => r.id === roleId);
    return role ? (role.role_name || role.name) : `Role ID: ${roleId}`;
  };

  // Helper function to get module name by ID
  const getModuleName = (moduleId) => {
    const module = modules.find(m => m.id === moduleId);
    return module ? (module.module_name || module.name) : `Module ID: ${moduleId}`;
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
      },
    },
    {
      title: 'Role',
      dataIndex: 'role_id',
      key: 'role_id',
      searchable: true,
      width: 200,
      render: (roleId) => getRoleName(roleId),
    },
    {
      title: 'Modules',
      dataIndex: 'module_ids',
      key: 'module_ids',
      searchable: false,
      ellipsis: true,
      render: (moduleIds) => {
        if (!moduleIds || !Array.isArray(moduleIds)) {
          return 'N/A';
        }
        return (
          <div>
            {moduleIds.map((moduleId, index) => (
              <Tag key={moduleId} color="#962E32" style={{ marginBottom: 4 }}>
                {getModuleName(moduleId)}
              </Tag>
            ))}
          </div>
        );
      },
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
    setSelectedAssignment(null);
    setIsCreateModalOpen(true);
  };

  const handleToggleStatus = async (record) => {
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const newStatus = !currentStatus;
    const action = newStatus ? 'activate' : 'deactivate';
    const actionPast = newStatus ? 'activated' : 'deactivated';

    modal.confirm({
      title: `Are you sure you want to ${action} this role-module assignment?`,
      content: `This will ${action} the module assignment for role "${getRoleName(record.role_id)}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.toggleBmsRoleModuleStatus(record.id);
          if (response.success) {
            message.success({
              content: `Role-module assignment has been ${actionPast} successfully.`,
              duration: 3,
            });
            const currentPage = pagination.current || 1;
            const currentPageSize = pagination.pageSize || 15;
            fetchAssignments(currentPage, currentPageSize);
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
    setSelectedAssignment(record);
    setIsViewModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedAssignment(record);
    setIsEditModalOpen(true);
  };

  const handleDelete = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this role-module assignment?',
      content: `This will remove the module assignment for role "${getRoleName(record.role_id)}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteBmsRoleModule(record.id);
          if (response.success) {
            message.success({
              content: 'Role-module assignment has been deleted successfully.',
              duration: 3,
            });
            const currentPage = pagination.current || 1;
            const currentPageSize = pagination.pageSize || 15;
            fetchAssignments(currentPage, currentPageSize);
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
    setSelectedAssignment(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.assignBmsModuleRole(formData);
      if (response.success) {
        message.success({
          content: 'Role-module assignment has been created successfully.',
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchAssignments();
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
      const response = await apiService.updateBmsRoleModule(selectedAssignment.id, formData);
      if (response.success) {
        message.success({
          content: 'Role-module assignment has been updated successfully.',
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchAssignments();
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
    if (!selectedAssignment) return null;
    return {
      role_id: selectedAssignment.role_id,
      module_ids: selectedAssignment.module_ids || [],
    };
  };

  return (
    <div className="module-role-container">
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
        data={assignmentData}
        loading={false}
        pagination={pagination}
        pageSize={pagination.pageSize}
        showSearch={true}
        showRefresh={false}
        onChange={(paginationInfo) => {
          if (paginationInfo) {
            const newPage = paginationInfo.current || 1;
            const newPageSize = paginationInfo.pageSize || 15;
            fetchAssignments(newPage, newPageSize);
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
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={showCreateModal}
              size="large"
              className="btn-standard-primary"
            >
              Assign Modules to Role
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
        title="Assign Modules to Role"
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <ModuleRoleForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Assignment Modal */}
      <Modal
        title="View Role-Module Assignment"
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={() => {
              if (selectedAssignment) {
                handleDelete(selectedAssignment);
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
        {selectedAssignment && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Role">
              {getRoleName(selectedAssignment.role_id)}
            </Descriptions.Item>
            <Descriptions.Item label="Modules">
              {selectedAssignment.module_ids && Array.isArray(selectedAssignment.module_ids) ? (
                <div>
                  {selectedAssignment.module_ids.map((moduleId) => (
                    <Tag key={moduleId} color="#962E32" style={{ marginBottom: 4 }}>
                      {getModuleName(moduleId)}
                    </Tag>
                  ))}
                </div>
              ) : 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={checkIsActive(selectedAssignment.is_active) ? 'green' : 'red'}>
                {checkIsActive(selectedAssignment.is_active) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {selectedAssignment.created_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedAssignment.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {selectedAssignment.modified_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedAssignment.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Assignment Modal */}
      <Modal
        title="Edit Role-Module Assignment"
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <ModuleRoleForm 
          onSubmit={handleEditSubmit} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={getEditInitialValues()}
        />
      </Modal>
    </div>
  );
};

export default ModuleRole;

