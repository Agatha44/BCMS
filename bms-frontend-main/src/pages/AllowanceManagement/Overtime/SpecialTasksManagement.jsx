import { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';
import { Button, Modal, Tag, Tabs, Space, App, Descriptions } from 'antd';
import { FileTextOutlined, PlusOutlined, EyeOutlined, EditOutlined, DeleteOutlined, CheckCircleOutlined, CloseCircleOutlined, HourglassOutlined, StopOutlined } from '@ant-design/icons';
import { DataTable } from '../../../common/data/index.jsx';
import SpecialTaskForm from '../../../common/components/forms/SpecialTaskForm.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../../services/api.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../../common/utils/employeeUtils.jsx';
import '../../../styles/common.css';
import dayjs from 'dayjs';

const SpecialTasksManagement = () => {
  const { message, modal } = App.useApp();
  const currentUser = useSelector((state) => state.auth.user);

  const [activeTab, setActiveTab] = useState('tasks');
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [isApplicationDetailModalOpen, setIsApplicationDetailModalOpen] = useState(false);
  const [selectedTask, setSelectedTask] = useState(null);
  const [selectedApplication, setSelectedApplication] = useState(null);
  const [loading, setLoading] = useState(false);
  const [tasksData, setTasksData] = useState([]);
  const [applicationsData, setApplicationsData] = useState([]);
  
  const [tasksPagination, setTasksPagination] = useState({
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
    if (activeTab === 'tasks') {
      fetchTasks(tasksPagination.current, tasksPagination.pageSize);
    } else if (activeTab === 'applications') {
      fetchApplications(applicationsPagination.current, applicationsPagination.pageSize);
    }
  }, [activeTab]);

  const fetchTasks = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      // Get current user identifier to filter tasks assigned by this user
      const currentUserIdentifier = currentUser?.username || currentUser?.user_id || currentUser?.id || currentUser?.pfNumber || currentUser?.pf_number;
      
      const params = {
        page: page,
        per_page: pageSize,
        // Filter to show tasks assigned BY the current user (manager-assigned tasks)
        // This ensures reviewers/validators see tasks they assigned to employees
        // Try both assigned_by and created_by in case API uses different field names
        ...(currentUserIdentifier && { created_by: currentUserIdentifier }),
        is_application: false // Only show manager-assigned tasks, not employee applications
      };
      
      const response = await apiService.getSpecialTasks(params);
      if (response.success && response.data) {
        const { items: tasks, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || tasks.length;
        
        setTasksData(tasks || []);
        setTasksPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch tasks');
        setTasksData([]);
      }
    } catch (error) {
      console.error('Error fetching tasks:', error);
      message.error('An error occurred while fetching tasks');
      setTasksData([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchApplications = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize,
        status: 'pending',
        is_application: true
      };
      
      // Applications are returned from the main special-tasks endpoint with filters
      const response = await apiService.getSpecialTasks(params);
      if (response.success && response.data) {
        const { items: applications, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || response.data.total || paginationData?.total || applications.length;
        
        setApplicationsData(applications || []);
        setApplicationsPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch applications');
        setApplicationsData([]);
      }
    } catch (error) {
      console.error('Error fetching applications:', error);
      message.error('An error occurred while fetching applications');
      setApplicationsData([]);
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
        return <Tag color="red" icon={<CloseCircleOutlined />}>Cancelled</Tag>;
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
    setSelectedTask(null);
    setIsCreateModalOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedTask(record);
    setIsEditModalOpen(true);
  };

  const handleView = (record) => {
    setSelectedTask(record);
    setIsDetailModalOpen(true);
  };

  const handleViewApplication = (record) => {
    setSelectedApplication(record);
    setIsApplicationDetailModalOpen(true);
  };

  // Consolidated modal close handler
  const handleModalClose = (setModalState) => {
    setModalState(false);
    setSelectedTask(null);
    setSelectedApplication(null);
  };

  const handleCancel = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to cancel this task?',
      content: `This will cancel the task "${record.taskName || record.task_name}".`,
      okText: 'Yes, Cancel',
      okType: 'danger',
      cancelText: 'No',
      async onOk() {
        try {
          const response = await apiService.updateSpecialTask(record.id, { status: 'cancelled' });
          if (response.success) {
            message.success({
              content: `Task "${record.taskName || record.task_name}" has been cancelled successfully.`,
              duration: 3,
            });
            fetchTasks(tasksPagination.current, tasksPagination.pageSize);
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

  const handleComplete = async (record) => {
    modal.confirm({
      title: 'Are you sure you want to mark this task as completed?',
      content: `This will mark the task "${record.taskName || record.task_name}" as completed.`,
      okText: 'Yes, Complete',
      okType: 'primary',
      cancelText: 'No',
      async onOk() {
        try {
          const response = await apiService.updateSpecialTask(record.id, { status: 'completed' });
          if (response.success) {
            message.success({
              content: `Task "${record.taskName || record.task_name}" has been marked as completed successfully.`,
              duration: 3,
            });
            fetchTasks(tasksPagination.current, tasksPagination.pageSize);
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

  const handleApproveApplication = async (applicationId, comment = '') => {
    try {
      const response = await apiService.approveSpecialTask(applicationId, { comment });
      if (response.success) {
        message.success({
          content: 'Application has been approved successfully.',
          duration: 3,
        });
        fetchApplications(applicationsPagination.current, applicationsPagination.pageSize);
      } else {
        message.error(response.message || 'Failed to approve application');
      }
    } catch (error) {
      console.error('Error approving application:', error);
      message.error('An error occurred while approving application');
    }
  };

  const handleRejectApplication = async (applicationId, comment = '') => {
    if (!comment.trim()) {
      message.warning('Please provide a reason for rejection');
      return;
    }

    modal.confirm({
      title: 'Are you sure you want to reject this application?',
      content: 'This will reject the application and notify the employee.',
      okText: 'Yes, Reject',
      okType: 'danger',
      cancelText: 'No',
      async onOk() {
        try {
          const response = await apiService.rejectSpecialTask(applicationId, { comment });
          if (response.success) {
            message.success({
              content: 'Application has been rejected successfully.',
              duration: 3,
            });
            fetchApplications(applicationsPagination.current, applicationsPagination.pageSize);
          } else {
            message.error(response.message || 'Failed to reject application');
          }
        } catch (error) {
          console.error('Error rejecting application:', error);
          message.error('An error occurred while rejecting application');
        }
      },
    });
  };

  const handleCreateSubmit = async (formData) => {
    try {
      const response = await apiService.createSpecialTask(formData);
      if (response.success) {
        message.success('Special task created successfully');
        setIsCreateModalOpen(false);
        fetchTasks(1, tasksPagination.pageSize);
      } else {
        message.error(response.message || 'Failed to create task');
        throw new Error(response.message || 'Failed to create task');
      }
    } catch (error) {
      console.error('Error creating task:', error);
      throw error;
    }
  };

  const handleEditSubmit = async (formData) => {
    try {
      const response = await apiService.updateSpecialTask(selectedTask.id, formData);
      if (response.success) {
        message.success('Special task updated successfully');
        setIsEditModalOpen(false);
        setSelectedTask(null);
        fetchTasks(tasksPagination.current, tasksPagination.pageSize);
      } else {
        message.error(response.message || 'Failed to update task');
        throw new Error(response.message || 'Failed to update task');
      }
    } catch (error) {
      console.error('Error updating task:', error);
      throw error;
    }
  };

  // Tasks table columns
  const tasksColumns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => {
        const current = tasksPagination.current || 1;
        const pageSize = tasksPagination.pageSize || 15;
        return (current - 1) * pageSize + index + 1;
      },
    },
    {
      title: 'Task Name',
      dataIndex: 'task_name',
      key: 'taskName',
      searchable: true,
      render: (text, record) => record.task_name || record.taskName || 'N/A',
    },
    {
      title: 'Employee',
      key: 'employee',
      render: (_, record) => {
        const employee = record.employee || record.employee_name || 'N/A';
        const pfNumber = record.employeePfNumber || record.employee_pf_number || record.pf_number || '';
        return pfNumber ? `${pfNumber} - ${employee}` : employee;
      },
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
      dataIndex: 'overtimeRule',
      key: 'overtimeRule',
      render: (rule, record) => {
        const ruleValue = rule || record.overtime_rule;
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

  // Applications table columns
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
      key: 'taskName',
      searchable: true,
      render: (text, record) => record.task_name || record.taskName || 'N/A',
    },
    {
      title: 'Employee',
      key: 'employee',
      render: (_, record) => {
        const employee = record.employee || record.employee_name || 'N/A';
        const pfNumber = record.employeePfNumber || record.employee_pf_number || record.pf_number || '';
        return pfNumber ? `${pfNumber} - ${employee}` : employee;
      },
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
      dataIndex: 'overtimeRule',
      key: 'overtimeRule',
      render: (rule) => getOvertimeRuleLabel(rule),
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
          onClick={() => handleViewApplication(record)}
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
          activeTab === 'tasks' ? (
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={handleCreate}
              style={{
                backgroundColor: '#962E32',
                borderColor: '#962E32',
              }}
            >
              Create Task
            </Button>
          ) : null
        }
        items={[
          {
            key: 'tasks',
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
                  columns={tasksColumns}
                  data={tasksData}
                  loading={false}
                  pagination={tasksPagination}
                  pageSize={tasksPagination.pageSize}
                  showSearch={true}
                  showRefresh={true}
                  onRefresh={() => fetchTasks(tasksPagination.current, tasksPagination.pageSize)}
                  searchPlaceholder="Search tasks..."
                  rowKey={(record) => record?.id || `task-${record?.taskName}`}
                  onChange={(paginationInfo) => {
                    if (paginationInfo) {
                      const newPage = paginationInfo.current || 1;
                      const newPageSize = Number(paginationInfo.pageSize) || 15;
                      fetchTasks(newPage, newPageSize);
                    }
                  }}
                />
              </div>
            ),
          },
          {
            key: 'applications',
            label: (
              <span>
                Pending Applications
                {applicationsData.length > 0 && (
                  <Tag color="orange" style={{ marginLeft: 8 }}>
                    {applicationsData.length}
                  </Tag>
                )}
              </span>
            ),
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
                  data={applicationsData}
                  loading={false}
                  pagination={applicationsPagination}
                  pageSize={applicationsPagination.pageSize}
                  showSearch={true}
                  showRefresh={true}
                  onRefresh={() => fetchApplications(applicationsPagination.current, applicationsPagination.pageSize)}
                  searchPlaceholder="Search applications..."
                  rowKey={(record) => record?.id || `app-${record?.taskName}`}
                  onChange={(paginationInfo) => {
                    if (paginationInfo) {
                      const newPage = paginationInfo.current || 1;
                      const newPageSize = Number(paginationInfo.pageSize) || 15;
                      fetchApplications(newPage, newPageSize);
                    }
                  }}
                />
              </div>
            ),
          },
        ]}
      />

      {/* Create Task Modal */}
      <Modal
        title={
          <span>
            <FileTextOutlined style={{ marginRight: 8 }} />
            Create Special Task
          </span>
        }
        open={isCreateModalOpen}
        onCancel={() => {
          setIsCreateModalOpen(false);
          setSelectedTask(null);
        }}
        footer={null}
        destroyOnHidden={true}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        <SpecialTaskForm
          onSubmit={handleCreateSubmit}
          onCancel={() => {
            setIsCreateModalOpen(false);
            setSelectedTask(null);
          }}
        />
      </Modal>

      {/* Edit Task Modal */}
      <Modal
        title={
          <span>
            <FileTextOutlined style={{ marginRight: 8 }} />
            Edit Special Task
          </span>
        }
        open={isEditModalOpen}
        onCancel={() => {
          setIsEditModalOpen(false);
          setSelectedTask(null);
        }}
        footer={null}
        destroyOnHidden={true}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        <SpecialTaskForm
          onSubmit={handleEditSubmit}
          onCancel={() => {
            setIsEditModalOpen(false);
            setSelectedTask(null);
          }}
          initialValues={selectedTask}
        />
      </Modal>

      {/* Task Detail Modal */}
      <Modal
        title="View Task Details"
        open={isDetailModalOpen}
        onCancel={() => handleModalClose(setIsDetailModalOpen)}
        footer={[
          <Button 
            key="edit" 
            type="primary" 
            icon={<EditOutlined />} 
            onClick={() => {
              handleModalClose(setIsDetailModalOpen);
              handleEdit(selectedTask);
            }} 
            className="btn-standard-primary"
          >
            Edit
          </Button>,
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
              {selectedTask.taskName || selectedTask.task_name || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Description">
              {selectedTask.description || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Employee">
              {(() => {
                const employee = selectedTask.employee || selectedTask.employee_name || 'N/A';
                const pfNumber = selectedTask.employeePfNumber || selectedTask.employee_pf_number || selectedTask.pf_number || '';
                return pfNumber ? `${pfNumber} - ${employee}` : employee;
              })()}
            </Descriptions.Item>
            <Descriptions.Item label="Date Range">
              {(() => {
                const startDate = selectedTask.startDate || selectedTask.start_date;
                const endDate = selectedTask.endDate || selectedTask.end_date;
                if (startDate && endDate) {
                  return `${dayjs(startDate).format('DD-MMM-YYYY')} to ${dayjs(endDate).format('DD-MMM-YYYY')}`;
                }
                return 'N/A';
              })()}
            </Descriptions.Item>
            <Descriptions.Item label="Overtime Rule">
              {(() => {
                const rule = selectedTask.overtimeRule || selectedTask.overtime_rule;
                const threshold = selectedTask.customOvertimeThreshold || selectedTask.custom_overtime_threshold;
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
            {selectedTask.notes && (
              <Descriptions.Item label="Notes">
                {selectedTask.notes}
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

      {/* Application Detail Modal */}
      <Modal
        title="View Application Details"
        open={isApplicationDetailModalOpen}
        onCancel={() => handleModalClose(setIsApplicationDetailModalOpen)}
        footer={[
          <Button 
            key="reject" 
            type="primary"
            danger
            icon={<CloseCircleOutlined />} 
            onClick={() => {
              if (selectedApplication) {
                const comment = prompt('Please provide a reason for rejection:');
                if (comment) {
                  handleRejectApplication(selectedApplication.id, comment);
                  handleModalClose(setIsApplicationDetailModalOpen);
                }
              }
            }}
            className="btn-standard-primary"
          >
            Reject
          </Button>,
          <Button 
            key="approve" 
            type="primary"
            icon={<CheckCircleOutlined />} 
            onClick={() => {
              if (selectedApplication) {
                handleApproveApplication(selectedApplication.id);
                handleModalClose(setIsApplicationDetailModalOpen);
              }
            }}
            className="btn-standard-primary"
          >
            Approve
          </Button>,
          <Button key="close" onClick={() => handleModalClose(setIsApplicationDetailModalOpen)}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 600}
        style={{ top: 20 }}
      >
        {selectedApplication && (
          <Descriptions bordered column={1}>
            <Descriptions.Item label="Task Name">
              {selectedApplication.taskName || selectedApplication.task_name || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Description">
              {selectedApplication.description || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Employee">
              {(() => {
                const employee = selectedApplication.employee || selectedApplication.employee_name || 'N/A';
                const pfNumber = selectedApplication.employeePfNumber || selectedApplication.employee_pf_number || selectedApplication.pf_number || '';
                return pfNumber ? `${pfNumber} - ${employee}` : employee;
              })()}
            </Descriptions.Item>
            <Descriptions.Item label="Date Range">
              {(() => {
                const startDate = selectedApplication.startDate || selectedApplication.start_date;
                const endDate = selectedApplication.endDate || selectedApplication.end_date;
                if (startDate && endDate) {
                  return `${dayjs(startDate).format('DD-MMM-YYYY')} to ${dayjs(endDate).format('DD-MMM-YYYY')}`;
                }
                return 'N/A';
              })()}
            </Descriptions.Item>
            <Descriptions.Item label="Overtime Rule">
              {getOvertimeRuleLabel(selectedApplication.overtimeRule || selectedApplication.overtime_rule)}
            </Descriptions.Item>
            <Descriptions.Item label="Justification">
              {selectedApplication.justification || 'N/A'}
            </Descriptions.Item>
            <Descriptions.Item label="Status">
              {getStatusTag(selectedApplication.status)}
            </Descriptions.Item>
            {selectedApplication.notes && (
              <Descriptions.Item label="Notes">
                {selectedApplication.notes}
              </Descriptions.Item>
            )}
            {selectedApplication.created_at && (
              <Descriptions.Item label="Created At">
                {dayjs(selectedApplication.created_at).format('MMMM D, YYYY [at] h:mm A')}
              </Descriptions.Item>
            )}
          </Descriptions>
        )}
      </Modal>
    </div>
  );
};

export default SpecialTasksManagement;

