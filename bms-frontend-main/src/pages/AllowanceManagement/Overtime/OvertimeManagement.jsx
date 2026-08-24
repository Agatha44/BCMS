import { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { Button, Modal, App, Space } from 'antd';
import { ClockCircleOutlined, CalendarOutlined, PlusOutlined, EyeOutlined, CheckCircleOutlined, HourglassOutlined, CloseCircleOutlined, RollbackOutlined } from '@ant-design/icons';
import { DataTable } from '../../../common/data/index.jsx';
import OvertimeForm from '../../../common/components/forms/OvertimeForm.jsx';
import OvertimeDetailModal from './OvertimeDetailModal.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import { overtimeService } from '../../../services/overtimeService.js';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../../common/utils/employeeUtils.jsx';
import '../../../styles/common.css';
import dayjs from 'dayjs';
import { formatAmount } from './overtimeUtils.js';

// UI label — workflowStatus (Applied, Validated, Returned, …)
const getWorkflowLabel = (record) => {
  if (!record?.workflowStatus) return 'Unknown';
  return String(record.workflowStatus).trim();
};


const OvertimeManagement = () => {
  const { message } = App.useApp();
  // Get current logged-in user from Redux store
  const currentUser = useSelector((state) => state.auth.user);
  const currentUserId = currentUser?.employeeId || currentUser?.id || currentUser?.userId;
  const navigate = useNavigate();

  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isResubmitModalOpen, setIsResubmitModalOpen] = useState(false);
  const [resubmitOvertimeId, setResubmitOvertimeId] = useState(null);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [selectedOvertimeId, setSelectedOvertimeId] = useState(null);
  const [loading, setLoading] = useState(false);
  const [overtimeData, setOvertimeData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch overtime records from API with server-side pagination
  const fetchOvertimeRecords = async (page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };
      
      const response = await overtimeService.getEmployeeOvertime(params);

      if (response.success && response.data) {
        // Extract data and pagination from response
        const { items: dataArray, pagination: paginationData } = extractArrayFromResponse(response.data);
        
        // Transform data to match expected format
        const transformedData = dataArray.map((record) => ({
          id: record.id,
          employeeId: record.pfNumber,
          employeeName: record.employeeName,
          month: record.month,
          monthDisplay: record.monthDisplay,
          totalOvertimeHours: record.totalOvertimeHours,
          totalDays: record.totalDays,
          totalAmount: record.totalAmount,
          status: record.status,
          canResubmit: Boolean(record.canResubmit),
          workflowStatus: record.workflowStatus,
          createdAt: record.createdAt,
        }));
        
        const totalCount = response.data.count || response.data.total || paginationData?.total || transformedData.length;
        
        setOvertimeData(transformedData);
        setPagination(prev => ({
          ...prev,
          ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount)
        }));
      } else {
        console.error('Error fetching overtime records:', response);
        message.error(response.message || 'Failed to fetch overtime records');
        setOvertimeData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching overtime records:', error);
      message.error('Failed to fetch overtime records. Please try again.');
      setOvertimeData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  // Fetch overtime records on component mount
  useEffect(() => {
    fetchOvertimeRecords(1, pagination.pageSize);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Define table columns for monthly records
  const columns = [
    {
      title: 'SN',
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
      title: 'Overtime Month',
      dataIndex: 'monthDisplay',
      key: 'monthDisplay',
      searchable: true,
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
      title: 'Overtime Status',
      dataIndex: 'workflowStatus',
      key: 'workflowStatus',
      width: 120,
      align: 'center',
      filters: [
        { text: 'Applied', value: 'Applied' },
        { text: 'Validated', value: 'Validated' },
        { text: 'Reviewed', value: 'Reviewed' },
        { text: 'Returned', value: 'Returned' },
        { text: 'Approved', value: 'Approved' },
        { text: 'Paid', value: 'Paid' },
        { text: 'Rejected', value: 'Rejected' },
      ],
      onFilter: (value, record) => getWorkflowLabel(record) === value,
      render: (_, record) => {
        const displayStatus = getWorkflowLabel(record);
        const statusConfig = {
          Applied: {
            color: '#fa8c16',
            bgColor: '#fff7e6',
            icon: <HourglassOutlined style={{ fontSize: 12, marginRight: 4 }} />,
          },
          Validated: {
            color: '#1890ff',
            bgColor: '#e6f7ff',
            icon: <CheckCircleOutlined style={{ fontSize: 12, marginRight: 4 }} />,
          },
          Returned: {
            color: '#fa8c16',
            bgColor: '#fff7e6',
            icon: <RollbackOutlined style={{ fontSize: 12, marginRight: 4 }} />,
          },
          Reviewed: {
            color: '#722ed1',
            bgColor: '#f9f0ff',
            icon: <CheckCircleOutlined style={{ fontSize: 12, marginRight: 4 }} />,
          },
          Approved: {
            color: '#52c41a',
            bgColor: '#f6ffed',
            icon: <CheckCircleOutlined style={{ fontSize: 12, marginRight: 4 }} />,
          },
          Paid: {
            color: '#389e0d',
            bgColor: '#f6ffed',
            icon: <CheckCircleOutlined style={{ fontSize: 12, marginRight: 4 }} />,
          },
          Rejected: {
            color: '#ff4d4f',
            bgColor: '#fff1f0',
            icon: <CloseCircleOutlined style={{ fontSize: 12, marginRight: 4 }} />,
          },
        };

        const config = statusConfig[displayStatus] || {
          color: '#000000',
          bgColor: '#f5f5f5',
          icon: null,
        };

        return (
          <span style={{
            color: config.color,
            backgroundColor: config.bgColor,
            padding: '4px 12px',
            borderRadius: '4px',
            fontSize: '13px',
            fontWeight: 500,
            display: 'inline-flex',
            alignItems: 'center',
          }}>
            {config.icon}
            {displayStatus}
          </span>
        );
      },
    },
    {
      title: 'Total Amount',
      dataIndex: 'totalAmount',
      key: 'totalAmount',
      width: 150,
      align: 'center',
      render: (amount) => {
        const formattedAmount = new Intl.NumberFormat('en-TZ', {
          minimumFractionDigits: 0,
        }).format(amount || 0);
        return (
          <span style={{ color: '#000000', fontSize: '14px' }}>
            {formattedAmount}
          </span>
        );
      },
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 200,
      align: 'center',
      render: (_, record) => (
        <Space size="small" onClick={(e) => e.stopPropagation()}>
          {record.canResubmit && (
            <Button
              type="primary"
              icon={<RollbackOutlined />}
              onClick={() => {
                setResubmitOvertimeId(record.id);
                setIsResubmitModalOpen(true);
              }}
              size="small"
              className="btn-standard-primary"
            >
              Edit
            </Button>
          )}
          <Button
            type={record.canResubmit ? 'default' : 'primary'}
            icon={<EyeOutlined />}
            onClick={() => {
              setSelectedOvertimeId(record.id);
              setIsDetailModalOpen(true);
            }}
            size="small"
            className={record.canResubmit ? undefined : 'btn-standard-primary'}
          >
            View
          </Button>
        </Space>
      ),
    },
  ];

  // Filter overtime data by current user if needed
  // Uncomment this if you want to filter by user
  // const filteredOvertimeData = overtimeData.filter(record => record.employeeId === currentUserId);
  const filteredOvertimeData = overtimeData;

  const showCreateModal = () => {
    setIsCreateModalOpen(true);
  };

  const handleCreateCancel = () => {
    setIsCreateModalOpen(false);
  };

  const handleCreateSubmit = async () => {
    try {
      setIsCreateModalOpen(false);
      const currentPage = pagination.current || 1;
      const currentPageSize = pagination.pageSize || 15;
      await fetchOvertimeRecords(currentPage, currentPageSize);
    } catch (error) {
      console.error('Error refreshing overtime records:', error);
    }
  };

  const handleResubmitCancel = () => {
    setIsResubmitModalOpen(false);
    setResubmitOvertimeId(null);
  };

  const handleResubmitSubmit = async () => {
    try {
      setIsResubmitModalOpen(false);
      setResubmitOvertimeId(null);
      const currentPage = pagination.current || 1;
      const currentPageSize = pagination.pageSize || 15;
      await fetchOvertimeRecords(currentPage, currentPageSize);
    } catch (error) {
      console.error('Error refreshing overtime records:', error);
    }
  };

  const handleOpenResubmitFromCreate = (overtimeId) => {
    setIsCreateModalOpen(false);
    setResubmitOvertimeId(overtimeId);
    setIsResubmitModalOpen(true);
  };

  const handleEditResubmitFromDetail = (id) => {
    setIsDetailModalOpen(false);
    setSelectedOvertimeId(null);
    setResubmitOvertimeId(id);
    setIsResubmitModalOpen(true);
  };

  return (
    <div className="overtime-management-container">
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
          data={filteredOvertimeData}
          loading={false}
          pagination={pagination}
          pageSize={pagination.pageSize}
          showSearch={true}
          showRefresh={false}
          onChange={(paginationInfo) => {
            if (paginationInfo) {
              const newPage = paginationInfo.current || 1;
              const newPageSize = paginationInfo.pageSize || 15;
              fetchOvertimeRecords(newPage, newPageSize);
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
              Apply
            </Button>
          }
          searchPlaceholder="Search overtime records..."
          rowKey="id"
          size="middle"
          bordered={true}
          onRow={(record) => ({
            onClick: () => {
              setSelectedOvertimeId(record.id);
              setIsDetailModalOpen(true);
            },
            style: { cursor: 'pointer' },
          })}
        />
      </div>

      {/* Create Overtime Modal */}
      <Modal
        title={
          <span>
            <ClockCircleOutlined style={{ marginRight: 8 }} />
            Apply for Overtime
          </span>
        }
        open={isCreateModalOpen}
        onCancel={handleCreateCancel}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 1280}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        <OvertimeForm
          mode="create"
          onSubmit={handleCreateSubmit}
          onCancel={handleCreateCancel}
          onOpenResubmit={handleOpenResubmitFromCreate}
        />
      </Modal>

      {/* Resubmit Overtime Modal */}
      <Modal
        title={
          <span>
            <RollbackOutlined style={{ marginRight: 8 }} />
            Resubmit Overtime
          </span>
        }
        open={isResubmitModalOpen}
        onCancel={handleResubmitCancel}
        footer={null}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 1280}
        destroyOnHidden={true}
        style={{ top: 20 }}
      >
        {resubmitOvertimeId && (
          <OvertimeForm
            mode="resubmit"
            overtimeId={resubmitOvertimeId}
            onSubmit={handleResubmitSubmit}
            onCancel={handleResubmitCancel}
          />
        )}
      </Modal>

      {/* Overtime Detail Modal */}
      <OvertimeDetailModal
        open={isDetailModalOpen}
        onClose={() => {
          setIsDetailModalOpen(false);
          setSelectedOvertimeId(null);
        }}
        overtimeId={selectedOvertimeId}
        onRefresh={fetchOvertimeRecords}
        onEditResubmit={handleEditResubmitFromDetail}
      />
    </div>
  );
};

export default OvertimeManagement;
