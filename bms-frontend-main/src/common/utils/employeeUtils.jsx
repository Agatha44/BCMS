import { Tag } from 'antd';
import {
  CheckCircleOutlined,
  CloseCircleOutlined,
  HourglassOutlined,
  StopOutlined
} from '@ant-design/icons';

/**
 * Extract array from various API response structures
 */
export const extractArrayFromResponse = (responseData) => {
  if (!responseData) return { items: [], pagination: {} };

  // Payroll paginated list shape: { "0": [...], pagination: {...} }
  if (Array.isArray(responseData['0'])) {
    return {
      items: responseData['0'],
      pagination: responseData.pagination || {},
    };
  }

  // Handle different response structures
  if (responseData.pending_requests && Array.isArray(responseData.pending_requests)) {
    return {
      items: responseData.pending_requests,
      pagination: {
        total: responseData.count || responseData.pending_requests.length,
        current_page: responseData.pagination?.current_page,
        per_page: responseData.pagination?.per_page
      }
    };
  }
  if (responseData.requests && Array.isArray(responseData.requests)) {
    return {
      items: responseData.requests,
      pagination: responseData.pagination || {}
    };
  }
  if (Array.isArray(responseData)) {
    return { items: responseData, pagination: {} };
  }
  if (responseData.data && Array.isArray(responseData.data)) {
    return {
      items: responseData.data,
      pagination: responseData.pagination || {}
    };
  }
  if (responseData.results && Array.isArray(responseData.results)) {
    return {
      items: responseData.results,
      pagination: responseData.pagination || {}
    };
  }
  if (responseData.items && Array.isArray(responseData.items)) {
    return {
      items: responseData.items,
      pagination: responseData.pagination || {}
    };
  }
  if (responseData.employees && Array.isArray(responseData.employees)) {
    return {
      items: responseData.employees,
      pagination: responseData.pagination || {}
    };
  }

  // Try to extract any array from the response
  const dataValues = Object.values(responseData || {});
  const foundArray = dataValues.find(val => Array.isArray(val));
  if (foundArray) {
    return {
      items: foundArray,
      pagination: responseData.pagination || {}
    };
  }

  return { items: [], pagination: {} };
};

/**
 * Format employee full name from various field structures
 */
export const formatEmployeeName = (employee) => {
  if (!employee) return 'N/A';

  if (employee.full_name) return employee.full_name;

  if (employee.fname || employee.sname) {
    return `${employee.fname || ''} ${employee.sname || ''}`.trim() || 'N/A';
  }

  if (employee.first_name || employee.surname) {
    return `${employee.first_name || ''} ${employee.surname || ''}`.trim() || 'N/A';
  }

  return employee.name || 'N/A';
};

/**
 * Get employee status display configuration
 */
export const getEmployeeStatus = (record) => {
  const referralStatus = record.referral_request?.status?.toLowerCase();
  const status = (record.employee_status || record.status || record.approval_status?.status || '').toLowerCase();

  // Handle referral request status
  if (referralStatus === 'pending') {
    const actionType = record.referral_request?.action_type?.toLowerCase();
    if (actionType === 'delete') {
      return { text: 'Pending Deletion', color: 'red', icon: <HourglassOutlined /> };
    }
    if (actionType === 'terminate') {
      return { text: 'Pending Termination', color: 'orange', icon: <HourglassOutlined /> };
    }
    if (actionType === 'update') {
      return { text: 'Pending Update', color: 'orange', icon: <HourglassOutlined /> };
    }
    return { text: 'Pending Approval', color: 'orange', icon: <HourglassOutlined /> };
  }

  // Handle employee status
  if (status.includes('pending_deletion') || status.includes('pending deletion')) {
    return { text: 'Pending Deletion', color: 'red', icon: <HourglassOutlined /> };
  }
  if (status.includes('pending_termination') || status.includes('pending termination') || status.includes('termination pending')) {
    return { text: 'Pending Termination', color: 'orange', icon: <HourglassOutlined /> };
  }
  if (status.includes('pending_update') || status.includes('pending update')) {
    return { text: 'Pending Update', color: 'orange', icon: <HourglassOutlined /> };
  }
  if (status.includes('pending_approval') || status.includes('pending approval') || status.includes('pending') || status.includes('submitted')) {
    return { text: 'Pending Approval', color: 'orange', icon: <HourglassOutlined /> };
  }
  if (status.includes('pending_creation') || status.includes('pending creation')) {
    return { text: 'Pending Creation', color: 'yellow', icon: <HourglassOutlined /> };
  }
  if (status.includes('approved')) {
    return { text: 'Approved', color: 'green', icon: <CheckCircleOutlined /> };
  }
  if (status.includes('terminated')) {
    return { text: 'Terminated', color: 'default', icon: <StopOutlined /> };
  }
  if (status.includes('deleted')) {
    return { text: 'Deleted', color: 'red', icon: <CloseCircleOutlined /> };
  }
  if (status.includes('rejected')) {
    return { text: 'Rejected', color: 'red', icon: <CloseCircleOutlined /> };
  }
  return { text: 'Active', color: 'blue', icon: null };
};

/**
 * Check if employee is pending approval
 */
export const isPendingApproval = (record) => {
  const status = (record.employee_status || record.status || '').toLowerCase();
  return status.includes('pending') || status.includes('submitted');
};

/**
 * Check if employee has pending termination request
 */
export const isPendingTermination = (record) => {
  const status = (record.employee_status || record.status || '').toLowerCase();
  return status.includes('pending termination') || status.includes('termination pending');
};

/**
 * Get action type display configuration
 */
export const getActionTypeDisplay = (actionType) => {
  const configs = {
    'CREATE': { text: 'Create', color: 'blue' },
    'UPDATE': { text: 'Update', color: 'orange' },
    'DELETE': { text: 'Delete', color: 'red' },
    'TERMINATE': { text: 'Terminate', color: 'orange' },
  };
  const config = configs[actionType] || { text: actionType, color: 'default' };
  return <Tag color={config.color}>{config.text}</Tag>;
};

/**
 * Update pagination state from API response
 */
export const updatePaginationFromResponse = (paginationData, page, pageSize, totalCount) => {
  return {
    current: paginationData?.current_page || paginationData?.page || page,
    pageSize: Number(paginationData?.per_page) || Number(paginationData?.pageSize) || pageSize,
    total: paginationData?.total || totalCount || 0
  };
};

/**
 * Map referral request to employee-like structure
 */
export const mapReferralRequestToEmployee = (req, employee = {}) => {
  const fullName = formatEmployeeName(employee);

  return {
    ...employee,
    id: employee.id || req.id,
    national_id: req.national_id || employee.national_id,
    pfno: employee.pfno || employee.pf_number || employee.pfNumber,
    full_name: fullName,
    email: employee.email || req.initiator?.email || 'N/A',
    username: employee.username || 'N/A',
    referral_request: req,
    approval_status: employee.approval_status || { status: req.status },
  };
};

/**
 * Normalize role name for comparison (case-insensitive)
 */
const normalizeRole = (roleName) => {
  if (!roleName) return '';
  return String(roleName).trim().toLowerCase();
};

/**
 * Check if user has Employee Registrar role
 * @param {string} selectedRole - The selected role from Redux state
 * @param {Array} userRoles - Array of user roles (optional fallback)
 * @returns {boolean}
 */
export const hasEmployeeRegistrarRole = (selectedRole, userRoles = []) => {
  const normalizedSelected = normalizeRole(selectedRole);
  const normalizedRoles = userRoles.map(normalizeRole);
  
  return normalizedSelected === 'employee registrar' || 
         normalizedRoles.includes('employee registrar');
};

/**
 * Check if user has Employee Approver role (supports both "employee approver" and "employer approver")
 * @param {string} selectedRole - The selected role from Redux state
 * @param {Array} userRoles - Array of user roles (optional fallback)
 * @returns {boolean}
 */
export const hasEmployeeApproverRole = (selectedRole, userRoles = []) => {
  const normalizedSelected = normalizeRole(selectedRole);
  const normalizedRoles = userRoles.map(normalizeRole);
  
  return normalizedSelected === 'employee approver' ||
         normalizedRoles.includes('employee approver');
};


const matchPayrollRole = (selectedRole, roleKeyword, userRoles = []) => {
  const keyword = normalizeRole(roleKeyword);
  const normalizedSelected = normalizeRole(selectedRole);
  const normalizedRoles = userRoles.map(normalizeRole);

  if (normalizedSelected.includes(keyword)) return true;
  return normalizedRoles.some((r) => r.includes(keyword));
};

export const hasPayrollInitiatorRole = (selectedRole, userRoles = []) =>
  matchPayrollRole(selectedRole, 'payroll initiator', userRoles);

export const hasPayrollExaminerRole = (selectedRole, userRoles = []) =>
  matchPayrollRole(selectedRole, 'payroll examiner', userRoles);

export const hasPayrollVerifierRole = (selectedRole, userRoles = []) =>
  matchPayrollRole(selectedRole, 'payroll verifier', userRoles);

export const hasPayrollApproverRole = (selectedRole, userRoles = []) =>
  matchPayrollRole(selectedRole, 'payroll approver', userRoles);

export const hasOvertimeReviewerRole = (selectedRole, userRoles = []) =>
  matchPayrollRole(selectedRole, 'overtime reviewer', userRoles);

export const hasOvertimeAccountantRole = (selectedRole, userRoles = []) =>
  matchPayrollRole(selectedRole, 'overtime accountant', userRoles);

export const canRepostOvertimeErms = (selectedRole, userRoles = []) =>
  hasOvertimeReviewerRole(selectedRole, userRoles) ||
  hasOvertimeAccountantRole(selectedRole, userRoles);

/** @deprecated Use hasPayrollInitiatorRole */
export const hasPayrollRole = hasPayrollInitiatorRole;

const PAYROLL_WORKFLOW_ROLE_BY_STATUS = {
  prepared: hasPayrollInitiatorRole,
  initiated: hasPayrollExaminerRole,
  examined: hasPayrollVerifierRole,
  verified: hasPayrollApproverRole,
  approved: hasPayrollInitiatorRole,
};

export const canPerformPayrollWorkflowAction = (status, selectedRole, userRoles = []) => {
  const checker = PAYROLL_WORKFLOW_ROLE_BY_STATUS[String(status || '').toLowerCase().trim()];
  return checker ? checker(selectedRole, userRoles) : false;
};

