import { useState, useEffect } from 'react';
import { Button, Modal, Table, Tag, DatePicker, Select, Space, Dropdown, App, Card } from 'antd';
import { EyeOutlined, CalendarOutlined, ClockCircleOutlined, FileExcelOutlined, FilePdfOutlined, DownloadOutlined, FilterOutlined } from '@ant-design/icons';
import DashboardInfoCard from '../../Dashboard/components/DashboardInfoCard.jsx';
import { DataTable } from '../../../common/data';
import { apiService } from '../../../services/api.jsx';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import '../../../styles/common.css';
import {
  generatePDFReportAllUsers,
  generateExcelReportAllUsers
} from '../../../common/utils/attendanceReportUtils';
import { formatDateWithDay, transformAttendanceData } from '../../../common/utils/attendanceUtils';

const DetailItem = ({ label, value }) => (
  <div style={{ marginBottom: 8 }}>
    <strong style={{ fontSize: '14px', color: '#8c8c8c' }}>{label}</strong>
    <div style={{ fontSize: '16px', fontWeight: 500, marginTop: 4 }}>
      {value}
    </div>
  </div>
);

const AttendanceReport = () => {
  const { message } = App.useApp();

  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [selectedAttendance, setSelectedAttendance] = useState(null);
  const [loading, setLoading] = useState(false);
  const [filterType, setFilterType] = useState('daily');
  const [dateFilter, setDateFilter] = useState(null);
  const [monthFilter, setMonthFilter] = useState(null);
  const [searchText, setSearchText] = useState('');
  const [attendanceData, setAttendanceData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  const buildParams = ({ page = 1, pageSize = 15 } = {}) => {
    const params = {
      page,
      per_page: pageSize
    };

    if (filterType === 'daily' && dateFilter) {
      const selectedDate = dateFilter.format('YYYY-MM-DD');
      params.start_date = selectedDate;
      params.end_date = selectedDate;
    } else if (filterType === 'monthly' && monthFilter) {
      params.start_date = monthFilter.startOf('month').format('YYYY-MM-DD');
      params.end_date = monthFilter.endOf('month').format('YYYY-MM-DD');
    }

    if (searchText?.trim()) {
      params.search = searchText.trim();
    }

    return params;
  };

  const parseResponse = (response, page, pageSize) => {
    if (!response?.success || !response.data) {
      return { dataArray: [], pagination: {} };
    }

    let dataArray = [];
    let paginationData = {};

    if (Array.isArray(response.data) && response.pagination) {
      dataArray = response.data;
      paginationData = response.pagination;
    } else if (Array.isArray(response.data)) {
      dataArray = response.data;
      paginationData = {
        total: response.count || response.total || response.pagination?.total || 0,
        current_page: response.pagination?.current_page || response.current_page || page,
        per_page: response.pagination?.per_page || response.per_page || pageSize
      };
    } else {
      const extracted = extractArrayFromResponse(response.data);
      dataArray = extracted.items;
      paginationData = extracted.pagination;
    }

    return { dataArray, paginationData };
  };

  const fetchAttendanceData = async (page = 1, pageSize = 15, forReport = false) => {
    if (!forReport) {
      setLoading(true);
    }

    try {
      const params = forReport
        ? buildParams({ page: 1, pageSize: 10000 })
        : buildParams({ page, pageSize });
      const response = await apiService.getAllSessions(params);

      console.log(response);

      if (!response?.success) {
        message.error(response?.message || 'Failed to fetch attendance data');
        return forReport ? [] : null;
      }

      const { dataArray, paginationData } = parseResponse(response, page, pageSize);
      const transformedData = transformAttendanceData(dataArray);

      if (forReport) {
        return transformedData;
      }

      setAttendanceData(transformedData);
      setPagination(prev => ({
        ...prev,
        current: Number(paginationData.current_page || page),
        pageSize: Number(paginationData.per_page || pageSize),
        total: Number(paginationData.total || 0)
      }));
    } catch (error) {
      console.error('Error fetching attendance data:', error);
      message.error('Error fetching attendance data. Please try again.');
      if (!forReport) {
        setAttendanceData([]);
        setPagination(prev => ({ ...prev, total: 0 }));
      }
      return forReport ? [] : null;
    } finally {
      if (!forReport) {
        setLoading(false);
      }
    }
  };

  const generateReport = async (generatorFn, loadingText) => {
    message.loading({ content: loadingText, key: 'report' });
    const allData = await fetchAttendanceData(1, 15, true);
    message.destroy('report');

    if (!allData || allData.length === 0) {
      message.warning('No data available to generate report');
      return;
    }

    generatorFn(
      allData,
      filterType,
      dateFilter,
      monthFilter,
      searchText,
      message
    );
  };

  const renderTimeTag = (color) => (time) => (
    <Tag color={color} style={{ fontSize: '13px', padding: '4px 12px' }}>
      {time}
    </Tag>
  );

  useEffect(() => {
    const currentPageSize = pagination.pageSize || 15;
    setPagination(prev => ({ ...prev, current: 1 }));
    fetchAttendanceData(1, currentPageSize);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filterType, dateFilter, monthFilter, searchText]);


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
      title: 'Name',
      dataIndex: 'employeeName',
      key: 'employeeName',
      width: 200,
    },
    {
      title: 'PF Number',
      dataIndex: 'pfNumber',
      key: 'pfNumber',
      width: 150,
      align: 'center',
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
      width: 120,
      align: 'center',
      render: (_, record) => (
        <Button
          type="primary"
          icon={<EyeOutlined />}
          size="small"
          onClick={() => handleViewDetails(record)}
          className="btn-standard-primary"
        >
          View
        </Button>
      ),
    },
  ];

  const handleViewDetails = (record) => {
    setSelectedAttendance(record);
    setIsDetailModalOpen(true);
  };

  const handleDetailModalClose = () => {
    setIsDetailModalOpen(false);
    setSelectedAttendance(null);
  };

  const handleGeneratePDFReport = async () => {
    await generateReport(
      generatePDFReportAllUsers,
      'Generating PDF report...'
    );
  };

  const handleGenerateExcelReport = async () => {
    await generateReport(
      generateExcelReportAllUsers,
      'Generating Excel report...'
    );
  };

  const reportMenuItems = [
    {
      key: 'pdf-all',
      label: 'Download PDF - All Users',
      icon: <FilePdfOutlined />,
      onClick: handleGeneratePDFReport,
    },
    {
      key: 'excel-all',
      label: 'Download Excel - All Users',
      icon: <FileExcelOutlined />,
      onClick: handleGenerateExcelReport,
    },
  ];

  const sessionColumns = [
    {
      title: 'S.No',
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
      render: (time) => renderTimeTag('green')(time),
    },
    {
      title: 'Time Out',
      dataIndex: 'timeOut',
      key: 'timeOut',
      width: 150,
      align: 'center',
      render: (time) => renderTimeTag('red')(time),
    },
  ];

  const totalRecords = pagination.total || 0;
  const uniqueEmployees = new Set(attendanceData.map(record => record.pfNumber)).size;
  const dateRange = filterType === 'daily' && dateFilter
    ? formatDateWithDay(dateFilter.format('YYYY-MM-DD'))
    : filterType === 'monthly' && monthFilter
    ? monthFilter.format('MMMM YYYY')
    : 'All Time';

  return (
    <div className="attendance-report-container">
      <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <DashboardInfoCard label="Total Records">
          {totalRecords}
        </DashboardInfoCard>

        <DashboardInfoCard label="Unique Employees">
          {uniqueEmployees}
        </DashboardInfoCard>

        <DashboardInfoCard label="Date Range">
          {dateRange}
        </DashboardInfoCard>
      </div>

      <Card style={{ marginBottom: 16 }}>
        <div style={{
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
                  setPagination(prev => ({ ...prev, current: 1 }));
                }}
              >
                Clear All Filters
              </Button>
            )}
          </Space>

          <Space>
            <Dropdown menu={{ items: reportMenuItems }} trigger={['click']}>
              <Button
                type="primary"
                icon={<DownloadOutlined />}
                size="large"
                className="btn-standard-primary"
              >
                Generate Report
              </Button>
            </Dropdown>
          </Space>
        </div>
      </Card>

      <Card>
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
          pageSize={pagination.pageSize}
          showSearch={true}
          showRefresh={false}
          onChange={(paginationInfo) => {
            if (paginationInfo) {
              const newPage = Number(paginationInfo.current || 1);
              const newPageSize = Number(paginationInfo.pageSize || 15);
              fetchAttendanceData(newPage, newPageSize);
            }
          }}
          searchPlaceholder="Search attendance records..."
          rowKey="id"
          size="middle"
          bordered={true}
          onSearchChange={(value) => {
            setSearchText(value);
            setPagination(prev => ({ ...prev, current: 1 }));
          }}
        />
        </div>
      </Card>

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
              <DetailItem
                label="Employee Name:"
                value={selectedAttendance.employeeName}
              />
              <DetailItem
                label="PF Number:"
                value={selectedAttendance.pfNumber || 'N/A'}
              />
              <DetailItem
                label="Date:"
                value={formatDateWithDay(selectedAttendance.dayDate)}
              />
            </div>

            <div>
              <div style={{ marginBottom: 12, fontSize: '14px', fontWeight: 500 }}>
                Sign In / Sign Out Sessions:
              </div>
              <Table
                columns={sessionColumns}
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

export default AttendanceReport;
