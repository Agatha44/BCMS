import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Tag, App, Row, Col, Card, Switch } from 'antd';
import { BookOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined, PlusOutlined, CalendarOutlined } from '@ant-design/icons';
import { DataTable } from '../../../common/data';
import EducationalLevelForm from '../../../common/components/forms/EducationalLevelForm.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../../common/utils/employeeUtils.jsx';
import '../../../styles/common.css';

const EducationalLevel = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedEducationalLevel, setSelectedEducationalLevel] = useState(null);
  const [loading, setLoading] = useState(false);
  const [educationalLevelData, setEducationalLevelData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch educational levels from API with server-side pagination
  const fetchEducationalLevels = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getEducationalLevels(params);

      console.log('Full API Response:', response);
      if (response.success && response.data) {
        // Extract data and pagination from response
        const { items: dataArray, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || dataArray.length;
        
        console.log('Processed data array:', dataArray);
        console.log('Data array length:', dataArray.length);
        
        // Ensure each record has a unique key for React
        const dataWithKeys = dataArray.map((record, index) => ({
          ...record,
          _uniqueKey: record.id || record.educational_level_id || `edu-level-${index}-${record.level_name || record.name || 'unknown'}-${record.created_at || ''}`,
        }));
        
        setEducationalLevelData(dataWithKeys);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        console.error('Error fetching educational levels:', response);
        message.error('Please contact system administrator');
        setEducationalLevelData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching educational levels:', error);
      message.error('Please contact system administrator');
      setEducationalLevelData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchEducationalLevels(1, pagination.pageSize);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

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
      title: 'Educational Level Name',
      dataIndex: 'level_name',
      key: 'level_name',
      searchable: true,
      width: 250,
      render: (text, record) => text || record.name,
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
    setSelectedEducationalLevel(null);
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedEducationalLevel(record);
    setIsViewModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedEducationalLevel(record);
    setIsEditModalOpen(true);
  };

  const handleDelete = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this educational level?',
      content: `This will permanently delete the educational level "${record.level_name || record.name}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        setLoading(true);
        try {
          const response = await apiService.deleteEducationalLevel(record.id);
          if (response.success) {
            message.success({
              content: `Educational level "${record.level_name || record.name}" has been deleted successfully.`,
              duration: 3,
            });
            const currentPage = pagination.current || 1;
            const currentPageSize = pagination.pageSize || 15;
            fetchEducationalLevels(currentPage, currentPageSize);
          } else {
            console.error('Error deleting educational level:', response);
            message.error('Please contact system administrator');
          }
        } catch (error) {
          console.error('Error deleting educational level:', error);
          message.error('Please contact system administrator');
        } finally {
          setLoading(false);
        }
      },
    });
  };

  const handleToggleStatus = async (record) => {
    const currentStatus = record.is_active === 1 || record.is_active === true || record.is_active === '1';
    const newStatus = !currentStatus;
    const action = newStatus ? 'activate' : 'deactivate';
    const actionPast = newStatus ? 'activated' : 'deactivated';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this educational level?`,
      content: `This will ${action} the educational level "${record.level_name || record.name}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        setLoading(true);
        try {
          const response = await apiService.toggleEducationalLevelStatus(record.id);
          if (response.success) {
            message.success({
              content: `Educational level "${record.level_name || record.name}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            const currentPage = pagination.current || 1;
            const currentPageSize = pagination.pageSize || 15;
            fetchEducationalLevels(currentPage, currentPageSize);
          } else {
            console.error(`Error ${action}ing educational level:`, response);
            message.error('Please contact system administrator');
          }
        } catch (error) {
          console.error(`Error ${action}ing educational level:`, error);
          message.error('Please contact system administrator');
        } finally {
          setLoading(false);
        }
      },
    });
  };

  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedEducationalLevel(null);
  };

  const handleCreateSubmit = async (formData) => {
    setLoading(true);
    try {
      const response = await apiService.createEducationalLevel(formData);
      if (response.success) {
        message.success({
          content: `Educational level "${formData.level_name || formData.name}" has been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchEducationalLevels();
      } else {
        console.error('Error creating educational level:', response);
        message.error('Please contact system administrator');
      }
    } catch (error) {
      console.error('Error creating educational level:', error);
      message.error('Please contact system administrator');
    } finally {
      setLoading(false);
    }
  };

  const handleEditSubmit = async (formData) => {
    if (!selectedEducationalLevel || !selectedEducationalLevel.id) {
      message.error('Educational level ID is missing');
      return;
    }

    setLoading(true);
    try {
      const response = await apiService.updateEducationalLevel(selectedEducationalLevel.id, formData);
      if (response.success) {
        message.success({
          content: `Educational level "${formData.level_name || formData.name}" has been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchEducationalLevels();
      } else {
        console.error('Error updating educational level:', response);
        message.error('Please contact system administrator');
      }
    } catch (error) {
      console.error('Error updating educational level:', error);
      message.error('Please contact system administrator');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="educational-level-container">
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
          data={educationalLevelData}
          loading={false}
          pagination={pagination}
          pageSize={pagination.pageSize}
          showSearch={true}
          showRefresh={false}
          onChange={(paginationInfo) => {
            if (paginationInfo) {
              const newPage = paginationInfo.current || 1;
              const newPageSize = paginationInfo.pageSize || 15;
              fetchEducationalLevels(newPage, newPageSize);
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
              Add
            </Button>
          }
          searchPlaceholder="Search educational levels..."
          rowKey={(record) => record._uniqueKey || record.id || record.educational_level_id || record.level_name || record.name || `edu-${Date.now()}`}
          size="middle"
          bordered={true}
        />
      </div>

      {/* Create Educational Level Modal */}
      <Modal
        title={
          <span>
            <BookOutlined style={{ marginRight: 8 }} />
            Create Educational Level
          </span>
        }
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <EducationalLevelForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Educational Level Modal */}
      <Modal
        title={
          <span>
            <BookOutlined style={{ marginRight: 8 }} />
            Educational Level Details
          </span>
        }
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={() => {
              if (selectedEducationalLevel) {
                handleDelete(selectedEducationalLevel);
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
              if (selectedEducationalLevel) {
                handleToggleStatus(selectedEducationalLevel);
              }
            }}
            className="btn-standard-primary"
          >
            {(selectedEducationalLevel && (selectedEducationalLevel.is_active === 1 || selectedEducationalLevel.is_active === true)) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => {
            handleModalClose(setIsViewModalOpen);
            handleEdit(selectedEducationalLevel);
          }} className="btn-standard-primary">
            Edit
          </Button>,
          <Button key="close" onClick={() => handleModalClose(setIsViewModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 700}
        style={{ top: 10 }}
        styles={{ body: { padding: '16px', maxHeight: 'calc(100vh - 100px)', overflowY: 'auto' } }}
      >
        {selectedEducationalLevel && (
          <div>
            {/* Basic Information Section */}
            <Card
              title={
                <span>
                  <BookOutlined style={{ marginRight: 8 }} />
                  Basic Information
                </span>
              }
              size="small"
              style={{ marginBottom: 12 }}
              styles={{ body: { padding: '12px' } }}
            >
              <Row gutter={[16, 8]}>
                <Col span={24}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Educational Level Name</div>
                    <div style={{ fontSize: '14px', fontWeight: 500 }}>{selectedEducationalLevel.level_name || selectedEducationalLevel.name}</div>
                  </div>
                </Col>
                <Col span={24}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Status</div>
                    <div>
                      <Tag color={(selectedEducationalLevel.is_active === 1 || selectedEducationalLevel.is_active === true) ? 'green' : 'red'}>
                        {(selectedEducationalLevel.is_active === 1 || selectedEducationalLevel.is_active === true) ? 'Active' : 'Inactive'}
                      </Tag>
                    </div>
                  </div>
                </Col>
              </Row>
            </Card>

            {/* Audit Information Section */}
            <Card
              title={
                <span>
                  <CalendarOutlined style={{ marginRight: 8 }} />
                  Audit Information
                </span>
              }
              size="small"
              styles={{ body: { padding: '12px' } }}
            >
              <Row gutter={[16, 8]}>
                <Col span={12}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Created At</div>
                    <div style={{ fontSize: '14px', fontWeight: 500 }}>
                      {selectedEducationalLevel.created_at}
                    </div>
                  </div>
                </Col>
                <Col span={12}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Created By</div>
                    <div style={{ fontSize: '14px', fontWeight: 500 }}>
                      {selectedEducationalLevel.created_by}
                    </div>
                  </div>
                </Col>
                <Col span={12}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Modified At</div>
                    <div style={{ fontSize: '14px', fontWeight: 500 }}>
                      {selectedEducationalLevel.modified_at}
                    </div>
                  </div>
                </Col>
                <Col span={12}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Modified By</div>
                    <div style={{ fontSize: '14px', fontWeight: 500 }}>
                      {selectedEducationalLevel.modified_by}
                    </div>
                  </div>
                </Col>
              </Row>
            </Card>
          </div>
        )}
      </Modal>

      {/* Edit Educational Level Modal */}
      <Modal
        title={
          <span>
            <BookOutlined style={{ marginRight: 8 }} />
            Edit Educational Level
          </span>
        }
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <EducationalLevelForm 
          onSubmit={handleEditSubmit} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={selectedEducationalLevel} 
        />
      </Modal>
    </div>
  );
};

export default EducationalLevel;

