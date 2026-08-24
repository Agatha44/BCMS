import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Switch } from 'antd';
import { FileTextOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import SchemeForm from '../../common/components/forms/SchemeForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const SchemeManagement = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedScheme, setSelectedScheme] = useState(null);
  const [loading, setLoading] = useState(false);
  const [schemeData, setSchemeData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch schemes from API with server-side pagination
  const fetchSchemes = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getSchemes(params);
      if (response.success && response.data) {
        const { items: schemes, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || schemes.length;
        
        setSchemeData(schemes);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
        console.log(response.data);
      } else {
        message.error(response.message || 'Failed to fetch schemes');
        setSchemeData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching schemes:', error);
      message.error('An error occurred while fetching schemes');
      setSchemeData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSchemes(1, pagination.pageSize);
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
      title: 'Scheme Name',
      dataIndex: 'scheme_name',
      key: 'scheme_name',
      searchable: true,
      width: 300,
      render: (text, record) => text || record.name || 'N/A',
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
    setSelectedScheme(null);
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedScheme(record);
    setIsViewModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedScheme(record);
    setIsEditModalOpen(true);
  };

  const deleteScheme = async (record) => {
    const schemeName = record.scheme_name || record.name || 'this scheme';
    modal.confirm({
      title: 'Are you sure you want to delete this scheme?',
      content: `This will permanently delete the scheme "${schemeName}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteScheme(record.id);
          if (response.success) {
            message.success({
              content: `Scheme "${schemeName}" has been deleted successfully.`,
              duration: 3,
            });
            fetchSchemes(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete scheme');
          }
        } catch (error) {
          console.error('Error deleting scheme:', error);
          message.error('An error occurred while deleting scheme');
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
    const schemeName = record.scheme_name || record.name || 'this scheme';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this scheme?`,
      content: `This will ${action} the scheme "${schemeName}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          // For toggle, we'll use update with the new status
          const response = await apiService.updateScheme(record.id, { is_active: newStatus ? 1 : 0 });
          if (response.success) {
            message.success({
              content: `Scheme "${schemeName}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchSchemes(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to update scheme status');
          }
        } catch (error) {
          console.error('Error toggling scheme status:', error);
          message.error('An error occurred while updating scheme status');
        }
      },
    });
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedScheme(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const schemeName = formData.scheme_name || formData.name;
      const response = await apiService.createScheme(formData);
      if (response.success) {
        message.success({
          content: `Scheme "${schemeName}" has been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchSchemes();
      } else {
        message.error(response.message || 'Failed to create scheme');
      }
    } catch (error) {
      console.error('Error creating scheme:', error);
      message.error('An error occurred while creating scheme');
    }
  };

  const handleEditSubmit = async (formData) => {
    try {
      const schemeName = formData.scheme_name || formData.name;
      const response = await apiService.updateScheme(selectedScheme.id, formData);
      if (response.success) {
        message.success({
          content: `Scheme "${schemeName}" has been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchSchemes();
      } else {
        message.error(response.message || 'Failed to update scheme');
      }
    } catch (error) {
      console.error('Error updating scheme:', error);
      message.error('An error occurred while updating scheme');
    }
  };

  return (
    <div className="scheme-management-container">
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
        data={schemeData}
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
              fetchSchemes(newPage, newPageSize);
            }
          }
        }}
        rightAction={
          <Button
            type="primary"
            icon={<FileTextOutlined />}
            onClick={showCreateModal}
            size="large"
            className="btn-standard-primary"
          >
            Create Scheme
          </Button>
        }
        searchPlaceholder="Search schemes..."
        rowKey="id"
        size="middle"
        bordered={true}
      />
      </div>

      {/* Create Scheme Modal */}
      <Modal
        title="Create Scheme"
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <SchemeForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Scheme Modal */}
      <Modal
        title="View Scheme Details"
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={() => {
              if (selectedScheme) {
                deleteScheme(selectedScheme);
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
              if (selectedScheme) {
                handleToggleStatus(selectedScheme);
              }
            }}
            className="btn-standard-primary"
          >
            {(selectedScheme && (selectedScheme.is_active === 1 || selectedScheme.is_active === true)) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => {
            handleModalClose(setIsViewModalOpen);
            handleEdit(selectedScheme);
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
        {selectedScheme && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Scheme Name">
              {selectedScheme.scheme_name || selectedScheme.name || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={(selectedScheme.is_active === 1 || selectedScheme.is_active === true) ? 'green' : 'red'}>
                {(selectedScheme.is_active === 1 || selectedScheme.is_active === true) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {selectedScheme.created_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedScheme.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {selectedScheme.modified_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedScheme.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Scheme Modal */}
      <Modal
        title="Edit Scheme"
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <SchemeForm 
          onSubmit={handleEditSubmit} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={selectedScheme}
        />
      </Modal>
    </div>
  );
};

export default SchemeManagement;

