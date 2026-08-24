import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Switch } from 'antd';
import { AppstoreOutlined, EyeOutlined, EditOutlined, StopOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import ModuleCreateForm from '../../common/components/forms/ModuleCreateForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const ModuleManagement = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedModule, setSelectedModule] = useState(null);
  const [loading, setLoading] = useState(false);
  const [moduleData, setModuleData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch modules from API with server-side pagination
  const fetchModules = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getBmsModules(params);
      if (response.success && response.data) {
        const { items: modules, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || modules.length;
        
        setModuleData(modules);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
        console.log(response.data);
      } else {
        message.error(response.message || 'Failed to fetch modules');
        setModuleData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching modules:', error);
      message.error('An error occurred while fetching modules');
      setModuleData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchModules(1, pagination.pageSize);
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
      title: 'Title',
      dataIndex: 'title',
      key: 'title',
      searchable: true,
      width: 200,
      render: (text, record) => text || record.module_name || 'N/A',
    },
    {
      title: 'Module ID',
      dataIndex: 'module_id',
      key: 'module_id',
      searchable: true,
      width: 180,
    },
    {
      title: 'Icon',
      dataIndex: 'icon',
      key: 'icon',
      searchable: false,
      width: 220,
      render: (icon) => icon || 'N/A',
    },
    {
      title: 'Description',
      dataIndex: 'description',
      key: 'description',
      searchable: true,
      ellipsis: true,
      render: (text, record) => text || record.module_description || 'N/A',
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
    setSelectedModule(null);
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedModule(record);
    setIsViewModalOpen(true);
  };

  const handleEditFromView = () => {
    setIsViewModalOpen(false);
    setIsEditModalOpen(true);
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedModule(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.registerBmsModule(formData);
      if (response.success) {
        message.success({
          content: `Module "${formData.title || formData.module_name}" has been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchModules(pagination.current, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to create module');
      }
    } catch (error) {
      console.error('Error creating module:', error);
      message.error('An error occurred while creating module');
    }
  };

  const handleEditSubmit = async (formData) => {
    try {
      const response = await apiService.updateBmsModule(selectedModule.id, formData);
      if (response.success) {
        message.success({
          content: `Module "${formData.title || formData.module_name}" has been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchModules(pagination.current, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to update module');
      }
    } catch (error) {
      console.error('Error updating module:', error);
      message.error('An error occurred while updating module');
    }
  };

  const handleToggleStatus = (record) => {
    // Handle numeric 1/0, boolean true/false, or string "1"/"0"
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const action = currentStatus ? 'deactivate' : 'activate';
    const actionPast = currentStatus ? 'deactivated' : 'activated';
    const moduleName = record.title || record.module_name || 'this module';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this module?`,
      content: `This will ${action} the module "${moduleName}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: !currentStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.toggleBmsModuleStatus(record.id);
          if (response.success) {
            message.success({
              content: `Module "${moduleName}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchModules(pagination.current, pagination.pageSize);
            // Update selected module if it's the one being toggled
            if (selectedModule && selectedModule.id === record.id) {
              setSelectedModule({ ...selectedModule, is_active: currentStatus ? 0 : 1 });
            }
          } else {
            message.error(response.message || 'Failed to update module status');
          }
        } catch (error) {
          console.error('Error toggling module status:', error);
          message.error('An error occurred while updating module status');
        }
      },
    });
  };

  return (
    <div className="module-management-container">
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
        data={moduleData}
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
              fetchModules(newPage, newPageSize);
            }
          }
        }}
        rightAction={
          <Button
            type="primary"
            icon={<AppstoreOutlined />}
            onClick={showCreateModal}
            size="large"
            className="btn-standard-primary"
          >
            Create Module
          </Button>
        }
        searchPlaceholder="Search modules..."
        rowKey="id"
        size="middle"
        bordered={true}
      />
      </div>

      {/* Create Module Modal */}
      <Modal
        title="Create Module"
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <ModuleCreateForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Module Modal */}
      <Modal
        title="View Module Details"
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
          <Button 
            key="toggle" 
            type="primary" 
            icon={<StopOutlined />} 
            onClick={() => {
              if (selectedModule) {
                handleToggleStatus(selectedModule);
              }
            }}
            className="btn-standard-primary"
          >
            {(selectedModule && (selectedModule.is_active === 1 || selectedModule.is_active === true)) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={handleEditFromView} className="btn-standard-primary">
            Edit
          </Button>,
          <Button key="close" onClick={() => handleModalClose(setIsViewModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        {selectedModule && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Title">
              {selectedModule.title || selectedModule.module_name || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Module ID">
              {selectedModule.module_id || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Icon">
              {selectedModule.icon || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Description">
              {selectedModule.description || selectedModule.module_description || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={(selectedModule.is_active === 1 || selectedModule.is_active === true) ? 'green' : 'red'}>
                {(selectedModule.is_active === 1 || selectedModule.is_active === true) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {selectedModule.created_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedModule.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {selectedModule.modified_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedModule.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Module Modal */}
      <Modal
        title="Edit Module"
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <ModuleCreateForm 
          onSubmit={handleEditSubmit} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={selectedModule}
        />
      </Modal>
    </div>
  );
};

export default ModuleManagement;

