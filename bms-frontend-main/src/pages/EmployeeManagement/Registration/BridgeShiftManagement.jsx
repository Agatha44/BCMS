import { useEffect, useState } from 'react';
import { Button, Modal, Tag, App, Select, Switch, Descriptions, Tabs, Form } from 'antd';
import { ClockCircleOutlined, PlusOutlined, EyeOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import { DataTable } from '../../../common/data';
import { apiService } from '../../../services/api.jsx';
import {
  extractArrayFromResponse,
  updatePaginationFromResponse,
} from '../../../common/utils/employeeUtils.jsx';
import BridgeShiftForm from '../../../common/components/forms/BridgeShiftForm.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import '../../../styles/common.css';

const CustomStatusToggle = ({ checked, onChange }) => {
  const handleClick = (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (onChange) {
      onChange();
    }
  };

  return (
    <div
      onClick={handleClick}
      style={{
        position: 'relative',
        width: '60px',
        height: '20px',
        backgroundColor: checked ? '#962E32' : '#d9d9d9',
        borderRadius: '11px',
        cursor: 'pointer',
        transition: 'all 0.3s ease',
        display: 'flex',
        alignItems: 'center',
        padding: '0 3px',
        boxSizing: 'border-box',
        userSelect: 'none',
      }}
    >
      <span
        style={{
          position: 'absolute',
          left: checked ? '6px' : 'auto',
          right: checked ? 'auto' : '6px',
          color: '#ffffff',
          fontSize: '10px',
          fontWeight: 500,
          transition: 'all 0.3s ease',
          whiteSpace: 'nowrap',
          zIndex: 1,
          pointerEvents: 'none',
        }}
      >
        {checked ? 'Active' : 'Inactive'}
      </span>
      <div
        style={{
          position: 'absolute',
          right: checked ? '3px' : 'auto',
          left: checked ? 'auto' : '3px',
          width: '18px',
          height: '18px',
          backgroundColor: '#ffffff',
          borderRadius: '50%',
          transition: 'all 0.3s ease',
          boxShadow: '0 2px 4px rgba(0, 0, 0, 0.2)',
          pointerEvents: 'none',
        }}
      />
    </div>
  );
};

const { Option } = Select;

const isActive = (value) => value === 1 || value === true || value === '1';

const BridgeShiftManagement = () => {
  const { message, modal } = App.useApp();

  const [activeTab, setActiveTab] = useState('bridge-shifts');
  const [shifts, setShifts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100'],
  });

  const [search, setSearch] = useState('');
  const [sortField, setSortField] = useState(undefined);
  const [sortOrder, setSortOrder] = useState(undefined);

  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [selectedShift, setSelectedShift] = useState(null);

  const fetchShifts = async (page = 1, pageSize = 15, extraParams = {}) => {
    setLoading(true);
    try {
      const params = {
        search: search || undefined,
        per_page: pageSize,
        page,
        sort_by: sortField,
        sort_order: sortOrder,
        ...extraParams,
      };

      const response = await apiService.getBridgeShifts(params);
      if (response.success && response.data) {
        const { items, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.count || response.total || paginationData?.total || items.length;

        setShifts(items);
        setPagination((prev) => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount),
        }));
      } else {
        message.error(response.message || 'Failed to fetch shifts');
        setShifts([]);
        setPagination((prev) => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching shifts:', error);
      message.error('An error occurred while fetching shifts');
      setShifts([]);
      setPagination((prev) => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchShifts(1, pagination.pageSize);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleRefresh = () => {
    fetchShifts(pagination.current, pagination.pageSize);
  };

  const handleCreateSubmit = async (payload) => {
    try {
      const response = await apiService.createBridgeShift(payload);
      if (response.success) {
        message.success(response.message || 'Shift created successfully');
        setIsCreateModalOpen(false);
        setSelectedShift(null);
        await fetchShifts(pagination.current, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to create shift');
      }
    } catch (error) {
      console.error('Error creating shift:', error);
      message.error('An error occurred while creating shift');
    }
  };

  const handleEditSubmit = async (payload) => {
    if (!selectedShift || !selectedShift.id) {
      message.error('Shift information is missing');
      return;
    }
    try {
      const response = await apiService.updateBridgeShift(selectedShift.id, payload);
      if (response.success) {
        message.success(response.message || 'Shift updated successfully');
        setIsEditModalOpen(false);
        setSelectedShift(null);
        await fetchShifts(pagination.current, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to update shift');
      }
    } catch (error) {
      console.error('Error updating shift:', error);
      message.error('An error occurred while updating shift');
    }
  };

  const handleView = (record) => {
    setSelectedShift(record);
    setIsViewModalOpen(true);
  };

  const handleEditFromView = () => {
    if (!selectedShift) return;
    setIsViewModalOpen(false);
    setIsEditModalOpen(true);
  };

  const handleDelete = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this shift?',
      content: `This will permanently delete the shift "${record.shift_name}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteBridgeShift(record.id);
          if (response.success) {
            message.success({
              content: `Shift "${record.shift_name}" has been deleted successfully.`,
              duration: 3,
            });
            setIsViewModalOpen(false);
            setSelectedShift(null);
            await fetchShifts(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete shift');
          }
        } catch (error) {
          console.error('Error deleting shift:', error);
          message.error('An error occurred while deleting shift');
        }
      },
    });
  };

  const handleToggleStatus = (record) => {
    const currentStatus = isActive(record.is_active);
    const newStatus = !currentStatus;
    const actionText = newStatus ? 'activate' : 'deactivate';

    modal.confirm({
      title: `Are you sure you want to ${actionText} this shift?`,
      content: `This will ${actionText} the shift "${record.shift_name}".`,
      okText: `Yes, ${actionText.charAt(0).toUpperCase() + actionText.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.toggleBridgeShiftStatus(record.id);
          if (response.success) {
            message.success(response.message || 'Shift status updated successfully');
            await fetchShifts(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to update shift status');
          }
        } catch (error) {
          console.error('Error toggling shift status:', error);
          message.error('An error occurred while updating shift status');
        }
      },
    });
  };

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
      title: 'Shift Name',
      dataIndex: 'shift_name',
      key: 'shift_name',
      searchable: true,
    },
    {
      title: 'Start Time',
      dataIndex: 'start_time',
      key: 'start_time',
      width: 120,
      render: (value) => value || 'N/A',
    },
    {
      title: 'End Time',
      dataIndex: 'end_time',
      key: 'end_time',
      width: 120,
      render: (value) => value || 'N/A',
    },
    {
      title: 'Status',
      dataIndex: 'is_active',
      key: 'is_active',
      width: 160,
      align: 'center',
      render: (is_active, record) => {
        const active = isActive(is_active);
        return (
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <CustomStatusToggle
              checked={active}
              onChange={() => handleToggleStatus(record)}
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

  const rightActions = (
    <div style={{ display: 'flex', gap: 8 }}>
      <Select
        value={pagination.pageSize}
        style={{ width: 120 }}
        onChange={(value) => {
          const newSize = Number(value) || 15;
          setPagination((prev) => ({ ...prev, pageSize: newSize, current: 1 }));
          fetchShifts(1, newSize);
        }}
      >
        {['10', '15', '20', '50', '100'].map((size) => (
          <Option key={size} value={Number(size)}>
            {size} / page
          </Option>
        ))}
      </Select>
      <Button
        type="primary"
        icon={<PlusOutlined />}
        onClick={() => {
          setSelectedShift(null);
          setIsCreateModalOpen(true);
        }}
        className="btn-standard-primary"
      >
        New Shift
      </Button>
    </div>
  );

  const [departmentShifts, setDepartmentShifts] = useState([]);
  const [departmentShiftsLoading, setDepartmentShiftsLoading] = useState(false);
  const [departmentShiftsPagination, setDepartmentShiftsPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100'],
  });
  const [isDepartmentShiftModalOpen, setIsDepartmentShiftModalOpen] = useState(false);
  const [isDepartmentShiftViewModalOpen, setIsDepartmentShiftViewModalOpen] = useState(false);
  const [selectedDepartmentShift, setSelectedDepartmentShift] = useState(null);
  const [availableShifts, setAvailableShifts] = useState([]);
  const [availableDepartments, setAvailableDepartments] = useState([]);

  const fetchAvailableShifts = async () => {
    try {
      const response = await apiService.getActiveBridgeShifts();
      
      if (response.success && response.data) {
        const { items } = extractArrayFromResponse(response.data);
        const activeShifts = (items || [])
          .filter(shift => 
            shift && shift.id != null && shift.id !== undefined && shift.id !== '' && shift.shift_name && 
            isActive(shift.is_active)
          )
          .filter((shift, index, self) => 
            index === self.findIndex(s => s.id === shift.id)
          )
          .map(shift => ({
            id: shift.id,
            shift_name: shift.shift_name,
            start_time: shift.start_time,
            end_time: shift.end_time,
            is_active: shift.is_active
          }));
        setAvailableShifts(activeShifts);
      } else {
        setAvailableShifts([]);
        if (response.message) {
          message.warning(response.message);
        }
      }
    } catch (error) {
      console.error('Error fetching active shifts:', error);
      message.error('Failed to load active shifts');
      setAvailableShifts([]);
    }
  };

  const fetchAvailableDepartments = async () => {
    try {
      const response = await apiService.getActiveBmsDepartments();
      if (response.success && Array.isArray(response.data)) {
        const validDepartments = response.data
          .filter(dept => dept && (dept.department_id != null || dept.id != null) && (dept.department_id !== undefined || dept.id !== undefined))
          .filter((dept, index, self) => {
            const deptId = dept.department_id || dept.id;
            return index === self.findIndex(d => (d.department_id || d.id) === deptId);
          })
          .map(dept => ({
            ...dept,
            id: dept.department_id || dept.id,
            department_name: dept.department_name || dept.name
          }));
        setAvailableDepartments(validDepartments);
      }
    } catch (error) {
      console.error('Error fetching departments:', error);
    }
  };

  const fetchDepartmentShifts = async (page = 1, pageSize = 15) => {
    setDepartmentShiftsLoading(true);
    try {
      const params = {
        per_page: pageSize,
        page,
      };
      const response = await apiService.getBridgeShiftDepartments(params);
      if (response.success && response.data) {
        const { items, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.count || response.total || paginationData?.total || items.length;
        const validItems = (items || []).filter(item => item?.id != null && item.id !== '');
        setDepartmentShifts(validItems);
        setDepartmentShiftsPagination((prev) => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount),
        }));
      } else {
        setDepartmentShifts([]);
        setDepartmentShiftsPagination((prev) => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching department shifts:', error);
      message.error('An error occurred while fetching department shifts');
      setDepartmentShifts([]);
      setDepartmentShiftsPagination((prev) => ({ ...prev, total: 0 }));
    } finally {
      setDepartmentShiftsLoading(false);
    }
  };

  useEffect(() => {
    if (activeTab === 'department-shifts') {
      fetchDepartmentShifts(1, departmentShiftsPagination.pageSize);
      fetchAvailableShifts();
      fetchAvailableDepartments();
    }
  }, [activeTab]);

  useEffect(() => {
    if (isDepartmentShiftModalOpen) {
      fetchAvailableShifts();
      fetchAvailableDepartments();
    }
  }, [isDepartmentShiftModalOpen]);

  const handleDepartmentShiftSubmit = async (payload) => {
    try {
      const departmentIds = Array.isArray(payload.department_ids) 
        ? payload.department_ids 
        : (payload.department_id ? [payload.department_id] : []);
      
      if (departmentIds.length > 0) {
        const promises = departmentIds.map(departmentId => {
          const mappingPayload = {
            shift_id: payload.shift_id,
            department_id: departmentId,
            is_active: payload.is_active,
          };
          
          if (selectedDepartmentShift) {
            return apiService.updateBridgeShiftDepartment(selectedDepartmentShift.id, mappingPayload);
          }
          return apiService.createBridgeShiftDepartment(mappingPayload);
        });
        
        const results = await Promise.all(promises);
        const allSuccess = results.every(r => r.success);
        
        if (allSuccess) {
          message.success(
            selectedDepartmentShift 
              ? 'Department shift mapping updated successfully' 
              : `${departmentIds.length} department shift mapping(s) created successfully`
          );
          setIsDepartmentShiftModalOpen(false);
          setSelectedDepartmentShift(null);
          await fetchDepartmentShifts(departmentShiftsPagination.current, departmentShiftsPagination.pageSize);
        } else {
          message.error('Some department shift mappings failed to save');
        }
      } else {
        message.error('Please select at least one department');
      }
    } catch (error) {
      console.error('Error saving department shift:', error);
      message.error('An error occurred while saving department shift');
    }
  };

  const handleViewDepartmentShift = async (record) => {
    // Set the record data immediately and open modal for instant display
    setSelectedDepartmentShift(record);
    setIsDepartmentShiftViewModalOpen(true);
    
    // Optionally fetch additional details in the background without blocking UI
    try {
      const response = await apiService.getBridgeShiftDepartmentById(record.id);
      if (response.success && response.data) {
        // Update with fresh data if available, but don't block the UI
        setSelectedDepartmentShift(response.data);
      }
    } catch (error) {
      console.error('Error fetching department shift details:', error);
      // Keep using the record data that was already set
    }
  };

  const handleEditDepartmentShiftFromView = () => {
    if (!selectedDepartmentShift) return;
    setIsDepartmentShiftViewModalOpen(false);
    setIsDepartmentShiftModalOpen(true);
  };

  const handleDeleteDepartmentShift = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this department shift mapping?',
      content: `This will remove the mapping between "${record.shift_name}" and "${record.department_name || record.department?.name || 'department'}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const response = await apiService.deleteBridgeShiftDepartment(record.id);
          if (response.success) {
            message.success('Department shift mapping deleted successfully');
            setIsDepartmentShiftViewModalOpen(false);
            setSelectedDepartmentShift(null);
            await fetchDepartmentShifts(departmentShiftsPagination.current, departmentShiftsPagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete department shift');
          }
        } catch (error) {
          console.error('Error deleting department shift:', error);
          message.error('An error occurred while deleting department shift');
        }
      },
    });
  };

  const departmentShiftsColumns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => {
        const current = departmentShiftsPagination.current || 1;
        const pageSize = departmentShiftsPagination.pageSize || 15;
        return (current - 1) * pageSize + index + 1;
      },
    },
    {
      title: 'Shift Name',
      dataIndex: 'shift_name',
      key: 'shift_name',
      searchable: true,
    },
    {
      title: 'Department',
      dataIndex: 'department_name',
      key: 'department_name',
      searchable: true,
      render: (_, record) => record.department_name || record.department?.name || 'N/A',
    },
    {
      title: 'Start Time',
      dataIndex: 'start_time',
      key: 'start_time',
      width: 120,
      render: (value) => value || 'N/A',
    },
    {
      title: 'End Time',
      dataIndex: 'end_time',
      key: 'end_time',
      width: 120,
      render: (value) => value || 'N/A',
    },
    {
      title: 'Status',
      dataIndex: 'is_active',
      key: 'is_active',
      width: 160,
      align: 'center',
      render: (is_active, record) => {
        const active = isActive(is_active);
        return (
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <CustomStatusToggle
              checked={active}
              onChange={() => {
                apiService.toggleBridgeShiftDepartmentStatus(record.id).then(response => {
                  if (response.success) {
                    message.success('Status updated successfully');
                    fetchDepartmentShifts(departmentShiftsPagination.current, departmentShiftsPagination.pageSize);
                  } else {
                    message.error(response.message || 'Failed to update status');
                  }
                }).catch(error => {
                  console.error('Error toggling department shift status:', error);
                  message.error('An error occurred while updating status');
                });
              }}
            />
          </div>
        );
      },
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 150,
      align: 'center',
      render: (_, record) => (
        <div style={{ display: 'flex', gap: 8, justifyContent: 'center' }}>
          <Button
            type="primary"
            icon={<EyeOutlined />}
            size="small"
            onClick={() => handleViewDepartmentShift(record)}
            className="btn-standard-primary"
          >
            View
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="role-management-container">
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={[
          {
            key: 'bridge-shifts',
            label: 'Bridge Shifts',
            children: (
              <>
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
        data={shifts}
        loading={false}
        pagination={pagination}
        pageSize={pagination.pageSize}
        showSearch={true}
        showRefresh={true}
        onRefresh={handleRefresh}
        onSearchChange={(value) => {
          setSearch(value);
          fetchShifts(1, pagination.pageSize, { search: value || undefined });
        }}
        rightAction={rightActions}
        searchPlaceholder="Search shifts..."
        rowKey="id"
        size="middle"
        bordered={true}
        onChange={(paginationInfo, _filters, sorter) => {
          if (paginationInfo) {
            const newPage = paginationInfo.current || 1;
            const newPageSize = Number(paginationInfo.pageSize) || 15;

            let extraSort = {};
            if (sorter && sorter.field) {
              const order =
                sorter.order === 'ascend'
                  ? 'asc'
                  : sorter.order === 'descend'
                  ? 'desc'
                  : undefined;
              setSortField(sorter.field);
              setSortOrder(order);
              extraSort = {
                sort_by: sorter.field,
                sort_order: order,
              };
            }

            if (
              newPage !== pagination.current ||
              newPageSize !== pagination.pageSize ||
              extraSort.sort_by
            ) {
              fetchShifts(newPage, newPageSize, extraSort);
            }
          }
        }}
      />
                </div>

      <Modal
        title={
          <span>
            <ClockCircleOutlined /> New Shift
          </span>
        }
        open={isCreateModalOpen}
        onCancel={() => {
          setIsCreateModalOpen(false);
          setSelectedShift(null);
        }}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <BridgeShiftForm
          onSubmit={handleCreateSubmit}
          onCancel={() => {
            setIsCreateModalOpen(false);
            setSelectedShift(null);
          }}
        />
      </Modal>

      <Modal
        title={
          <span>
            <ClockCircleOutlined /> Edit Shift
          </span>
        }
        open={isEditModalOpen}
        onCancel={() => {
          setIsEditModalOpen(false);
          setSelectedShift(null);
        }}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <BridgeShiftForm
          onSubmit={handleEditSubmit}
          onCancel={() => {
            setIsEditModalOpen(false);
            setSelectedShift(null);
          }}
          initialValues={selectedShift}
        />
      </Modal>

      <Modal
        title={
          <span>
            <ClockCircleOutlined /> Shift Details
          </span>
        }
        open={isViewModalOpen}
        onCancel={() => {
          setIsViewModalOpen(false);
          setSelectedShift(null);
        }}
        footer={[
          <Button
            key="delete"
            type="primary"
            danger
            icon={<DeleteOutlined />}
            onClick={() => {
              if (selectedShift) {
                handleDelete(selectedShift);
              }
            }}
            className="btn-standard-primary"
          >
            Delete
          </Button>,
          <Button
            key="edit"
            type="primary"
            icon={<EditOutlined />}
            onClick={handleEditFromView}
            className="btn-standard-primary"
          >
            Edit
          </Button>,
          <Button
            key="close"
            onClick={() => {
              setIsViewModalOpen(false);
              setSelectedShift(null);
            }}
          >
            Close
          </Button>,
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        {selectedShift && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Shift Name">
              {selectedShift.shift_name || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Start Time">
              {selectedShift.start_time || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="End Time">
              {selectedShift.end_time || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag
                color={isActive(selectedShift.is_active) ? 'green' : 'red'}
              >
                {isActive(selectedShift.is_active) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {selectedShift.created_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedShift.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {selectedShift.modified_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedShift.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>
              </>
            ),
          },
          {
            key: 'department-shifts',
            label: 'Department Shifts',
            children: (
              <>
                <div className="relative">
                  {departmentShiftsLoading ? (
                    <div
                      className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
                      style={{ top: '4.75rem' }}
                    >
                      <CollectionLoader />
                    </div>
                  ) : null}
                <DataTable
                  columns={departmentShiftsColumns}
                  data={departmentShifts}
                  loading={false}
                  pagination={departmentShiftsPagination}
                  pageSize={departmentShiftsPagination.pageSize}
                  showSearch={true}
                  showRefresh={true}
                  onRefresh={() => fetchDepartmentShifts(departmentShiftsPagination.current, departmentShiftsPagination.pageSize)}
                  rightAction={
                    <Button
                      type="primary"
                      icon={<PlusOutlined />}
                      onClick={() => {
                        setSelectedDepartmentShift(null);
                        setIsDepartmentShiftModalOpen(true);
                      }}
                      className="btn-standard-primary"
                    >
                      Map Shift to Department
                    </Button>
                  }
                  searchPlaceholder="Search department shifts..."
                  rowKey={(record) => record?.id ?? `row-${record?.shift_id || 'unknown'}-${record?.department_id || 'unknown'}`}
                  size="middle"
                  bordered={true}
                  onChange={(paginationInfo) => {
                    if (paginationInfo) {
                      const newPage = paginationInfo.current || 1;
                      const newPageSize = Number(paginationInfo.pageSize) || 15;
                      if (
                        newPage !== departmentShiftsPagination.current ||
                        newPageSize !== departmentShiftsPagination.pageSize
                      ) {
                        fetchDepartmentShifts(newPage, newPageSize);
                      }
                    }
                  }}
                />
                </div>

                <Modal
                  title={
                    <span>
                      <ClockCircleOutlined /> {selectedDepartmentShift ? 'Edit' : 'Map'} Shift to Department
                    </span>
                  }
                  open={isDepartmentShiftModalOpen}
                  onCancel={() => {
                    setIsDepartmentShiftModalOpen(false);
                    setSelectedDepartmentShift(null);
                  }}
                  footer={null}
                  width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
                  destroyOnHidden={true}
                  style={{ top: 20 }}
                >
                  <Form
                    layout="vertical"
                    preserve={false}
                    onFinish={(values) => {
                      const payload = {
                        shift_id: values.shift_id,
                        department_ids: Array.isArray(values.department_ids) 
                          ? values.department_ids 
                          : (values.department_ids ? [values.department_ids] : []),
                        is_active: values.is_active ? 1 : 0,
                      };
                      handleDepartmentShiftSubmit(payload);
                    }}
                    initialValues={selectedDepartmentShift ? {
                      shift_id: selectedDepartmentShift.shift_id,
                      department_ids: selectedDepartmentShift.department_id 
                        ? [selectedDepartmentShift.department_id] 
                        : (selectedDepartmentShift.department_ids || []),
                      is_active: isActive(selectedDepartmentShift.is_active),
                    } : {
                      department_ids: [],
                      is_active: true,
                    }}
                  >
                    <Form.Item
                      name="shift_id"
                      label="Shift"
                      rules={[{ required: true, message: 'Please select a shift' }]}
                    >
                      <Select
                        placeholder="Select a shift"
                        showSearch
                        loading={availableShifts.length === 0}
                        filterOption={(input, option) =>
                          (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
                        }
                        disabled={!!selectedDepartmentShift}
                        notFoundContent={availableShifts.length === 0 ? 'Loading shifts...' : 'No shifts found'}
                      >
                        {availableShifts
                          .filter(shift => shift?.id)
                          .map((shift, index) => (
                            <Option key={`shift-${shift.id}-${index}`} value={shift.id}>
                              {shift.shift_name} {shift.start_time && shift.end_time ? `(${shift.start_time} - ${shift.end_time})` : ''}
                            </Option>
                          ))}
                      </Select>
                    </Form.Item>

                    <Form.Item
                      name="department_ids"
                      label="Departments"
                      rules={[
                        { required: true, message: 'Please select at least one department' },
                        { type: 'array', min: 1, message: 'Please select at least one department' }
                      ]}
                    >
                      <Select
                        mode="multiple"
                        placeholder="Select one or more departments"
                        showSearch
                        filterOption={(input, option) =>
                          (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
                        }
                        maxTagCount="responsive"
                        allowClear
                      >
                        {availableDepartments
                          .filter(dept => dept?.id)
                          .map((department, index) => (
                            <Option key={`dept-${department.id}-${index}`} value={department.id}>
                              {department.department_name || department.name}
                            </Option>
                          ))}
                      </Select>
                    </Form.Item>

                    <Form.Item
                      name="is_active"
                      label="Status"
                      valuePropName="checked"
                    >
                      <Switch checkedChildren="Active" unCheckedChildren="Inactive" />
                    </Form.Item>

                    <Form.Item style={{ marginBottom: 0, marginTop: 24 }}>
                      <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <Button
                          onClick={() => {
                            setIsDepartmentShiftModalOpen(false);
                            setSelectedDepartmentShift(null);
                          }}
                        >
                          Cancel
                        </Button>
                        <Button
                          type="primary"
                          htmlType="submit"
                          style={{
                            backgroundColor: '#962E32',
                            borderColor: '#962E32',
                          }}
                        >
                          {selectedDepartmentShift ? 'Update' : 'Map'}
                        </Button>
                      </div>
                    </Form.Item>
                  </Form>
                </Modal>

                <Modal
                  title={
                    <span>
                      <ClockCircleOutlined /> Department Shift Details
                    </span>
                  }
                  open={isDepartmentShiftViewModalOpen}
                  onCancel={() => {
                    setIsDepartmentShiftViewModalOpen(false);
                    setSelectedDepartmentShift(null);
                  }}
                  footer={[
                    <Button
                      key="update"
                      type="primary"
                      icon={<EditOutlined />}
                      onClick={handleEditDepartmentShiftFromView}
                      className="btn-standard-primary"
                    >
                      Update
                    </Button>,
                    <Button
                      key="close"
                      onClick={() => {
                        setIsDepartmentShiftViewModalOpen(false);
                        setSelectedDepartmentShift(null);
                      }}
                    >
                      Close
                    </Button>,
                  ]}
                  width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
                  style={{ top: 20 }}
                >
                  {selectedDepartmentShift && (
                    <Descriptions bordered column={1}>
                      <Descriptions.Item label="Shift Name">
                        {selectedDepartmentShift.shift_name || 'N/A'}
                      </Descriptions.Item>
                      <Descriptions.Item label="Department">
                        {selectedDepartmentShift.department_name || selectedDepartmentShift.department?.name || 'N/A'}
                      </Descriptions.Item>
                      <Descriptions.Item label="Start Time">
                        {selectedDepartmentShift.start_time || 'N/A'}
                      </Descriptions.Item>
                      <Descriptions.Item label="End Time">
                        {selectedDepartmentShift.end_time || 'N/A'}
                      </Descriptions.Item>
                      <Descriptions.Item label="Status">
                        <Tag
                          color={isActive(selectedDepartmentShift.is_active) ? 'green' : 'red'}
                        >
                          {isActive(selectedDepartmentShift.is_active) ? 'Active' : 'Inactive'}
                        </Tag>
                      </Descriptions.Item>
                      <Descriptions.Item label="Created At">
                        {selectedDepartmentShift.created_at || 'N/A'}
                      </Descriptions.Item>
                      <Descriptions.Item label="Created By">
                        {selectedDepartmentShift.created_by || 'N/A'}
                      </Descriptions.Item>
                      <Descriptions.Item label="Modified At">
                        {selectedDepartmentShift.modified_at || 'N/A'}
                      </Descriptions.Item>
                      <Descriptions.Item label="Modified By">
                        {selectedDepartmentShift.modified_by || 'N/A'}
                      </Descriptions.Item>
                    </Descriptions>
                  )}
                </Modal>
              </>
            ),
          },
        ]}
      />
    </div>
  );
};

export default BridgeShiftManagement;
