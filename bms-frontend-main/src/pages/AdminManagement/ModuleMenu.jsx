import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Select, Switch } from 'antd';
import { MenuOutlined, EyeOutlined, EditOutlined, DeleteOutlined, PlusOutlined, StopOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import ModuleMenuForm from '../../common/components/forms/ModuleMenuForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const { Option } = Select;

const ModuleMenu = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedMenu, setSelectedMenu] = useState(null);
  const [loading, setLoading] = useState(false);
  const [menuData, setMenuData] = useState([]);
  const [modules, setModules] = useState([]);
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

  // Function to fetch module menus with server-side pagination
  const fetchMenus = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      // Add module filter if selected
      if (filterModuleId) {
        params.module_id = filterModuleId;
      }
      
      const response = await apiService.getBmsModuleMenus(params);
      if (response.success && response.data) {
        // Extract menus and pagination data from response
        const { items: menusArray, pagination: paginationData } = extractArrayFromResponse(response.data);
        
        // Get total count from response (could be in different places)
        const totalCount = response.data.count || response.data.total || paginationData?.total || menusArray.length;
        
        setMenuData(menusArray);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
        console.log('Fetched menus:', menusArray, 'Pagination:', paginationData);
      } else {
        message.error(response.message || 'Failed to fetch module menus');
        setMenuData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching menus:', error);
      message.error('An error occurred while fetching module menus');
      setMenuData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  // Fetch modules for filter dropdown
  const fetchModules = async () => {
    try {
      const response = await apiService.getBmsModules();
      if (response.success && response.data) {
        // Handle different response structures
        let modulesData = [];
        if (Array.isArray(response.data)) {
          modulesData = response.data;
        } else if (response.data.modules && Array.isArray(response.data.modules)) {
          modulesData = response.data.modules;
        } else if (response.data.data && Array.isArray(response.data.data)) {
          modulesData = response.data.data;
        }
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
    fetchMenus(1, currentPageSize);
    fetchModules();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filterModuleId]);

  // Helper function to get module name by ID
  const getModuleName = (moduleId, menuRecord = null) => {
    // First check if the menu record itself has a title field (module title from API)
    if (menuRecord) {
      if (menuRecord.title) {
        return menuRecord.title;
      }
      // Also check for module_title field (alternative API response structure)
      if (menuRecord.module_title) {
        return menuRecord.module_title;
      }
    }
    
    // Otherwise, find the module by ID from the modules list
    if (modules && modules.length > 0) {
      const module = modules.find(m => m.id === moduleId);
      if (module) {
        return module.title || module.module_name || module.name || `Module ID: ${moduleId}`;
      }
    }
    
    return `Module ID: ${moduleId}`;
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
      title: 'Module',
      key: 'module',
      searchable: true,
      width: 180,
      // Use title field for search filtering
      onFilter: (value, record) => {
        const moduleTitle = record?.title || getModuleName(record?.module_id, record);
        return moduleTitle?.toString().toLowerCase().includes(value.toLowerCase()) || false;
      },
      render: (_, record) => {
        // Use the title field from the menu record (module title from API)
        if (record && record.title) {
          return record.title;
        }
        // Fallback to getModuleName if title is not available
        return getModuleName(record?.module_id, record);
      },
    },
    {
      title: 'Menu Name',
      dataIndex: 'menu_name',
      key: 'menu_name',
      searchable: true,
      width: 200,
    },
    {
      title: 'Menu Path',
      dataIndex: 'menu_path',
      key: 'menu_path',
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
    setSelectedMenu(null);
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedMenu(record);
    setIsViewModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedMenu(record);
    setIsEditModalOpen(true);
  };

  const handleDelete = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this menu?',
      content: `This will permanently delete the menu "${record.menu_name}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteBmsModuleMenu(record.id);
          if (response.success) {
            message.success({
              content: `Menu "${record.menu_name}" has been deleted successfully.`,
              duration: 3,
            });
            handleModalClose(setIsViewModalOpen);
            fetchMenus(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete menu');
          }
        } catch (error) {
          console.error('Error deleting menu:', error);
          message.error('An error occurred while deleting menu');
        }
      },
    });
  };

  const handleToggleStatus = (record) => {
    // Handle numeric 1/0, boolean true/false, or string "1"/"0"
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const action = currentStatus ? 'deactivate' : 'activate';
    const actionPast = currentStatus ? 'deactivated' : 'activated';
    const menuName = record.menu_name || 'this menu';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this menu?`,
      content: `This will ${action} the menu "${menuName}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: !currentStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.toggleBridgeModuleMenuStatus(record.id);
          if (response.success) {
            message.success({
              content: `Menu "${menuName}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchMenus(pagination.current, pagination.pageSize);
            // Update selected menu if it's the one being toggled
            if (selectedMenu && selectedMenu.id === record.id) {
              setSelectedMenu({ ...selectedMenu, is_active: currentStatus ? 0 : 1 });
            }
          } else {
            message.error(response.message || 'Failed to update menu status');
          }
        } catch (error) {
          console.error('Error toggling menu status:', error);
          message.error('An error occurred while updating menu status');
        }
      },
    });
  };

  const handleDeleteFromView = () => {
    if (selectedMenu) {
      handleDelete(selectedMenu);
    }
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedMenu(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.registerBmsModuleMenu(formData);
      if (response.success) {
        message.success({
          content: `Menu "${formData.menu_name}" has been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchMenus(pagination.current, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to create menu');
      }
    } catch (error) {
      console.error('Error creating menu:', error);
      message.error('An error occurred while creating menu');
    }
  };

  const handleEditSubmit = async (formData) => {
    try {
      const response = await apiService.updateBmsModuleMenu(selectedMenu.id, formData);
      if (response.success) {
        message.success({
          content: `Menu "${formData.menu_name}" has been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchMenus(pagination.current, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to update menu');
      }
    } catch (error) {
      console.error('Error updating menu:', error);
      message.error('An error occurred while updating menu');
    }
  };

  return (
    <div className="module-menu-container">
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
        data={menuData}
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
              fetchMenus(newPage, newPageSize);
            }
          }
        }}
        rightAction={
          <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
            <Select
              placeholder="Filter by Module"
              allowClear
              style={{ width: 200 }}
              value={filterModuleId}
              onChange={setFilterModuleId}
            >
              {modules.map((module) => (
                <Option key={module.id} value={module.id}>
                  {module.title || module.module_name || module.name || `Module ID: ${module.id}`}
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
              Create Menu
            </Button>
          </div>
        }
        searchPlaceholder="Search menus..."
        rowKey="id"
        size="middle"
        bordered={true}
      />
      </div>

      {/* Create Menu Modal */}
      <Modal
        title="Create Module Menu"
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 700}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <ModuleMenuForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Menu Modal */}
      <Modal
        title="View Menu Details"
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => {
            setIsViewModalOpen(false);
            setIsEditModalOpen(true);
          }} className="btn-standard-primary">
            Edit
          </Button>,
          <Button 
            key="toggle" 
            type="primary"
            icon={<StopOutlined />} 
            onClick={() => handleToggleStatus(selectedMenu)}
            className="btn-standard-primary"
          >
            {(selectedMenu && (selectedMenu.is_active === 1 || selectedMenu.is_active === true || selectedMenu.is_active === '1')) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={handleDeleteFromView}
            className="btn-standard-primary"
          >
            Delete
          </Button>,
          <Button key="close" onClick={() => handleModalClose(setIsViewModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        {selectedMenu && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Module">
              {getModuleName(selectedMenu.module_id)}
            </Descriptions.Item>
            <Descriptions.Item label="Menu Name">
              {selectedMenu.menu_name}
            </Descriptions.Item>
            <Descriptions.Item label="Menu Path">
              {selectedMenu.menu_path || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Menu Icon">
              {selectedMenu.menu_icon || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Menu Order">
              {selectedMenu.menu_order || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Description">
              {selectedMenu.menu_description || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Parent Menu">
              {selectedMenu.parent_menu_id ? `Menu ID: ${selectedMenu.parent_menu_id}` : 'Top-level menu'}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={checkIsActive(selectedMenu.is_active) ? 'green' : 'red'}>
                {checkIsActive(selectedMenu.is_active) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {selectedMenu.created_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedMenu.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {selectedMenu.modified_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedMenu.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Menu Modal */}
      <Modal
        title="Edit Module Menu"
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 700}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <ModuleMenuForm 
          onSubmit={handleEditSubmit} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={selectedMenu}
        />
      </Modal>
    </div>
  );
};

export default ModuleMenu;

