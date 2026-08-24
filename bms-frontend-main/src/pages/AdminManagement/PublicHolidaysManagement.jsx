import { useState, useEffect } from 'react';
import { Button, Modal, Tag, Space, App, Select, Input, Row, Col, Descriptions, Switch } from 'antd';
import { CalendarOutlined, PlusOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined, CheckCircleOutlined, CloseCircleOutlined, SearchOutlined, ReloadOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data/index.jsx';
import PublicHolidayForm from '../../common/components/forms/PublicHolidayForm.jsx';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';
import dayjs from 'dayjs';

const { Option } = Select;

const PublicHolidaysManagement = () => {
  const { message, modal } = App.useApp();
  const currentYear = new Date().getFullYear();

  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [selectedHoliday, setSelectedHoliday] = useState(null);
  const [loading, setLoading] = useState(false);
  const [holidaysData, setHolidaysData] = useState([]);
  const [filteredData, setFilteredData] = useState([]);
  const [searchText, setSearchText] = useState('');
  const [yearFilter, setYearFilter] = useState(currentYear);
  const [typeFilter, setTypeFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');
  
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  useEffect(() => {
    fetchHolidays(pagination.current, pagination.pageSize);
  }, []);

  useEffect(() => {
    applyFilters();
  }, [holidaysData, searchText, yearFilter, typeFilter, statusFilter]);

  const fetchHolidays = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getPublicHolidays(params);
      if (response.success && response.data) {
        const { items: holidays, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || holidays.length;
        
        setHolidaysData(holidays || []);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch holidays');
        setHolidaysData([]);
      }
    } catch (error) {
      console.error('Error fetching holidays:', error);
      message.error('An error occurred while fetching holidays');
      setHolidaysData([]);
    } finally {
      setLoading(false);
    }
  };

  const applyFilters = () => {
    let filtered = [...holidaysData];

    // Search filter
    if (searchText) {
      const searchLower = searchText.toLowerCase();
      filtered = filtered.filter(holiday => {
        const name = (holiday.holiday_name || holiday.holidayName || '').toLowerCase();
        const description = (holiday.description || '').toLowerCase();
        return name.includes(searchLower) || description.includes(searchLower);
      });
    }

    // Year filter
    if (yearFilter && yearFilter !== 'all') {
      filtered = filtered.filter(holiday => {
        const date = holiday.holidayDate || holiday.holiday_date;
        if (!date) return false;
        return dayjs(date).year() === parseInt(yearFilter);
      });
    }

    // Type filter
    if (typeFilter && typeFilter !== 'all') {
      filtered = filtered.filter(holiday => {
        const type = holiday.holidayType || holiday.holiday_type;
        return type === typeFilter;
      });
    }

    // Status filter
    if (statusFilter && statusFilter !== 'all') {
      filtered = filtered.filter(holiday => {
        const isActive = holiday.isActive !== undefined 
          ? holiday.isActive 
          : (holiday.is_active !== undefined ? holiday.is_active : true);
        return statusFilter === 'active' ? isActive : !isActive;
      });
    }

    setFilteredData(filtered);
  };

  const getStatusTag = (isActive) => {
    return isActive ? (
      <Tag color="green" icon={<CheckCircleOutlined />}>Active</Tag>
    ) : (
      <Tag color="default" icon={<CloseCircleOutlined />}>Inactive</Tag>
    );
  };

  const getTypeTag = (type) => {
    const typeValue = type || 'National';
    const colorMap = {
      'National': 'blue',
      'Regional': 'green',
      'Religious': 'purple',
      'Cultural': 'orange',
      'Other': 'default'
    };
    return <Tag color={colorMap[typeValue] || 'default'}>{typeValue}</Tag>;
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = dayjs(dateString);
    return date.format('MMMM D, YYYY (dddd)');
  };

  const handleCreate = () => {
    setSelectedHoliday(null);
    setIsCreateModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedHoliday(record);
    setIsEditModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedHoliday(record);
    setIsDetailModalOpen(true);
  };

  const handleToggleStatus = async (record) => {
    const currentStatus = record.isActive !== undefined 
      ? record.isActive 
      : (record.is_active !== undefined ? record.is_active : true);
    const newStatus = !currentStatus;
    
    const action = newStatus ? 'activate' : 'deactivate';
    const actionPast = newStatus ? 'activated' : 'deactivated';
    
    modal.confirm({
      title: `Are you sure you want to ${action} this holiday?`,
      content: `This will ${action} the holiday "${record.holiday_name || record.holidayName}". ${!newStatus ? 'This holiday will no longer affect overtime calculations.' : ''}`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.togglePublicHolidayStatus(record.id);
          if (response.success) {
            message.success({
              content: `Holiday "${record.holiday_name || record.holidayName}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchHolidays(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || `Failed to ${action} holiday`);
          }
        } catch (error) {
          console.error(`Error ${action}ing holiday:`, error);
          message.error('An error occurred while updating holiday status');
        }
      },
    });
  };

  const handleDelete = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this holiday?',
      content: `This will permanently delete the holiday "${record.holiday_name || record.holidayName}". Historical records will remain.`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deletePublicHoliday(record.id);
          if (response.success) {
            message.success({
              content: `Holiday "${record.holiday_name || record.holidayName}" has been deleted successfully.`,
              duration: 3,
            });
            fetchHolidays(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete holiday');
          }
        } catch (error) {
          console.error('Error deleting holiday:', error);
          message.error('An error occurred while deleting holiday');
        }
      },
    });
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedHoliday(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.createPublicHoliday(formData);
      if (response.success) {
        message.success({
          content: `Holiday "${formData.holiday_name}" has been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchHolidays(1, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to create holiday');
        throw new Error(response.message || 'Failed to create holiday');
      }
    } catch (error) {
      console.error('Error creating holiday:', error);
      throw error;
    }
  };

  const handleEditSubmit = async (formData) => {
    try {
      const response = await apiService.updatePublicHoliday(selectedHoliday.id, formData);
      if (response.success) {
        message.success({
          content: `Holiday "${formData.holiday_name}" has been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchHolidays(pagination.current, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to update holiday');
        throw new Error(response.message || 'Failed to update holiday');
      }
    } catch (error) {
      console.error('Error updating holiday:', error);
      throw error;
    }
  };

  // Table columns
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
      title: 'Holiday Name',
      dataIndex: 'holiday_name',
      key: 'holiday_name',
      searchable: true,
      render: (text, record) => record.holiday_name || record.holidayName || 'N/A',
    },
    {
      title: 'Date',
      key: 'date',
      searchable: false,
      render: (_, record) => {
        const date = record.holidayDate || record.holiday_date;
        return date ? formatDate(date) : 'N/A';
      },
    },
    {
      title: 'Type',
      dataIndex: 'holidayType',
      key: 'holidayType',
      searchable: false,
      render: (type, record) => getTypeTag(type || record.holiday_type),
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
      onFilter: (value, record) => {
        const isActive = record.isActive !== undefined 
          ? record.isActive 
          : (record.is_active !== undefined ? record.is_active : true);
        return value === 1 ? isActive : !isActive;
      },
      render: (_, record) => {
        const isActive = record.isActive !== undefined 
          ? record.isActive 
          : (record.is_active !== undefined ? record.is_active : true);
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

  // Generate year options (current year ± 5 years)
  const yearOptions = [];
  for (let i = currentYear - 5; i <= currentYear + 5; i++) {
    yearOptions.push(i);
  }

  return (
    <div style={{ padding: '24px' }}>
      <div style={{ marginBottom: 24, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 8 }}>
          <CalendarOutlined />
          Public Holidays Management
        </h1>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={handleCreate}
          style={{
            backgroundColor: '#962E32',
            borderColor: '#962E32',
          }}
        >
          Create Holiday
        </Button>
      </div>

      {/* Filters */}
      <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
        <Col xs={24} sm={12} md={6} lg={6}>
          <Input
            placeholder="Search holidays..."
            prefix={<SearchOutlined />}
            value={searchText}
            onChange={(e) => setSearchText(e.target.value)}
            allowClear
          />
        </Col>
        <Col xs={24} sm={12} md={6} lg={6}>
          <Select
            placeholder="Filter by Year"
            style={{ width: '100%' }}
            value={yearFilter}
            onChange={setYearFilter}
          >
            <Option value="all">All Years</Option>
            {yearOptions.map(year => (
              <Option key={year} value={year}>{year}</Option>
            ))}
          </Select>
        </Col>
        <Col xs={24} sm={12} md={6} lg={6}>
          <Select
            placeholder="Filter by Type"
            style={{ width: '100%' }}
            value={typeFilter}
            onChange={setTypeFilter}
          >
            <Option value="all">All Types</Option>
            <Option value="National">National</Option>
            <Option value="Regional">Regional</Option>
            <Option value="Religious">Religious</Option>
            <Option value="Cultural">Cultural</Option>
            <Option value="Other">Other</Option>
          </Select>
        </Col>
        <Col xs={24} sm={12} md={6} lg={6}>
          <Select
            placeholder="Filter by Status"
            style={{ width: '100%' }}
            value={statusFilter}
            onChange={setStatusFilter}
          >
            <Option value="all">All Status</Option>
            <Option value="active">Active</Option>
            <Option value="inactive">Inactive</Option>
          </Select>
        </Col>
      </Row>

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
          data={filteredData}
          loading={false}
          pagination={{
            ...pagination,
            total: filteredData.length,
          }}
          pageSize={pagination.pageSize}
          showSearch={false}
          showRefresh={true}
          onRefresh={() => fetchHolidays(pagination.current, pagination.pageSize)}
          rowKey={(record) => record?.id || `holiday-${record?.holiday_name || record?.holidayName}`}
          onChange={(paginationInfo) => {
            if (paginationInfo) {
              const newPage = paginationInfo.current || 1;
              const newPageSize = Number(paginationInfo.pageSize) || 15;
              fetchHolidays(newPage, newPageSize);
            }
          }}
        />
      </div>

      {/* Create Holiday Modal */}
      <Modal
        title="Create Public Holiday"
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <PublicHolidayForm
          onSubmit={handleCreateSubmit}
          onCancel={() => handleModalClose(setIsCreateModalOpen)}
        />
      </Modal>

      {/* Edit Holiday Modal */}
      <Modal
        title="Edit Public Holiday"
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <PublicHolidayForm
          onSubmit={handleEditSubmit}
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={selectedHoliday}
        />
      </Modal>

      {/* View Holiday Modal */}
      <Modal
        title="View Holiday Details"
        open={isDetailModalOpen}
        onCancel={() => handleModalClose(setIsDetailModalOpen)}
        footer={[
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={() => {
              if (selectedHoliday) {
                handleDelete(selectedHoliday);
                handleModalClose(setIsDetailModalOpen);
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
              if (selectedHoliday) {
                handleToggleStatus(selectedHoliday);
              }
            }}
            className="btn-standard-primary"
          >
            {(selectedHoliday && (selectedHoliday.is_active === 1 || selectedHoliday.is_active === true || selectedHoliday.isActive === true || selectedHoliday.isActive === 1)) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button 
            key="edit" 
            type="primary" 
            icon={<EditOutlined />} 
            onClick={() => {
              handleModalClose(setIsDetailModalOpen);
              handleEdit(selectedHoliday);
            }} 
            className="btn-standard-primary"
          >
            Edit
          </Button>,
          <Button key="close" onClick={() => handleModalClose(setIsDetailModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        {selectedHoliday && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Holiday Name">
              {selectedHoliday.holiday_name || selectedHoliday.holidayName || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Date">
              {formatDate(selectedHoliday.holiday_date || selectedHoliday.holidayDate)}
            </Descriptions.Item>
            <Descriptions.Item label="Type">
              {getTypeTag(selectedHoliday.holiday_type || selectedHoliday.holidayType)}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={((selectedHoliday.is_active === 1 || selectedHoliday.is_active === true || selectedHoliday.isActive === true || selectedHoliday.isActive === 1)) ? 'green' : 'red'}>
                {((selectedHoliday.is_active === 1 || selectedHoliday.is_active === true || selectedHoliday.isActive === true || selectedHoliday.isActive === 1)) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Description">
              {selectedHoliday.description || 'N/A'}
            </Descriptions.Item>
            {selectedHoliday.created_at && (
              <Descriptions.Item label="Created At">
                {dayjs(selectedHoliday.created_at).format('MMMM D, YYYY [at] h:mm A')}
              </Descriptions.Item>
            )}
            {selectedHoliday.created_by && (
              <Descriptions.Item label="Created By">
                {selectedHoliday.created_by || 'N/A'}
              </Descriptions.Item>
            )}
            {selectedHoliday.updated_at && (
              <Descriptions.Item label="Modified At">
                {dayjs(selectedHoliday.updated_at).format('MMMM D, YYYY [at] h:mm A')}
              </Descriptions.Item>
            )}
            {selectedHoliday.updated_by && (
              <Descriptions.Item label="Modified By">
                {selectedHoliday.updated_by || 'N/A'}
              </Descriptions.Item>
            )}
          </Descriptions>
        )}
      </Modal>
    </div>
  );
};

export default PublicHolidaysManagement;

