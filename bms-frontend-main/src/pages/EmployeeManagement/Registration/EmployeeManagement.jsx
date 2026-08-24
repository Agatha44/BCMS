import { useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { useLocation, useNavigate } from 'react-router-dom';
import { Button, Modal, Tag, Row, Col, Card, App, Tabs, Input } from 'antd';
import { UserAddOutlined, EyeOutlined, EditOutlined, DeleteOutlined, StopOutlined, MailOutlined, PhoneOutlined, CalendarOutlined, IdcardOutlined, CheckCircleOutlined, CloseCircleOutlined, HourglassOutlined, FileTextOutlined } from '@ant-design/icons';
import { DataTable } from '../../../common/data';
import UserRegistrationForm from '../../../common/components/forms/UserRegistrationForm.jsx';
import { apiService } from '../../../services/api.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import {
  extractArrayFromResponse,
  formatEmployeeName,
  getEmployeeStatus,
  isPendingApproval,
  isPendingTermination,
  updatePaginationFromResponse,
  mapReferralRequestToEmployee,
  hasEmployeeRegistrarRole,
  hasEmployeeApproverRole
} from '../../../common/utils/employeeUtils.jsx';
import './EmployeeManagement.css';
import '../../../styles/common.css';

const EmployeeManagement = () => {
  const { message, modal } = App.useApp();
  const location = useLocation();
  const navigate = useNavigate();
  const currentUser = useSelector((state) => state.auth.user);
  const selectedRole = useSelector((state) => state.app.selectedRole);
  
  // Check if we're on the manage-employee route (hide register button)
  const isManageEmployeeRoute = location.pathname === '/employee-management/manage-employee';
  
  const { TextArea } = Input;
  
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedEmployee, setSelectedEmployee] = useState(null);
  const [loading, setLoading] = useState(false);
  const [employeeData, setEmployeeData] = useState([]);
  const [activeTab, setActiveTab] = useState('all');
  const [showCommentField, setShowCommentField] = useState(false);
  const [pendingActionType, setPendingActionType] = useState(null);
  const [commentText, setCommentText] = useState('');
  const [approvalLoading, setApprovalLoading] = useState(false);
  const [referralRequestHistory, setReferralRequestHistory] = useState([]);
  const [loadingHistory, setLoadingHistory] = useState(false);
  const [documentsMinutesTab, setDocumentsMinutesTab] = useState('minutes');
  const [pagination, setPagination] = useState({ 
    current: 1, 
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });
  const [pendingEmployeeData, setPendingEmployeeData] = useState([]);
  const [pendingLoading, setPendingLoading] = useState(false);
  const [pendingPagination, setPendingPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });
  const [approvedEmployeeData, setApprovedEmployeeData] = useState([]);
  const [approvedLoading, setApprovedLoading] = useState(false);
  const [approvedPagination, setApprovedPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Fetch employees with server-side pagination
  const fetchRegisteredEmployee = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await apiService.getActiveBridgeEmployees(params);
      
      if (response.success && response.data) {
        const { items: employees, pagination: paginationData } = extractArrayFromResponse(response.data);
        setEmployeeData(employees);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, employees.length)
        }));
      } else {
        message.error(response.message || 'Failed to fetch Employee');
        setEmployeeData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      message.error('An error occurred while fetching Employees');
      setEmployeeData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  // Fetch approved employee referral requests
  const fetchApprovedEmployeeRequests = async (page = 1, pageSize = 15) => {
    setApprovedLoading(true);
    try {
      const params = {
        module_code: 'EMPLOYMENT_MANAGEMENT',
        status: 'APPROVED',
        page: page,
        per_page: pageSize
      };

      const response = await apiService.getReferralRequests(params);

      if (response.success && response.data) {
        const { items: requests, pagination: paginationData } = extractArrayFromResponse(response.data);

        // Map referral requests to employee-like rows
        // For approved requests, fetch actual employee data to get PF number
        const mappedEmployeesPromises = requests.map(async (req) => {
          const employee = req.employee || req.employee_data || {};
          const nationalId = req.national_id || employee.national_id;

          // Try to fetch actual employee record for PF number
          let actualEmployeeData = null;
          if (nationalId) {
            try {
              const employeeResponse = await apiService.getBridgeEmployeeById(nationalId);
              if (employeeResponse.success && employeeResponse.data) {
                actualEmployeeData = employeeResponse.data.employees?.length > 0 
                  ? employeeResponse.data.employees[0] 
                  : employeeResponse.data;
              }
            } catch (error) {
              console.warn(`Failed to fetch employee data for ${nationalId}:`, error);
            }
          }

          const finalEmployee = actualEmployeeData || employee;
          const mapped = mapReferralRequestToEmployee(req, finalEmployee);
          
          return {
            ...mapped,
            employee_status: 'approved',
            status: 'APPROVED'
          };
        });

        const mappedEmployees = await Promise.all(mappedEmployeesPromises);
        setApprovedEmployeeData(mappedEmployees);

        const totalCount = response.data.count || paginationData.total || mappedEmployees.length;
        setApprovedPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        const errorMsg = response.message || 'Failed to fetch approved employee requests';
        console.error('API Error:', errorMsg, response);
        setApprovedEmployeeData([]);
        setApprovedPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching approved employee referral requests:', error);
      setApprovedEmployeeData([]);
      setApprovedPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setApprovedLoading(false);
    }
  };

  // Fetch pending employee referral requests (maker-checker)
  const fetchPendingEmployeeRequests = async (page = 1, pageSize = 15) => {
    setPendingLoading(true);
    try {
      const params = {
        module_code: 'EMPLOYMENT_MANAGEMENT',
        page: page,
        per_page: pageSize
      };

      const response = await apiService.getPendingReferralRequests(params);


      if (response.success && response.data) {
        const { items: requests, pagination: paginationData } = extractArrayFromResponse(response.data);
        const mappedEmployees = requests.map((req) => mapReferralRequestToEmployee(req, req.employee || req.employee_data || {}));
        
        setPendingEmployeeData(mappedEmployees);
        const totalCount = response.data.count || paginationData.total || mappedEmployees.length;
        setPendingPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      }
      else {
        const errorMsg = response.message || 'Failed to fetch pending employee requests';
        console.error('API Error:', errorMsg, response);
        setPendingEmployeeData([]);
        setPendingPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching pending employee referral requests:', error);
      setPendingEmployeeData([]);
      setPendingPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setPendingLoading(false);
    }
  };

  useEffect(() => {
    fetchRegisteredEmployee(1, 15);
    // Preload pending employee requests so the Pending Approval tab and badge are accurate
    fetchPendingEmployeeRequests(1, 15);
    // Preload approved employee requests
    fetchApprovedEmployeeRequests(1, 15);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Refetch data when switching tabs
  useEffect(() => {
    if (activeTab === 'all') {
      fetchRegisteredEmployee(pagination.current, pagination.pageSize);
    } else if (activeTab === 'pending') {
      fetchPendingEmployeeRequests(pendingPagination.current, pendingPagination.pageSize);
    } else if (activeTab === 'approved') {
      fetchApprovedEmployeeRequests(approvedPagination.current, approvedPagination.pageSize);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab]);

  // Helper function to check if status is active
  const isActiveStatus = (value) => {
    return value === 1 || value === '1' || value === true;
  };

  // Helper function to check if user can approve (checker role)
  const canApprove = () => {
    return true; // Bypassed - any user can approve
  };

  // Filter employees based on active tab
  const getFilteredEmployees = () => {
    if (activeTab === 'all') {
      // Show all employees from bridge-employees endpoint
      return employeeData;
    }
    if (activeTab === 'pending') {
      // For Pending tab, use data coming from referral-requests/pending endpoint
      return pendingEmployeeData;
    }
    if (activeTab === 'approved') {
      // For Approved tab, use data coming from referral-requests with APPROVED status
      return approvedEmployeeData;
    }
    if (activeTab === 'terminated') {
      return employeeData.filter(emp => {
        const status = (emp.employee_status || emp.status || '').toLowerCase();
        return status.includes('terminated');
      });
    }
    return employeeData;
  };

  // Define table columns
  const columns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => {
        let current, pageSize;
        if (activeTab === 'pending') {
          current = pendingPagination.current || 1;
          pageSize = pendingPagination.pageSize || 10;
        } else if (activeTab === 'approved') {
          current = approvedPagination.current || 1;
          pageSize = approvedPagination.pageSize || 10;
        } else {
          current = pagination.current || 1;
          pageSize = pagination.pageSize || 10;
        }
        return (current - 1) * pageSize + index + 1;
      }
    },
    {
      title: 'PF Number',
      key: 'pf_number',
      searchable: true,
      render: (_, record) => {
        // Show "Pending" or "N/A" if employee is pending approval and has no PF number
        const isPending = isPendingApproval(record) && !record.pfno;
        return isPending ? 'Pending' : (record.pfno || 'N/A');
      }
    },
    {
      title: 'Full Name',
      key: 'full_name',
      searchable: true,
      render: (_, record) => {
        const fullName = record.full_name;
        return fullName && fullName !== 'undefined' ? fullName : 'N/A';
      }
    },
    {
      title: 'Username',
      key: 'username',
      searchable: true,
      render: (_, record) => {
        // Check employee username first
        if (record.username) {
          return record.username;
        }
        return 'N/A';
      }
    },
    {
      title: 'Status',
      key: 'approval_status',
      width: 150,
      align: 'center',
      filters: [
        { text: 'Pending', value: 'pending' },
        { text: 'Approved', value: 'approved' },
        { text: 'Terminated', value: 'terminated' },
        { text: 'Rejected', value: 'rejected' },
      ],
      onFilter: (value, record) => {
        const status = getEmployeeStatus(record);
        return status.text.toLowerCase().replace(' ', '_') === value;
      },
      render: (_, record) => {
        const status = getEmployeeStatus(record);
        return (
          <Tag color={status.color} icon={status.icon}>
            {status.text}
          </Tag>
        );
      }
    },
    {
      title: 'Actions',
      key: 'actions',
      // Keep this column compact so that other columns (especially Full Name) have more space
      width: 140,
      align: 'center',
      render: (_, record) => {
        return (
          <div className="employee-management-action-buttons">
            <Button 
              icon={<EyeOutlined />} 
              size="small" 
              onClick={() => handleView(record)}
              className="employee-management-view-button"
            >
              View
            </Button>
          </div>
        );
      }
    }
  ];

  const showCreateModal = () => {
    setSelectedEmployee(null);
    setIsCreateModalOpen(true);
  };

  const handleView = async (record) => {
    if (!record || !record.national_id) {
      message.error('Employee ID is missing');
      return;
    }

    // Prepare employee data from record immediately (for faster UI response)
    const initialEmployeeData = {
      ...record,
      // Ensure we have the necessary fields from the record
      national_id: record.national_id,
      full_name: record.full_name,
      email: record.email,
      mobile: record.mobile,
      pfno: record.pfno || record.pf_number,
      username: record.username,
      employee_status: record.employee_status || record.status,
    };

    // Open modal immediately with existing data for better UX
    setSelectedEmployee(initialEmployeeData);
    setIsViewModalOpen(true);
    setLoading(true);

    // Fetch detailed employee data and history in parallel for better performance
    try {
      const [employeeResponse, historyResponse] = await Promise.allSettled([
        apiService.getBridgeEmployeeById(record.national_id),
        apiService.getReferralRequests({
          national_id: record.national_id,
          module_code: 'EMPLOYMENT_MANAGEMENT'
        })
      ]);

      // Process employee data response
      if (employeeResponse.status === 'fulfilled' && employeeResponse.value.success && employeeResponse.value.data) {
        const { items } = extractArrayFromResponse(employeeResponse.value.data);
        const employeeData = items.length > 0 ? items[0] : employeeResponse.value.data;
        
        // Preserve the original record's ID if the fetched data doesn't have it
        if (!employeeData.id && record.id) {
          employeeData.id = record.id;
        }
        
        // Preserve referral_request from the record if it exists (important for approval/rejection)
        if (record.referral_request) {
          employeeData.referral_request = record.referral_request;
        }
        
        // Preserve status_id and approval_status_id from record if they exist
        if (record.status_id) {
          employeeData.status_id = record.status_id;
        }
        if (record.approval_status_id) {
          employeeData.approval_status_id = record.approval_status_id;
        }
        
        // Merge with initial data to ensure we have all fields
        setSelectedEmployee({
          ...initialEmployeeData,
          ...employeeData,
        });
      } else if (employeeResponse.status === 'rejected') {
        console.error('Error fetching employee details:', employeeResponse.reason);
        // Don't show error if we have initial data - just log it
      }

      // Process history response
      if (historyResponse.status === 'fulfilled' && historyResponse.value.success && historyResponse.value.data) {
        const { items: requests } = extractArrayFromResponse(historyResponse.value.data);
        setReferralRequestHistory(requests);
        
        // If selectedEmployee doesn't have a referral_request, try to find a pending one
        setSelectedEmployee(prevEmployee => {
          if (!prevEmployee || prevEmployee.referral_request) return prevEmployee;
          
          const pendingRequest = requests.find(req => 
            req.status?.toLowerCase() === 'pending'
          );
          
          return pendingRequest ? {
            ...prevEmployee,
            referral_request: pendingRequest,
            status_id: pendingRequest.id,
            approval_status_id: pendingRequest.id
          } : prevEmployee;
        });
      } else if (historyResponse.status === 'rejected') {
        console.error('Error fetching referral request history:', historyResponse.reason);
        setReferralRequestHistory([]);
      }
    } catch (error) {
      console.error('Error in handleView:', error);
      // Don't show error message if we have initial data - user can still see the modal
    } finally {
      setLoading(false);
      setLoadingHistory(false);
    }
  };

  // Fetch referral request history for an employee
  const fetchReferralRequestHistory = async (nationalId) => {
    if (!nationalId) return;
    
    setLoadingHistory(true);
    try {
      const response = await apiService.getReferralRequests({
        national_id: nationalId,
        module_code: 'EMPLOYMENT_MANAGEMENT'
      });
      
      if (response.success && response.data) {
        const { items: requests } = extractArrayFromResponse(response.data);
        setReferralRequestHistory(requests);
        
        // If selectedEmployee doesn't have a referral_request, try to find a pending one
        setSelectedEmployee(prevEmployee => {
          if (!prevEmployee || prevEmployee.referral_request) return prevEmployee;
          
          const pendingRequest = requests.find(req => 
            req.status?.toLowerCase() === 'pending'
          );
          
          return pendingRequest ? {
            ...prevEmployee,
            referral_request: pendingRequest,
            status_id: pendingRequest.id,
            approval_status_id: pendingRequest.id
          } : prevEmployee;
        });
      } else {
        setReferralRequestHistory([]);
      }
    } catch (error) {
      console.error('Error fetching referral request history:', error);
      setReferralRequestHistory([]);
    } finally {
      setLoadingHistory(false);
    }
  };

  const handleEdit = async (record) => {
    if (!record || !record.national_id) {
      message.error('Employee ID is missing');
      return;
    }

    setLoading(true);
    try {
      const response = await apiService.getBridgeEmployeeById(record.national_id);
      if (response.success && response.data) {
        setSelectedEmployee(response.data);
        setIsEditModalOpen(true);
      } else {
        message.error(response.message || 'Failed to fetch employee details');
      }
    } catch (error) {
      message.error('An error occurred while fetching employee details');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (record) => {
    // Check if employee has pending actions
    if (isPendingApproval(record) || isPendingTermination(record)) {
      const pendingStatus = getEmployeeStatus(record);
      message.warning(`Cannot submit deletion request. Employee has ${pendingStatus.text} request. Please wait for approval or rejection.`);
      return;
    }

    const employeeName = record.full_name || 'this employee';
    modal.confirm({
      title: 'Submit Deletion Request',
      content: (
        <div>
          <p><strong>Warning:</strong> This will mark employee for deletion.</p>
          <p>The request requires approval before the employee is permanently deleted.</p>
          <p>Employee data will remain until approved.</p>
        </div>
      ),
      okText: 'Submit Deletion Request',
      okType: 'danger',
      cancelText: 'Cancel',
      onOk: async () => {
        setLoading(true);
        try {
          const response = await apiService.deleteBmsUser(record.id);
          if (response.success) {
            // Handle new response structure with referral_request
            const referralRequest = response.data?.referral_request;
            const referralId = referralRequest?.id;
            
            let successMessage = `Deletion request for "${employeeName}" has been submitted for approval.`;
            if (referralId) {
              successMessage += ` Referral Request ID: ${referralId}`;
            }
            
            message.success({
              content: response.message || successMessage,
              duration: 5
            });
            // Refresh the employee list with current pagination
            await fetchRegisteredEmployee(pagination.current, pagination.pageSize);
          } else {
            // Handle error: pending action exists
            if (response.message && response.message.includes('pending')) {
              message.warning(response.message);
            } else {
              message.error(response.message || 'Failed to submit deletion request');
            }
          }
        } catch (error) {
          message.error('An error occurred while submitting deletion request');
        } finally {
          setLoading(false);
        }
      }
    });
  };

  const handleApproveRejectClick = (actionType) => {
    setPendingActionType(actionType);
    setShowCommentField(true);
    setCommentText('');
  };

  const handleCommentCancel = () => {
    setShowCommentField(false);
    setPendingActionType(null);
    setCommentText('');
  };

  const handleApprovalSubmit = async () => {
    if (!selectedEmployee) {
      message.error('Employee information is missing');
      return;
    }

    // Get employee ID - try multiple possible fields
    const employeeId = selectedEmployee.national_id || selectedEmployee.employee_id || selectedEmployee.user_id;
    if (!employeeId) {
      message.error('Employee ID is missing. Please try refreshing the employee details.');
      return;
    }

    if (!pendingActionType) {
      message.error('Please select an action');
      return;
    }

    // Validate comment for rejection, termination, and termination approval/rejection
    if ((pendingActionType === 'reject' || 
         pendingActionType === 'terminate' || 
         pendingActionType === 'reject-termination' ||
         pendingActionType === 'approve-termination') && !commentText.trim()) {
      const actionName = pendingActionType === 'reject' ? 'rejection' : 
                        pendingActionType === 'terminate' ? 'termination request' :
                        pendingActionType === 'reject-termination' ? 'termination rejection' :
                        'termination approval';
      message.error(`Please provide a reason for ${actionName}`);
      return;
    }

    setApprovalLoading(true);
    try {
      let response;
      let data;

      // Only include the relevant field based on action type
      if (pendingActionType === 'approve') {
        data = {
          comment: commentText.trim() || '',
        };
        // For approval, use referral request ID or status ID, not employee national_id
        // Try multiple sources: selectedEmployee properties, then referralRequestHistory
        let approvalId = selectedEmployee.referral_request?.id || 
                        selectedEmployee.status_id || 
                        selectedEmployee.approval_status_id || 
                        selectedEmployee.id;
        
        // If still not found, check referralRequestHistory for pending requests
        if (!approvalId && referralRequestHistory && referralRequestHistory.length > 0) {
          const pendingRequest = referralRequestHistory.find(req => 
            req.status === 'PENDING' || req.status === 'pending'
          );
          if (pendingRequest) {
            approvalId = pendingRequest.id;
          }
        }
        
        if (!approvalId) {
          message.error('Referral request ID is missing. Please try refreshing the employee details.');
          setApprovalLoading(false);
          return;
        }
        data.approved_by = currentUser?.nida || currentUser?.username || 'System';
        response = await apiService.approveEmployee(approvalId, data);
      } else if (pendingActionType === 'reject') {
        // For rejection, use referral request ID or status ID, not employee national_id
        // Try multiple sources: selectedEmployee properties, then referralRequestHistory
        let approvalId = selectedEmployee.referral_request?.id || 
                        selectedEmployee.status_id || 
                        selectedEmployee.approval_status_id || 
                        selectedEmployee.id;
        
        // If still not found, check referralRequestHistory for pending requests
        if (!approvalId && referralRequestHistory && referralRequestHistory.length > 0) {
          const pendingRequest = referralRequestHistory.find(req => 
            req.status === 'PENDING' || req.status === 'pending'
          );
          if (pendingRequest) {
            approvalId = pendingRequest.id;
          }
        }
        
        if (!approvalId) {
          message.error('Referral request ID is missing. Please try refreshing the employee details.');
          setApprovalLoading(false);
          return;
        }
        data = {
          rejection_reason: commentText.trim() || '',
          approved_by: currentUser?.nida || currentUser?.username || 'System'
        };
        response = await apiService.rejectEmployee(approvalId, data);
      } else if (pendingActionType === 'terminate') {
        // Submit termination request (goes to pending approval)
        data = {
          termination_reason: commentText.trim() || '',
          requested_by: currentUser?.nida || currentUser?.username || 'System'
        };

        response = await apiService.submitTerminationRequest(employeeId, data);

      } else if (pendingActionType === 'approve-termination') {
        // Approve termination request (for approvers)
        const id = selectedEmployee.status_id || selectedEmployee.approval_status_id || selectedEmployee.id || employeeId;
        data = {
          comment: commentText.trim() || '',
          approved_by: currentUser?.nida || currentUser?.username || 'System'
        };
        response = await apiService.approveTermination(id, data);
      } else if (pendingActionType === 'reject-termination') {
        // Reject termination request (for approvers)
        const id = selectedEmployee.status_id || selectedEmployee.approval_status_id || selectedEmployee.id || employeeId;
        data = {
          rejection_reason: commentText.trim() || '',
          approved_by: currentUser?.nida || currentUser?.username || 'System'
        };
        response = await apiService.rejectTermination(id, data);
      } else {
        message.error('Invalid action type');
        return;
      }

      if (response.success) {
        const actionMessages = {
          approve: 'approved',
          reject: 'rejected',
          terminate: 'termination request submitted successfully. Waiting for approval.',
          'approve-termination': 'termination request approved',
          'reject-termination': 'termination request rejected'
        };
        message.success(
          `Employee ${actionMessages[pendingActionType] || 'action completed'} successfully`
        );
        setShowCommentField(false);
        setPendingActionType(null);
        setCommentText('');
        
        // Refresh employee lists based on active tab
        if (activeTab === 'all') {
          await fetchRegisteredEmployee(pagination.current, pagination.pageSize);
        } else if (activeTab === 'pending') {
          await fetchPendingEmployeeRequests(pendingPagination.current, pendingPagination.pageSize);
        } else if (activeTab === 'approved') {
          await fetchApprovedEmployeeRequests(approvedPagination.current, approvedPagination.pageSize);
        } else {
          // Refresh all tabs to ensure data is up to date
          await fetchRegisteredEmployee(pagination.current, pagination.pageSize);
          await fetchPendingEmployeeRequests(pendingPagination.current, pendingPagination.pageSize);
          await fetchApprovedEmployeeRequests(approvedPagination.current, approvedPagination.pageSize);
        }
        
        // Close the view modal and reset state
        setIsViewModalOpen(false);
        setSelectedEmployee(null);
        setReferralRequestHistory([]);
      } else {
        message.error(response.message || `Failed to ${pendingActionType} employee`);
      }
    } catch (error) {
      message.error(`An error occurred while ${pendingActionType}ing the employee`);
    } finally {
      setApprovalLoading(false);
    }
  };

  const handleCreateCancel = () => {
    setIsCreateModalOpen(false);
    setSelectedEmployee(null);
  };

  const handleViewCancel = () => {
    setIsViewModalOpen(false);
    setSelectedEmployee(null);
    setShowCommentField(false);
    setPendingActionType(null);
    setCommentText('');
    setReferralRequestHistory([]);
  };

  const handleEditFromView = async () => {
    if (!selectedEmployee || !selectedEmployee.national_id) {
      message.error('Employee ID is missing');
      return;
    }
    setIsViewModalOpen(false);
    await handleEdit(selectedEmployee);
  };

  const handleDeleteFromView = async () => {
    if (!selectedEmployee) {
      message.error('Employee information is missing');
      return;
    }
    await handleDelete(selectedEmployee);
    // Refresh employee data in view modal if still open
    if (isViewModalOpen && selectedEmployee && selectedEmployee.national_id) {
      try {
        const response = await apiService.getBridgeEmployeeById(selectedEmployee.national_id);
        if (response.success && response.data) {
          const { items } = extractArrayFromResponse(response.data);
          const employeeData = items.length > 0 ? items[0] : response.data;
          setSelectedEmployee(employeeData);
        }
      } catch (error) {
        // Error refreshing employee details - silently fail
      }
    }
  };

  const handleTerminateFromView = () => {
    if (!selectedEmployee) {
      message.error('Employee information is missing');
      return;
    }
    
    // Check if employee has pending actions (except termination)
    if (isPendingApproval(selectedEmployee) && !isPendingTermination(selectedEmployee)) {
      const pendingStatus = getEmployeeStatus(selectedEmployee);
      message.warning(`Cannot submit termination request. Employee has ${pendingStatus.text} request. Please wait for approval or rejection.`);
      return;
    }
    
    setPendingActionType('terminate');
    setShowCommentField(true);
    setCommentText('');
  };

  const handleEditCancel = () => {
    setIsEditModalOpen(false);
    setSelectedEmployee(null);
  };

  const handleCreateSubmit = async (formData) => {
    setLoading(true);
    try {
      const response = await apiService.registerBridgeEmployee(formData);
      if (response.success) {
        // Handle new response structure with referral_request
        const referralRequest = response.data?.referral_request;
        const referralId = referralRequest?.id;
        
        let successMessage = 'Employee created successfully. Request submitted for approval.';
        if (referralId) {
          successMessage += ` Referral Request ID: ${referralId}`;
        }
        
        message.success({
          content: response.message || successMessage,
          duration: 5
        });
        setIsCreateModalOpen(false);
        setSelectedEmployee(null);
        // Refresh the employee list with current pagination
        await fetchRegisteredEmployee(pagination.current, pagination.pageSize);
        return { success: true };
      } else {
        // Handle validation errors from response.data
        if (response.data && typeof response.data === 'object' && Object.keys(response.data).length > 0) {
          // Format validation errors for display
          const errorMessages = Object.entries(response.data)
            .map(([field, messages]) => {
              const fieldName = field.charAt(0).toUpperCase() + field.slice(1).replace(/_/g, ' ');
              const messageText = Array.isArray(messages) ? messages.join(', ') : messages;
              return `${fieldName}: ${messageText}`;
            })
            .join('\n');
          message.error({
            content: `Validation failed:\n${errorMessages}`,
            duration: 6
          });
        } else {
          message.error(response.message || 'Failed to register bridge employee');
        }
        // Return the error response so the form can handle field-specific errors
        return response;
      }
    } catch (error) {
      message.error('An error occurred while registering the bridge employee');
      return { success: false, message: 'An error occurred while registering the bridge employee' };
    } finally {
      setLoading(false);
    }
  };

  const handleEditSubmit = async (formData) => {
    if (!selectedEmployee || !selectedEmployee.national_id) {
      message.error('Employee National ID is missing');
      return;
    }

    // Check if employee has pending actions
    if (isPendingApproval(selectedEmployee) || isPendingTermination(selectedEmployee)) {
      const pendingStatus = getEmployeeStatus(selectedEmployee);
      message.warning(`Cannot update employee. Employee has ${pendingStatus.text} request. Please wait for approval or rejection.`);
      return { success: false, message: 'Employee has pending approval request' };
    }

    setLoading(true);
    try {
      const response = await apiService.updateBridgeEmployee(selectedEmployee.national_id, formData);
      if (response.success) {
        // Handle new response structure with referral_request
        const referralRequest = response.data?.referral_request;
        const referralId = referralRequest?.id;
        
        let successMessage = 'Employee update submitted for approval.';
        if (referralId) {
          successMessage += ` Referral Request ID: ${referralId}`;
        }
        
        message.success({
          content: response.message || successMessage,
          duration: 5
        });
        setIsEditModalOpen(false);
        setSelectedEmployee(null);
        // Refresh the employee list with current pagination
        await fetchRegisteredEmployee(pagination.current, pagination.pageSize);
        return { success: true };
      } else {
        // Handle error: pending action exists
        if (response.message && response.message.includes('pending')) {
          message.warning(response.message);
        } else {
          // Handle validation errors from response.data
          if (response.data && typeof response.data === 'object' && Object.keys(response.data).length > 0) {
            // Format validation errors for display
            const errorMessages = Object.entries(response.data)
              .map(([field, messages]) => {
                const fieldName = field.charAt(0).toUpperCase() + field.slice(1).replace(/_/g, ' ');
                const messageText = Array.isArray(messages) ? messages.join(', ') : messages;
                return `${fieldName}: ${messageText}`;
              })
              .join('\n');
            message.error({
              content: `Validation failed:\n${errorMessages}`,
              duration: 6
            });
          } else {
            message.error(response.message || 'Failed to update employee');
          }
        }
        // Return the error response so the form can handle field-specific errors
        return response;
      }
    } catch (error) {
      message.error('An error occurred while updating the employee');
      return { success: false, message: 'An error occurred while updating the employee' };
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="employee-management-container">
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={[
          {
            key: 'all',
            label: 'All Employees'
          },
          {
            key: 'pending',
            label: (
              <span>
                Pending
                {pendingEmployeeData.length > 0 && (
                  <Tag color="orange" className="employee-management-pending-badge">
                    {pendingEmployeeData.length}
                  </Tag>
                )}
              </span>
            )
          },
          {
            key: 'approved',
            label: 'Approved'
          },
          {
            key: 'terminated',
            label: 'Terminated'
          }
        ]}
        className="employee-management-tabs"
      />

      {/* DataTable */}
      <div className="relative">
        {(activeTab === 'pending' ? pendingLoading : activeTab === 'approved' ? approvedLoading : loading) ? (
          <div
            className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
            style={{ top: '4.75rem' }}
          >
            <CollectionLoader />
          </div>
        ) : null}
      <DataTable
        columns={columns}
        data={getFilteredEmployees()}
        loading={false}
        pagination={activeTab === 'pending' ? pendingPagination : activeTab === 'approved' ? approvedPagination : pagination}
        pageSize={
          Number(
            activeTab === 'pending'
              ? pendingPagination.pageSize
              : activeTab === 'approved'
              ? approvedPagination.pageSize
              : pagination.pageSize
          ) || 15
        }
        showSearch={true}
        showRefresh={false}
        rightAction={
          !isManageEmployeeRoute ? (
            <Button
              type="primary"
              icon={<UserAddOutlined />}
              onClick={showCreateModal}
              size="large"
              className="employee-management-register-button"
            >
              Register
            </Button>
          ) : null
        }
        searchPlaceholder="Search employees..."
        rowKey={(record) => record?.national_id || record?.pfno || record?.email || `row-${record?.username}`}
        size="middle"
        bordered={true}
        onChange={(paginationInfo, filters, sorter) => {
          if (paginationInfo) {
            const newPage = paginationInfo.current || 1;
            const newPageSize = Number(paginationInfo.pageSize) || 15;

            // Only fetch if page or pageSize changed
            if (activeTab === 'pending') {
              if (newPage !== pendingPagination.current || newPageSize !== pendingPagination.pageSize) {
                fetchPendingEmployeeRequests(newPage, newPageSize);
              }
            } else if (activeTab === 'approved') {
              if (newPage !== approvedPagination.current || newPageSize !== approvedPagination.pageSize) {
                fetchApprovedEmployeeRequests(newPage, newPageSize);
              }
            } else {
              if (newPage !== pagination.current || newPageSize !== pagination.pageSize) {
                fetchRegisteredEmployee(newPage, newPageSize);
              }
            }
          }
        }}
      />
      </div>

      {/* Create Employee Modal */}
      <Modal
        title="Employee Registration"
        open={isCreateModalOpen}
        onCancel={handleCreateCancel}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 800}
        destroyOnHidden={true}
        className="employee-management-modal"
      >
        <UserRegistrationForm onSubmit={handleCreateSubmit} onCancel={handleCreateCancel} />
      </Modal>

      {/* View Employee Modal */}
      <Modal
        title="Employee Details"
        open={isViewModalOpen}
        onCancel={handleViewCancel}
        footer={(() => {
          const isPending = selectedEmployee && isPendingApproval(selectedEmployee);
          const isPendingTerm = selectedEmployee && isPendingTermination(selectedEmployee);
          const canApproveRecord = canApprove();

          const footerButtons = [
            <Button key="close" onClick={handleViewCancel}>
              Close
            </Button>
          ];

          if (
            showCommentField &&
            (pendingActionType === 'approve' ||
              pendingActionType === 'reject' ||
              pendingActionType === 'terminate' ||
              pendingActionType === 'approve-termination' ||
              pendingActionType === 'reject-termination')
          ) {
            // Show comment field with Submit/Cancel buttons
            footerButtons.unshift(
              <Button key="cancel-comment" onClick={handleCommentCancel}>
                Cancel
              </Button>,
              <Button
                key="submit-comment"
                type="primary"
                icon={
                  pendingActionType === 'approve' || pendingActionType === 'approve-termination' ? (
                    <CheckCircleOutlined />
                  ) : pendingActionType === 'reject' || pendingActionType === 'reject-termination' ? (
                    <CloseCircleOutlined />
                  ) : (
                    <StopOutlined />
                  )
                }
                onClick={handleApprovalSubmit}
                loading={approvalLoading}
                className="employee-management-button-primary"
              >
                {pendingActionType === 'approve'
                  ? 'Approve'
                  : pendingActionType === 'reject'
                  ? 'Reject'
                  : pendingActionType === 'approve-termination'
                  ? 'Approve Termination'
                  : pendingActionType === 'reject-termination'
                  ? 'Reject Termination'
                  : pendingActionType === 'terminate'
                  ? 'Submit Termination Request'
                  : 'Submit'}
              </Button>
            );
          } else if (isPending && canApproveRecord && hasEmployeeApproverRole(selectedRole)) {
            // Show Approve/Reject buttons for pending employees (only for Employee Approver role)
            footerButtons.unshift(
              <Button
                key="reject"
                type="primary"
                icon={<CloseCircleOutlined />}
                onClick={() => handleApproveRejectClick('reject')}
                className="employee-management-button-primary"
              >
                Reject
              </Button>,
              <Button
                key="approve"
                type="primary"
                icon={<CheckCircleOutlined />}
                onClick={() => handleApproveRejectClick('approve')}
                className="employee-management-button-primary"
              >
                Approve
              </Button>
            );
          } else if (isPendingTerm && canApproveRecord && hasEmployeeApproverRole(selectedRole)) {
            // Show Approve/Reject Termination buttons (only for Employee Approver role)
            footerButtons.unshift(
              <Button
                key="reject-termination"
                type="primary"
                icon={<CloseCircleOutlined />}
                onClick={() => handleApproveRejectClick('reject-termination')}
                className="employee-management-button-primary"
              >
                Reject Termination
              </Button>,
              <Button
                key="approve-termination"
                type="primary"
                icon={<CheckCircleOutlined />}
                onClick={() => handleApproveRejectClick('approve-termination')}
                className="employee-management-button-primary"
              >
                Approve Termination
              </Button>
            );
          } else if (!isPending && !isPendingTerm) {
            // Show Edit, Delete, Terminate buttons for approved/active employees based on roles
            const isEmployeeApprover = hasEmployeeApproverRole(selectedRole);
            const isEmployeeRegistrar = hasEmployeeRegistrarRole(selectedRole);

            // Terminate, Delete buttons - only for Employee Approver role
            if (isEmployeeApprover) {
              footerButtons.unshift(
                <Button
                  key="terminate"
                  type="primary"
                  icon={<StopOutlined />}
                  onClick={handleTerminateFromView}
                  className="btn-standard-primary"
                >
                  Terminate
                </Button>,
                <Button
                  key="delete"
                  type="primary"
                  icon={<DeleteOutlined />}
                  onClick={handleDeleteFromView}
                  className="btn-standard-primary"
                >
                  Delete
                </Button>
              );
            }

            // Edit button - only for Employee Registrar role
            if (isEmployeeRegistrar) {
              footerButtons.unshift(
                <Button key="edit" type="primary" icon={<EditOutlined />} onClick={handleEditFromView} className="btn-standard-primary">
                  Update
                </Button>
              );
            }
          }

          return footerButtons;
        })()}
        width={
          typeof window !== 'undefined'
            ? window.innerWidth < 768
              ? '95%'
              : window.innerWidth < 1024
              ? '90%'
              : window.innerWidth < 1440
              ? '80%'
              : 1400
            : 1400
        }
        className="employee-management-modal"
        styles={{ body: { padding: '20px', maxHeight: 'calc(100vh - 100px)', overflowY: 'auto', overflowX: 'hidden' } }}
      >
        {selectedEmployee && (
          <div className="employee-management-modal-content">
            <Row gutter={[16, 16]}>
              {/* First Column: Employee Details */}
              <Col xs={24} sm={24} md={24} lg={16} xl={16}>
                {/* Personal Information Section */}
                <Card
                  title={
                    <span>
                      <IdcardOutlined className="employee-management-icon" />
                      Personal Information
                    </span>
                  }
                  size="small"
                  className="employee-management-card"
                  styles={{ body: { padding: '10px' } }}
                >
                  <Row gutter={[16, 6]}>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">PF Number</div>
                        <div className="employee-management-value">
                          {(() => {
                            const isPending = isPendingApproval(selectedEmployee);
                            const hasPF = selectedEmployee.pfno && selectedEmployee.pfno.trim() !== '';
                            
                            // Check if office auto-generates PF
                            const office = selectedEmployee.office || selectedEmployee.employment_details?.office;
                            const autoGeneratesPF =
                              office?.auto_generate_pf === true ||
                              office?.auto_generate_pf === 1 ||
                              office?.auto_generate_pf === '1' ||
                              office?.auto_generate_pf === 'true' ||
                              office?.auto_generate_pf === 'TRUE';
                            
                            if (isPending && !hasPF) {
                              if (autoGeneratesPF) {
                                return 'Pending (will be generated after approval)';
                              } else {
                                return (
                                  <span style={{ color: '#ff4d4f' }}>
                                    Missing (required for {office?.office_name || 'selected office'})
                                  </span>
                                );
                              }
                            }
                            return hasPF ? selectedEmployee.pfno : 'N/A';
                          })()}
                        </div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">Full Name</div>
                        <div className="employee-management-value">{selectedEmployee.full_name || 'N/A'}</div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">National Id</div>
                        <div className="employee-management-value">{selectedEmployee.national_id}</div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">Email</div>
                        <div className="employee-management-value">{selectedEmployee.email || 'N/A'}</div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">Username</div>
                        <div className="employee-management-value">{selectedEmployee.username || 'N/A'}</div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">Role Name</div>
                        <div className="employee-management-value">
                          {selectedEmployee.role_name ||
                            selectedEmployee.roleName ||
                            selectedEmployee.role?.name ||
                            selectedEmployee.role ||
                            'N/A'}
                        </div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">Active Status</div>
                        <div>
                          <Tag color={isActiveStatus(selectedEmployee.is_active) ? 'green' : 'red'} className="employee-management-tag">
                            {isActiveStatus(selectedEmployee.is_active) ? 'Active' : 'Inactive'}
                          </Tag>
                        </div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">Approval Status</div>
                        <div>
                          {(() => {
                            const status = getEmployeeStatus(selectedEmployee);
                            return (
                              <Tag color={status.color} icon={status.icon} className="employee-management-tag">
                                {status.text}
                              </Tag>
                            );
                          })()}
                        </div>
                      </div>
                    </Col>
                  </Row>
                </Card>

                {/* Contact Information Section */}
                <Card
                  title={
                    <span>
                      <MailOutlined className="employee-management-icon" />
                      Contact Information
                    </span>
                  }
                  size="small"
                  className="employee-management-card"
                  styles={{ body: { padding: '10px' } }}
                >
                  <Row gutter={[16, 6]}>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">
                          <MailOutlined className="employee-management-icon-small" />
                          Email Address
                        </div>
                        <div className="employee-management-value">{selectedEmployee.email}</div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">
                          <PhoneOutlined className="employee-management-icon-small" />
                          Phone Number
                        </div>
                        <div className="employee-management-value">{selectedEmployee.mobile}</div>
                      </div>
                    </Col>
                  </Row>
                </Card>

                {/* Audit Information Section */}
                <Card
                  title={
                    <span>
                      <CalendarOutlined className="employee-management-icon" />
                      Audit Information
                    </span>
                  }
                  size="small"
                  className="employee-management-card"
                  styles={{ body: { padding: '10px' } }}
                >
                  <Row gutter={[16, 6]}>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">Created At</div>
                        <div className="employee-management-value">{selectedEmployee.created_at || 'N/A'}</div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">Created By</div>
                        <div className="employee-management-value">{selectedEmployee.created_by || 'N/A'}</div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">Modified At</div>
                        <div className="employee-management-value">{selectedEmployee.modified_at || 'N/A'}</div>
                      </div>
                    </Col>
                    <Col span={12}>
                      <div className="employee-management-info-row">
                        <div className="employee-management-label">Modified By</div>
                        <div className="employee-management-value">{selectedEmployee.modified_by || 'N/A'}</div>
                      </div>
                    </Col>
                  </Row>
                </Card>
              </Col>

              {/* Second Column: Documents & Minutes (Combined with Tabs) */}
              <Col xs={24} sm={24} md={24} lg={8} xl={8}>
                <Card
                  title={
                    <span>
                      <FileTextOutlined className="employee-management-icon" />
                      Minutes & Documents
                    </span>
                  }
                  size="small"
                  className="employee-management-card"
                  styles={{ body: { padding: '10px' } }}
                >
                  <Tabs
                    activeKey={documentsMinutesTab}
                    onChange={setDocumentsMinutesTab}
                    items={[
                      {
                        key: 'minutes',
                        label: (
                          <span>
                            <CalendarOutlined />
                            Minutes
                          </span>
                        ),
                        children: loadingHistory ? (
                          <CollectionLoader size={64} compact />
                        ) : referralRequestHistory.length > 0 ? (
                          <div style={{ maxHeight: '500px', overflowY: 'auto' }}>
                            {referralRequestHistory.flatMap((request, requestIndex) => {
                              const workflowSteps = [];
                              
                              // Helper to format date
                              const formatDate = (dateString) => {
                                if (!dateString) return 'N/A';
                                const date = new Date(dateString);
                                return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                              };

                              // Helper to get status tag
                              const getStatusTag = (status) => {
                                const statusLower = (status || '').toLowerCase();
                                if (statusLower === 'pending') {
                                  return <Tag color="orange" icon={<HourglassOutlined />}>Pending</Tag>;
                                }
                                if (statusLower === 'approved') {
                                  return <Tag color="green" icon={<CheckCircleOutlined />}>Approved</Tag>;
                                }
                                if (statusLower === 'rejected') {
                                  return <Tag color="red" icon={<CloseCircleOutlined />}>Rejected</Tag>;
                                }
                                return <Tag color="red">{status || 'Unknown'}</Tag>;
                              };

                              // Get action type display name
                              const getActionDisplayName = (actionType) => {
                                const actionMap = {
                                  'CREATE': 'Initiate Employee',
                                  'UPDATE': 'Update Employee',
                                  'DELETE': 'Delete Employee',
                                  'TERMINATE': 'Terminate Employee'
                                };
                                return actionMap[actionType] || actionType;
                              };

                              // Step 1: Initiate Employee
                              if (request.created_at) {
                                workflowSteps.push({
                                  key: `initiate-${request.id}-${requestIndex}`,
                                  action: getActionDisplayName(request.action_type),
                                  date: formatDate(request.created_at),
                                  performedBy: request.initiated_by || 'N/A',
                                  role: request.initiated_by_role || 'Employee Registrar',
                                  comment: null,
                                  status: 'Initiated'
                                });
                              }

                              // Step 2: Review Employee (if reviewed - check for review fields)
                              if (request.reviewed_by || request.reviewed_at || request.reviewer_name) {
                                workflowSteps.push({
                                  key: `review-${request.id}-${requestIndex}`,
                                  action: 'Review Employee',
                                  date: formatDate(request.reviewed_at || request.created_at),
                                  performedBy: request.reviewed_by || request.reviewer_name || 'N/A',
                                  role: request.reviewed_by_role || request.reviewer_role || 'Employee Reviewer',
                                  comment: request.review_comment || request.reviewer_comment || null,
                                  status: 'Reviewed'
                                });
                              }

                              // Step 3: Approve/Reject Employee (if actioned)
                              if (request.approved_by || request.actioned_at || request.actioned_by) {
                                const finalStatus = (request.status || '').toLowerCase();
                                workflowSteps.push({
                                  key: `approve-${request.id}-${requestIndex}`,
                                  action: finalStatus === 'rejected' ? 'Reject Employee' : 'Approve Employee',
                                  date: formatDate(request.actioned_at || request.approved_at || request.updated_at),
                                  performedBy: request.approved_by || request.actioned_by || request.approver_name || 'N/A',
                                  role: request.approved_by_role || request.actioned_by_role || request.approver_role || 'Employee Approver',
                                  comment: request.remarks || request.comment || request.approver_comment || null,
                                  status: finalStatus === 'rejected' ? 'Rejected' : 'Approved'
                                });
                              }

                              return workflowSteps.map((step) => (
                                <div
                                  key={step.key}
                                  style={{
                                    marginBottom: '16px',
                                    padding: '12px',
                                    border: '1px solid #e8e8e8',
                                    borderRadius: '4px',
                                    backgroundColor: '#fafafa',
                                    position: 'relative'
                                  }}
                                >
                                  <div style={{ marginBottom: '8px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <strong style={{ fontSize: '14px' }}>{step.action}</strong>
                                    <div style={{ fontSize: '12px', color: '#8c8c8c' }}>
                                      {step.date}
                                    </div>
                                  </div>
                                  <div style={{ fontSize: '13px', marginBottom: step.comment ? '8px' : '0' }}>
                                    <strong>{step.performedBy}</strong>
                                    <span style={{ color: '#8c8c8c', marginLeft: '4px' }}>({step.role})</span>
                                  </div>
                                  {step.comment && (
                                    <div
                                      style={{
                                        marginTop: '8px',
                                        padding: '8px 12px',
                                        backgroundColor: '#f0f0f0',
                                        borderRadius: '4px',
                                        fontSize: '12px',
                                        color: '#595959',
                                        lineHeight: '1.5',
                                        marginBottom: '8px'
                                      }}
                                    >
                                      {step.comment}
                                    </div>
                                  )}
                                  <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '8px' }}>
                                    {getStatusTag(step.status)}
                                  </div>
                                </div>
                              ));
                            })}
                          </div>
                        ) : (
                          <div className="employee-management-info-row">
                            <div className="employee-management-value" style={{ textAlign: 'center', padding: '20px', color: '#999' }}>
                              No minutes available
                            </div>
                          </div>
                        )
                      },
                      {
                        key: 'documents',
                        label: (
                          <span>
                            <FileTextOutlined />
                            Documents
                          </span>
                        ),
                        children: (
                          <div className="employee-management-info-row">
                            <div className="employee-management-value" style={{ textAlign: 'center', padding: '20px', color: '#999' }}>
                              No documents available
                            </div>
                          </div>
                        )
                      }
                    ]}
                  />
                </Card>
              </Col>
            </Row>

            {/* Comment Field Section - shown when approve/reject/terminate is clicked */}
            {showCommentField && (
              <Card
                size="small"
                className="employee-management-comment-section"
                style={{ marginTop: '16px' }}
                styles={{ body: { padding: '12px' } }}
              >
                <div className="employee-management-comment-title">
                  <strong>
                    {pendingActionType === 'approve'
                      ? 'Approve Employee'
                      : pendingActionType === 'reject'
                      ? 'Reject Employee'
                      : pendingActionType === 'approve-termination'
                      ? 'Approve Termination Request'
                      : pendingActionType === 'reject-termination'
                      ? 'Reject Termination Request'
                      : 'Submit Termination Request'}
                  </strong>
                </div>
                <TextArea
                  rows={4}
                  value={commentText}
                  onChange={(e) => setCommentText(e.target.value)}
                  placeholder={
                    pendingActionType === 'approve'
                      ? 'Add any comments (optional)'
                      : pendingActionType === 'reject'
                      ? 'Please provide a reason for rejection (required)'
                      : pendingActionType === 'approve-termination'
                      ? 'Please provide a reason for approving termination (required)'
                      : pendingActionType === 'reject-termination'
                      ? 'Please provide a reason for rejecting termination (required)'
                      : 'Please provide a reason for termination request (required)'
                  }
                  className="employee-management-comment-textarea"
                />
                <div className="employee-management-comment-hint">
                  {pendingActionType === 'approve'
                    ? 'Comment is optional for approval'
                    : pendingActionType === 'reject'
                    ? 'Comment is required for rejection'
                    : pendingActionType === 'approve-termination'
                    ? 'Comment is required for termination approval'
                    : pendingActionType === 'reject-termination'
                    ? 'Comment is required for termination rejection'
                    : 'Comment is required for termination request'}
                </div>
              </Card>
            )}
          </div>
        )}
      </Modal>

      {/* Edit Employee Modal */}
      <Modal
        title="Edit Employee"
        open={isEditModalOpen}
        onCancel={handleEditCancel}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 800}
        destroyOnHidden={true}
        className="employee-management-modal"
      >
        <UserRegistrationForm onSubmit={handleEditSubmit} onCancel={handleEditCancel} initialValues={selectedEmployee} />
      </Modal>
    </div>
  );
};

export default EmployeeManagement;

