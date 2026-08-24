import { useState, useEffect, useCallback, useMemo } from 'react';
import { useSelector } from 'react-redux';
import { Button, Modal, Table, Tag, DatePicker, Select, Space, App } from 'antd';
import { EyeOutlined, CalendarOutlined, ClockCircleOutlined, FilterOutlined } from '@ant-design/icons';
import { DataTable } from '../../../common/data';
import { attendanceService } from '../../../services/attendanceService.js';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import '../../../styles/common.css';
import { formatDateWithDay, transformAttendanceData } from '../../../common/utils/attendanceUtils';

const INITIAL_SUMMARY = {
  total_sessions: 0,
  total_hours: 0,
};

const INITIAL_EMPLOYEE_INFO = {
  pf_number: '',
  name: '',
};

const INITIAL_PAGINATION = {
  current: 1,
  pageSize: 15,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
  pageSizeOptions: ['10', '15', '20', '50', '100'],
};

const SESSION_COLUMNS = [
  {
    title: 'SN',
    key: 'serialNumber',
    width: 80,
    align: 'center',
    render: (_, __, index) => index + 1,
  },
  {
    title: 'Time In',
    dataIndex: 'timeIn',
    key: 'timeIn',
    width: 150,
    align: 'center',
    render: (time) => (
      <Tag color="green" style={{ fontSize: '13px', padding: '4px 12px' }}>
        {time}
      </Tag>
    ),
  },
  {
    title: 'Time Out',
    dataIndex: 'timeOut',
    key: 'timeOut',
    width: 150,
    align: 'center',
    render: (time) => (
      <Tag color="red" style={{ fontSize: '13px', padding: '4px 12px' }}>
        {time}
      </Tag>
    ),
  },
];

const StaffAttendance = () => {
  const { message } = App.useApp();
  const currentUser = useSelector((state) => state.auth.user);
  // Use PF number as employee_id since attendance log database doesn't have user_id
  const employeePfNumber = currentUser?.pfNumber || currentUser?.pf_number || currentUser?.pfno || currentUser?.pf_no || currentUser?.employeeId;

  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [selectedAttendance, setSelectedAttendance] = useState(null);
  const [loading, setLoading] = useState(false);
  const [filterType, setFilterType] = useState('daily');
  const [dateFilter, setDateFilter] = useState(null);
  const [monthFilter, setMonthFilter] = useState(null);
  const [attendanceData, setAttendanceData] = useState([]);
  const [summary, setSummary] = useState(INITIAL_SUMMARY);
  const [employeeInfo, setEmployeeInfo] = useState(INITIAL_EMPLOYEE_INFO);
  const [pagination, setPagination] = useState(INITIAL_PAGINATION);

  const fetchAttendanceData = useCallback(async (page = 1, pageSize = INITIAL_PAGINATION.pageSize) => {
    setLoading(true);
    try {
      const params = {
        page: page,
        per_page: pageSize
      };

      // Convert date/month filters to start_date/end_date format
      if (filterType === 'daily' && dateFilter) {
        const selectedDate = dateFilter.format('YYYY-MM-DD');
        params.start_date = selectedDate;
        params.end_date = selectedDate;
      } else if (filterType === 'monthly' && monthFilter) {
        const startOfMonth = monthFilter.startOf('month').format('YYYY-MM-DD');
        const endOfMonth = monthFilter.endOf('month').format('YYYY-MM-DD');
        params.start_date = startOfMonth;
        params.end_date = endOfMonth;
      }

      const response = await attendanceService.getUserSessions(params);

      if (response.success) {
        // Handle new response structure: { employee, summary, data, pagination }
        const employee = response.employee || {};
        const summaryData = response.summary || {};
        const dataArray = response.data || [];
        const paginationData = response.pagination || {};

        // Set employee info and summary
        setEmployeeInfo({
          pf_number: employee.pf_number || employeePfNumber || '',
          name: employee.name || 'Unknown Employee'
        });
        setSummary({
          total_sessions: summaryData.total_sessions || 0,
          total_hours: summaryData.total_hours || 0
        });

        // Transform the data array
        const transformedData = transformAttendanceData(dataArray);
        
        // Extract pagination info
        const totalCount = paginationData.total || dataArray.length;
        
        setAttendanceData(transformedData);
        setPagination(prev => ({
          ...prev,
          current: Number(paginationData.current_page || page),
          pageSize: Number(paginationData.per_page || pageSize),
          total: Number(totalCount),
        }));
      } else {
        message.error(response.message || 'Failed to fetch attendance data');
        setAttendanceData([]);
        setSummary(INITIAL_SUMMARY);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
    } catch (error) {
      console.error('Error fetching attendance data:', error);
      message.error('Error fetching attendance data. Please try again.');
      setAttendanceData([]);
      setSummary(INITIAL_SUMMARY);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  }, [employeePfNumber, filterType, dateFilter, monthFilter, message]);

  useEffect(() => {
    const currentPageSize = pagination.pageSize || INITIAL_PAGINATION.pageSize;
    setPagination(prev => ({ ...prev, current: 1 }));
    fetchAttendanceData(1, currentPageSize);
  }, [pagination.pageSize, fetchAttendanceData]);

  const handleViewDetails = useCallback((record) => {
    setSelectedAttendance(record);
    setIsDetailModalOpen(true);
  }, []);

  const handleDetailModalClose = useCallback(() => {
    setIsDetailModalOpen(false);
    setSelectedAttendance(null);
  }, []);

  const handleTableChange = useCallback(
    (paginationInfo) => {
      if (paginationInfo) {
        const newPage = Number(paginationInfo.current || 1);
        const newPageSize = Number(paginationInfo.pageSize || INITIAL_PAGINATION.pageSize);
        fetchAttendanceData(newPage, newPageSize);
      }
    },
    [fetchAttendanceData]
  );

  const columns = useMemo(
    () => [
      {
        title: 'S.No',
        key: 'serialNumber',
        width: 80,
        align: 'center',
        render: (_, __, index) => {
          const current = pagination.current || 1;
          const pageSize = pagination.pageSize || INITIAL_PAGINATION.pageSize;
          return (current - 1) * pageSize + index + 1;
        },
      },
      {
        title: 'Day',
        dataIndex: 'dayDate',
        key: 'dayDate',
        width: 220,
        render: (date) => (
          <span>
            <CalendarOutlined style={{ marginRight: 4 }} />
            {formatDateWithDay(date)}
          </span>
        ),
      },
      {
        title: 'Time In',
        dataIndex: 'timeIn',
        key: 'timeIn',
        width: 120,
        align: 'center',
        render: (time) => (
          <span>
            <ClockCircleOutlined style={{ marginRight: 4, color: '#52c41a' }} />
            {time}
          </span>
        ),
      },
      {
        title: 'Time Out',
        dataIndex: 'timeOut',
        key: 'timeOut',
        width: 120,
        align: 'center',
        render: (time) => (
          <span>
            <ClockCircleOutlined style={{ marginRight: 4, color: '#ff4d4f' }} />
            {time}
          </span>
        ),
      },
      {
        title: 'Overtime Status',
        dataIndex: 'overtimeStatus',
        key: 'overtimeStatus',
        width: 150,
        align: 'center',
        render: (status) => {
          if (!status) {
            return <Tag color="default">N/A</Tag>;
          }
          const normalized = (status || '').toString().trim().toLowerCase();

          if (normalized === 'overtime') {
            return <Tag color="orange">Overtime</Tag>;
          }
          if (normalized === 'normal') {
            return <Tag color="#962E32">Normal</Tag>;
          }

          return <Tag color="default">{status}</Tag>;
        },
      },
      {
        title: 'Actions',
        key: 'actions',
        width: 180,
        align: 'center',
        render: (_, record) => (
          <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
            <Button
              type="primary"
              icon={<EyeOutlined />}
              size="small"
              onClick={() => handleViewDetails(record)}
              className="btn-standard-primary"
            >
              View
            </Button>
          </div>
        ),
      },
    ],
    [pagination.current, pagination.pageSize, handleViewDetails]
  );

  return (
    <div className="attendance-management-container">
      {/* Summary Section */}
      {(summary.total_sessions > 0 || summary.total_hours > 0) && (
        <div style={{
          marginBottom: 16,
          padding: '16px',
          backgroundColor: '#f0f9ff',
          borderRadius: '4px',
          border: '1px solid #bae6fd',
          display: 'flex',
          justifyContent: 'space-around',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '16px'
        }}>
          <div style={{ textAlign: 'center' }}>
            <div style={{ fontSize: '24px', fontWeight: 600, color: '#0369a1' }}>
              {summary.total_sessions}
            </div>
            <div style={{ fontSize: '12px', color: '#64748b' }}>Total Sessions</div>
          </div>
          <div style={{ textAlign: 'center' }}>
            <div style={{ fontSize: '24px', fontWeight: 600, color: '#0369a1' }}>
              {summary.total_hours.toFixed(2)}
            </div>
            <div style={{ fontSize: '12px', color: '#64748b' }}>Total Hours</div>
          </div>
          <div style={{ textAlign: 'center' }}>
            <div style={{ fontSize: '24px', fontWeight: 600, color: '#0369a1' }}>
              {employeeInfo.name || 'N/A'}
            </div>
            <div style={{ fontSize: '12px', color: '#64748b' }}>Employee Name</div>
          </div>
        </div>
      )}

      <div style={{
        marginBottom: 16,
        padding: '16px',
        backgroundColor: '#fafafa',
        borderRadius: '4px',
        border: '1px solid #e8e8e8',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: '12px'
      }}>
        <Space size="middle" wrap>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <FilterOutlined />
            <Select
              value={filterType}
              onChange={(value) => {
                setFilterType(value);
                // Clear the other filter when switching types
                if (value === 'daily') {
                  setMonthFilter(null);
                } else {
                  setDateFilter(null);
                }
              }}
              style={{ width: 120 }}
              options={[
                { value: 'daily', label: 'Daily' },
                { value: 'monthly', label: 'Monthly' },
              ]}
            />
          </div>
          {filterType === 'daily' ? (
            <DatePicker
              value={dateFilter}
              onChange={setDateFilter}
              format="YYYY-MM-DD"
              placeholder="Select Date"
              allowClear
            />
          ) : (
            <DatePicker
              value={monthFilter}
              onChange={setMonthFilter}
              picker="month"
              format="MMMM YYYY"
              placeholder="Select Month"
              allowClear
            />
          )}
          {(dateFilter || monthFilter) && (
            <Button
              onClick={() => {
                setDateFilter(null);
                setMonthFilter(null);
                // Reset pagination when clearing filters
                setPagination(prev => ({ ...prev, current: 1 }));
              }}
            >
              Clear All Filters
            </Button>
          )}
        </Space>
      </div>

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
        data={attendanceData}
        loading={false}
        pagination={pagination}
        showSearch={false}
        showRefresh={false}
        onChange={(paginationInfo) => {
          if (paginationInfo) {
            const newPage = Number(paginationInfo.current || 1);
            const newPageSize = Number(paginationInfo.pageSize || 15);
            fetchAttendanceData(newPage, newPageSize);
          }
        }}
        rowKey={(record) => record.id || record.dayDate || `${record.employeeId}-${record.dayDate}`}
        size="middle"
        bordered={true}
      />
      </div>

      <Modal
        title={
          <span>
            <CalendarOutlined style={{ marginRight: 8 }} />
            Daily Attendance Sessions
          </span>
        }
        open={isDetailModalOpen}
        onCancel={handleDetailModalClose}
        footer={[
          <Button key="close" onClick={handleDetailModalClose}>
            Close
          </Button>
        ]}
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 700}
        style={{ top: 20 }}
      >
        {selectedAttendance && (
          <div>
            <div style={{
              marginBottom: 20,
              padding: '16px',
              backgroundColor: '#f5f5f5',
              borderRadius: '4px',
              border: '1px solid #e8e8e8'
            }}>
              <div style={{ marginBottom: 8 }}>
                <strong style={{ fontSize: '14px', color: '#8c8c8c' }}>Employee Name:</strong>
                <div style={{ fontSize: '16px', fontWeight: 500, marginTop: 4 }}>
                  {selectedAttendance.employeeName}
                </div>
              </div>
              <div style={{ marginBottom: 8 }}>
                <strong style={{ fontSize: '14px', color: '#8c8c8c' }}>PF Number:</strong>
                <div style={{ fontSize: '16px', fontWeight: 500, marginTop: 4 }}>
                  {selectedAttendance.pfNumber || 'N/A'}
                </div>
              </div>
              <div>
                <strong style={{ fontSize: '14px', color: '#8c8c8c' }}>Date:</strong>
                <div style={{ fontSize: '16px', fontWeight: 500, marginTop: 4 }}>
                  {formatDateWithDay(selectedAttendance.dayDate)}
                </div>
              </div>
            </div>

            <div>
              <div style={{ marginBottom: 12, fontSize: '14px', fontWeight: 500 }}>
                Sign In / Sign Out Sessions:
              </div>
              <Table
                columns={SESSION_COLUMNS}
                dataSource={selectedAttendance.dailyRecords || []}
                pagination={false}
                rowKey="sessionId"
                size="middle"
                bordered
                style={{ marginTop: 12 }}
              />
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default StaffAttendance;
