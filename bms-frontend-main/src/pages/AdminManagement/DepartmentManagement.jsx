import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Switch } from 'antd';
import { TeamOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import DepartmentCreateForm from '../../common/components/forms/DepartmentCreateForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import dayjs from 'dayjs';
import '../../styles/common.css';

const DepartmentManagement = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedDepartment, setSelectedDepartment] = useState(null);
  const [loading, setLoading] = useState(false);
  const [departmentData, setDepartmentData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch departments from API with server-side pagination
  const fetchDepartments = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getBmsDepartments(params);
      if (response.success && response.data) {
        const { items: departments, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || departments.length;
        
        setDepartmentData(departments);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
        console.log(response.data);
      } else {
        message.error(response.message || 'Failed to fetch departments');
        setDepartmentData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching departments:', error);
      message.error('An error occurred while fetching departments');
      setDepartmentData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDepartments(1, pagination.pageSize);
  }, []);

  // Format date to dd-mm-yyyy time format
  const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      return dayjs(dateString).format('DD-MM-YYYY HH:mm:ss');
    } catch {
      return dateString;
    }
  };

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
      title: 'Department Name',
      dataIndex: 'department_name',
      key: 'department_name',
      searchable: true,
      width: 200,
    },
    {
      title: 'Status',
      dataIndex: 'is_active',
      key: 'is_active',
      searchable: false,
      width: 120,
      align: 'center',
      filters: [
        { text: 'Active', value: 1, key: 'active' },
        { text: 'Inactive', value: 0, key: 'inactive' },
      ],
      onFilter: (value, record) => record.is_active === value,
      render: (is_active, record) => {
        // Handle numeric 1/0, boolean true/false, or string "1"/"0"
        const isActive = is_active === 1 || is_active === true || is_active === '1';
        return (
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px' }}>
            <Switch
              checked={isActive}
              onChange={() => UpdateBmsDepartmentstatus(record)}
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
    setSelectedDepartment(null);
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedDepartment(record);
    setIsViewModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedDepartment(record);
    setIsEditModalOpen(true);
  };

  const deleteBmsDepartment = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this department?',
      content: `This will permanently delete the department "${record.department_name}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteBmsDepartment(record.id);
          if (response.success) {
            message.success({
              content: `Department "${record.department_name}" has been deleted successfully.`,
              duration: 3,
            });
            fetchDepartments(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete department');
          }
        } catch (error) {
          console.error('Error deleting department:', error);
          message.error('An error occurred while deleting department');
        }
      },
    });
  };

  const UpdateBmsDepartmentstatus = async (record) => {
    // Handle numeric 1/0, boolean true/false, or string "1"/"0"
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const newStatus = !currentStatus;
    const action = newStatus ? 'activate' : 'deactivate';
    const actionPast = newStatus ? 'activated' : 'deactivated';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this department?`,
      content: `This will ${action} the department "${record.department_name}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.toggleBmsDepartmentStatus(record.id);
          if (response.success) {
            message.success({
              content: `Department "${record.department_name}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchDepartments(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to update department status');
          }
        } catch (error) {
          console.error('Error toggling department status:', error);
          message.error('An error occurred while updating department status');
        }
      },
    });
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedDepartment(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.registerBmsDepartment(formData);
      if (response.success) {
        message.success({
          content: `Department "${formData.department_name}" has been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchDepartments();
      } else {
        message.error(response.message || 'Failed to create department');
      }
    } catch (error) {
      console.error('Error creating department:', error);
      message.error('An error occurred while creating department');
    }
  };

  const EditBmsDepartment = async (formData) => {
    try {
      const response = await apiService.updateBmsDepartment(selectedDepartment.id, formData);
      if (response.success) {
        message.success({
          content: `Department "${formData.department_name}" has been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchDepartments();
      } else {
        message.error(response.message || 'Failed to update department');
      }
    } catch (error) {
      console.error('Error updating department:', error);
      message.error('An error occurred while updating department');
    }
  };

  return (
    <div className="department-management-container">
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
        data={departmentData}
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
              fetchDepartments(newPage, newPageSize);
            }
          }
        }}
        rightAction={
          <Button
            type="primary"
            icon={<TeamOutlined />}
            onClick={showCreateModal}
            size="large"
            className="btn-standard-primary"
          >
            Create Department
          </Button>
        }
        searchPlaceholder="Search departments..."
        rowKey="id"
        size="middle"
        bordered={true}
      />
      </div>

      {/* Create Department Modal */}
      <Modal
        title="Create Department"
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <DepartmentCreateForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Department Modal */}
      <Modal
        title="View Department Details"
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={() => {
              if (selectedDepartment) {
                deleteBmsDepartment(selectedDepartment);
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
              if (selectedDepartment) {
                UpdateBmsDepartmentstatus(selectedDepartment);
              }
            }}
            className="btn-standard-primary"
          >
            {(selectedDepartment && (selectedDepartment.is_active === 1 || selectedDepartment.is_active === true)) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => {
            handleModalClose(setIsViewModalOpen);
            handleEdit(selectedDepartment);
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
        {selectedDepartment && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Department Name">
              {selectedDepartment.department_name}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={(selectedDepartment.is_active === 1 || selectedDepartment.is_active === true) ? 'green' : 'red'}>
                {(selectedDepartment.is_active === 1 || selectedDepartment.is_active === true) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {formatDateTime(selectedDepartment.created_at)}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedDepartment.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {formatDateTime(selectedDepartment.modified_at)}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedDepartment.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Department Modal */}
      <Modal
        title="Edit Department"
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <DepartmentCreateForm 
          onSubmit={EditBmsDepartment} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={selectedDepartment}
        />
      </Modal>
    </div>
  );
};

export default DepartmentManagement;

