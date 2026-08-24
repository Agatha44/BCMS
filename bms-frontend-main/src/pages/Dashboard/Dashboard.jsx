import { useEffect, useMemo, useState } from 'react';
import { Card, Row, Col, Statistic, Button, App, Empty, Tag } from 'antd';
import {
  UserOutlined,
  ClockCircleOutlined,
  CheckCircleOutlined,
  TeamOutlined,
  CalendarOutlined,
  DollarOutlined,
  FileTextOutlined,
  SafetyOutlined,
  ExclamationCircleOutlined,
  PlusOutlined,
} from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import ReactApexChart from 'react-apexcharts';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse } from '../../common/utils/employeeUtils.jsx';
import { attendanceService } from '../../services/attendanceService.js';
import { transformAttendanceData } from '../../common/utils/attendanceUtils.js';
import EmployeeManagementDashboard from './modules/EmployeeManagementDashboard.jsx';
import AllowanceManagementDashboard from './modules/AllowanceManagementDashboard.jsx';
import PayrollManagementDashboard from './modules/PayrollManagementDashboard.jsx';
import PlaceholderModuleDashboard from './modules/PlaceholderModuleDashboard.jsx';
import AccountManagementDashboard from './modules/AccountManagementDashboard.jsx';
import AdministrationManagementDashboard from './modules/AdministrationManagementDashboard.jsx';
import IncidentManagementDashboard from './modules/IncidentManagementDashboard.jsx';
import GeneralDashboard from './modules/GeneralDashboard.jsx';
import CollectionDashboard from '../CollectionManagement/CollectionDashboard.jsx';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import './Dashboard.css';
import '../../styles/common.css';

const Dashboard = () => {
  const { message } = App.useApp();
  const navigate = useNavigate();
  const selectedModule = useSelector((state) => state.app.selectedModule);
  const currentUser = useSelector((state) => state.auth.user);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState({
    totalEmployees: 0,
    activeAttendanceToday: 0,
    pendingOvertimeRequests: 0,
    totalRoles: 0,
    totalOvertimeRecords: 0,
    approvedOvertimeThisMonth: 0,
    totalEducationalLevels: 0,
    totalPermissions: 0,
    totalModules: 0,
    totalMenuAssignments: 0,
    totalIncidents: 0,
    openIncidents: 0,
    pendingIncidents: 0,
    resolvedIncidents: 0,
  });

  const [attendanceData, setAttendanceData] = useState([]);
  const [overtimeData, setOvertimeData] = useState([]);
  const [recentActivities, setRecentActivities] = useState([]);
  const [todayAttendance, setTodayAttendance] = useState({
    loading: false,
    timeIn: null,
    timeOut: null,
  });

  // Use PF number as employee_id since attendance log database doesn't have user_id
  const employeePfNumber = useMemo(() => {
    return (
      currentUser?.pfNumber ||
      currentUser?.employeeId ||
      null
    );
  }, [currentUser]);

  useEffect(() => {
    fetchDashboardData();
  }, [selectedModule, currentUser]);

  // Helper function to check if data belongs to current user
  const belongsToUser = (item) => {
    if (!currentUser) return false;
    
    const userIdentifiers = [
      currentUser.username,
      currentUser.national_id,
      currentUser.nationalId,
      currentUser.user_id,
      currentUser.id,
      currentUser.email
    ].filter(Boolean);

    // Check various possible fields that might link data to user
    return userIdentifiers.some(identifier => {
      if (!identifier) return false;
      const normalizedId = String(identifier).toLowerCase().trim();
      
      return (
        String(item.username || '').toLowerCase().trim() === normalizedId ||
        String(item.national_id || item.nationalId || '').toLowerCase().trim() === normalizedId ||
        String(item.user_id || item.userId || '').toLowerCase().trim() === normalizedId ||
        String(item.employee_id || item.employeeId || '').toLowerCase().trim() === normalizedId ||
        String(item.created_by || item.createdBy || '').toLowerCase().trim() === normalizedId ||
        String(item.applicant_id || item.applicantId || '').toLowerCase().trim() === normalizedId ||
        String(item.user_email || item.email || '').toLowerCase().trim() === normalizedId
      );
    });
  };

  const fetchDashboardData = async () => {
    setLoading(true);
    try {
      // Always fetch today's time-in/time-out (if user is known)
      await fetchTodayAttendanceTimes();

      // Fetch data based on selected module
      if (!selectedModule) {
        // If no module selected, fetch general data
        const [
          employeesRes,
          attendanceRes,
          overtimeRes,
          rolesRes,
          educationalLevelsRes,
          permissionsRes,
        ] = await Promise.allSettled([
          apiService.getBridgeEmployees({ per_page: 1000 }),
          apiService.getAttendanceManagement({ per_page: 100 }),
          apiService.getOvertimeRecords({ per_page: 100 }),
          apiService.getBmsRoles(),
          apiService.getEducationalLevels(),
          apiService.getBmsPermissions(),
        ]);
        
        processGeneralData(employeesRes, attendanceRes, overtimeRes, rolesRes, educationalLevelsRes, permissionsRes);
      } else {
        // Fetch module-specific data
        await fetchModuleSpecificData(selectedModule);
      }
    } catch (error) {
      console.error('Error fetching dashboard data:', error);
      message.error('Failed to load dashboard data');
    } finally {
      setLoading(false);
    }
  };

  const processGeneralData = (employeesRes, attendanceRes, overtimeRes, rolesRes, educationalLevelsRes, permissionsRes) => {
    // Process employees - filter by current user
    if (employeesRes.status === 'fulfilled' && employeesRes.value.success) {
      const { items: employees } = extractArrayFromResponse(employeesRes.value.data);
      const userEmployees = currentUser ? employees.filter(belongsToUser) : [];
      setStats(prev => ({ ...prev, totalEmployees: userEmployees.length }));
    }

    // Process attendance - filter by current user
    if (attendanceRes.status === 'fulfilled' && attendanceRes.value.success) {
      const attendance = Array.isArray(attendanceRes.value.data) 
        ? attendanceRes.value.data 
        : [];
      
      const userAttendance = currentUser ? attendance.filter(belongsToUser) : [];
      
      // Count today's active attendance
      const today = new Date().toISOString().split('T')[0];
      const todayAttendance = userAttendance.filter(item => {
        const itemDate = item.date || item.created_at;
        return itemDate && itemDate.startsWith(today);
      });
      
      setStats(prev => ({ 
        ...prev, 
        activeAttendanceToday: todayAttendance.length 
      }));
      setAttendanceData(userAttendance.slice(0, 7)); // Last 7 days for chart
    }

    // Process overtime - filter by current user
    if (overtimeRes.status === 'fulfilled' && overtimeRes.value.success) {
      // API returns response.data.data
      const overtime = Array.isArray(overtimeRes.value.data?.data) 
        ? overtimeRes.value.data.data 
        : [];
      
      const userOvertime = currentUser ? overtime.filter(belongsToUser) : [];
      
      // Count pending requests (status: pending, submitted, etc.)
      const pending = userOvertime.filter(item => {
        const status = item.status?.toLowerCase() || '';
        return status.includes('pending') || status.includes('submitted') || 
               status === '0' || status === 0;
      });
      
      // Count approved this month
      const currentMonth = new Date().getMonth();
      const currentYear = new Date().getFullYear();
      const approvedThisMonth = userOvertime.filter(item => {
        const status = item.status?.toLowerCase() || '';
        const isApproved = status.includes('approved') || status === '1' || status === 1;
        if (!isApproved) return false;
        
        const itemDate = new Date(item.created_at || item.date);
        return itemDate.getMonth() === currentMonth && 
               itemDate.getFullYear() === currentYear;
      });
      
      setStats(prev => ({ 
        ...prev, 
        pendingOvertimeRequests: pending.length,
        totalOvertimeRecords: userOvertime.length,
        approvedOvertimeThisMonth: approvedThisMonth.length,
      }));
      setOvertimeData(userOvertime.slice(0, 10)); // Last 10 for chart
      setRecentActivities(userOvertime.slice(0, 5)); // Recent 5 activities
    }

    // Process roles - roles are typically not user-specific, but we'll keep as is
    if (rolesRes.status === 'fulfilled' && rolesRes.value.success) {
      const roles = Array.isArray(rolesRes.value.data) 
        ? rolesRes.value.data 
        : [];
      setStats(prev => ({ ...prev, totalRoles: roles.length }));
    }

    // Process educational levels - typically not user-specific
    if (educationalLevelsRes.status === 'fulfilled' && educationalLevelsRes.value.success) {
      // API returns response.data.data
      const levels = Array.isArray(educationalLevelsRes.value.data?.data) 
        ? educationalLevelsRes.value.data.data 
        : [];
      setStats(prev => ({ ...prev, totalEducationalLevels: levels.length }));
    }

    // Process permissions - typically not user-specific
    if (permissionsRes.status === 'fulfilled' && permissionsRes.value.success) {
      const permissions = Array.isArray(permissionsRes.value.data) 
        ? permissionsRes.value.data 
        : [];
      setStats(prev => ({ ...prev, totalPermissions: permissions.length }));
    }
  };

  const fetchModuleSpecificData = async (module) => {
    switch (module) {
      case 'employee-management':
        await fetchEmployeeManagementData();
        break;
      case 'allowance-management':
        await fetchAllowanceManagementData();
        break;
      case 'collection-management':
        await fetchCollectionManagementData();
        break;
      case 'leave-management':
        await fetchLeaveManagementData();
        break;
      case 'payroll-management':
        await fetchPayrollManagementData();
        break;
      case 'account-management':
        await fetchAccessManagementData();
        break;
      case 'administration-management':
        break;
      case 'incident-management':
        await fetchIncidentManagementData();
        break;
      default:
        // Reset stats for unknown modules
        setStats({
          totalEmployees: 0,
          activeAttendanceToday: 0,
          pendingOvertimeRequests: 0,
          totalRoles: 0,
          totalOvertimeRecords: 0,
          approvedOvertimeThisMonth: 0,
          totalEducationalLevels: 0,
          totalPermissions: 0,
          totalModules: 0,
          totalMenuAssignments: 0,
          totalIncidents: 0,
          openIncidents: 0,
          pendingIncidents: 0,
          resolvedIncidents: 0,
        });
    }
  };

  const fetchEmployeeManagementData = async () => {
    try {
      const [employeesRes, attendanceRes, rolesRes] = await Promise.allSettled([
        apiService.getBridgeEmployees({ per_page: 1000 }),
        apiService.getAttendanceManagement({ per_page: 100 }),
        apiService.getBmsRoles(),
      ]);

      if (employeesRes.status === 'fulfilled' && employeesRes.value.success) {
        const { items: employees } = extractArrayFromResponse(employeesRes.value.data);
        const userEmployees = currentUser ? employees.filter(belongsToUser) : [];
        setStats(prev => ({ ...prev, totalEmployees: userEmployees.length }));
      }

      if (attendanceRes.status === 'fulfilled' && attendanceRes.value.success) {
        const attendance = Array.isArray(attendanceRes.value.data) ? attendanceRes.value.data : [];
        const userAttendance = currentUser ? attendance.filter(belongsToUser) : [];
        const today = new Date().toISOString().split('T')[0];
        const todayAttendance = userAttendance.filter(item => {
          const itemDate = item.date || item.created_at;
          return itemDate && itemDate.startsWith(today);
        });
        setStats(prev => ({ ...prev, activeAttendanceToday: todayAttendance.length }));
        setAttendanceData(userAttendance.slice(0, 7));
      }

      if (rolesRes.status === 'fulfilled' && rolesRes.value.success) {
        const roles = Array.isArray(rolesRes.value.data) ? rolesRes.value.data : [];
        setStats(prev => ({ ...prev, totalRoles: roles.length }));
      }
    } catch (error) {
      console.error('Error fetching employee management data:', error);
    }
  };

  const fetchTodayAttendanceTimes = async () => {

    const employeePfNumber = currentUser?.pf_number;

    console.log('employeePfNumber', employeePfNumber);

    if (!currentUser || !employeePfNumber) {
      setTodayAttendance({ loading: false, timeIn: null, timeOut: null });
      return;
    }

    const today = new Date().toISOString().split('T')[0];
    setTodayAttendance((prev) => ({ ...prev, loading: true }));
    try {
      const response = await attendanceService.getUserSessions({
        start_date: today,
        end_date: today,
        per_page: 50,
      });

      if (!response?.success) {
        setTodayAttendance({ loading: false, timeIn: null, timeOut: null });
        return;
      }

      const dataArray = Array.isArray(response.data) ? response.data : [];
      const transformed = transformAttendanceData(dataArray);
      const todayRecord = transformed.find((r) => r?.dayDate === today) || transformed[0] || null;

      setTodayAttendance({
        loading: false,
        timeIn: todayRecord?.timeIn || null,
        timeOut: todayRecord?.timeOut || null,
      });
    } catch (error) {
      console.error('Error fetching today attendance times:', error);
      setTodayAttendance({ loading: false, timeIn: null, timeOut: null });
    }
  };

  const fetchAllowanceManagementData = async () => {
    try {
      const response = await apiService.getOvertimeRecords({ per_page: 100 });
    
      if (!response?.success) return;
  
      const overtimeList = Array.isArray(response.data?.data) ? response.data.data : [];
  
      // Filter records for logged-in user
      const userPfNumber = currentUser?.pf_number;
      if (!userPfNumber) {
        setStats((prev) => ({
          ...prev,
          pendingOvertimeRequests: 0,
          totalOvertimeRecords: 0,
          approvedOvertimeThisMonth: 0,
        }));
        return;
      }

      const userOvertime = overtimeList.filter(item => {
        if (!item) return false;
        const pf = item.pfNumber || item.pf_number || item.pfno || item.pfNo;
        return String(pf || '').trim() === String(userPfNumber).trim();
      });
  
      // Pending overtime
      const pendingOvertime = userOvertime.filter(item => {
        const status = String(item?.workflowStatus || '').toLowerCase().trim();
        return (
          status === 'applied' ||
          status === 'validated' ||
          status === 'reviewed'
        );
      });
  
      // Approved overtime
      const approvedOvertime = userOvertime.filter(item => {
        const status = String(item?.workflowStatus || '').toLowerCase().trim();
        return (
          status === 'approved'
        );
      });
  
      // Update stats
      setStats(prev => ({
        ...prev,
        pendingOvertimeRequests: pendingOvertime.length,
        totalOvertimeRecords: userOvertime.length,
        approvedOvertimeThisMonth: approvedOvertime.length,
      }));
  
    } catch (error) {
      console.error('Error fetching allowance management data:', error);
    }
  };

  const fetchCollectionManagementData = async () => {
    // Placeholder for collection management data
    // Add API calls when available
    setStats({
      totalEmployees: 0,
      activeAttendanceToday: 0,
      pendingOvertimeRequests: 0,
      totalRoles: 0,
      totalOvertimeRecords: 0,
      approvedOvertimeThisMonth: 0,
      totalEducationalLevels: 0,
      totalPermissions: 0,
    });
  };

  const fetchLeaveManagementData = async () => {
    // Placeholder for leave management data
    // Add API calls when available
    setStats({
      totalEmployees: 0,
      activeAttendanceToday: 0,
      pendingOvertimeRequests: 0,
      totalRoles: 0,
      totalOvertimeRecords: 0,
      approvedOvertimeThisMonth: 0,
      totalEducationalLevels: 0,
      totalPermissions: 0,
    });
  };

  const fetchPayrollManagementData = async () => {
    // Placeholder for payroll management data
    // Add API calls when available
    setStats({
      totalEmployees: 0,
      activeAttendanceToday: 0,
      pendingOvertimeRequests: 0,
      totalRoles: 0,
      totalOvertimeRecords: 0,
      approvedOvertimeThisMonth: 0,
      totalEducationalLevels: 0,
      totalPermissions: 0,
    });
  };

  const countFromAccessApi = (result) => {
    if (result.status !== 'fulfilled' || !result.value?.success || !result.value?.data) return 0;
    const data = result.value.data;
    if (typeof data.count === 'number') return data.count;
    if (typeof data.total === 'number') return data.total;
    const { items, pagination } = extractArrayFromResponse(data);
    return pagination?.total ?? items.length;
  };

  const fetchAccessManagementData = async () => {
    try {
      const [rolesRes, permissionsRes, modulesRes, menuAssignmentsRes] = await Promise.allSettled([
        apiService.getRolesList({ per_page: 1 }),
        apiService.getBmsPermissions({ per_page: 1 }),
        apiService.getBmsModules({ per_page: 1 }),
        apiService.getBmsRoleModuleMenus({ per_page: 1 }),
      ]);

      setStats((prev) => ({
        ...prev,
        totalRoles: countFromAccessApi(rolesRes),
        totalPermissions: countFromAccessApi(permissionsRes),
        totalModules: countFromAccessApi(modulesRes),
        totalMenuAssignments: countFromAccessApi(menuAssignmentsRes),
      }));
    } catch (error) {
      console.error('Error fetching access management data:', error);
    }
  };

  const fetchIncidentManagementData = async () => {
    try {
      // Placeholder: Replace with actual API call when available
      // const response = await apiService.getIncidents();
      // if (response.success) {
      //   const incidents = Array.isArray(response.data) ? response.data : [];
      //   setStats(prev => ({
      //     ...prev,
      //     totalIncidents: incidents.length,
      //     openIncidents: incidents.filter(i => i.status === 'open').length,
      //     resolvedIncidents: incidents.filter(i => i.status === 'resolved').length,
      //     pendingIncidents: incidents.filter(i => i.status === 'pending').length,
      //   }));
      // }
      
      // For now, set placeholder data
      setStats(prev => ({
        ...prev,
        totalIncidents: 0,
        openIncidents: 0,
        resolvedIncidents: 0,
        pendingIncidents: 0,
      }));
    } catch (error) {
      console.error('Error fetching incident management data:', error);
      message.error('Failed to load incident data');
    }
  };

  // Prepare attendance chart data
  const attendanceChartOptions = {
    chart: {
      type: 'area',
      height: typeof window !== 'undefined' && window.innerWidth < 640 ? 250 : 350,
      toolbar: { show: false },
    },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    colors: ['#962E32'],
    xaxis: {
      categories: attendanceData.map((_, index) => {
        const date = new Date();
        date.setDate(date.getDate() - (6 - index));
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      }),
    },
    yaxis: { title: { text: 'Attendance Count' } },
    title: { text: 'Attendance Trend (Last 7 Days)', style: { fontSize: '16px', fontWeight: 600 } },
    grid: { borderColor: '#e7e7e7' },
  };

  const attendanceChartSeries = [{
    name: 'Attendance',
    data: attendanceData.map(() => Math.floor(Math.random() * 50) + 20), // Placeholder data
  }];

  // Prepare overtime status chart data
  const overtimeStatusCounts = {
    pending: stats.pendingOvertimeRequests,
    approved: stats.approvedOvertimeThisMonth,
    total: stats.totalOvertimeRecords,
  };

  const overtimeChartOptions = {
    chart: {
      type: 'donut',
      height: typeof window !== 'undefined' && window.innerWidth < 640 ? 250 : 350,
    },
    labels: ['Pending', 'Approved', 'Total'],
    colors: ['#f59e0b', '#10b981', '#962E32'],
    legend: { position: 'bottom' },
    title: { text: 'Overtime Requests Status', style: { fontSize: '16px', fontWeight: 600 } },
    dataLabels: { enabled: true },
  };

  const overtimeChartSeries = [
    overtimeStatusCounts.pending,
    overtimeStatusCounts.approved,
    overtimeStatusCounts.total - overtimeStatusCounts.pending - overtimeStatusCounts.approved
  ];

  const getModuleTitle = () => {
    const moduleTitles = {
      'employee-management': 'Employee Management',
      'collection-management': 'Toll Management',
      'leave-management': 'Leave Management',
      'payroll-management': 'Payroll Management',
      'allowance-management': 'Allowance Management',
      'account-management': 'Account Management',
      'administration-management': 'Administration Management',
      'incident-management': 'Report Incident',
    };
    return moduleTitles[selectedModule] || 'Dashboard';
  };

  const getModuleDescription = () => {
    const moduleDescriptions = {
      'employee-management': 'View your employee information, attendance, and related statistics',
      'collection-management': 'View your toll records and statistics',
      'leave-management': 'View your leave requests and related information',
      'payroll-management': 'View your payroll information and payment history',
      'allowance-management': 'View your allowances, overtime requests, and related statistics',
      'account-management': 'Manage user access, permissions and security controls',
      'administration-management': 'Configure reports, system settings, and administration tools',
      'incident-management': 'View reported incidents and track their resolution',
    };
    return moduleDescriptions[selectedModule] || 'View your personalized statistics and information';
  };

  if (loading) {
    return (
      <div className="flex min-h-[400px] items-center justify-center">
        <CollectionLoader />
      </div>
    );
  }

  return (
    <div className="space-y-4 sm:space-y-6">
      {/* Employee Management Dashboard */}
      {selectedModule === 'employee-management' && (
        <EmployeeManagementDashboard
          stats={stats}
          todayAttendance={todayAttendance}
          onViewProfile={() => navigate('/employee-management/profile')}
          onViewAttendance={() => navigate('/employee-management/attendance')}
        />
      )}

      {/* Allowance Management Dashboard */}
      {selectedModule === 'allowance-management' && (
        <AllowanceManagementDashboard
          stats={stats}
          onViewOvertime={() => navigate('/allowance-management/overtime-management')}
        />
      )}

      {/* Collection Management Dashboard */}
      {selectedModule === 'collection-management' && (
        <CollectionDashboard />
      )}

      {/* Leave Management Dashboard */}
      {selectedModule === 'leave-management' && (
        <PlaceholderModuleDashboard
          icon={<CalendarOutlined className="dashboard-icon-large" />}
          title="Leave Management Dashboard"
          description="Leave management features coming soon"
        />
      )}

      {/* Payroll Management Dashboard */}
      {selectedModule === 'payroll-management' && (
        <PayrollManagementDashboard />
      )}

      {/* Access Management Dashboard */}
      {selectedModule === 'account-management' && (
        <AccountManagementDashboard
          stats={stats}
          onManageUsers={() => navigate('/admin-management/manage-users')}
          onManageRoles={() => navigate('/admin-management/roles')}
          onManagePermissions={() => navigate('/admin-management/permission-management')}
          onManageModules={() => navigate('/admin-management/manage-modules')}
          onGrantRole={() => navigate('/admin-management/manage-users?tab=bms-users')}
          onRoleModuleMenu={() => navigate('/admin-management/manage-modules?tab=role-module-menu')}
        />
      )}

      {selectedModule === 'administration-management' && (
        <AdministrationManagementDashboard
          onOpenConfiguration={() => navigate('/administration-management/configuration')}
          onOpenReportEngine={() => navigate('/administration-management/report-engine')}
        />
      )}

      {/* Incident Management Dashboard */}
      {selectedModule === 'incident-management' && (
        <IncidentManagementDashboard
          stats={stats}
          onReportIncident={() => message.info('Create incident feature coming soon')}
        />
      )}

      {/* General Dashboard (when no module is selected) */}
      {!selectedModule && (
        <GeneralDashboard
          stats={stats}
          todayAttendance={todayAttendance}
          recentActivities={recentActivities}
          onViewProfile={() => navigate('/employee-management/profile')}
          onViewAttendance={() => navigate('/employee-management/attendance')}
          onViewOvertime={() => navigate('/allowance-management/overtime-management')}
          onManageRoles={() => navigate('/admin-management/roles')}
        />
      )}
    </div>
  );
};

export default Dashboard;
