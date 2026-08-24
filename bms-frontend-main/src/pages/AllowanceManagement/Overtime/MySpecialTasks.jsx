import { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';
import { Button, Modal, Tag, Tabs, Space, App, Descriptions } from 'antd';
import { FileTextOutlined, PlusOutlined, EyeOutlined, CheckCircleOutlined, HourglassOutlined, CloseCircleOutlined } from '@ant-design/icons';
import { DataTable } from '../../../common/data/index.jsx';
import SpecialTaskForm from '../../../common/components/forms/SpecialTaskForm.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../../common/utils/employeeUtils.jsx';
import '../../../styles/common.css';
import dayjs from 'dayjs';

const MySpecialTasks = () => {
  const { message, modal } = App.useApp();
  const currentUser = useSelector((state) => state.auth.user);
  const employeePfNumber = currentUser?.pfNumber || currentUser?.pf_number || currentUser?.pfno || currentUser?.pf_no;

  const [activeTab, setActiveTab] = useState('assigned');
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [selectedTask, setSelectedTask] = useState(null);
  const [loading, setLoading] = useState(false);
  const [assignedTasksData, setAssignedTasksData] = useState([]);
  const [myApplicationsData, setMyApplicationsData] = useState([]);
  
  const [assignedPagination, setAssignedPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  const [applicationsPagination, setApplicationsPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  useEffect(() => {
    if (activeTab === 'assigned') {
      fetchAssignedTasks(assignedPagination.current, assignedPagination.pageSize);
    } else if (activeTab === 'applications') {
      fetchMyApplications(applicationsPagination.current, applicationsPagination.pageSize);
    }
  }, [activeTab]);

  const fetchAssignedTasks = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize,
        status: 'active'
      };
      
      const response = await apiService.getMySpecialTasks(params);
      if (response.success && response.data) {
        const { items: tasks, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || tasks.length;
        
        // Filter to only show assigned tasks (not applications)
        const assigned = (tasks || []).filter(task => 
          task.status !== 'pending' || !task.is_application
        );
        
        setAssignedTasksData(assigned);
        setAssignedPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch assigned tasks');
        setAssignedTasksData([]);
      }
    } catch (error) {
      console.error('Error fetching assigned tasks:', error);
      message.error('An error occurred while fetching assigned tasks');
      setAssignedTasksData([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchMyApplications = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize,
        employee_pf_number: employeePfNumber,
        is_application: true
      };
      
      // Applications are returned from the main special-tasks endpoint with filters
      const response = await apiService.getSpecialTasks(params);
      if (response.success && response.data) {
        const { items: applications, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || applications.length;
        
        setMyApplicationsData(applications || []);
        setApplicationsPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch applications');
        setMyApplicationsData([]);
      }
    } catch (error) {
      console.error('Error fetching applications:', error);
      message.error('An error occurred while fetching applications');
      setMyApplicationsData([]);
    } finally {
      setLoading(false);
    }
  };

  const getStatusTag = (status) => {
    const statusLower = (status || '').toLowerCase();
    switch (statusLower) {
      case 'active':
        return <Tag color="green" icon={<CheckCircleOutlined />}>Active</Tag>;
      case 'pending':
        return <Tag color="orange" icon={<HourglassOutlined />}>Pending</Tag>;
      case 'completed':
        return <Tag color="default">Completed</Tag>;
      case 'cancelled':
      case 'rejected':
        return <Tag color="red" icon={<CloseCircleOutlined />}>
          {statusLower === 'rejected' ? 'Rejected' : 'Cancelled'}
        </Tag>;
      default:
        return <Tag>{status || 'Unknown'}</Tag>;
    }
  };

  const getOvertimeRuleLabel = (rule) => {
    switch (rule) {
      case 'all_hours':
        return 'All Hours';
      case 'standard':
        return 'Standard (9-hour rule)';
      case 'custom':
        return 'Custom';
      default:
        return rule || 'N/A';
    }
  };

  const handleCreate = () => {
    setIsCreateModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedTask(record);
    setIsDetailModalOpen(true);
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedTask(null);
  };

  const handleComplete = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to mark this task as completed?',
      content: `This will mark the task "${record.task_name || record.taskName}" as completed.`,
      okText: 'Yes, Complete',
      okType: 'primary',
      cancelText: 'No',
      async onOk() {
        try {
          const response = await apiService.updateSpecialTask(record.id, { status: 'completed' });
          if (response.success) {
            message.success({
              content: `Task "${record.task_name || record.taskName}" has been marked as completed successfully.`,
              duration: 3,
            });
            if (activeTab === 'assigned') {
              fetchAssignedTasks(assignedPagination.current, assignedPagination.pageSize);
            }
            handleModalClose(setIsDetailModalOpen);
          } else {
            message.error(response.message || 'Failed to complete task');
          }
        } catch (error) {
          console.error('Error completing task:', error);
          message.error('An error occurred while completing task');
        }
      },
    });
  };

  const handleCancel = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to cancel this task?',
      content: `This will cancel the task "${record.task_name || record.taskName}".`,
      okText: 'Yes, Cancel',
      okType: 'danger',
      cancelText: 'No',
      async onOk() {
        try {
          const response = await apiService.updateSpecialTask(record.id, { status: 'cancelled' });
          if (response.success) {
            message.success({
              content: `Task "${record.task_name || record.taskName}" has been cancelled successfully.`,
              duration: 3,
            });
            if (activeTab === 'assigned') {
              fetchAssignedTasks(assignedPagination.current, assignedPagination.pageSize);
            }
            handleModalClose(setIsDetailModalOpen);
          } else {
            message.error(response.message || 'Failed to cancel task');
          }
        } catch (error) {
          console.error('Error cancelling task:', error);
          message.error('An error occurred while cancelling task');
        }
      },
    });
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.createSpecialTaskApplication(formData);
      if (response.success) {
        message.success('Application submitted successfully. Waiting for manager approval.');
        setIsCreateModalOpen(false);
        fetchMyApplications(1, applicationsPagination.pageSize);
      } else {
        message.error(response.message || 'Failed to submit application');
        throw new Error(response.message || 'Failed to submit application');
      }
    } catch (error) {
      console.error('Error creating application:', error);
      throw error;
    }
  };

  // Assigned tasks columns
  const assignedTasksColumns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => {
        const current = assignedPagination.current || 1;
        const pageSize = assignedPagination.pageSize || 15;
        return (current - 1) * pageSize + index + 1;
      },
    },
    {
      title: 'Task Name',
      dataIndex: 'task_name',
      key: 'task_name',
      searchable: true,
      render: (text, record) => record.task_name || record.taskName || 'N/A',
    },
    {
      title: 'Description',
      dataIndex: 'description',
      key: 'description',
      render: (text) => text ? (text.length > 50 ? `${text.substring(0, 50)}...` : text) : 'N/A',
    },
    {
      title: 'Date Range',
      key: 'dateRange',
      render: (_, record) => {
        const startDate = record.startDate || record.start_date;
        const endDate = record.endDate || record.end_date;
        if (startDate && endDate) {
          return `${dayjs(startDate).format('DD-MMM-YYYY')} to ${dayjs(endDate).format('DD-MMM-YYYY')}`;
        }
        return 'N/A';
      },
    },
    {
      title: 'Overtime Rule',
      dataIndex: 'overtime_rule',
      key: 'overtimeRule',
      render: (rule, record) => {
        const ruleValue = rule || record.overtimeRule || record.overtime_rule;
        const threshold = record.customOvertimeThreshold || record.custom_overtime_threshold;
        let label = getOvertimeRuleLabel(ruleValue);
        if (ruleValue === 'custom' && threshold) {
          label += ` (${threshold} hrs)`;
        }
        return label;
      },
    },
    {
      title: 'Pre-Approved',
      key: 'preApproved',
      render: (_, record) => {
        const isPreApproved = record.isPreApproved || record.is_pre_approved;
        return isPreApproved ? (
          <Tag color="green" icon={<CheckCircleOutlined />}>Yes</Tag>
        ) : (
          <Tag>No</Tag>
        );
      },
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      render: (status) => getStatusTag(status),
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

  // My applications columns
  const applicationsColumns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => {
        const current = applicationsPagination.current || 1;
        const pageSize = applicationsPagination.pageSize || 15;
        return (current - 1) * pageSize + index + 1;
      },
    },
    {
      title: 'Task Name',
      dataIndex: 'task_name',
      key: 'task_name',
      searchable: true,
      render: (text, record) => record.task_name || record.taskName || 'N/A',
    },
    {
      title: 'Date Range',
      key: 'dateRange',
      render: (_, record) => {
        const startDate = record.startDate || record.start_date;
        const endDate = record.endDate || record.end_date;
        if (startDate && endDate) {
          return `${dayjs(startDate).format('DD-MMM-YYYY')} to ${dayjs(endDate).format('DD-MMM-YYYY')}`;
        }
        return 'N/A';
      },
    },
    {
      title: 'Overtime Rule',
      dataIndex: 'overtime_rule',
      key: 'overtimeRule',
      render: (rule, record) => {
        const ruleValue = rule || record.overtimeRule || record.overtime_rule;
        const threshold = record.customOvertimeThreshold || record.custom_overtime_threshold;
        let label = getOvertimeRuleLabel(ruleValue);
        if (ruleValue === 'custom' && threshold) {
          label += ` (${threshold} hrs)`;
        }
        return label;
      },
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      render: (status) => getStatusTag(status),
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

  return (
    <div style={{ padding: '24px' }}>
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        tabBarExtraContent={
          activeTab === 'applications' ? (
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={handleCreate}
              className="btn-standard-primary"
            >
              Apply for Special Task
            </Button>
          ) : null
        }
        items={[
          {
            key: 'assigned',
            label: 'Assigned Tasks',
            children: (
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
                  columns={assignedTasksColumns}
                  data={assignedTasksData}
                  loading={false}
                  pagination={assignedPagination}
                  pageSize={assignedPagination.pageSize}
                  showSearch={true}
                  showRefresh={true}
                  onRefresh={() => fetchAssignedTasks(assignedPagination.current, assignedPagination.pageSize)}
                  searchPlaceholder="Search tasks..."
                  rowKey={(record) => record?.id || `task-${record?.task_name || record?.taskName}`}
                  onChange={(paginationInfo) => {
                    if (paginationInfo) {
                      const newPage = paginationInfo.current || 1;
                      const newPageSize = Number(paginationInfo.pageSize) || 15;
                      fetchAssignedTasks(newPage, newPageSize);
                    }
                  }}
                />
              </div>
            ),
          },
          {
            key: 'applications',
            label: 'My Applications',
            children: (
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
                  columns={applicationsColumns}
                  data={myApplicationsData}
                  loading={false}
                  pagination={applicationsPagination}
                  pageSize={applicationsPagination.pageSize}
                  showSearch={true}
                  showRefresh={true}
                  onRefresh={() => fetchMyApplications(applicationsPagination.current, applicationsPagination.pageSize)}
                  searchPlaceholder="Search applications..."
                  rowKey={(record) => record?.id || `app-${record?.task_name || record?.taskName}`}
                  onChange={(paginationInfo) => {
                    if (paginationInfo) {
                      const newPage = paginationInfo.current || 1;
                      const newPageSize = Number(paginationInfo.pageSize) || 15;
                      fetchMyApplications(newPage, newPageSize);
                    }
                  }}
                />
              </div>
            ),
          },
        ]}
      />

      {/* Create Application Modal */}
      <Modal
        title={
          <span>
            <FileTextOutlined style={{ marginRight: 8 }} />
            Apply for Special Task
          </span>
        }
        open={isCreateModalOpen}
        onCancel={() => setIsCreateModalOpen(false)}
        footer={null}
        destroyOnHidden={true}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        <SpecialTaskForm
          onSubmit={handleCreateSubmit}
          onCancel={() => setIsCreateModalOpen(false)}
          isApplication={true}
        />
      </Modal>

      {/* Detail Modal */}
      <Modal
        title="View Task Details"
        open={isDetailModalOpen}
        onCancel={() => handleModalClose(setIsDetailModalOpen)}
        footer={[
          <Button 
            key="cancel" 
            type="primary"
            icon={<CloseCircleOutlined />} 
            onClick={() => {
              if (selectedTask) {
                handleCancel(selectedTask);
              }
            }}
            className="btn-standard-primary"
            disabled={!selectedTask || (selectedTask.status || '').toLowerCase() !== 'active'}
          >
            Cancel Task
          </Button>,
          <Button 
            key="complete" 
            type="primary"
            icon={<CheckCircleOutlined />} 
            onClick={() => {
              if (selectedTask) {
                handleComplete(selectedTask);
              }
            }}
            className="btn-standard-primary"
            disabled={!selectedTask || (selectedTask.status || '').toLowerCase() !== 'active'}
          >
            Complete
          </Button>,
          <Button key="close" onClick={() => handleModalClose(setIsDetailModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        {selectedTask && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Task Name">
              {selectedTask.task_name || selectedTask.taskName || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Description">
              {selectedTask.description || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Date Range">
              {(() => {
                const startDate = selectedTask.start_date || selectedTask.startDate;
                const endDate = selectedTask.end_date || selectedTask.endDate;
                if (startDate && endDate) {
                  return `${dayjs(startDate).format('DD-MMM-YYYY')} to ${dayjs(endDate).format('DD-MMM-YYYY')}`;
                }
                return 'N/A';
              })()}
            </Descriptions.Item>
            <Descriptions.Item label="Overtime Rule">
              {(() => {
                const rule = selectedTask.overtime_rule || selectedTask.overtimeRule;
                const threshold = selectedTask.custom_overtime_threshold || selectedTask.customOvertimeThreshold;
                let label = getOvertimeRuleLabel(rule);
                if (rule === 'custom' && threshold) {
                  label += ` (${threshold} hrs)`;
                }
                return label;
              })()}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              {getStatusTag(selectedTask.status)}
            </Descriptions.Item>
            {(selectedTask.is_pre_approved || selectedTask.isPreApproved) && (
              <Descriptions.Item label="Pre-Approved">
                <Tag color="green">Yes</Tag>
              </Descriptions.Item>
            )}
            {selectedTask.justification && (
              <Descriptions.Item label="Justification">
                {selectedTask.justification}
              </Descriptions.Item>
            )}
            {selectedTask.notes && (
              <Descriptions.Item label="Notes">
                {selectedTask.notes}
              </Descriptions.Item>
            )}
            {selectedTask.manager_comment && (
              <Descriptions.Item label="Manager Comment">
                {selectedTask.manager_comment}
              </Descriptions.Item>
            )}
            {selectedTask.created_at && (
              <Descriptions.Item label="Created At">
                {dayjs(selectedTask.created_at).format('MMMM D, YYYY [at] h:mm A')}
              </Descriptions.Item>
            )}
            {selectedTask.updated_at && (
              <Descriptions.Item label="Modified At">
                {dayjs(selectedTask.updated_at).format('MMMM D, YYYY [at] h:mm A')}
              </Descriptions.Item>
            )}
          </Descriptions>
        )}
      </Modal>
    </div>
  );
};

export default MySpecialTasks;

