import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Switch, Tabs } from 'antd';
import { SafetyOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined, GlobalOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import RegionForm from '../../common/components/forms/RegionForm.jsx';
import DistrictForm from '../../common/components/forms/DistrictForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const RegionDistrictManagement = () => {
  const { message, modal } = App.useApp();
  const [activeTab, setActiveTab] = useState('regions');
  
  // Region states
  const [isCreateRegionModalOpen, setIsCreateRegionModalOpen] = useState(false);
  const [isViewRegionModalOpen, setIsViewRegionModalOpen] = useState(false);
  const [isEditRegionModalOpen, setIsEditRegionModalOpen] = useState(false);
  const [selectedRegion, setSelectedRegion] = useState(null);
  const [regionLoading, setRegionLoading] = useState(false);
  const [regionData, setRegionData] = useState([]);
  const [regionPagination, setRegionPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // District states
  const [isCreateDistrictModalOpen, setIsCreateDistrictModalOpen] = useState(false);
  const [isViewDistrictModalOpen, setIsViewDistrictModalOpen] = useState(false);
  const [isEditDistrictModalOpen, setIsEditDistrictModalOpen] = useState(false);
  const [selectedDistrict, setSelectedDistrict] = useState(null);
  const [districtLoading, setDistrictLoading] = useState(false);
  const [districtData, setDistrictData] = useState([]);
  const [districtPagination, setDistrictPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  const [domiciles, setDomiciles] = useState([]); // For district form dropdown (active regions)

  // Fetch regions from API with server-side pagination
  const fetchRegions = async (page = 1, pageSize = 15) => {
    setRegionLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getRegions(params);
      if (response.success && response.data) {
        const { items: regions, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || regions.length;
        
        setRegionData(regions);
        setRegionPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch regions');
        setRegionData([]);
        setRegionPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching regions:', error);
      message.error('An error occurred while fetching regions');
      setRegionData([]);
      setRegionPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setRegionLoading(false);
    }
  };

  // Fetch districts from API with server-side pagination
  const fetchDistricts = async (page = 1, pageSize = 15) => {
    setDistrictLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getDistricts(params);
      if (response.success && response.data) {
        const { items: districts, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || districts.length;
        
        setDistrictData(districts);
        setDistrictPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch districts');
        setDistrictData([]);
        setDistrictPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching districts:', error);
      message.error('An error occurred while fetching districts');
      setDistrictData([]);
      setDistrictPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setDistrictLoading(false);
    }
  };

  // Fetch active domiciles/regions for dropdown (used in district form)
  const fetchActiveDomiciles = async () => {
    try {
      const response = await apiService.getActiveRegions();
      if (response.success && response.data) {
        const { items: activeDomiciles } = extractArrayFromResponse(response.data);
        // Filter to only include active domiciles
        const filteredDomiciles = (activeDomiciles || []).filter(
          domicile => domicile.is_active === 1 || domicile.is_active === true || domicile.is_active === '1'
        );
        setDomiciles(filteredDomiciles);
      }
    } catch (error) {
      console.error('Error fetching active domiciles:', error);
    }
  };

  useEffect(() => {
    if (activeTab === 'regions') {
      fetchRegions(1, regionPagination.pageSize);
    } else if (activeTab === 'districts') {
      fetchDistricts(1, districtPagination.pageSize);
      fetchActiveDomiciles(); // Fetch active domiciles for district form dropdown
    }
  }, [activeTab]);

  // Region table columns
  const regionColumns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => {
        const current = regionPagination.current || 1;
        const pageSize = regionPagination.pageSize || 15;
        return (current - 1) * pageSize + index + 1;
      },
    },
    {
      title: 'Region Name',
      dataIndex: 'name',
      key: 'name',
      searchable: true,
      width: 200,
    },
    {
      title: 'Description',
      dataIndex: 'description',
      key: 'description',
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
        const isActive = is_active === 1 || is_active === true || is_active === '1';
        return (
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px' }}>
            <Switch
              checked={isActive}
              onChange={() => updateRegionStatus(record)}
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
          onClick={() => handleViewRegion(record)}
          className="btn-standard-primary"
        >
          View
        </Button>
      ),
    },
  ];

  // District table columns
  const districtColumns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => {
        const current = districtPagination.current || 1;
        const pageSize = districtPagination.pageSize || 15;
        return (current - 1) * pageSize + index + 1;
      }
    },
    {
      title: 'District Name',
      dataIndex: 'name',
      key: 'name',
      searchable: true,
      width: 200
    },
    {
      title: 'Region',
      dataIndex: 'domicile_name',
      key: 'domicile_name',
      searchable: true,
      width: 200,
      render: (text, record) => record.region?.domicile_name || text || 'N/A'
    },
    {
      title: 'Description',
      dataIndex: 'description',
      key: 'description',
      searchable: true,
      ellipsis: true
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
        const isActive = is_active === 1 || is_active === true || is_active === '1';
        return (
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px' }}>
            <Switch
              checked={isActive}
              onChange={() => updateDistrictStatus(record)}
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
        <Button
          type="primary"
          icon={<EyeOutlined />}
          size="small"
          onClick={() => handleViewDistrict(record)}
          className="btn-standard-primary"
        >
          View
        </Button>
      )
    }
  ];

  // Region handlers
  const showCreateRegionModal = () => {
    setSelectedRegion(null);
    setIsCreateRegionModalOpen(true);
  };

  const handleViewRegion = (record) => {
    setSelectedRegion(record);
    setIsViewRegionModalOpen(true);
  };

  const handleEditRegion = (record) => {
    setSelectedRegion(record);
    setIsEditRegionModalOpen(true);
  };

  const deleteRegion = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this region?',
      content: `This will permanently delete the region "${record.region_name}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteRegion(record.id);
          if (response.success) {
            message.success({
              content: `Region "${record.region_name}" has been deleted successfully.`,
              duration: 3,
            });
            fetchRegions(regionPagination.current, regionPagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete region');
          }
        } catch (error) {
          console.error('Error deleting region:', error);
          message.error('An error occurred while deleting region');
        }
      },
    });
  };

  const updateRegionStatus = async (record) => {
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const newStatus = !currentStatus;
    const action = newStatus ? 'activate' : 'deactivate';
    const actionPast = newStatus ? 'activated' : 'deactivated';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this region?`,
      content: `This will ${action} the region "${record.region_name}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.toggleRegionStatus(record.id);
          if (response.success) {
            message.success({
              content: `Region "${record.region_name}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchRegions(regionPagination.current, regionPagination.pageSize);
          } else {
            message.error(response.message || 'Failed to update region status');
          }
        } catch (error) {
          console.error('Error toggling region status:', error);
          message.error('An error occurred while updating region status');
        }
      },
    });
  };

  const handleRegionModalClose = (setModalState) => {
    setModalState(false);
    setSelectedRegion(null);
  };

  const handleCreateRegionSubmit = async (formData) => {
    try {
      const response = await apiService.createRegion(formData);
      if (response.success) {
        message.success({
          content: `Region "${formData.name || formData.region_name}" has been created successfully.`,
          duration: 3,
        });
        handleRegionModalClose(setIsCreateRegionModalOpen);
        fetchRegions();
      } else {
        message.error(response.message || 'Failed to create region');
      }
    } catch (error) {
      console.error('Error creating region:', error);
      message.error('An error occurred while creating region');
    }
  };

  const handleEditRegionSubmit = async (formData) => {
    try {
      const response = await apiService.updateRegion(selectedRegion.id, formData);
      if (response.success) {
        message.success({
          content: `Region "${formData.name || formData.region_name}" has been updated successfully.`,
          duration: 3,
        });
        handleRegionModalClose(setIsEditRegionModalOpen);
        fetchRegions();
      } else {
        message.error(response.message || 'Failed to update region');
      }
    } catch (error) {
      console.error('Error updating region:', error);
      message.error('An error occurred while updating region');
    }
  };

  // District handlers
  const showCreateDistrictModal = () => {
    setSelectedDistrict(null);
    setIsCreateDistrictModalOpen(true);
  };

  const handleViewDistrict = (record) => {
    setSelectedDistrict(record);
    setIsViewDistrictModalOpen(true);
  };

  const handleEditDistrict = (record) => {
    setSelectedDistrict(record);
    setIsEditDistrictModalOpen(true);
  };

  const deleteDistrict = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this district?',
      content: `This will permanently delete the district "${record.name}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteDistrict(record.id);
          if (response.success) {
            message.success({
              content: `District "${record.name}" has been deleted successfully.`,
              duration: 3,
            });
            fetchDistricts(districtPagination.current, districtPagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete district');
          }
        } catch (error) {
          console.error('Error deleting district:', error);
          message.error('An error occurred while deleting district');
        }
      },
    });
  };

  const updateDistrictStatus = async (record) => {
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const newStatus = !currentStatus;
    const action = newStatus ? 'activate' : 'deactivate';
    const actionPast = newStatus ? 'activated' : 'deactivated';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this district?`,
      content: `This will ${action} the district "${record.name}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.toggleDistrictStatus(record.id);
          if (response.success) {
            message.success({
              content: `District "${record.name}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchDistricts(districtPagination.current, districtPagination.pageSize);
          } else {
            message.error(response.message || 'Failed to update district status');
          }
        } catch (error) {
          console.error('Error toggling district status:', error);
          message.error('An error occurred while updating district status');
        }
      },
    });
  };

  const handleDistrictModalClose = (setModalState) => {
    setModalState(false);
    setSelectedDistrict(null);
  };

  const handleCreateDistrictSubmit = async (formData) => {
    try {
      const response = await apiService.createDistrict(formData);
      if (response.success) {
        message.success({
          content: `District "${formData.name || formData.name}" has been created successfully.`,
          duration: 3,
        });
        handleDistrictModalClose(setIsCreateDistrictModalOpen);
        fetchDistricts();
        fetchActiveDomiciles(); // Refresh domiciles list
      } else {
        message.error(response.message || 'Failed to create district');
      }
    } catch (error) {
      console.error('Error creating district:', error);
      message.error('An error occurred while creating district');
    }
  };

  const handleEditDistrictSubmit = async (formData) => {
    try {
      const response = await apiService.updateDistrict(selectedDistrict.id, formData);
      if (response.success) {
        message.success({
          content: `District "${formData.name || formData.name}" has been updated successfully.`,
          duration: 3,
        });
        handleDistrictModalClose(setIsEditDistrictModalOpen);
        fetchDistricts();
      } else {
        message.error(response.message || 'Failed to update district');
      }
    } catch (error) {
      console.error('Error updating district:', error);
      message.error('An error occurred while updating district');
    }
  };

  return (
    <div className="region-district-management-container">
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={[
          {
            key: 'regions',
            label: 'Regions'
          },
          {
            key: 'districts',
            label: 'Districts'
          }
        ]}
        className="region-district-tabs"
      />

      {/* Regions Tab Content */}
      {activeTab === 'regions' && (
        <div className="relative">
          {regionLoading ? (
            <div
              className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
              style={{ top: '4.75rem' }}
            >
              <CollectionLoader />
            </div>
          ) : null}
        <DataTable
          columns={regionColumns}
          data={regionData}
          loading={false}
          pagination={regionPagination}
          pageSize={regionPagination.pageSize}
          showSearch={true}
          showRefresh={false}
          onChange={(paginationInfo) => {
            if (paginationInfo) {
              const newPage = paginationInfo.current || 1;
              const newPageSize = Number(paginationInfo.pageSize) || 15;

              if (newPage !== regionPagination.current || newPageSize !== regionPagination.pageSize) {
                fetchRegions(newPage, newPageSize);
              }
            }
          }}
          rightAction={
            <Button
              type="primary"
              icon={<GlobalOutlined />}
              onClick={showCreateRegionModal}
              size="large"
              className="btn-standard-primary"
            >
              Create Region
            </Button>
          }
          searchPlaceholder="Search regions..."
          rowKey="id"
          size="middle"
          bordered={true}
        />
        </div>
      )}

      {/* Districts Tab Content */}
      {activeTab === 'districts' && (
        <div className="relative">
          {districtLoading ? (
            <div
              className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
              style={{ top: '4.75rem' }}
            >
              <CollectionLoader />
            </div>
          ) : null}
        <DataTable
          columns={districtColumns}
          data={districtData}
          loading={false}
          pagination={districtPagination}
          pageSize={districtPagination.pageSize}
          showSearch={true}
          showRefresh={false}
          onChange={(paginationInfo) => {
            if (paginationInfo) {
              const newPage = paginationInfo.current || 1;
              const newPageSize = Number(paginationInfo.pageSize) || 15;

              if (newPage !== districtPagination.current || newPageSize !== districtPagination.pageSize) {
                fetchDistricts(newPage, newPageSize);
              }
            }
          }}
          rightAction={
            <Button
              type="primary"
              icon={<GlobalOutlined />}
              onClick={showCreateDistrictModal}
              size="large"
              className="btn-standard-primary"
            >
              Create District
            </Button>
          }
          searchPlaceholder="Search districts..."
          rowKey="id"
          size="middle"
          bordered={true}
        />
        </div>
      )}

      {/* Create Region Modal */}
      <Modal
        title="Create Region"
        open={isCreateRegionModalOpen}
        onCancel={() => handleRegionModalClose(setIsCreateRegionModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <RegionForm onSubmit={handleCreateRegionSubmit} onCancel={() => handleRegionModalClose(setIsCreateRegionModalOpen)} />
      </Modal>

      {/* View Region Modal */}
      <Modal
        title="View Region Details"
        open={isViewRegionModalOpen}
        onCancel={() => handleRegionModalClose(setIsViewRegionModalOpen)}
        footer={[
          <Button
            key="delete"
            type="primary"
            icon={<DeleteOutlined />}
            onClick={() => {
              if (selectedRegion) {
                deleteRegion(selectedRegion);
                handleRegionModalClose(setIsViewRegionModalOpen);
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
              if (selectedRegion) {
                updateRegionStatus(selectedRegion);
              }
            }}
            className="btn-standard-primary"
          >
            {selectedRegion && (selectedRegion.is_active === 1 || selectedRegion.is_active === true) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button
            key="edit"
            type="primary"
            icon={<EditOutlined />}
            onClick={() => {
              handleRegionModalClose(setIsViewRegionModalOpen);
              handleEditRegion(selectedRegion);
            }}
            className="btn-standard-primary"
          >
            Edit
          </Button>,
          <Button key="close" onClick={() => handleRegionModalClose(setIsViewRegionModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        {selectedRegion && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Region Name">{selectedRegion.name}</Descriptions.Item>
            <Descriptions.Item label="Description">{selectedRegion.description || 'N/A'}</Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={selectedRegion.is_active === 1 || selectedRegion.is_active === true ? 'green' : 'red'}>
                {selectedRegion.is_active === 1 || selectedRegion.is_active === true ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">{selectedRegion.created_at || 'N/A'}</Descriptions.Item>
            <Descriptions.Item label="Created By">{selectedRegion.created_by || 'N/A'}</Descriptions.Item>
            <Descriptions.Item label="Modified At">{selectedRegion.modified_at || 'N/A'}</Descriptions.Item>
            <Descriptions.Item label="Modified By">{selectedRegion.modified_by || 'N/A'}</Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Region Modal */}
      <Modal
        title="Edit Region"
        open={isEditRegionModalOpen}
        onCancel={() => handleRegionModalClose(setIsEditRegionModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <RegionForm
          onSubmit={handleEditRegionSubmit}
          onCancel={() => handleRegionModalClose(setIsEditRegionModalOpen)}
          initialValues={selectedRegion}
        />
      </Modal>

      {/* Create District Modal */}
      <Modal
        title="Create District"
        open={isCreateDistrictModalOpen}
        onCancel={() => handleDistrictModalClose(setIsCreateDistrictModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <DistrictForm
          onSubmit={handleCreateDistrictSubmit}
          onCancel={() => handleDistrictModalClose(setIsCreateDistrictModalOpen)}
          domiciles={domiciles}
        />
      </Modal>

      {/* View District Modal */}
      <Modal
        title="View District Details"
        open={isViewDistrictModalOpen}
        onCancel={() => handleDistrictModalClose(setIsViewDistrictModalOpen)}
        footer={[
          <Button
            key="delete"
            type="primary"
            icon={<DeleteOutlined />}
            onClick={() => {
              if (selectedDistrict) {
                deleteDistrict(selectedDistrict);
                handleDistrictModalClose(setIsViewDistrictModalOpen);
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
              if (selectedDistrict) {
                updateDistrictStatus(selectedDistrict);
              }
            }}
            className="btn-standard-primary"
          >
            {selectedDistrict && (selectedDistrict.is_active === 1 || selectedDistrict.is_active === true) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button
            key="edit"
            type="primary"
            icon={<EditOutlined />}
            onClick={() => {
              handleDistrictModalClose(setIsViewDistrictModalOpen);
              handleEditDistrict(selectedDistrict);
            }}
            className="btn-standard-primary"
          >
            Edit
          </Button>,
          <Button key="close" onClick={() => handleDistrictModalClose(setIsViewDistrictModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        {selectedDistrict && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="District Name">{selectedDistrict.name}</Descriptions.Item>
            <Descriptions.Item label="Region">{selectedDistrict.domicile_name}</Descriptions.Item>
            <Descriptions.Item label="Description">{selectedDistrict.description || 'N/A'}</Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={selectedDistrict.is_active === 1 || selectedDistrict.is_active === true ? 'green' : 'red'}>
                {selectedDistrict.is_active === 1 || selectedDistrict.is_active === true ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">{selectedDistrict.created_at || 'N/A'}</Descriptions.Item>
            <Descriptions.Item label="Created By">{selectedDistrict.created_by || 'N/A'}</Descriptions.Item>
            <Descriptions.Item label="Modified At">{selectedDistrict.modified_at || 'N/A'}</Descriptions.Item>
            <Descriptions.Item label="Modified By">{selectedDistrict.modified_by || 'N/A'}</Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit District Modal */}
      <Modal
        title="Edit District"
        open={isEditDistrictModalOpen}
        onCancel={() => handleDistrictModalClose(setIsEditDistrictModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <DistrictForm
          onSubmit={handleEditDistrictSubmit}
          onCancel={() => handleDistrictModalClose(setIsEditDistrictModalOpen)}
          initialValues={selectedDistrict}
          domiciles={domiciles}
        />
      </Modal>
    </div>
  );
};

export default RegionDistrictManagement;

