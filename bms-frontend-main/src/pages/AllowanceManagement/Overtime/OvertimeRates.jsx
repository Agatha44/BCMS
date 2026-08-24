import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Tag, App, Row, Col, Card, Switch } from 'antd';
import { DollarOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined, PlusOutlined, CalendarOutlined } from '@ant-design/icons';
import { DataTable } from '../../../common/data';
import OvertimeRateForm from '../../../common/components/forms/OvertimeRateForm.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import { overtimeService } from '../../../services/overtimeService.js';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../../common/utils/employeeUtils.jsx';
import '../../../styles/common.css';

const OvertimeRates = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedOvertimeRate, setSelectedOvertimeRate] = useState(null);
  const [loading, setLoading] = useState(false);
  const [overtimeRateData, setOvertimeRateData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch overtime rates from API with server-side pagination
  const fetchOvertimeRates = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await overtimeService.getOvertimeRates(params);
      if (response.success && response.data) {
        // Extract data and pagination from response
        const { items: dataArray, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || dataArray.length;

        setOvertimeRateData(dataArray);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        console.error('Error fetching overtime rates:', response);
        message.error('Please contact system administrator');
        setOvertimeRateData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching overtime rates:', error);
      message.error('Please contact system administrator');
      setOvertimeRateData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOvertimeRates(1, pagination.pageSize);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Format currency
  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-TZ', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount || 0);
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
      title: 'Educational Level',
      dataIndex: 'educational_level_name',
      key: 'educational_level_name',
      searchable: true,
      width: 200,
      render: (text, record) => text || record.name,
    },
    {
      title: 'Daily Rate (TSh)',
      dataIndex: 'rate',
      key: 'rate',
      searchable: false,
      width: 180,
      align: 'right',
      render: (rate, record) => (
        <span style={{ fontWeight: 500, color: '#000000' }}>
          {formatCurrency(rate || record.daily_rate || record.hourly_rate)}
        </span>
      ),
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
    setSelectedOvertimeRate(null);
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedOvertimeRate(record);
    setIsViewModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedOvertimeRate(record);
    setIsEditModalOpen(true);
  };

  const handleDelete = async (record) => {
    const recordName = record.educational_level_name || record.name;
    modal.confirm({
      title: 'Are you sure you want to delete this overtime rate?',
      content: `This will permanently delete the overtime rate "${recordName}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        setLoading(true);
        try {
          const response = await overtimeService.deleteOvertimeRate(record.id);
          if (response.success) {
            message.success({
              content: `Overtime rate "${recordName}" has been deleted successfully.`,
              duration: 3,
            });
            const currentPage = pagination.current || 1;
            const currentPageSize = pagination.pageSize || 15;
            fetchOvertimeRates(currentPage, currentPageSize);
          } else {
            console.error('Error deleting overtime rate:', response);
            message.error('Please contact system administrator');
          }
        } catch (error) {
          console.error('Error deleting overtime rate:', error);
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
    const recordName = record.educational_level_name || record.name;
    
    modal.confirm({
      title: `Are you sure you want to ${action} this overtime rate?`,
      content: `This will ${action} the overtime rate "${recordName}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        setLoading(true);
        try {
          const response = await overtimeService.toggleOvertimeRateStatus(record.id);
          if (response.success) {
            message.success({
              content: `Overtime rate "${recordName}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            const currentPage = pagination.current || 1;
            const currentPageSize = pagination.pageSize || 15;
            fetchOvertimeRates(currentPage, currentPageSize);
          } else {
            console.error(`Error ${action}ing overtime rate:`, response);
            message.error('Please contact system administrator');
          }
        } catch (error) {
          console.error(`Error ${action}ing overtime rate:`, error);
          message.error('Please contact system administrator');
        } finally {
          setLoading(false);
        }
      },
    });
  };

  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedOvertimeRate(null);
  };

  const handleCreateSubmit = async (formData) => {
    setLoading(true);
    try {
      const response = await overtimeService.createOvertimeRate(formData);
      if (response.success) {
        const recordName = formData.name || formData.educational_level_name;
        message.success({
          content: `Overtime rate "${recordName}" has been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchOvertimeRates();
      } else {
        console.error('Error creating overtime rate:', response);
        message.error('Please contact system administrator');
      }
    } catch (error) {
      console.error('Error creating overtime rate:', error);
      message.error('Please contact system administrator');
    } finally {
      setLoading(false);
    }
  };

  const handleEditSubmit = async (formData) => {
    if (!selectedOvertimeRate || !selectedOvertimeRate.id) {
      message.error('Overtime rate ID is missing');
      return;
    }

    setLoading(true);
    try {
      const response = await overtimeService.updateOvertimeRate(selectedOvertimeRate.id, formData);
      if (response.success) {
        const recordName = formData.name || formData.educational_level_name;
        message.success({
          content: `Overtime rate "${recordName}" has been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchOvertimeRates();
      } else {
        console.error('Error updating overtime rate:', response);
        message.error('Please contact system administrator');
      }
    } catch (error) {
      console.error('Error updating overtime rate:', error);
      message.error('Please contact system administrator');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="overtime-rates-container">
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
          data={overtimeRateData}
          loading={false}
          pagination={pagination}
          pageSize={pagination.pageSize}
          showSearch={true}
          showRefresh={false}
          onChange={(paginationInfo) => {
            if (paginationInfo) {
              const newPage = paginationInfo.current || 1;
              const newPageSize = paginationInfo.pageSize || 15;
              fetchOvertimeRates(newPage, newPageSize);
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
          searchPlaceholder="Search overtime rates..."
          rowKey={(record) => record.id || record.educational_level_id || `${record.educational_level_name || record.name || 'row'}-${record.rate || '0'}`}
          size="middle"
          bordered={true}
        />
      </div>

      {/* Create Overtime Rate Modal */}
      <Modal
        title={
          <span>
            <DollarOutlined style={{ marginRight: 8 }} />
            Create Overtime Rate
          </span>
        }
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <OvertimeRateForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Overtime Rate Modal */}
      <Modal
        title={
          <span>
            <DollarOutlined style={{ marginRight: 8 }} />
            Overtime Rate Details
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
              if (selectedOvertimeRate) {
                handleDelete(selectedOvertimeRate);
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
              if (selectedOvertimeRate) {
                handleToggleStatus(selectedOvertimeRate);
              }
            }}
            className="btn-standard-primary"
          >
            {(selectedOvertimeRate && (selectedOvertimeRate.is_active === 1 || selectedOvertimeRate.is_active === true)) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => {
            handleModalClose(setIsViewModalOpen);
            handleEdit(selectedOvertimeRate);
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
        {selectedOvertimeRate && (
          <div>
            {/* Basic Information Section */}
            <Card
              title={
                <span>
                  <DollarOutlined style={{ marginRight: 8 }} />
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
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Educational Level</div>
                    <div style={{ fontSize: '14px', fontWeight: 500 }}>
                      {selectedOvertimeRate.educational_level_name || selectedOvertimeRate.name}
                    </div>
                  </div>
                </Col>
                <Col span={24}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Daily Rate</div>
                    <div style={{ fontSize: '16px', fontWeight: 600, color: '#962E32' }}>
                      {formatCurrency(selectedOvertimeRate.rate || selectedOvertimeRate.daily_rate || selectedOvertimeRate.hourly_rate)} TSh
                    </div>
                  </div>
                </Col>
                <Col span={24}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Status</div>
                    <div>
                      <Tag color={(selectedOvertimeRate.is_active === 1 || selectedOvertimeRate.is_active === true) ? 'green' : 'red'}>
                        {(selectedOvertimeRate.is_active === 1 || selectedOvertimeRate.is_active === true) ? 'Active' : 'Inactive'}
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
                      {selectedOvertimeRate.created_at || 'N/A'}
                    </div>
                  </div>
                </Col>
                <Col span={12}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Created By</div>
                    <div style={{ fontSize: '14px', fontWeight: 500 }}>
                      {selectedOvertimeRate.created_by || 'N/A'}
                    </div>
                  </div>
                </Col>
                <Col span={12}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Modified At</div>
                    <div style={{ fontSize: '14px', fontWeight: 500 }}>
                      {selectedOvertimeRate.modified_at || 'N/A'}
                    </div>
                  </div>
                </Col>
                <Col span={12}>
                  <div style={{ marginBottom: 8 }}>
                    <div style={{ color: '#8c8c8c', fontSize: '12px', marginBottom: 2 }}>Modified By</div>
                    <div style={{ fontSize: '14px', fontWeight: 500 }}>
                      {selectedOvertimeRate.modified_by || 'N/A'}
                    </div>
                  </div>
                </Col>
              </Row>
            </Card>
          </div>
        )}
      </Modal>

      {/* Edit Overtime Rate Modal */}
      <Modal
        title={
          <span>
            <DollarOutlined style={{ marginRight: 8 }} />
            Edit Overtime Rate
          </span>
        }
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <OvertimeRateForm 
          onSubmit={handleEditSubmit} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={selectedOvertimeRate} 
        />
      </Modal>
    </div>
  );
};

export default OvertimeRates;

