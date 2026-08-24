import { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';
import { Button, App, Tabs, Input, Space, Row, Col } from 'antd';
import { CalendarOutlined, EyeOutlined, CheckCircleOutlined, HourglassOutlined, CloseCircleOutlined, HistoryOutlined, SearchOutlined, ReloadOutlined } from '@ant-design/icons';
import { DataTable } from '../../../common/data/index.jsx';
import OvertimeDetailModal from './OvertimeDetailModal.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import { overtimeService } from '../../../services/overtimeService.js';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';
import '../../../styles/common.css';
import dayjs from 'dayjs';

// Reusable helper to format amounts with commas (no currency symbol)
const formatAmount = (amount) =>
  new Intl.NumberFormat('en-TZ', {
    minimumFractionDigits: 0,
  }).format(amount || 0);

// Logic — status (Pending, Validator Approved, Returned, …)
const normalizeRecordStatus = (status) => {
  if (status === undefined || status === null || status === '') return '';
  return String(status).trim().toLowerCase();
};

// Helper function to check if user has a specific role
const hasRole = (userRoles, roleName) => {
  if (!userRoles) return false;
  const normalizedRoleName = String(roleName).toLowerCase().trim();
  
  // Collect all possible role values
  const roleSources = [];
  
  // Handle different role formats
  if (typeof userRoles === 'string') {
    roleSources.push(userRoles);
  } else if (Array.isArray(userRoles)) {
    userRoles.forEach(r => {
      if (typeof r === 'string') {
        roleSources.push(r);
      } else if (r?.name) {
        roleSources.push(r.name);
      } else if (r?.role_name) {
        roleSources.push(r.role_name);
      }
    });
  } else if (userRoles?.name) {
    roleSources.push(userRoles.name);
  } else if (userRoles?.role_name) {
    roleSources.push(userRoles.role_name);
  }
  
  // Normalize all roles to lowercase and handle spaces/underscores
  const normalizedRoles = roleSources
    .filter(Boolean)
    .map(r => {
      const roleStr = String(r).toLowerCase().trim();
      // Replace spaces with underscores for consistency
      return roleStr.replace(/\s+/g, '_');
    });
  
  // Check each normalized role
  for (const role of normalizedRoles) {
    // Direct match (e.g., "overtime_reviewer" === "overtime_reviewer")
    if (role === normalizedRoleName) {
      return true;
    }
    
    // Check if role contains the roleName (e.g., "overtime_reviewer" contains "reviewer")
    if (role.includes(normalizedRoleName)) {
      return true;
    }
    
    // Also check exact word match (e.g., "reviewer" matches "reviewer" in "overtime_reviewer")
    // Split by underscore and check if any part matches
    const roleParts = role.split('_');
    if (roleParts.includes(normalizedRoleName)) {
      return true;
    }
  }
  
  return false;
};


const ManageOvertime = () => {
  const { message } = App.useApp();
  const currentUser = useSelector((state) => state.auth.user);
  const selectedRole = useSelector((state) => state.app.selectedRole);
  // Normalize the explicitly selected role; we only use this for overtime decisions
  const normalizedSelectedRole = selectedRole
    ? String(selectedRole).toLowerCase().trim().replace(/\s+/g, '_')
    : '';
  const isOvertimeValidator = normalizedSelectedRole === 'overtime_validator';
  const isOvertimeReviewer = normalizedSelectedRole === 'overtime_reviewer';

  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [selectedOvertimeId, setSelectedOvertimeId] = useState(null);
  const [loading, setLoading] = useState(false);
  const [overtimeData, setOvertimeData] = useState([]);
  const [allActionedData, setAllActionedData] = useState([]); // Store all actioned records for client-side pagination
  const [activeTab, setActiveTab] = useState('pending');
  const [searchText, setSearchText] = useState('');
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100']
  });

  // Function to fetch overtime records from API with filters and server-side pagination
  const fetchOvertimeRecords = async (tab = activeTab, page = 1, pageSize = 15) => {
    setLoading(true);
    try {
      let response;
      let dataArray = [];
      let totalCount = 0;
      
      if (tab === 'actioned') {
        // For actioned tab: use the new my-actions endpoint
        // Only fetch if we don't have cached data or if explicitly refreshing
        if (allActionedData.length === 0 || page === 1) {
          response = await overtimeService.getMyActionedOvertime();

          if (response.success && response.data) {
            // Extract data from response (API returns everything)
            const { items } = extractArrayFromResponse(response.data);
            dataArray = items || response.data || [];
            
            // Transform and sort data (newest first)
            const transformed = dataArray.map((record) => ({
              id: record.id,
              employeeId: record.employeeId,
              employeeName: record.employeeName,
              month: record.month,
              monthDisplay: record.monthDisplay,
              totalOvertimeHours: record.totalOvertimeHours,
              totalDays: record.totalDays,
              status: record.status,
              totalAmount: record.totalAmount,
              workflowStatus: record.workflowStatus,
              createdAt: record.createdAt,
            }));
            
            // Sort by createdAt descending (newest first)
            transformed.sort((a, b) => {
              const dateA = dayjs(a.createdAt).valueOf();
              const dateB = dayjs(b.createdAt).valueOf();
              return dateB - dateA;
            });
            
            setAllActionedData(transformed);
            dataArray = transformed;
            totalCount = transformed.length;
          } else {
            message.error(response.message || 'Failed to fetch actioned overtime records');
            setOvertimeData([]);
            setAllActionedData([]);
            setPagination(prev => ({ ...prev, total: 0 }));
            setLoading(false);
            return;
          }
        } else {
          // Use cached data for pagination
          dataArray = allActionedData;
          totalCount = allActionedData.length;
        }
      } else {
        const params = {
          page: page,
          per_page: pageSize,
          sort_by: 'createdAt',
          sort_order: 'asc',
        };

        if (isOvertimeReviewer) {
          params.stage = 'reviewer_pending';
        } else if (isOvertimeValidator) {
          params.stage = 'validator_pending';
        } else {
          params.stage = 'validator_pending';
        }

        response = await overtimeService.getOvertimeRecords(params);

        if (response.success && response.data) {
          // Extract data and pagination from response
          const { items, pagination: paginationData } = extractArrayFromResponse(response.data);
          dataArray = items || [];
          totalCount = response.data.count || response.data.total || paginationData?.total || dataArray.length;
        } else {
          message.error(response.message || 'Failed to fetch overtime records');
          setOvertimeData([]);
          setPagination(prev => ({ ...prev, total: 0 }));
          setLoading(false);
          return;
        }
      }
      
      // Transform data to match expected format (only for pending tab, actioned is already transformed)
      let transformedData;
      if (tab === 'actioned') {
        transformedData = dataArray; // Already transformed above
      } else {
        transformedData = dataArray.map((record) => ({
          id: record.id,
          employeeId: record.employeeId,
          employeeName: record.employeeName,
          month: record.month,
          monthDisplay: record.monthDisplay,
          totalOvertimeHours: record.totalOvertimeHours,
          totalDays: record.totalDays,
          status: record.status,
          totalAmount: record.totalAmount,
          workflowStatus: record.workflowStatus,
          createdAt: record.createdAt,
        }));
      }
      
      let filteredData = transformedData.filter((record) => {
        if (tab === 'pending') {
          const recordStatus = normalizeRecordStatus(record.status);

          if (!recordStatus || recordStatus === 'rejected' || recordStatus === 'paid' || recordStatus === 'returned') {
            return false;
          }

          if (isOvertimeValidator) {
            return recordStatus === 'pending';
          }

          if (isOvertimeReviewer) {
            return recordStatus === 'validator approved';
          }

          return ['pending', 'validator approved'].includes(recordStatus);
        }
        return true;
      });
      
      // For actioned tab, apply client-side pagination since API returns everything
      if (tab === 'actioned') {
        const startIndex = (page - 1) * pageSize;
        const endIndex = startIndex + pageSize;
        filteredData = filteredData.slice(startIndex, endIndex);
      }
      
      setOvertimeData(filteredData);
      setPagination(prev => ({
        ...prev,
        current: page,
        pageSize: pageSize,
        total: tab === 'actioned' ? totalCount : (response.data?.count || response.data?.total || totalCount),
      }));
    } catch (error) {
      console.error('Error fetching overtime records:', error);
      message.error('Failed to fetch overtime records. Please try again.');
      setOvertimeData([]);
      setPagination(prev => ({ ...prev, total: 0 }));
    } finally {
      setLoading(false);
    }
  };

  // Fetch overtime records on component mount and when tab changes
  useEffect(() => {
    // Reset to page 1 when tab changes
    const currentPageSize = pagination.pageSize || 15;
    setPagination(prev => ({ ...prev, current: 1 }));
    fetchOvertimeRecords(activeTab, 1, currentPageSize);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab]);

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
      }
    },
    {
      title: 'Employee Name',
      dataIndex: 'employeeName',
      key: 'employeeName',
      width: 200
    },
    {
      title: 'Overtime Month',
      dataIndex: 'monthDisplay',
      key: 'monthDisplay',
      width: 180,
      render: (month) => (
        <span>
          <CalendarOutlined style={{ marginRight: 4 }} />
          {month}
        </span>
      )
    },
    {
      title: 'Overtime Days',
      dataIndex: 'totalDays',
      key: 'totalDays',
      width: 120,
      align: 'center',
      render: (days) => `${days} day${days !== 1 ? 's' : ''}`
    },
    {
      title: 'Overtime Status',
      dataIndex: 'workflowStatus',
      key: 'workflowStatus',
      width: 180,
      align: 'center',
      render: (workflowStatus) => {
        const value = (workflowStatus || '').toString().trim();
        const normalized = value.toLowerCase();

        const statusConfig = {
          applied: {
            color: '#fa8c16',
            bgColor: '#fff7e6',
          },
          validated: {
            color: '#1890ff',
            bgColor: '#e6f7ff',
          },
          returned: {
            color: '#fa8c16',
            bgColor: '#fff7e6',
          },
          reviewed: {
            color: '#722ed1',
            bgColor: '#f9f0ff',
          },
          approved: {
            color: '#52c41a',
            bgColor: '#f6ffed',
          },
          paid: {
            color: '#389e0d',
            bgColor: '#f6ffed',
          },
          rejected: {
            color: '#ff4d4f',
            bgColor: '#fff1f0',
          },
        };

        const config = statusConfig[normalized];

        if (!config) {
          // Any other status: just display as plain text
          return value || 'Unknown';
        }

        return (
          <span
            style={{
              color: config.color,
              backgroundColor: config.bgColor,
              padding: '4px 12px',
              borderRadius: '4px',
              fontSize: '13px',
              fontWeight: 500,
            }}
          >
            {value}
          </span>
        );
      },
    },
    {
      title: 'Total Amount',
      dataIndex: 'totalAmount',
      key: 'totalAmount',
      width: 150,
      align: 'right',
      render: (totalAmount) => (
        <span style={{ color: '#000000', fontSize: '14px' }}>
          {formatAmount(totalAmount)}
        </span>
      )
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 100,
      align: 'center',
      render: (_, record) => (
        <Button
          type="primary"
          icon={<EyeOutlined />}
          onClick={(e) => {
            e.stopPropagation();
            setSelectedOvertimeId(record.id);
            setIsDetailModalOpen(true);
          }}
          size="small"
          className="btn-standard-primary"
        >
          View
        </Button>
      )
    }
  ];

  // Apply search filter to the fetched data
  const getFilteredData = () => {
    let filtered = [...overtimeData];
    
    // Apply search filter if search text exists
    if (searchText) {
      filtered = filtered.filter((record) =>
        columns.some((col) => {
          if (col.dataIndex && record[col.dataIndex]) {
            return record[col.dataIndex]
              .toString()
              .toLowerCase()
              .includes(searchText.toLowerCase());
          }
          return false;
        })
      );
    }
    
    return filtered;
  };

  const filteredOvertimeData = getFilteredData();

  const searchPlaceholder = activeTab === 'pending' 
    ? 'Search pending overtime records...' 
    : isOvertimeReviewer 
      ? 'Search reviewed overtime records...' 
      : 'Search validated overtime records...';

  return (
    <div className="manage-overtime-container">
      {/* Tabs and Search on same row */}
      <div style={{ marginBottom: 16, borderBottom: '1px solid #f0f0f0' }}>
        <Row gutter={16} align="middle" style={{ marginBottom: 0 }}>
          <Col flex="auto">
            <Tabs
              activeKey={activeTab}
              onChange={(key) => {
                setActiveTab(key);
                setSearchText(''); // Clear search when switching tabs
                // Clear cached actioned data when switching away from actioned tab
                if (key !== 'actioned') {
                  setAllActionedData([]);
                }
              }}
              items={[
                {
                  key: 'pending',
                  label: (
                    <span>
                      <HourglassOutlined style={{ marginRight: 8 }} />
                      Pending Overtime
                    </span>
                  ),
                },
                {
                  key: 'actioned',
                  label: (
                    <span>
                      <HistoryOutlined style={{ marginRight: 8 }} />
                      {isOvertimeReviewer ? 'Reviewed Overtime' : 'Validated Overtime'}
                    </span>
                  ),
                },
              ]}
              style={{ marginBottom: 0 }}
              tabBarStyle={{ marginBottom: 0 }}
            />
          </Col>
          <Col>
            <Space>
              <Input
                placeholder={searchPlaceholder}
                prefix={<SearchOutlined />}
                value={searchText}
                onChange={(e) => setSearchText(e.target.value)}
                allowClear
                style={{ width: 300 }}
              />
              <Button
                icon={<ReloadOutlined />}
                onClick={() => {
                  // Clear cached actioned data to force refetch
                  if (activeTab === 'actioned') {
                    setAllActionedData([]);
                  }
                  const currentPage = pagination.current || 1;
                  const currentPageSize = pagination.pageSize || 15;
                  fetchOvertimeRecords(activeTab, currentPage, currentPageSize);
                }}
                loading={loading}
              >
                Refresh
              </Button>
            </Space>
          </Col>
        </Row>
      </div>

      {/* Tab Content */}
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
          showSearch={false}
          showRefresh={false}
          rowKey="id"
          size="middle"
          bordered={true}
          onChange={(paginationInfo) => {
            if (paginationInfo) {
              const newPage = paginationInfo.current || 1;
              const newPageSize = paginationInfo.pageSize || 15;
              fetchOvertimeRecords(activeTab, newPage, newPageSize);
            }
          }}
          onRow={(record) => ({
            onClick: () => {
              setSelectedOvertimeId(record.id);
              setIsDetailModalOpen(true);
            },
            style: { cursor: 'pointer' },
          })}
        />
      </div>

      {/* Overtime Detail Modal */}
      <OvertimeDetailModal
        open={isDetailModalOpen}
        onClose={() => {
          setIsDetailModalOpen(false);
          setSelectedOvertimeId(null);
        }}
        overtimeId={selectedOvertimeId}
        onRefresh={() => {
          const currentPage = pagination.current || 1;
          const currentPageSize = pagination.pageSize || 15;
          fetchOvertimeRecords(activeTab, currentPage, currentPageSize);
        }}
      />
    </div>
  );
};

export default ManageOvertime;

