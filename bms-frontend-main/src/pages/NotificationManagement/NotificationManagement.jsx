import { useState, useEffect } from 'react';
import { Button, Modal, Tooltip, Descriptions, Tag, App, Switch, Badge, Space } from 'antd';
import { BellOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined, PlusOutlined, CheckCircleOutlined } from '@ant-design/icons';
import { DataTable } from '../../common/data';
import NotificationForm from '../../common/components/forms/NotificationForm.jsx';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../common/utils/employeeUtils.jsx';
import '../../styles/common.css';
import dayjs from 'dayjs';

const NotificationManagement = () => {
  const { message, modal } = App.useApp();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedNotification, setSelectedNotification] = useState(null);
  const [loading, setLoading] = useState(false);
  const [notificationData, setNotificationData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch notifications from API with server-side pagination
  const fetchNotifications = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getNotifications(params);
      if (response.success && response.data) {
        const { items: notifications, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || notifications.length;
        
        setNotificationData(notifications);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch notifications');
        setNotificationData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching notifications:', error);
      message.error('An error occurred while fetching notifications');
      setNotificationData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchNotifications(1, pagination.pageSize);
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
      title: 'Title',
      dataIndex: 'title',
      key: 'title',
      searchable: true,
      width: 250,
      render: (text, record) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          {text || record.notification_title || 'N/A'}
          {record.is_read === 0 && (
            <Badge dot color="#ff4d4f" />
          )}
        </div>
      ),
    },
    {
      title: 'Message',
      dataIndex: 'message',
      key: 'message',
      searchable: true,
      ellipsis: true,
      width: 300,
      render: (text, record) => {
        const messageText = text || record.notification_message || record.message || 'N/A';
        return (
          <Tooltip title={messageText}>
            <span>{messageText.length > 50 ? `${messageText.substring(0, 50)}...` : messageText}</span>
          </Tooltip>
        );
      },
    },
    {
      title: 'Type',
      dataIndex: 'type',
      key: 'type',
      searchable: false,
      width: 120,
      align: 'center',
      filters: [
        { text: 'Info', value: 'info' },
        { text: 'Warning', value: 'warning' },
        { text: 'Error', value: 'error' },
        { text: 'Success', value: 'success' },
      ],
      onFilter: (value, record) => {
        const notificationType = record.type || record.notification_type || 'info';
        return notificationType.toLowerCase() === value.toLowerCase();
      },
      render: (type, record) => {
        const notificationType = type || record.notification_type || 'info';
        const typeColors = {
          info: 'blue',
          warning: 'orange',
          error: 'red',
          success: 'green',
        };
        return (
          <Tag color={typeColors[notificationType.toLowerCase()] || 'default'}>
            {notificationType.toUpperCase()}
          </Tag>
        );
      },
    },
    {
      title: 'Status',
      dataIndex: 'is_read',
      key: 'is_read',
      searchable: false,
      width: 120,
      align: 'center',
      filters: [
        { text: 'Read', value: 1 },
        { text: 'Unread', value: 0 },
      ],
      onFilter: (value, record) => {
        const isRead = record.is_read === 1 || record.is_read === true || record.is_read === '1';
        return value === 1 ? isRead : !isRead;
      },
      render: (is_read, record) => {
        const isRead = is_read === 1 || is_read === true || is_read === '1';
        return (
          <Tag color={isRead ? 'green' : 'red'} icon={isRead ? <CheckCircleOutlined /> : null}>
            {isRead ? 'Read' : 'Unread'}
          </Tag>
        );
      },
    },
    {
      title: 'Created Date',
      dataIndex: 'created_at',
      key: 'created_at',
      searchable: false,
      width: 180,
      render: (date, record) => {
        const dateValue = date || record.createdAt || record.created_at;
        return dateValue ? dayjs(dateValue).format('DD-MMM-YYYY HH:mm') : 'N/A';
      },
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 150,
      align: 'center',
      render: (_, record) => (
        <Space>
          <Button 
            type="primary"
            icon={<EyeOutlined />} 
            size="small"
            onClick={() => handleView(record)}
            className="btn-standard-primary"
          >
            View
          </Button>
          <Button 
            type="default"
            icon={<EditOutlined />} 
            size="small"
            onClick={() => handleEdit(record)}
          >
            Edit
          </Button>
        </Space>
      ),
    },
  ];

  const showCreateModal = () => {
    setSelectedNotification(null);
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedNotification(record);
    setIsViewModalOpen(true);
    // Mark as read when viewing
    if (record.is_read === 0) {
      handleMarkAsRead(record.id || record.notification_id);
    }
  };

  const handleEdit = (record) => {
    setSelectedNotification(record);
    setIsEditModalOpen(true);
  };

  const handleMarkAsRead = async (id) => {
    try {
      const response = await apiService.markNotificationAsRead(id);
      if (response.success) {
        fetchNotifications(pagination.current, pagination.pageSize);
      }
    } catch (error) {
      console.error('Error marking notification as read:', error);
    }
  };

  const handleToggleStatus = async (record) => {
    try {
      const id = record.id || record.notification_id;
      if (!id) {
        message.error('Notification ID is missing. Cannot toggle status.');
        return;
      }
      const response = await apiService.toggleNotificationStatus(id);
      if (response.success) {
        message.success('Notification status updated successfully');
        fetchNotifications(pagination.current, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to update notification status');
      }
    } catch (error) {
      console.error('Error toggling notification status:', error);
      message.error('An error occurred while updating notification status');
    }
  };

  const deleteNotification = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to delete this notification?',
      content: `This will permanently delete the notification "${record.title || record.notification_title}".`,
      okText: 'Yes, Delete',
      okType: 'danger',
      cancelText: 'Cancel',
      async onOk() {
        try {
          const id = record.id || record.notification_id;
          if (!id) {
            message.error('Notification ID is missing. Cannot delete notification.');
            return;
          }
          const response = await apiService.deleteNotification(id);
          if (response.success) {
            message.success({
              content: `Notification "${record.title || record.notification_title}" has been deleted successfully.`,
              duration: 3,
            });
            fetchNotifications(pagination.current, pagination.pageSize);
          } else {
            message.error(response.message || 'Failed to delete notification');
          }
        } catch (error) {
          console.error('Error deleting notification:', error);
          message.error('An error occurred while deleting notification');
        }
      },
    });
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.createNotification(formData);
      if (response.success) {
        message.success('Notification created successfully');
        setIsCreateModalOpen(false);
        setSelectedNotification(null);
        fetchNotifications(1, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to create notification');
      }
    } catch (error) {
      console.error('Error creating notification:', error);
      message.error('An error occurred while creating notification');
    }
  };

  const handleEditSubmit = async (formData) => {
    try {
      const id = selectedNotification.id || selectedNotification.notification_id;
      if (!id) {
        message.error('Notification ID is missing. Cannot update notification.');
        return;
      }
      const response = await apiService.updateNotification(id, formData);
      if (response.success) {
        message.success('Notification updated successfully');
        setIsEditModalOpen(false);
        setSelectedNotification(null);
        fetchNotifications(pagination.current, pagination.pageSize);
      } else {
        message.error(response.message || 'Failed to update notification');
      }
    } catch (error) {
      console.error('Error updating notification:', error);
      message.error('An error occurred while updating notification');
    }
  };

  const handleEditFromView = () => {
    setIsViewModalOpen(false);
    setIsEditModalOpen(true);
  };

  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedNotification(null);
  };

  return (
    <div className="notification-management-container">
      {/* DataTable */}
      <DataTable
        columns={columns}
        data={notificationData}
        loading={loading}
        pagination={pagination}
        pageSize={pagination.pageSize}
        showSearch={true}
        showRefresh={false}
        onChange={(paginationInfo) => {
          if (paginationInfo) {
            const newPage = paginationInfo.current || 1;
            const newPageSize = Number(paginationInfo.pageSize) || 15;
            
            if (newPage !== pagination.current || newPageSize !== pagination.pageSize) {
              fetchNotifications(newPage, newPageSize);
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
            Create Notification
          </Button>
        }
        searchPlaceholder="Search notifications..."
        rowKey="id"
        size="middle"
        bordered={true}
      />

      {/* Create Notification Modal */}
      <Modal
        title={
          <span>
            <BellOutlined style={{ marginRight: 8 }} />
            Create Notification
          </span>
        }
        open={isCreateModalOpen}
        onCancel={() => handleModalClose(setIsCreateModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <NotificationForm 
          onSubmit={handleCreateSubmit} 
          onCancel={() => handleModalClose(setIsCreateModalOpen)} 
        />
      </Modal>

      {/* View Notification Modal */}
      <Modal
        title={
          <span>
            <BellOutlined style={{ marginRight: 8 }} />
            Notification Details
          </span>
        }
        open={isViewModalOpen}
        onCancel={() => handleModalClose(setIsViewModalOpen)}
        footer={[
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
            key="delete" 
            type="primary"
            danger
            icon={<DeleteOutlined />} 
            onClick={() => deleteNotification(selectedNotification)}
          >
            Delete
          </Button>,
          <Button key="close" onClick={() => handleModalClose(setIsViewModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 700}
        style={{ top: 10 }}
        styles={{ body: { padding: '16px', maxHeight: 'calc(100vh - 100px)', overflowY: 'auto' } }}
      >
        {selectedNotification && (
          <Descriptions bordered column={1} size="small">
            <Descriptions.Item label="Title">
              {selectedNotification.title || selectedNotification.notification_title || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Message">
              {selectedNotification.message || selectedNotification.notification_message || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Type">
              <Tag color={
                (selectedNotification.type || selectedNotification.notification_type || 'info') === 'info' ? 'blue' :
                (selectedNotification.type || selectedNotification.notification_type || 'info') === 'warning' ? 'orange' :
                (selectedNotification.type || selectedNotification.notification_type || 'info') === 'error' ? 'red' : 'green'
              }>
                {(selectedNotification.type || selectedNotification.notification_type || 'info').toUpperCase()}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              <Tag color={
                (selectedNotification.is_read === 1 || selectedNotification.is_read === true || selectedNotification.is_read === '1') ? 'green' : 'red'
              }>
                {(selectedNotification.is_read === 1 || selectedNotification.is_read === true || selectedNotification.is_read === '1') ? 'Read' : 'Unread'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Created Date">
              {selectedNotification.created_at || selectedNotification.createdAt 
                ? dayjs(selectedNotification.created_at || selectedNotification.createdAt).format('DD-MMM-YYYY HH:mm:ss')
                : 'N/A'}
            </Descriptions.Item>
            {selectedNotification.updated_at || selectedNotification.updatedAt ? (
              <Descriptions.Item label="Updated Date">
                {dayjs(selectedNotification.updated_at || selectedNotification.updatedAt).format('DD-MMM-YYYY HH:mm:ss')}
              </Descriptions.Item>
            ) : null}
          </Descriptions>
        )}
      </Modal>

      {/* Edit Notification Modal */}
      <Modal
        title={
          <span>
            <BellOutlined style={{ marginRight: 8 }} />
            Edit Notification
          </span>
        }
        open={isEditModalOpen}
        onCancel={() => handleModalClose(setIsEditModalOpen)}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <NotificationForm 
          initialValues={selectedNotification}
          onSubmit={handleEditSubmit} 
          onCancel={() => handleModalClose(setIsEditModalOpen)} 
        />
      </Modal>
    </div>
  );
};

export default NotificationManagement;

