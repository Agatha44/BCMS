import { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';
import { Card, Table, Tag, Button, Row, Col, Descriptions, Modal, App, Form, Input, Space } from 'antd';
import { CalendarOutlined, ClockCircleOutlined, CheckCircleOutlined, HourglassOutlined, CloseCircleOutlined, CheckOutlined, AuditOutlined, RollbackOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { overtimeService } from '../../../services/overtimeService.js';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';

const { TextArea } = Input;

/** Logic field from API — Pending, Validator Approved, Returned, etc. */
const normalizeRecordStatus = (status) => {
  if (status === undefined || status === null || status === '') return '';
  return String(status).trim().toLowerCase();
};

const isPendingRecord = (status) => normalizeRecordStatus(status) === 'pending';
const isValidatorApprovedRecord = (status) => normalizeRecordStatus(status) === 'validator approved';

const OvertimeDetailModal = ({ open, onClose, overtimeId, onRefresh, onEditResubmit }) => {
  const { message } = App.useApp();
  const selectedRole = useSelector((state) => state.app.selectedRole);
  
  const [loading, setLoading] = useState(false);
  const [overtimeRecord, setOvertimeRecord] = useState(null);
  const [approvalActionType, setApprovalActionType] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [form] = Form.useForm();

  // Helper function to normalize overtime data from API response
  const normalizeOvertimeData = (data) => {
    // Convert history object to array if needed (API returns object with numeric keys)
    let historyData = [];
    if (data.history) {
      if (Array.isArray(data.history)) {
        historyData = data.history;
      } else if (typeof data.history === 'object') {
        historyData = Object.values(data.history);
      }
    }
    
    return {
      ...data,
      history: historyData,
    };
  };

  // Fetch overtime detail from API
  useEffect(() => {
    const fetchOvertimeDetail = async () => {
      if (!overtimeId || !open) return;
      
      setLoading(true);
      try {
        const response = await overtimeService.getOvertimeRecordById(overtimeId);
        if (response.success && response.data) {
          const normalizedData = normalizeOvertimeData(response.data);
          setOvertimeRecord(normalizedData);
        } else {
          message.error(response.message);
        }
      } catch (error) {
        console.error('Error fetching overtime detail:', error);
        message.error('Failed to fetch overtime details. Please try again.');
      } finally {
        setLoading(false);
      }
    };

    fetchOvertimeDetail();
  }, [overtimeId, open]);

  // Disable body scroll when modal is open
  useEffect(() => {
    if (open) {
      // Save current overflow style
      const originalOverflow = document.body.style.overflow;
      // Disable body scroll
      document.body.style.overflow = 'hidden';
      
      // Cleanup: restore original overflow when modal closes
      return () => {
        document.body.style.overflow = originalOverflow;
      };
    }
  }, [open]);

  // Reset approvalActionType and form when modal closes
  useEffect(() => {
    if (!open) {
      setApprovalActionType(null);
      // Only reset form if overtimeRecord exists (Form is rendered)
      // This prevents the warning about form not being connected
      if (overtimeRecord) {
        try {
          form.resetFields();
        } catch (error) {
          // Form not connected yet, ignore
        }
      }
    }
  }, [open, form, overtimeRecord]);

  // Auto-open comment area for validators and reviewers
  useEffect(() => {
    if (!open || !overtimeRecord || approvalActionType) return;
    
    const checkCanPerformAction = (actionType) => {
      const recordStatus = overtimeRecord?.status;
      if (!recordStatus) return false;

      const normalizedStatus = normalizeRecordStatus(recordStatus);

      if (['rejected', 'paid', 'returned'].includes(normalizedStatus)) {
        return false;
      }

      if (actionType === 'validator' && !isPendingRecord(recordStatus)) return false;
      if (actionType === 'reviewer' && !isValidatorApprovedRecord(recordStatus)) return false;

      const normalizedSelected =
        selectedRole
          ? String(selectedRole).toLowerCase().trim().replace(/\s+/g, '_')
          : '';

      if (!normalizedSelected.startsWith('overtime_')) {
        return false;
      }

      if (actionType === 'validator') {
        return normalizedSelected === 'overtime_validator';
      }
      if (actionType === 'reviewer') {
        return normalizedSelected === 'overtime_reviewer';
      }
      return false;
    };
    
    // Check if user can perform validator action
    if (checkCanPerformAction('validator')) {
      setApprovalActionType('validator');
      return;
    }
    
    // Check if user can perform reviewer action
    if (checkCanPerformAction('reviewer')) {
      setApprovalActionType('reviewer');
      return;
    }
  }, [open, overtimeRecord, selectedRole, approvalActionType]);

  const handleApproveClick = async () => {
    try {
      const values = await form.validateFields();
      await handleApproval(approvalActionType, values.comment ?? '');
    } catch (error) {
      if (error.errorFields) {
        message.error('Please fill in all required fields');
      }
    }
  };

  // Handle reject button click
  const handleRejectClick = async () => {
    try {
      const values = await form.validateFields();
      if (!values.comment || values.comment.trim() === '') {
        message.error('Please provide a reason for rejection');
        return;
      }
      await handleRejection(approvalActionType, values.comment);
    } catch (error) {
      if (error.errorFields) {
        message.error('Please provide a reason for rejection');
      }
    }
  };

  const handleReturnClick = async () => {
    try {
      const values = await form.validateFields();
      if (!values.comment || values.comment.trim() === '') {
        message.error('Please provide a reason for returning to the applicant');
        return;
      }
      await handleReturn(values.comment);
    } catch (error) {
      if (error.errorFields) {
        message.error('Please provide a reason for returning to the applicant');
      }
    }
  };

  const handleReturn = async (comment) => {
    setActionLoading(true);
    try {
      const response = await overtimeService.returnOvertime(overtimeId, {
        comment: comment ?? '',
      });

      if (response.success) {
        message.success(response.message);
        setApprovalActionType(null);
        form.resetFields();
        const refreshResponse = await overtimeService.getOvertimeRecordById(overtimeId);
        if (refreshResponse.success && refreshResponse.data) {
          setOvertimeRecord(normalizeOvertimeData(refreshResponse.data));
        }
        if (onRefresh) {
          onRefresh();
        }
        if (onClose) {
          onClose();
        }
      } else {
        message.error(response.message);
      }
    } catch (error) {
      console.error('Error returning overtime:', error);
      message.error(error.message);
    } finally {
      setActionLoading(false);
    }
  };

  // Handle approval action
  const handleApproval = async (actionType, comment) => {
    setActionLoading(true);
    try {
      let response;
      const data = { 
        comment: comment ?? '',
        // Capture the exact overtime role the user is using for this approval
        performedByRole: selectedRole || null,
      };
      
      switch (actionType) {
        case 'validator':
          response = await overtimeService.validatorApproveOvertime(overtimeId, data);
          break;
        case 'reviewer':
          response = await overtimeService.reviewerApproveOvertime(overtimeId, data);
          break;
        default:
          throw new Error('Invalid action type');
      }
      
      if (response.success) {
        message.success(response.message);
        form.resetFields();
        // Refresh the overtime record from backend API
        const refreshResponse = await overtimeService.getOvertimeRecordById(overtimeId);
        if (refreshResponse.success && refreshResponse.data) {
          const normalizedData = normalizeOvertimeData(refreshResponse.data);
          setOvertimeRecord(normalizedData);
        }
        setApprovalActionType(null);
        // Call onRefresh if provided to refresh the parent list
        if (onRefresh) {
          onRefresh();
        }
        // Close modal after successful validation or review
        if (onClose) {
          onClose();
        }
      } else {
        message.error(response.message);
        setApprovalActionType(null);
      }
    } catch (error) {
      console.error('Error performing approval action:', error);
      message.error('Failed to complete action. Please try again.');
    } finally {
      setActionLoading(false);
    }
  };

  // Handle rejection
  const handleRejection = async (actionType, comment) => {
    setActionLoading(true);
      try {
      const response = await overtimeService.rejectOvertime(overtimeId, { 
        comment: comment ?? '',
        // Capture the exact overtime role the user is using for this rejection
        performedByRole: selectedRole || null,
      });
      
      if (response.success) {
        message.success(response.message);
        setApprovalActionType(null);
        form.resetFields();
        // Refresh the overtime record from backend API
        const refreshResponse = await overtimeService.getOvertimeRecordById(overtimeId);

        if (refreshResponse.success && refreshResponse.data) {
          const normalizedData = normalizeOvertimeData(refreshResponse.data);
          setOvertimeRecord(normalizedData);
        }
        // Call onRefresh if provided to refresh the parent list
        if (onRefresh) {
          onRefresh();
        }
        // Close modal after rejection
        if (onClose) {
          onClose();
        }
      } else {
        message.error(response.message);
      }
    } catch (error) {
      console.error('Error rejecting overtime:', error);
      message.error('Failed to reject overtime request. Please try again.');
    } finally {
      setActionLoading(false);
    }
  };

  // Get status icon (styles based on workflowStatus from backend)
  const getStatusIcon = (status) => {
    if (!status) return <ClockCircleOutlined />;
    const normalized = String(status).trim().toLowerCase();

    if (normalized === 'applied') {
      return <HourglassOutlined style={{ color: '#faad14' }} />;
    }
    if (normalized === 'validated') {
      return <CheckCircleOutlined style={{ color: '#1890ff' }} />;
    }
    if (normalized === 'reviewed') {
      return <CheckCircleOutlined style={{ color: '#722ed1' }} />;
    }
    if (normalized === 'approved') {
      return <CheckCircleOutlined style={{ color: '#52c41a' }} />;
    }
    if (normalized === 'paid') {
      return <CheckCircleOutlined style={{ color: '#389e0d' }} />;
    }
    if (normalized === 'rejected') {
      return <CloseCircleOutlined style={{ color: '#ff4d4f' }} />;
    }
    if (normalized === 'returned') {
      return <RollbackOutlined style={{ color: '#fa8c16' }} />;
    }

    return <ClockCircleOutlined />;
  };

  // UI label colours — workflowStatus only
  const getStatusColor = (status) => {
    if (!status) return 'default';
    const normalized = String(status).trim().toLowerCase();

    if (normalized === 'applied') return 'orange';
    if (normalized === 'validated') return 'blue';
    if (normalized === 'returned') return 'warning';
    if (normalized === 'reviewed') return 'purple';
    if (normalized === 'approved') return 'green';
    if (normalized === 'paid') return 'green';
    if (normalized === 'rejected') return 'red';

    return 'default';
  };

  // UI label — workflowStatus (Applied, Validated, Returned, …)
  const getDisplayStatus = () => {
    if (!overtimeRecord?.workflowStatus) return 'Unknown';
    return String(overtimeRecord.workflowStatus).trim();
  };

  // Table columns for days - optimized for no horizontal scrolling
  const daysColumns = [
    {
      title: 'SN',
      key: 'serialNumber',
      width: 61,
      align: 'center',
      render: (_, __, index) => index + 1,
    },
    {
      title: 'Overtime Date',
      dataIndex: 'dayDate',
      key: 'dayDate',
      width: 116,
      render: (date) => (
        <span style={{ fontSize: 11 }}>
          <CalendarOutlined style={{ marginRight: 2, fontSize: 11 }} />
          {dayjs(date).format('DD-MMM-YY')}
        </span>
      ),
    },
    {
      title: 'Overtime Hours',
      dataIndex: 'overtimeHours',
      key: 'overtimeHours',
      width: 127,
      align: 'right',
      render: (hours) => {
        return (
          <span style={{ fontWeight: 500, color: '#000000', fontSize: 11 }}>
            {hours}
          </span>
        );
      },
    },
    {
      title: 'Overtime Rate',
      dataIndex: 'daily_rate',
      key: 'daily_rate',
      width: 126,
      align: 'right',
      render: (rate) => {
        const formattedRate = new Intl.NumberFormat('en-TZ', {
          minimumFractionDigits: 0,
        }).format(rate || 0);
        return (
          <span style={{ color: '#000000', fontSize: 11 }}>
            {formattedRate}
          </span>
        );
      },
    },
  ];

  const totalAmount = overtimeRecord?.days?.reduce((sum, day) => sum + day.amount, 0) ?? 0;

  const canResubmit = Boolean(overtimeRecord?.canResubmit);

  return (
    <>
      <Modal
        title={
          <span>
            <ClockCircleOutlined style={{ marginRight: 8 }} />
            Overtime Details
          </span>
        }
        open={open}
        onCancel={onClose}
        footer={
          <Space style={{ width: '100%', justifyContent: 'flex-end' }}>
            {approvalActionType && (
              <>
                <Button
                  type="primary"
                  icon={approvalActionType === 'validator' ? <CheckOutlined /> : <AuditOutlined />}
                  onClick={handleApproveClick}
                  loading={actionLoading}
                  style={{
                    backgroundColor: '#962E32',
                    borderColor: '#962E32',
                  }}
                >
                  {approvalActionType === 'validator' ? 'Validate' : 'Approve'}
                </Button>
                <Button
                  icon={<RollbackOutlined />}
                  onClick={handleReturnClick}
                  loading={actionLoading}
                  style={{
                    borderColor: '#fa8c16',
                    color: '#fa8c16',
                  }}
                >
                  Return
                </Button>
                <Button
                  danger
                  icon={<CloseCircleOutlined />}
                  onClick={handleRejectClick}
                  loading={actionLoading}
                >
                  Reject
                </Button>
              </>
            )}
            {!approvalActionType && canResubmit && onEditResubmit && (
              <Button
                type="primary"
                icon={<RollbackOutlined />}
                onClick={() => onEditResubmit(overtimeId)}
                style={{
                  backgroundColor: '#962E32',
                  borderColor: '#962E32',
                }}
              >
                Edit & Resubmit
              </Button>
            )}
            <Button
              type="default"
              onClick={onClose}
              style={{
                minWidth: '120px',
              }}
            >
              Close
            </Button>
          </Space>
        }
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 1280}
        destroyOnHidden={true}
        centered={false}
        style={{ 
          top: 20,
          paddingBottom: 0,
          maxHeight: 'calc(100vh - 40px)',
        }}
        styles={{ 
          body: { 
            maxHeight: 'calc(100vh - 140px)', 
            overflowY: 'visible',
            overflowX: 'hidden',
            padding: '16px',
          },
          content: {
            maxHeight: 'calc(100vh - 80px)',
            display: 'flex',
            flexDirection: 'column',
            overflow: 'visible',
          },
          wrapper: {
            overflow: 'hidden',
          }
        }}
        className="hide-scrollbar-modal"
      >
        {loading ? (
          <CollectionLoader size={64} compact />
        ) : !overtimeRecord ? (
          <div style={{ textAlign: 'center', padding: '40px' }}>Overtime record not found</div>
        ) : (
          <>
          <Row gutter={[12, 12]}>
            {/* Left Side - Days Table and Summary */}
            <Col xs={24} lg={14}>
              {/* Employee Information */}
              <Card
                title="Employee Information"
                style={{ marginBottom: 12 }}
                size="small"
              >
                <Descriptions column={2} size="small">
                  <Descriptions.Item label="Employee ID">
                    {overtimeRecord.pfNumber}
                  </Descriptions.Item>
                  <Descriptions.Item label="Employee Name">
                    {overtimeRecord.employeeName}
                  </Descriptions.Item>
                  <Descriptions.Item label="Overtime Month">
                    {overtimeRecord.monthDisplay}
                  </Descriptions.Item>
                  <Descriptions.Item label="Overtime Status">
                    <Tag color={getStatusColor(getDisplayStatus())} icon={getStatusIcon(getDisplayStatus())}>
                      {getDisplayStatus()}
                    </Tag>
                  </Descriptions.Item>
                  <Descriptions.Item label="Submitted Date">
                    {dayjs(overtimeRecord.createdAt).format('DD-MMM-YYYY')}
                  </Descriptions.Item>
                </Descriptions>
              </Card>

              {/* Days Table */}
              <Card
                title={
                  <span>
                    <CalendarOutlined style={{ marginRight: 8 }} />
                    Overtime Days ({overtimeRecord.totalDays})
                  </span>
                }
                style={{ marginBottom: 12 }}
              >
                <Table
                  columns={daysColumns}
                  dataSource={overtimeRecord.days}
                  pagination={false}
                  rowKey={(record) => record.id}
                  size="small"
                  bordered
                  components={{
                    header: {
                      cell: (props) => (
                        <th
                          {...props}
                          style={{
                            ...props.style,
                            backgroundColor: '#962E32',
                            color: '#ffffff',
                            fontWeight: 600,
                          }}
                        />
                      ),
                    },
                  }}
                  summary={(pageData) => {
                    const totalAmount = pageData.reduce((sum, record) => sum + record.amount, 0);
                    
                    return (
                      <Table.Summary fixed>
                        <Table.Summary.Row style={{ backgroundColor: '#fafafa', fontWeight: 600 }}>
                          <Table.Summary.Cell index={0} align="center">
                            <span style={{ fontSize: 12, fontWeight: 600 }}>Total:</span>
                          </Table.Summary.Cell>
                          <Table.Summary.Cell index={2} align="center">
                            <span style={{ color: '#000000', fontSize: '12px', fontWeight: 600 }}>
                              
                            </span>
                          </Table.Summary.Cell>
                          <Table.Summary.Cell index={3} colSpan={1}>
                            {' '}
                          </Table.Summary.Cell>
                          <Table.Summary.Cell index={4} align="right">
                            <span style={{ fontWeight: 600, color: '#000000', fontSize: 12 }}>
                              TSh {new Intl.NumberFormat('en-TZ', { minimumFractionDigits: 0 }).format(totalAmount)}
                            </span>
                          </Table.Summary.Cell>
                        </Table.Summary.Row>
                      </Table.Summary>
                    );
                  }}
                />
              </Card>
            </Col>

            {/* Right Side - Minutes */}
            <Col xs={24} lg={10}>
              <Card
                title={
                  <span>
                    <ClockCircleOutlined style={{ marginRight: 8 }} />
                    Minutes
                  </span>
                }
                style={{ 
                  position: 'sticky', 
                  top: 24,
                  maxHeight: 'calc(100vh - 200px)',
                  display: 'flex',
                  flexDirection: 'column',
                }}
                styles={{
                  body: {
                    flex: 1,
                    display: 'flex',
                    flexDirection: 'column',
                    overflow: 'hidden',
                    padding: '16px',
                  },
                }}
              >
                <div style={{
                  display: 'flex',
                  flexDirection: 'column',
                  flex: 1,
                  minHeight: 0,
                }}>
                  {overtimeRecord.history && Array.isArray(overtimeRecord.history) && overtimeRecord.history.length > 0 ? (
                    <div style={{ 
                      flex: 1,
                      overflowY: 'auto',
                      overflowX: 'hidden',
                      paddingRight: '4px',
                      minHeight: 0,
                    }}>
                      {overtimeRecord.history
                      .slice()
                      .sort((a, b) => {
                        // Sort by id (higher id = more recent)
                        return (b.id || 0) - (a.id || 0);
                      })
                      .reverse() // Reverse to show oldest first
                      .map((item, index) => {
                        // Helper to format date - check common timestamp field names
                        const formatDate = (historyItem) => {
                          if (!historyItem?.timestamp) return 'N/A';
                          return dayjs(historyItem.timestamp).format('DD MMM YYYY');
                        };

                        // Helper to get status tag from workflowStatus (minutes)
                        const getStatusTag = (workflowStatus) => {
                          if (!workflowStatus) return null;
                          const normalized = String(workflowStatus).trim().toLowerCase();

                          if (normalized === 'applied') {
                            return (
                              <Tag color="orange" icon={<HourglassOutlined />}>
                                Applied
                              </Tag>
                            );
                          }
                          if (normalized === 'validated') {
                            return (
                              <Tag color="#962E32" icon={<CheckCircleOutlined />}>
                                Validated
                              </Tag>
                            );
                          }
                          if (normalized === 'reviewed') {
                            return (
                              <Tag color="purple" icon={<CheckCircleOutlined />}>
                                Reviewed
                              </Tag>
                            );
                          }
                          if (normalized === 'approved') {
                            return (
                              <Tag color="green" icon={<CheckCircleOutlined />}>
                                Approved
                              </Tag>
                            );
                          }
                          if (normalized === 'paid') {
                            return (
                              <Tag color="green" icon={<CheckCircleOutlined />}>
                                Paid
                              </Tag>
                            );
                          }
                          if (normalized === 'rejected') {
                            return (
                              <Tag color="red" icon={<CloseCircleOutlined />}>
                                Rejected
                              </Tag>
                            );
                          }
                          if (normalized === 'returned') {
                            return (
                              <Tag color="warning" icon={<RollbackOutlined />}>
                                Returned
                              </Tag>
                            );
                          }

                          return null;
                        };

                        return (
                          <div
                            key={item.id}
                            style={{
                              marginBottom: 12,
                              padding: '10px',
                              border: '1px solid #f0f0f0',
                              borderRadius: 6,
                              backgroundColor: '#fafafa'
                            }}
                          >
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
                              <strong style={{ fontSize: 13 }}>{item.action}</strong>
                              <div style={{ fontSize: 11, color: '#8c8c8c' }}>
                                {formatDate(item)}
                              </div>
                            </div>
                            <div
                              style={{
                                marginBottom: 4,
                                wordBreak: 'break-word',
                              }}
                            >
                              <strong style={{ fontSize: 12 }}>
                                {item.performedBy}
                                {item.performedByRole && (
                                  <span style={{ color: '#8c8c8c', fontSize: 12 }}>
                                    {' '}({item.performedByRole})
                                  </span>
                                )}
                              </strong>
                            </div>
                            {item.comment && (
                              <div
                                style={{
                                  marginTop: 6,
                                  padding: '6px 10px',
                                  backgroundColor: '#e6f7ff',
                                  borderRadius: 4,
                                  fontSize: 11,
                                  color: '#595959',
                                  lineHeight: 1.4
                                }}
                              >
                                {item.comment}
                              </div>
                            )}
                            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8 }}>
                              {getStatusTag(item.workflowStatus)}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  ) : (
                    <div style={{ textAlign: 'center', padding: '20px', color: '#999' }}>
                      No minutes available
                    </div>
                  )}

                  {/* Approval Form - Conditionally show */}
                  {approvalActionType && (
                    <div style={{ 
                      marginTop: 16, 
                      paddingTop: 16, 
                      borderTop: '1px solid #f0f0f0',
                      flexShrink: 0,
                    }}>
                    {/* Note: Using the form instance from the hidden Form above */}
                    <div>
                      <Form
                        form={form}
                        layout="vertical"
                        autoComplete="off"
                      >
                        <Form.Item
                          name="comment"
                          label="Comment"
                          rules={[
                            {
                              required: false,
                              message: 'Comment is optional but recommended',
                            },
                          ]}
                        >
                          <TextArea
                            rows={4}
                            placeholder="Enter your comment or remark..."
                            maxLength={500}
                            showCount
                          />
                        </Form.Item>
                      </Form>
                    </div>
                  </div>
                  )}
                </div>
              </Card>
            </Col>
          </Row>
          </>
        )}
        
        {/* Form component - Always render to keep form instance connected (hidden when overtimeRecord is null) */}
        {!overtimeRecord && (
          <Form
            form={form}
            layout="vertical"
            autoComplete="off"
            style={{ 
              display: 'none',
              position: 'absolute',
              visibility: 'hidden',
              height: 0,
              width: 0,
              overflow: 'hidden'
            }}
          >
            <Form.Item name="comment">
              <TextArea />
            </Form.Item>
          </Form>
        )}
      </Modal>

    </>
  );
};

export default OvertimeDetailModal;

