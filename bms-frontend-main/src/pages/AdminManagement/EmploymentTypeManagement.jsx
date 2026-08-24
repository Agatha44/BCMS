import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Switch } from 'antd';
import { PlusOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import EmploymentTypeForm from '../../common/components/forms/EmploymentTypeForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const EmploymentTypeManagement = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedEmploymentType, setSelectedEmploymentType] = useState(null);
  const [loading, setLoading] = useState(false);
  const [employmentTypeData, setEmploymentTypeData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch employment types from API with server-side pagination
  const fetchEmploymentTypes = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getBridgeEmploymentTypes(params);
      if (response.success && response.data) {
        const { items: employmentTypes, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || employmentTypes.length;
        
        setEmploymentTypeData(employmentTypes);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
        console.log(response.data);
      } else {
        message.error(response.message || 'Failed to fetch employment types');
        setEmploymentTypeData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching employment types:', error);
      message.error('An error occurred while fetching employment types');
      setEmploymentTypeData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchEmploymentTypes(1, pagination.pageSize);
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
      title: 'Employment Type Name',
      dataIndex: 'emptype_name',
      key: 'emptype_name',
      searchable: true,
      width: 300,
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
    setSelectedEmploymentType(null);
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedEmploymentType(record);
    setIsViewModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedEmploymentType(record);
    setIsEditModalOpen(true);
  };

  const deleteEmploymentType = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this employment type?',
      content: `This will permanently delete the employment type "${record.emptype_name}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const id = record.emptype_id || record.id;
          const response = await apiService.deleteBridgeEmploymentType(id);
          if (response.success) {
            message.success({
              content: `Employment type "${record.emptype_name}" has been deleted successfully.`,
              duration: 3,
            });
            fetchEmploymentTypes(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete employment type');
          }
        } catch (error) {
          console.error('Error deleting employment type:', error);
          message.error('An error occurred while deleting employment type');
        }
      },
    });
  };

  const handleToggleStatus = async (record) => {
    // Handle numeric 1/0, boolean true/false, or string "1"/"0"
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const newStatus = !currentStatus;
    const action = newStatus ? 'activate' : 'deactivate';
    const actionPast = newStatus ? 'activated' : 'deactivated';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this employment type?`,
      content: `This will ${action} the employment type "${record.emptype_name}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          // For toggle, we'll use update with the new status
          const id = record.emptype_id || record.id;
          const response = await apiService.updateBridgeEmploymentType(id, { is_active: newStatus ? 1 : 0 });
          if (response.success) {
            message.success({
              content: `Employment type "${record.emptype_name}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchEmploymentTypes(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to update employment type status');
          }
        } catch (error) {
          console.error('Error toggling employment type status:', error);
          message.error('An error occurred while updating employment type status');
        }
      },
    });
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedEmploymentType(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.createBridgeEmploymentType(formData);
      if (response.success) {
        message.success({
          content: `Employment type "${formData.emptype_name}" has been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchEmploymentTypes();
      } else {
        message.error(response.message || 'Failed to create employment type');
      }
    } catch (error) {
      console.error('Error creating employment type:', error);
      message.error('An error occurred while creating employment type');
    }
  };

  const handleEditSubmit = async (formData) => {
    try {
      const id = selectedEmploymentType?.emptype_id || selectedEmploymentType?.id;
      const response = await apiService.updateBridgeEmploymentType(id, formData);
      if (response.success) {
        message.success({
          content: `Employment type "${formData.emptype_name}" has been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchEmploymentTypes();
      } else {
        message.error(response.message || 'Failed to update employment type');
      }
    } catch (error) {
      console.error('Error updating employment type:', error);
      message.error('An error occurred while updating employment type');
    }
  };

  return (
    <div className="employment-type-management-container">
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
        data={employmentTypeData}
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
              fetchEmploymentTypes(newPage, newPageSize);
            }
          }
        }}
        rightAction={
          <Button
            type="primary"
            icon={<PlusOutlined />}
            onClick={showCreateModal}
            size="large"
            className="btn-standard-primary"
          >
            Create Employment Type
          </Button>
        }
        searchPlaceholder="Search employment types..."
        rowKey={(record) => record.emptype_id || record.id}
        size="middle"
        bordered={true}
      />
      </div>

      {/* Create Employment Type Modal */}
      <Modal
        title="Create Employment Type"
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <EmploymentTypeForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Employment Type Modal */}
      <Modal
        title="View Employment Type Details"
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={() => {
              if (selectedEmploymentType) {
                deleteEmploymentType(selectedEmploymentType);
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
              if (selectedEmploymentType) {
                handleToggleStatus(selectedEmploymentType);
              }
            }}
            className="btn-standard-primary"
          >
            {(selectedEmploymentType && (selectedEmploymentType.is_active === 1 || selectedEmploymentType.is_active === true)) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => {
            handleModalClose(setIsViewModalOpen);
            handleEdit(selectedEmploymentType);
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
        {selectedEmploymentType && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Employment Type Name">
              {selectedEmploymentType.emptype_name}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={(selectedEmploymentType.is_active === 1 || selectedEmploymentType.is_active === true) ? 'green' : 'red'}>
                {(selectedEmploymentType.is_active === 1 || selectedEmploymentType.is_active === true) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {selectedEmploymentType.created_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedEmploymentType.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {selectedEmploymentType.modified_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedEmploymentType.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Employment Type Modal */}
      <Modal
        title="Edit Employment Type"
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <EmploymentTypeForm 
          onSubmit={handleEditSubmit} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={selectedEmploymentType}
        />
      </Modal>
    </div>
  );
};

export default EmploymentTypeManagement;

