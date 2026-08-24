import { useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { Button, Modal, Tag, Row, Col, Card, App, Tabs, Input, Select, Space, Table, Badge } from 'antd';
import { 
  CheckCircleOutlined, 
  CloseCircleOutlined, 
  HourglassOutlined, 
  EyeOutlined,
  FilterOutlined,
  ReloadOutlined
} from '@ant-design/icons';
import { DataTable } from '../../../common/data';
import { apiService } from '../../../services/api.jsx';
import dayjs from 'dayjs';
import { extractArrayFromResponse, formatEmployeeName, getActionTypeDisplay, hasEmployeeApproverRole } from '../../../common/utils/employeeUtils.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import './PendingApprovalsDashboard.css';
import '../../../styles/common.css';

const { TextArea } = Input;
const { Option } = Select;

const PendingApprovalsDashboard = () => {
  const { message } = App.useApp();
  const currentUser = useSelector((state) => state.auth.user);
  const selectedRole = useSelector((state) => state.app.selectedRole);
  
  const [loading, setLoading] = useState(false);
  const [referralRequests, setReferralRequests] = useState([]);
  const [selectedRequest, setSelectedRequest] = useState(null);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [viewModalLoading, setViewModalLoading] = useState(false);
  const [actionType, setActionType] = useState(null); // 'approve' or 'reject'
  const [remarks, setRemarks] = useState('');
  const [approvalLoading, setApprovalLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('pending');
  const [showCommentSection, setShowCommentSection] = useState(false);
  const [filters, setFilters] = useState({
    action_type: '',
    national_id: '',
  });
  const [pagination, setPagination] = useState({ 
    current: 1, 
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Fetch pending referral requests
  const fetchPendingReferralRequests = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        module_code: 'EMPLOYMENT_MANAGEMENT',
        page: page,
        per_page: pageSize,
      };
      
      // Only add filters that have values
      if (filters.action_type && filters.action_type.trim()) {
        params.action_type = filters.action_type;
      }
      if (filters.national_id && filters.national_id.trim()) {
        params.national_id = filters.national_id;
      }
      
      const response = await apiService.getPendingReferralRequests(params);

      // Debug logging
      console.log('Pending Referral Requests Response:', {
        success: response.success,
        message: response.message,
        data: response.data,
        dataType: typeof response.data,
        isArray: Array.isArray(response.data),
        dataKeys: response.data && typeof response.data === 'object' ? Object.keys(response.data) : null
      });

      if (response.success && response.data) {
        const { items: requests, pagination: paginationData } = extractArrayFromResponse(response.data);
        const totalCount = response.data.count || paginationData.total || requests.length;
        
        setReferralRequests(requests);
        setPagination(prev => ({
          ...prev,
          current: paginationData.current_page || paginationData.page || page,
          pageSize: Number(paginationData.per_page) || Number(paginationData.pageSize) || pageSize,
          total: totalCount
        }));
        
        // Show info message if no requests found
        if (requests.length === 0) {
          console.log('No pending referral requests found');
        }
      } else {
        const errorMsg = response.message || 'Failed to fetch pending approvals';
        console.error('API Error:', errorMsg, response);
        message.error(errorMsg);
        setReferralRequests([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching pending referral requests:', error);
      message.error('An error occurred while fetching pending approvals');
      setReferralRequests([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPendingReferralRequests(1, 15);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters]);


  // Handle view request details
  const handleView = async (record) => {
    // Open modal immediately for better UX
    setIsViewModalOpen(true);
    setSelectedRequest(null); // Clear previous data
    setViewModalLoading(true);
    
    try {
      const response = await apiService.getReferralRequestById(record.id);
      if (response.success && response.data) {
        setSelectedRequest(response.data);
      } else {
        message.error(response.message || 'Failed to fetch request details');
        setIsViewModalOpen(false);
      }
    } catch (error) {
      console.error('Error fetching request details:', error);
      message.error('An error occurred while fetching request details');
      setIsViewModalOpen(false);
    } finally {
      setViewModalLoading(false);
    }
  };

  // Handle approve/reject click - show comment section in view modal
  const handleApproveRejectClick = (action) => {
    setActionType(action);
    setRemarks('');
    setShowCommentSection(true);
  };

  // Cancel comment section
  const handleCommentCancel = () => {
    setShowCommentSection(false);
    setActionType(null);
    setRemarks('');
  };

  // Handle approval submission
  const handleApprovalSubmit = async () => {
    if (!selectedRequest) {
      message.error('Request information is missing');
      return;
    }

    // Validate remarks for rejection
    if (actionType === 'reject' && !remarks.trim()) {
      message.error('Please provide a reason for rejection');
      return;
    }

    setApprovalLoading(true);
    try {
      let data;
      let response;
      
      if (actionType === 'approve') {
        data = {
          remarks: remarks.trim() || '',
        };
        response = await apiService.approveReferralRequest(selectedRequest.id, data);
      } else {
        data = {
          rejection_reason: remarks.trim() || '',
        };
        response = await apiService.rejectReferralRequest(selectedRequest.id, data);
      }

      if (response.success) {
        message.success(
          `Request ${actionType === 'approve' ? 'approved' : 'rejected'} successfully`
        );
        setShowCommentSection(false);
        setActionType(null);
        setRemarks('');
        setIsViewModalOpen(false);
        setSelectedRequest(null);
        setViewModalLoading(false);
        
        // Refresh the list
        await fetchPendingReferralRequests(pagination.current, pagination.pageSize);
      } else {
        message.error(response.message || `Failed to ${actionType} request`);
      }
    } catch (error) {
      message.error(`An error occurred while ${actionType}ing the request`);
    } finally {
      setApprovalLoading(false);
    }
  };

  // Filter requests based on active tab
  const getFilteredRequests = () => {
    if (activeTab === 'pending') {
      return referralRequests.filter(req => req.status === 'PENDING' || req.status === 'pending');
    }
    // Add more tabs as needed (approved, rejected)
    return referralRequests;
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
        const pageSize = pagination.pageSize || 10;
        return (current - 1) * pageSize + index + 1;
      }
    },
    {
      title: 'Request ID',
      key: 'id',
      dataIndex: 'id',
      width: 100,
      searchable: true,
    },
    {
      title: 'Action Type',
      key: 'action_type',
      width: 120,
      filters: [
        { text: 'Create', value: 'CREATE' },
        { text: 'Update', value: 'UPDATE' },
        { text: 'Delete', value: 'DELETE' },
        { text: 'Terminate', value: 'TERMINATE' },
      ],
      onFilter: (value, record) => record.action_type === value,
      render: (_, record) => getActionTypeDisplay(record.action_type),
    },
    {
      title: 'Employee National ID',
      key: 'national_id',
      dataIndex: 'national_id',
      searchable: true,
      width: 150,
    },
    {
      title: 'Employee Name',
      key: 'employee_name',
      searchable: true,
      render: (_, record) => {
        const employee = record.employee || record.employee_data;
        return formatEmployeeName(employee);
      }
    },
    {
      title: 'Initiated By',
      key: 'initiated_by',
      render: (_, record) => {
        // Handle different initiator structures
        if (record.initiator?.username) {
          return record.initiator.username;
        }
        return 'N/A';
      },
    },
    {
      title: 'Created At',
      key: 'created_at',
      render: (_, record) => {
        if (!record.created_at) return 'N/A';
        return dayjs(record.created_at).format('YYYY-MM-DD HH:mm:ss');
      }
    },
    {
      title: 'Status',
      key: 'status',
      width: 120,
      render: (_, record) => (
        <Tag color="orange" icon={<HourglassOutlined />}>
          Pending
        </Tag>
      )
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 200,
      align: 'center',
      render: (_, record) => {
        // Check if current user is the maker (cannot approve own request)
        const initiatorUsername = record.initiator?.username || record.initiated_by || record.created_by;
        const canApprove = initiatorUsername !== currentUser?.nida && 
                          initiatorUsername !== currentUser?.username;
        const isEmployeeApprover = hasEmployeeApproverRole(selectedRole);
        
        return (
          <Space size="small">
            <Button 
              icon={<EyeOutlined />} 
              size="small" 
              onClick={() => handleView(record)}
              className="pending-approvals-view-button"
            >
              View
            </Button>
            {canApprove && isEmployeeApprover && record.status === 'PENDING' && (
              <>
                <Button 
                  icon={<CheckCircleOutlined />} 
                  size="small"
                  type="primary"
                  onClick={() => handleApproveRejectClick(record, 'approve')}
                  className="pending-approvals-approve-button"
                >
                  Approve
                </Button>
                <Button 
                  icon={<CloseCircleOutlined />} 
                  size="small"
                  type="primary"
                  onClick={() => handleApproveRejectClick(record, 'reject')}
                >
                  Reject
                </Button>
              </>
            )}
            {!canApprove && (
              <Tag color="default">Own Request</Tag>
            )}
          </Space>
        );
      }
    }
  ];

  return (
    <div className="pending-approvals-dashboard">
      <Card
        title={
          <div className="pending-approvals-header">
            <span>Pending Approvals Dashboard</span>
            <Badge count={referralRequests.length} showZero>
              <Button 
                icon={<ReloadOutlined />}
                onClick={() => fetchPendingReferralRequests(pagination.current, pagination.pageSize)}
              >
                Refresh
              </Button>
            </Badge>
          </div>
        }
        extra={
          <Space>
            <Select
              placeholder="Filter by Action Type"
              className="pending-approvals-filter-select"
              allowClear
              value={filters.action_type || undefined}
              onChange={(value) => setFilters({ ...filters, action_type: value || '' })}
            >
              <Option value="CREATE">Create</Option>
              <Option value="UPDATE">Update</Option>
              <Option value="DELETE">Delete</Option>
              <Option value="TERMINATE">Terminate</Option>
            </Select>
            <Input
              placeholder="Search by National ID"
              className="pending-approvals-filter-input"
              value={filters.national_id}
              onChange={(e) => setFilters({ ...filters, national_id: e.target.value })}
              allowClear
            />
          </Space>
        }
        className="pending-approvals-card"
      >
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
          data={getFilteredRequests()}
          loading={false}
          pagination={pagination}
          pageSize={Number(pagination.pageSize) || 15}
          showSearch={true}
          showRefresh={false}
          searchPlaceholder="Search requests..."
          rowKey={(record) => record?.id || `row-${record?.national_id}`}
          size="middle"
          bordered={true}
          onChange={(paginationInfo) => {
            if (paginationInfo) {
              const newPage = paginationInfo.current || 1;
              const newPageSize = Number(paginationInfo.pageSize) || 15;
              
              if (newPage !== pagination.current || newPageSize !== pagination.pageSize) {
                fetchPendingReferralRequests(newPage, newPageSize);
              }
            }
          }}
        />
        </div>
      </Card>

      {/* View Request Details Modal */}
      <Modal
        title="Referral Request Details"
        open={isViewModalOpen}
        onCancel={() => {
          if (!showCommentSection) {
            setIsViewModalOpen(false);
            setSelectedRequest(null);
            setActionType(null);
            setRemarks('');
            setViewModalLoading(false);
          } else {
            handleCommentCancel();
          }
        }}
        footer={(() => {
          // Check if current user can approve (cannot approve own request)
          const canApproveRecord = selectedRequest && currentUser && 
            (selectedRequest.initiator?.username !== currentUser?.nida && 
             selectedRequest.initiator?.username !== currentUser?.username &&
             selectedRequest.initiated_by !== currentUser?.nida &&
             selectedRequest.initiated_by !== currentUser?.username &&
             selectedRequest.created_by !== currentUser?.nida &&
             selectedRequest.created_by !== currentUser?.username);
          const isEmployeeApprover = hasEmployeeApproverRole(selectedRole);

          if (showCommentSection) {
            // Show comment section with Submit/Cancel buttons
            return [
              <Button key="cancel" onClick={handleCommentCancel}>
                Cancel
              </Button>,
              <Button
                key="submit"
                type="primary"
                icon={actionType === 'approve' ? <CheckCircleOutlined /> : <CloseCircleOutlined />}
                onClick={handleApprovalSubmit}
                loading={approvalLoading}
                className={actionType === 'approve' ? 'pending-approvals-submit-button' : 'pending-approvals-submit-button'}
              >
                {actionType === 'approve' ? 'Approve' : 'Reject'}
              </Button>
            ];
          } else if (canApproveRecord && isEmployeeApprover && selectedRequest?.status === 'PENDING') {
            // Show Approve/Reject buttons
            return [
              <Button key="close" onClick={() => {
                setIsViewModalOpen(false);
                setSelectedRequest(null);
                setViewModalLoading(false);
              }}>
                Close
              </Button>,
              <Button
                key="reject"
                type="primary"
                icon={<CloseCircleOutlined />}
                onClick={() => handleApproveRejectClick('reject')}
                className="pending-approvals-submit-button"
              >
                Reject
              </Button>,
              <Button
                key="approve"
                type="primary"
                icon={<CheckCircleOutlined />}
                onClick={() => handleApproveRejectClick('approve')}
                className="pending-approvals-submit-button"
              >
                Approve
              </Button>
            ];
          } else {
            // Just show Close button
            return [
              <Button key="close" onClick={() => {
                setIsViewModalOpen(false);
                setSelectedRequest(null);
                setViewModalLoading(false);
              }}>
                Close
              </Button>
            ];
          }
        })()}
        width={800}
        className="pending-approvals-modal"
      >
        {viewModalLoading ? (
          <CollectionLoader size={64} compact />
        ) : selectedRequest ? (
            <div>
            <Row gutter={[16, 16]}>
              <Col span={12}>
                <div><strong>Request ID:</strong> {selectedRequest.id}</div>
              </Col>
              <Col span={12}>
                <div><strong>Action Type:</strong> {getActionTypeDisplay(selectedRequest.action_type)}</div>
              </Col>
              <Col span={12}>
                <div><strong>Status:</strong> 
                  <Tag color="orange" className="pending-approvals-status-tag">Pending</Tag>
                </div>
              </Col>
              <Col span={12}>
                <div><strong>Created At:</strong> {
                  selectedRequest.created_at 
                    ? dayjs(selectedRequest.created_at).format('YYYY-MM-DD HH:mm:ss')
                    : 'N/A'
                }</div>
              </Col>
              <Col span={12}>
                <div><strong>Initiated By:</strong> {
                  selectedRequest.initiator?.username || 
                  selectedRequest.initiated_by || 
                  selectedRequest.created_by || 
                  'N/A'
                }</div>
              </Col>
              <Col span={12}>
                <div><strong>National ID:</strong> {selectedRequest.national_id || 'N/A'}</div>
              </Col>
            </Row>
            
            {selectedRequest.employee && (
              <Card title="Employee Details" className="pending-approvals-detail-card" size="small">
                <Row gutter={[16, 8]}>
                  <Col span={12}>
                    <div><strong>Name:</strong> {
                      selectedRequest.employee.full_name || 
                      (selectedRequest.employee.fname || selectedRequest.employee.sname
                        ? `${selectedRequest.employee.fname || ''} ${selectedRequest.employee.sname || ''}`.trim()
                        : `${selectedRequest.employee.first_name || ''} ${selectedRequest.employee.surname || ''}`.trim()) || 
                      'N/A'
                    }</div>
                  </Col>
                  <Col span={12}>
                    <div><strong>Email:</strong> {selectedRequest.employee.email || 'N/A'}</div>
                  </Col>
                  <Col span={12}>
                    <div><strong>PF Number:</strong> {
                      (() => {
                        const employee = selectedRequest.employee;
                        const hasPF = employee.pfno && employee.pfno.trim() !== '';
                        const isCreateAction = selectedRequest.action_type === 'CREATE';
                        
                        // Check if office auto-generates PF
                        const office = employee.office || employee.employment_details?.office;
                        const autoGeneratesPF =
                          office?.auto_generate_pf === true ||
                          office?.auto_generate_pf === 1 ||
                          office?.auto_generate_pf === '1' ||
                          office?.auto_generate_pf === 'true' ||
                          office?.auto_generate_pf === 'TRUE';
                        
                        if (isCreateAction && !hasPF) {
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
                        return hasPF ? employee.pfno : 'N/A';
                      })()
                    }</div>
                  </Col>
                  {selectedRequest.employee.office && (
                    <Col span={12}>
                      <div><strong>Office:</strong> {
                        selectedRequest.employee.office.office_name || 
                        selectedRequest.employee.employment_details?.office?.office_name ||
                        'N/A'
                      }</div>
                    </Col>
                  )}
                  <Col span={12}>
                    <div><strong>Mobile:</strong> {selectedRequest.employee.mobile || 'N/A'}</div>
                  </Col>
                </Row>
              </Card>
            )}
            
            {selectedRequest.proposed_changes && (
              <Card title="Proposed Changes" className="pending-approvals-detail-card" size="small">
                <pre className="pending-approvals-proposed-changes-pre">
                  {JSON.stringify(selectedRequest.proposed_changes, null, 2)}
                </pre>
              </Card>
            )}
            
            {selectedRequest.description && (
              <Card title="Description" className="pending-approvals-detail-card" size="small">
                <div>{selectedRequest.description}</div>
              </Card>
            )}

            {/* Comment Section - shown when approve/reject is clicked */}
            {showCommentSection && (
              <Card 
                title={actionType === 'approve' ? 'Approve Request' : 'Reject Request'} 
                className="pending-approvals-comment-section" 
                size="small"
              >
                <div className="pending-approvals-comment-textarea-wrapper">
                  <TextArea
                    rows={4}
                    value={remarks}
                    onChange={(e) => setRemarks(e.target.value)}
                    placeholder={
                      actionType === 'approve'
                        ? 'Add any comments (optional)'
                        : 'Please provide a reason for rejection (required)'
                    }
                  />
                  <div className="pending-approvals-comment-hint">
                    {actionType === 'approve'
                      ? 'Comments are optional for approval'
                      : 'Comments are required for rejection'}
                  </div>
                </div>
              </Card>
            )}
          </div>
          ) : (
            <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
              No request data available
            </div>
          )}
      </Modal>
    </div>
  );
};

export default PendingApprovalsDashboard;

