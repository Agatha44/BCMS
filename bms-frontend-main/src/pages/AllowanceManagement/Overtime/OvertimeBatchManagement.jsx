import { useState, useEffect, useRef, useCallback } from 'react';
import { useSelector } from 'react-redux';
import { 
  Button, 
  App,
  Table, 
  Checkbox, 
  Space, 
  Row, 
  Col, 
  Card, 
  Input, 
  Tag,
  Typography,
  Badge,
  Tabs
} from 'antd';
import { 
  CheckCircleOutlined, 
  ReloadOutlined, 
  SearchOutlined,
  SaveOutlined,
  FileTextOutlined,
  CalendarOutlined,
  EyeOutlined
} from '@ant-design/icons';
import { overtimeService } from '../../../services/overtimeService.js';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import {
  canRepostOvertimeErms,
  extractArrayFromResponse,
  updatePaginationFromResponse,
} from '../../../common/utils/employeeUtils.jsx';
import '../../../styles/common.css';
import dayjs from 'dayjs';
import {
  canSubmitBatchToEoffice,
  formatReadinessText,
  getSubmissionReadinessBlockingMessages,
} from './submissionApprovalDocumentsUtils.js';
import OvertimeBatchDetailsModal from './components/OvertimeBatchDetailsModal.jsx';

const { Text } = Typography;

// Constants and helper functions
const BUTTON_STYLE = { backgroundColor: '#8B0000', borderColor: '#8B0000', color: '#ffffff' };
const BUTTON_HOVER_STYLE = { backgroundColor: '#A52A2A', borderColor: '#A52A2A' };

const createButtonHoverHandlers = (disabled = false) => ({
  onMouseEnter: (e) => {
    if (!disabled) {
      Object.assign(e.currentTarget.style, BUTTON_HOVER_STYLE);
    }
  },
  onMouseLeave: (e) => {
    if (!disabled) {
      Object.assign(e.currentTarget.style, BUTTON_STYLE);
    }
  }
});

const getSerialNumber = (index, pagination) => {
  const current = pagination.current || 1;
  const pageSize = pagination.pageSize || 15;
  return (current - 1) * pageSize + index + 1;
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return dayjs(date).format('DD-MMM-YYYY HH:mm');
};

const OvertimeBatchManagement = () => {
  const { message, modal } = App.useApp();
  const selectedRole = useSelector((state) => state.app.selectedRole);
  const canRepostErms = canRepostOvertimeErms(selectedRole);
  const [loading, setLoading] = useState(false);
  const [readyRecords, setReadyRecords] = useState([]);
  const [selectedRecords, setSelectedRecords] = useState(new Set());
  const [batches, setBatches] = useState([]);
  const [activeTab, setActiveTab] = useState('ready');
  const [searchText, setSearchText] = useState('');
  const createPagination = () => ({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  const [pagination, setPagination] = useState(createPagination());
  const [batchPagination, setBatchPagination] = useState(createPagination());
  const [isBatchDetailsModalOpen, setIsBatchDetailsModalOpen] = useState(false);
  const [currentBatchData, setCurrentBatchData] = useState(null);
  const [activeBatchTab, setActiveBatchTab] = useState('details');
  const [repostingErms, setRepostingErms] = useState(false);
  const [postingErms, setPostingErms] = useState(false);
  const isFetchingReady = useRef(false);
  const isFetchingBatches = useRef(false);
  const lastReadyFetchKey = useRef('');
  const lastBatchesFetchKey = useRef('');

  // Fetch records ready for batch
  const fetchReadyRecords = async (page = 1, pageSize = 15, force = false) => {
    const fetchKey = `ready-${page}-${pageSize}`;
    if (!force && (isFetchingReady.current || lastReadyFetchKey.current === fetchKey)) {
      return;
    }
    isFetchingReady.current = true;
    lastReadyFetchKey.current = fetchKey;
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };

      const response = await overtimeService.getReadyForBatch(params);

      if (response.success && response.data) {
        const { items: dataArray, pagination: paginationData } = extractArrayFromResponse(response.data);
        
        const transformedData = dataArray.map((record) => ({
          id: record.id,
          employeeName: record.employeeName,
          pfNumber: record.pfNumber,
          month: record.month,
          monthDisplay: record.monthDisplay || (record.month ? dayjs(record.month).format('MMMM YYYY') : ''),
          totalOvertimeHours: record.totalOvertimeHours || record.total_overtime_hours || 0,
          totalDays: record.totalDays || record.total_days || 0,
          status: record.status,
          // Prefer new `workflowstatus` field from API
          workflowStatus:
            record.workflowstatus ||
            record.workflowStatus ||
            record.workflow_status,
        }));

        const totalCount = response.data.count || response.data.total || paginationData?.total || transformedData.length;
        
        setReadyRecords(transformedData);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch records ready for batch');
        setReadyRecords([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching ready records:', error);
      message.error('Failed to fetch records ready for batch. Please try again.');
      setReadyRecords([]);
      lastReadyFetchKey.current = '';
    } finally {
      setLoading(false);
      isFetchingReady.current = false;
    }
  };

  // Fetch existing batches
  const fetchBatches = async (page = 1, pageSize = 15, force = false) => {
    const fetchKey = `batches-${page}-${pageSize}`;
    if (!force && (isFetchingBatches.current || lastBatchesFetchKey.current === fetchKey)) {
      return;
    }
    isFetchingBatches.current = true;
    lastBatchesFetchKey.current = fetchKey;
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };

      const response = await overtimeService.listBatches(params);

      if (response.success && response.data) {
        const { items: dataArray, pagination: paginationData } = extractArrayFromResponse(response.data);
        
        const transformedData = dataArray.map((batch) => ({
          id: batch.id,
          batchNumber: batch.batch_number || `BATCH-${batch.id}`,
          status: batch.status,
          totalRecords: batch.total_requests,
          totalAmount: batch.total_amount,
          createdAt: batch.created_at,
        }));

        const totalCount = response.data.count || response.data.total || paginationData?.total || transformedData.length;
        
        setBatches(transformedData);
        setBatchPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        message.error(response.message || 'Failed to fetch batches');
        setBatches([]);
        setBatchPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching batches:', error);
      message.error('Failed to fetch batches. Please try again.');
      setBatches([]);
      lastBatchesFetchKey.current = '';
    } finally {
      setLoading(false);
      isFetchingBatches.current = false;
    }
  };

  const handleSelectRecord = (recordId, checked) => {
    setSelectedRecords(prev => {
      const newSelected = new Set(prev);
      checked ? newSelected.add(recordId) : newSelected.delete(recordId);
      return newSelected;
    });
  };

  const handleSelectAll = (checked) => {
    setSelectedRecords(checked ? new Set(readyRecords.map(r => r.id)) : new Set());
  };

  const handleSaveBatch = async () => {
    if (selectedRecords.size === 0) {
      message.warning('Please select at least one record to create a batch');
      return;
    }

    modal.confirm({
      title: 'Create Batch',
      content: `Are you sure you want to create a batch with ${selectedRecords.size} selected record(s)? The batch will be created and saved.`,
      okText: 'Yes, Create Batch',
      cancelText: 'Cancel',
      onOk: async () => {
        setLoading(true);
        try {
          const createResponse = await overtimeService.createBatch({
            overtime_ids: Array.from(selectedRecords)
          });
          
          if (createResponse.success && createResponse.data) {
            message.success(`Batch created successfully with ${selectedRecords.size} record(s)`);
            setSelectedRecords(new Set());
            await Promise.all([
              fetchReadyRecords(pagination.current, pagination.pageSize, true),
              fetchBatches(batchPagination.current, batchPagination.pageSize, true),
            ]);
            setActiveTab('batches');
          } else {
            message.error(createResponse.message || 'Failed to create batch');
          }
        } catch (error) {
          console.error('Error creating batch:', error);
          message.error('Failed to create batch. Please try again.');
        } finally {
          setLoading(false);
        }
      }
    });
  };

  const handleSubmitBatch = async (batchId) => {
    modal.confirm({
      title: 'Submit Batch',
      content: 'Are you sure you want to submit this batch to the EOffice system?.',
      okText: 'Yes, Submit',
      cancelText: 'Cancel',
      okButtonProps: { danger: true },
      onOk: async () => {
        setLoading(true);
        try {
          const response = await overtimeService.saveAndSubmitBatch(batchId, {
            action: 'submit'
          });

          if (response.success) {
            message.success('Batch submitted successfully to EOffice system');
            fetchBatches(batchPagination.current, batchPagination.pageSize);
            // Refresh current batch details if the details modal is open for this batch
            if (isBatchDetailsModalOpen && currentBatchData?.batchId === batchId) {
              overtimeService.getBatch(batchId)
                .then(batchResponse => {
                  if (batchResponse.success && batchResponse.data) {
                    setCurrentBatchData(prev => ({ ...prev, batch: batchResponse.data }));
                  }
                })
                .catch(() => {});
            }
          } else {
            message.error(response.message || 'Failed to submit batch');
          }
        } catch (error) {
          console.error('Error submitting batch:', error);
          message.error('Failed to submit batch. Please try again.');
        } finally {
          setLoading(false);
        }
      }
    });
  };

  const formatNumber = (amount) => {
    return new Intl.NumberFormat('en-TZ', {
      minimumFractionDigits: 0,
    }).format(amount || 0);
  };

  const handleViewBatch = async (batchId) => {
    setLoading(true);
    try {
      const response = await overtimeService.getBatch(batchId);

      if (response.success && response.data) {
        const batch = response.data;
        const recordsArray = Array.isArray(batch.overtime_requests) ? batch.overtime_requests : [];

        const processedRecords = recordsArray.map((record) => ({
          id: record.id,
          employeeName: record.employeeName || 'N/A',
          pfNumber: record.pfNumber,
          date: record.month ? dayjs(record.month).format('MMMM YYYY') : 'N/A',
          hours: record.totalOvertimeHours ?? 0,
          amount: record.totalAmount ?? 0,
        }));

        const batchTotalAmount = batch.total_amount ?? 0;
        const batchNumber = batch.batch_number || `BATCH-${batch.id}`;

        setActiveBatchTab('details');
        setCurrentBatchData({ batch, batchId: batch.id, batchNumber, processedRecords, batchTotalAmount });
        setIsBatchDetailsModalOpen(true);
      } else {
        message.error(response.message || 'Failed to fetch batch details');
      }
    } catch (error) {
      console.error('Error fetching batch details:', error);
      message.error('Failed to fetch batch details. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (activeTab === 'ready') {
      fetchReadyRecords(pagination.current, pagination.pageSize);
    } else {
      fetchBatches(batchPagination.current, batchPagination.pageSize);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab]);

  const filteredReadyRecords = readyRecords.filter(record => {
    if (!searchText) return true;
    const search = searchText.toLowerCase();
    return [record.employeeName, record.pfNumber, record.monthDisplay]
      .some(field => (field || '').toLowerCase().includes(search));
  });

  const getStatusTag = (status, workflowStatus) => {
    const ws = (workflowStatus || '').toString().toLowerCase();
    const statusMap = {
      paid: { color: 'green', label: 'Paid' },
      approved: { color: 'green', label: 'Approved' },
      rejected: { color: 'red', label: 'Rejected' },
      reviewed: { color: 'blue', label: 'Reviewed' },
      validated: { color: 'blue', label: 'Validated' },
      applied: { color: 'orange', label: 'Applied' }
    };
    const mapped = statusMap[ws];
    return <Tag color={mapped?.color || 'blue'}>{mapped?.label || status || 'Unknown'}</Tag>;
  };

  const readyColumns = [
    {
      title: (
        <Checkbox
          checked={readyRecords.length > 0 && selectedRecords.size === readyRecords.length}
          indeterminate={selectedRecords.size > 0 && selectedRecords.size < readyRecords.length}
          onChange={(e) => handleSelectAll(e.target.checked)}
        />
      ),
      key: 'select',
      width: 60,
      render: (_, record) => (
        <Checkbox
          checked={selectedRecords.has(record.id)}
          onChange={(e) => handleSelectRecord(record.id, e.target.checked)}
        />
      ),
    },
    {
      title: 'SN',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => getSerialNumber(index, pagination),
    },
    {
      title: 'Employee Name',
      dataIndex: 'employeeName',
      key: 'employeeName',
      width: 200,
    },
    {
      title: 'PF Number',
      dataIndex: 'pfNumber',
      key: 'pfNumber',
      width: 150,
    },
    {
      title: 'OvertimeMonth',
      dataIndex: 'monthDisplay',
      key: 'monthDisplay',
      width: 180,
      render: (month) => (
        <span>
          <CalendarOutlined style={{ marginRight: 4 }} />
          {month}
        </span>
      ),
    },
    {
      title: 'Overtime Days',
      dataIndex: 'totalDays',
      key: 'totalDays',
      width: 120,
      align: 'center',
      render: (days) => `${days} day${days !== 1 ? 's' : ''}`,
    },
    {
      title: 'Overtime Hours',
      dataIndex: 'totalOvertimeHours',
      key: 'totalOvertimeHours',
      width: 180,
      align: 'center',
      render: (hours) => (
        <span style={{ color: '#000000', fontSize: '14px' }}>
          {hours.toFixed(2)} hrs
        </span>
      ),
    },
    {
      title: 'Overtime Status',
      dataIndex: 'status',
      key: 'status',
      width: 150,
      align: 'center',
      render: (status, record) => getStatusTag(status, record.workflowStatus),
    },
  ];

  const batchColumns = [
    {
      title: 'SN',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => getSerialNumber(index, batchPagination),
    },
    {
      title: 'Batch Number',
      dataIndex: 'batchNumber',
      key: 'batchNumber',
      width: 200,
    },
    {
      title: 'Batch Records',
      dataIndex: 'totalRecords',
      key: 'totalRecords',
      width: 150,
      align: 'center',
    },
    {
      title: 'Batch Amount',
      dataIndex: 'totalAmount',
      key: 'totalAmount',
      width: 150,
      align: 'center',
      render: (totalAmount) => formatNumber(totalAmount),
    },
    {
      title: 'Batch Status',
      dataIndex: 'status',
      key: 'status',
      width: 150,
      align: 'center',
      render: (status) => {
        const s = (status || '').toLowerCase();
        const color = s === 'submitted' ? 'green' : s === 'pending' ? 'orange' : 'blue';
        return <Tag color={color}>{status || 'Unknown'}</Tag>;
      },
    },
    {
      title: 'Batch Date',
      dataIndex: 'createdAt',
      key: 'createdAt',
      width: 180,
      render: formatDate,
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 120,
      render: (_, record) => (
        <Button
          icon={<EyeOutlined />}
          onClick={() => handleViewBatch(record.id)}
          size="small"
          style={{ ...BUTTON_STYLE, borderRadius: '6px', boxShadow: '0 2px 4px rgba(0, 0, 0, 0.1)' }}
          {...createButtonHoverHandlers()}
        >
          View
        </Button>
      ),
    },
  ];

  const tabItems = [
    {
      key: 'ready',
      label: (
        <span>
          <Badge count={selectedRecords.size} offset={[10, 0]}>
            <CheckCircleOutlined style={{ marginRight: 8 }} />
            Overtime Requests
          </Badge>
        </span>
      ),
      children: (
        <div>
          <Card>
            <Row gutter={16} style={{ marginBottom: 16 }}>
              <Col flex="auto">
                <Input.Search
                  placeholder="Search by employee name, PF number, or month..."
                  prefix={<SearchOutlined />}
                  value={searchText}
                  onChange={(e) => setSearchText(e.target.value)}
                  allowClear
                  style={{ width: 400 }}
                />
              </Col>
              <Col>
                <Space>
                  <Button
                    icon={<ReloadOutlined />}
                    onClick={() => fetchReadyRecords(pagination.current, pagination.pageSize, true)}
                    loading={loading}
                  >
                    Refresh
                  </Button>
                  <Button
                    type="primary"
                    icon={<SaveOutlined />}
                    onClick={handleSaveBatch}
                    disabled={selectedRecords.size === 0}
                    loading={loading}
                  >
                    Create Batch ({selectedRecords.size})
                  </Button>
                </Space>
              </Col>
            </Row>
            <div className="relative">
              {loading ? (
                <div
                  className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
                  style={{ top: '1rem' }}
                >
                  <CollectionLoader />
                </div>
              ) : null}
              <Table
                columns={readyColumns}
                dataSource={filteredReadyRecords}
                loading={false}
                pagination={{
                  ...pagination,
                  onChange: (page, pageSize) => {
                    setPagination(prev => ({ ...prev, current: page, pageSize }));
                    fetchReadyRecords(page, pageSize);
                  },
                }}
                rowKey={(record) => record?.id ?? record?.batchId ?? `${record?.batchNumber ?? 'batch'}-${record?.createdAt ?? Date.now()}`}
                size="middle"
                bordered
                className="overtime-batch-table"
                style={{
                  borderRadius: '8px',
                  overflow: 'hidden',
                }}
              />
            </div>
          </Card>
        </div>
      ),
    },
    {
      key: 'batches',
      label: (
        <span>
          <FileTextOutlined style={{ marginRight: 8 }} />
          Overtime Batches
        </span>
      ),
      children: (
        <div>
          <Card>
            <Row gutter={16} style={{ marginBottom: 16 }}>
              <Col span={24} align="right">
                <Button
                  icon={<ReloadOutlined />}
                  onClick={() => fetchBatches(batchPagination.current, batchPagination.pageSize, true)}
                  loading={loading}
                >
                  Refresh
                </Button>
              </Col>
            </Row>
            <div className="relative">
              {loading ? (
                <div
                  className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
                  style={{ top: '1rem' }}
                >
                  <CollectionLoader />
                </div>
              ) : null}
              <Table
                columns={batchColumns}
                dataSource={batches}
                loading={false}
                pagination={{
                  ...batchPagination,
                  onChange: (page, pageSize) => {
                    setBatchPagination(prev => ({ ...prev, current: page, pageSize }));
                    fetchBatches(page, pageSize);
                  },
                }}
                rowKey={(record) => record?.id ?? record?.batchId ?? `${record?.batchNumber ?? 'batch'}-${record?.createdAt ?? Date.now()}`}
                size="middle"
                bordered
                className="overtime-batch-table"
                style={{
                  borderRadius: '8px',
                  overflow: 'hidden',
                }}
              />
            </div>
          </Card>
        </div>
      ),
    },
  ];

  const handleCloseBatchModal = () => {
    setIsBatchDetailsModalOpen(false);
    setCurrentBatchData(null);
    setActiveBatchTab('details');
  };

  const handleErmsUpdated = useCallback(async () => {
    if (!currentBatchData?.batchId) return;
    try {
      const batchResponse = await overtimeService.getBatch(currentBatchData.batchId);
      if (batchResponse.success && batchResponse.data) {
        setCurrentBatchData((prev) => (prev ? { ...prev, batch: batchResponse.data } : prev));
      }
      fetchBatches(batchPagination.current, batchPagination.pageSize);
    } catch {
      // ignore refresh errors
    }
  }, [currentBatchData?.batchId]);

  const handleRepostErms = () => {
    if (!canRepostErms || !currentBatchData?.batch?.can_repost || !currentBatchData?.batchId) return;

    const batchNumber = currentBatchData.batchNumber;
    modal.confirm({
      title: 'Repost to ERMS',
      content: `This will retry the ERMS submission${batchNumber ? ` for batch ${batchNumber}` : ''} after e-office approval.`,
      okText: 'Repost to ERMS',
      cancelText: 'Cancel',
      okButtonProps: { style: { backgroundColor: '#962E32', borderColor: '#962E32' } },
      onOk: async () => {
        setRepostingErms(true);
        try {
          const res = await overtimeService.repostOvertimeBatchErms(currentBatchData.batchId);
          if (!res?.success) {
            message.error(res?.message || 'Failed to repost to ERMS.');
            return;
          }
          message.success(res?.message || 'ERMS repost submitted.');
          await handleErmsUpdated();
        } catch (e) {
          message.error(e?.message || 'Failed to repost to ERMS.');
        } finally {
          setRepostingErms(false);
        }
      },
    });
  };

  const handleDocumentsUpdated = useCallback(async () => {
    if (!currentBatchData?.batchId) return;
    try {
      const batchResponse = await overtimeService.getBatch(currentBatchData.batchId);
      if (batchResponse.success && batchResponse.data) {
        setCurrentBatchData((prev) => (prev ? { ...prev, batch: batchResponse.data } : prev));
      }
      fetchBatches(batchPagination.current, batchPagination.pageSize);
    } catch {
      // ignore refresh errors
    }
  }, [currentBatchData?.batchId]);

  const batch = currentBatchData?.batch;
  const submissionReadiness = batch?.submission_readiness;
  const canSubmitToEoffice = canSubmitBatchToEoffice(submissionReadiness);
  const showErmsDetails = batch && ['submitted', 'approved'].includes(String(batch.status || '').toLowerCase());
  const canShowRepostButton = canRepostErms && Boolean(batch?.can_repost);
  const isApproved = String(batch?.status || '').toLowerCase() === 'approved';
  const canShowPostButton =
    isApproved &&
    !canShowRepostButton &&
    Number(batch?.erms_status) !== 1;

  const handleSubmitClick = () => {
    if (!currentBatchData?.batchId) return;
    if (!canSubmitToEoffice) {
      const blockingMessages = getSubmissionReadinessBlockingMessages(submissionReadiness);
      message.warning(
        blockingMessages[0] ||
          formatReadinessText(submissionReadiness?.message) ||
          'Batch is not ready for E-Office submission.'
      );
      return;
    }
    handleSubmitBatch(currentBatchData.batchId);
  };

  const handlePostErms = () => {
    if (!currentBatchData?.batchId) return;
    if (!canShowPostButton) return;

    const batchNumber = currentBatchData.batchNumber;
    modal.confirm({
      title: 'Post to ERMS',
      content: `This will submit the batch${batchNumber ? ` ${batchNumber}` : ''} to ERMS.`,
      okText: 'Post to ERMS',
      cancelText: 'Cancel',
      okButtonProps: { style: { backgroundColor: '#962E32', borderColor: '#962E32' } },
      onOk: async () => {
        setPostingErms(true);
        try {
          const res = await overtimeService.submitOvertimeBatchErms(currentBatchData.batchId);
          if (!res?.success) {
            message.error(res?.message || 'Failed to post to ERMS.');
            return;
          }
          message.success(res?.message || 'ERMS submission submitted.');
          await handleErmsUpdated();
        } catch (e) {
          message.error(e?.message || 'Failed to post to ERMS.');
        } finally {
          setPostingErms(false);
        }
      },
    });
  };

  const handleBatchTabChange = (activeKey) => {
    setActiveBatchTab(activeKey);
  };

  return (
    <div className="overtime-batch-management-container">
      <Card>
        <Tabs activeKey={activeTab} onChange={setActiveTab} items={tabItems} />
      </Card>

      <OvertimeBatchDetailsModal
        open={isBatchDetailsModalOpen}
        onClose={handleCloseBatchModal}
        batchData={currentBatchData}
        activeTab={activeBatchTab}
        onTabChange={handleBatchTabChange}
        loading={loading}
        postingErms={postingErms}
        repostingErms={repostingErms}
        showErmsDetails={showErmsDetails}
        canShowPostButton={canShowPostButton}
        canShowRepostButton={canShowRepostButton}
        canSubmitToEoffice={canSubmitToEoffice}
        submissionReadiness={submissionReadiness}
        onSubmit={handleSubmitClick}
        onPostErms={handlePostErms}
        onRepostErms={handleRepostErms}
        onDocumentsUpdated={handleDocumentsUpdated}
      />
    </div>
  );
};

export default OvertimeBatchManagement;
