import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Switch } from 'antd';
import { BankOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import BankForm from '../../common/components/forms/BankForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';

const BankManagement = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedBank, setSelectedBank] = useState(null);
  const [loading, setLoading] = useState(false);
  const [bankData, setBankData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch banks from API with server-side pagination
  const fetchBanks = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getBanks(params);
      if (response.success && response.data) {
        const { items: banks, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || banks.length;
        
        setBankData(banks);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
        console.log(response.data);
      } else {
        message.error(response.message || 'Failed to fetch banks');
        setBankData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching banks:', error);
      message.error('An error occurred while fetching banks');
      setBankData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchBanks(1, pagination.pageSize);
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
      title: 'Bank Name',
      dataIndex: 'bank_name',
      key: 'bank_name',
      searchable: true,
      width: 200,
    },
    {
      title: 'Short Name',
      dataIndex: 'short_name',
      key: 'short_name',
      searchable: true,
      width: 150,
    },
    {
      title: 'Sort Code',
      dataIndex: 'sort_code',
      key: 'sort_code',
      searchable: true,
      width: 120,
    },
    {
      title: 'Bank Code',
      dataIndex: 'bank_code',
      key: 'bank_code',
      searchable: true,
      width: 120,
    },
    {
      title: 'SWIFT Code',
      dataIndex: 'swift_code',
      key: 'swift_code',
      searchable: true,
      width: 150,
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
    setSelectedBank(null);
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedBank(record);
    setIsViewModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedBank(record);
    setIsEditModalOpen(true);
  };

  const deleteBank = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this bank?',
      content: `This will permanently delete the bank "${record.bank_name}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const id = record.id || record.bank_id;
          if (!id) {
            message.error('Bank ID is missing. Cannot delete bank.');
            return;
          }
          const response = await apiService.deleteBank(id);
          if (response.success) {
            message.success({
              content: `Bank "${record.bank_name}" has been deleted successfully.`,
              duration: 3,
            });
            fetchBanks(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete bank');
          }
        } catch (error) {
          console.error('Error deleting bank:', error);
          message.error('An error occurred while deleting bank');
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
      title: `Are you sure you want to ${action} this bank?`,
      content: `This will ${action} the bank "${record.bank_name}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
      okType: newStatus ? 'primary' : 'default',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const id = record.id || record.bank_id;
          if (!id) {
            message.error('Bank ID is missing. Cannot update status.');
            return;
          }
          const response = await apiService.toggleBankStatus(id);
          if (response.success) {
            message.success({
              content: `Bank "${record.bank_name}" has been ${actionPast} successfully.`,
              duration: 3,
            });
            fetchBanks(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to update bank status');
          }
        } catch (error) {
          console.error('Error toggling bank status:', error);
          message.error('An error occurred while updating bank status');
        }
      },
    });
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedBank(null);
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.createBank(formData);
      if (response.success) {
        message.success({
          content: `Bank "${formData.bank_name}" has been created successfully.`,
          duration: 3,
        });
        handleModalClose(setIsCreateModalOpen);
        fetchBanks();
      } else {
        message.error(response.message || 'Failed to create bank');
      }
    } catch (error) {
      console.error('Error creating bank:', error);
      message.error('An error occurred while creating bank');
    }
  };

  const handleEditSubmit = async (formData) => {
    try {
      const id = selectedBank?.id || selectedBank?.bank_id;
      if (!id) {
        message.error('Bank ID is missing. Cannot update bank.');
        return;
      }
      const response = await apiService.updateBank(id, formData);
      if (response.success) {
        message.success({
          content: `Bank "${formData.bank_name}" has been updated successfully.`,
          duration: 3,
        });
        handleModalClose(setIsEditModalOpen);
        fetchBanks();
      } else {
        message.error(response.message || 'Failed to update bank');
      }
    } catch (error) {
      console.error('Error updating bank:', error);
      message.error('An error occurred while updating bank');
    }
  };

  return (
    <div className="bank-management-container">
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
        data={bankData}
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
              fetchBanks(newPage, newPageSize);
            }
          }
        }}
        rightAction={
          <Button
            type="primary"
            icon={<BankOutlined />}
            onClick={showCreateModal}
            size="large"
            className="btn-standard-primary"
          >
            Create Bank
          </Button>
        }
        searchPlaceholder="Search banks..."
        rowKey="id"
        size="middle"
        bordered={true}
      />
      </div>

      {/* Create Bank Modal */}
      <Modal
        title="Create Bank"
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 800}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <BankForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Bank Modal */}
      <Modal
        title="View Bank Details"
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
          <Button 
            key="delete" 
            type="primary"
            icon={<DeleteOutlined />} 
            onClick={() => {
              if (selectedBank) {
                deleteBank(selectedBank);
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
              if (selectedBank) {
                handleToggleStatus(selectedBank);
              }
            }}
            className="btn-standard-primary"
          >
            {(selectedBank && (selectedBank.is_active === 1 || selectedBank.is_active === true)) ? 'Deactivate' : 'Activate'}
          </Button>,
          <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => {
            handleModalClose(setIsViewModalOpen);
            handleEdit(selectedBank);
          }} className="btn-standard-primary">
            Edit
          </Button>,
          <Button key="close" onClick={() => handleModalClose(setIsViewModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 800}
        style={{ top: 20 }}
      >
        {selectedBank && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Bank Name">
              {selectedBank.bank_name}
            </Descriptions.Item>
            <Descriptions.Item label="Short Name">
              {selectedBank.short_name || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Sort Code">
              {selectedBank.sort_code || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Bank Code">
              {selectedBank.bank_code || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="BI Code">
              {selectedBank.bi_code || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="ERP BC">
              {selectedBank.erp_bc || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="ERP BR">
              {selectedBank.erp_br || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Citi Code">
              {selectedBank.citi_code || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="SWIFT Code">
              {selectedBank.swift_code || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={(selectedBank.is_active === 1 || selectedBank.is_active === true) ? 'green' : 'red'}>
                {(selectedBank.is_active === 1 || selectedBank.is_active === true) ? 'Active' : 'Inactive'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created At">
              {selectedBank.created_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Created By">
              {selectedBank.created_by || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified At">
              {selectedBank.modified_at || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Modified By">
              {selectedBank.modified_by || 'N/A'}
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>

      {/* Edit Bank Modal */}
      <Modal
        title="Edit Bank"
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 800}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <BankForm 
          onSubmit={handleEditSubmit} 
          onCancel={() => handleModalClose(setIsEditModalOpen)}
          initialValues={selectedBank}
        />
      </Modal>
    </div>
  );
};

export default BankManagement;

