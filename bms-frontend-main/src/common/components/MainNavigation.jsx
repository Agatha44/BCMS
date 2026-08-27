import {
  LayoutDashboard,
  Users,
  Shield,
  Calendar,
  Clock,
  GraduationCap,
  DollarSign,
  Building2,
  Wallet,
  FileText,
  AlertTriangle,
  UserCog,
  AppWindow,
  UserCircle,
  Globe,
  Briefcase,
  Landmark,
  Network,
  Layers,
  Bell,
  BarChart3,
  CreditCard,
  Settings,
  Car,
  Banknote,
  ArrowRightLeft,
  Receipt,
  Scale,
} from 'lucide-react';

/**
 * @typedef {Object} NavItem
 * @property {string} id - Unique identifier for the navigation item
 * @property {string} label - Display label for the navigation item
 * @property {React.ReactNode} icon - Icon component for the navigation item
 * @property {string} [path] - Optional route path for the navigation item
 * @property {NavItem[]} [children] - Optional array of child navigation items
 * @property {string[]} roles - Array of role names that can access this item
 */

/**
 * SoD-aligned navigation block (new structure).
 * Each parent's `roles` mirrors the "Mapped from" list for that menu.
 * Sub-functions are surfaced as tabs inside each parent's page (not as
 * collapsible children in the sidebar), so the sidebar stays clean.
 */
const SOD_NAV_ITEMS = [
  {
    id: 'sod-vehicle-management',
    label: 'Manage Vehicles',
    icon: <Car size={20} />,
    path: '/collection-management/vehicle-management',
    roles: ['Toll Registrar', 'Toll Supervisor', 'Toll Approver'],
  },
  {
    id: 'sod-account-management',
    label: 'Manage Accounts',
    icon: <CreditCard size={20} />,
    path: '/collection-management/account-management',
    roles: [
      'Toll Registrar',
      'Toll Collector',
      'Toll Supervisor',
      'Toll Reviewer',
      'Toll Auditor',
      'Toll Administrator',
      'Toll Accountant',
    ],
  },
  {
    id: 'sod-collection-management',
    label: 'Manage Collections',
    icon: <Layers size={20} />,
    path: '/collection-management/collections',
    roles: [
      'Toll Registrar',
      'Toll Collector',
      'Toll Supervisor',
      'Toll Approver',
      'Toll Reviewer',
      'Toll Auditor',
      'Toll Administrator',
      'Toll Accountant',
    ],
  },
  {
    id: 'sod-receipt-management',
    label: 'Manage Receipts',
    icon: <Receipt size={20} />,
    path: '/collection-management/receipt-management',
    roles: [
      'Toll Registrar',
      'Toll Collector',
      'Toll Supervisor',
      'Toll Approver',
      'Toll Reviewer',
      'Toll Auditor',
      'Toll Administrator',
      'Toll Accountant',
    ],
  },
  {
    id: 'sod-pre-receipt-management',
    label: 'Manage Pre-receipts',
    icon: <FileText size={20} />,
    path: '/collection-management/pre-receipt-management',
    roles: [
      'Toll Registrar',
      'Toll Collector',
      'Toll Supervisor',
      'Toll Approver',
      'Toll Reviewer',
      'Toll Auditor',
      'Toll Administrator',
      'Toll Accountant',
    ],
  },
  {
    id: 'sod-reconciliation-management',
    label: 'Manage Update-receipts',
    icon: <Scale size={20} />,
    path: '/collection-management/reconciliation-management',
    roles: [
      'Toll Registrar',
      'Toll Collector',
      'Toll Supervisor',
      'Toll Approver',
      'Toll Reviewer',
      'Toll Auditor',
      'Toll Administrator',
      'Toll Accountant',
    ],
  },
  {
    id: 'sod-shift-record-transfer',
    label: 'Shift Record Transfer',
    icon: <ArrowRightLeft size={20} />,
    path: '/collection-management/shift-record-transfer',
    roles: [
      'Toll Supervisor',
      'Toll Reviewer',
      'Toll Auditor',
      'Toll Administrator',
      'Toll Accountant',
    ],
  },
  {
    id: 'manage-configurations',
    label: 'Manage Settings',
    icon: <Settings size={20} />,
    path: '/collection-management/configuration',
    roles: [
      'Toll Registrar',
      'Toll Collector',
      'Toll Supervisor',
      'Toll Reviewer',
      'Toll Auditor',
      'Toll Administrator',
      'Toll Accountant',
    ],
  },
  {
    id: 'sod-report-management',
    label: 'Manage Reports',
    icon: <BarChart3 size={20} />,
    path: '/collection-management/collection-reports',
    roles: ['*'],
  },
];

/** Visual divider to separate the new SoD navigation from the legacy menus. */
const SOD_LEGACY_DIVIDER = {
  id: 'sod-legacy-divider',
  type: 'divider',
  label: 'Legacy modules',
};

export const navigationItems = [
  {
    id: 'dashboard',
    label: 'Dashboard',
    icon: <LayoutDashboard size={20} />,
    path: null, // Path is set dynamically in MainSidebar based on selectedModule
    roles: ['Employee', 'Employee Approver', 'Employee Registrar', 'Overtime Applicant', 'Overtime Validator', 'Overtime Reviewer', 'Overtime Accountant', 'Toll Registrar', 'Toll Collector', 'Toll Supervisor', 'Toll Approver', 'Toll Reviewer', 'Toll Auditor', 'Toll Administrator', 'Toll Accountant', 'Payroll Initiator', 'Payroll Examiner', 'Payroll Verifier', 'Payroll Approver']
  },
  {
    id: 'manage-users',
    label: 'Manage Users',
    icon: <Users size={16} />,
    path: '/admin-management/manage-users',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  ...SOD_NAV_ITEMS,
  SOD_LEGACY_DIVIDER,
  {
    id: 'employee-profile',
    label: 'Employee Profile',
    icon: <UserCircle size={20} />,
    path: '/employee-management/profile',
    roles: ['Employee', 'Employee Approver', 'Employee Registrar']
  },
  {
    id: 'employee-payslips',
    label: 'Payslips',
    icon: <Banknote size={20} />,
    path: '/employee-management/payslips',
    roles: ['Employee', 'Employee Approver', 'Employee Registrar']
  },
  {
    id: 'employee-registration',
    label: 'Register Employee',
    icon: <Users size={20} />,
    path: '/employee-management/registration',
    roles: ['Employee Registrar']
  },
  {
    id: 'manage-employee',
    label: 'Manage Employee',
    icon: <UserCog size={20} />,
    path: '/employee-management/manage-employee',
    roles: ['Employee Approver']
  },
  {
    id: 'attendance-management',
    label: 'Employee Attendance',
    icon: <Calendar size={20} />,
    path: '/employee-management/attendance',
    roles: ['Employee', 'Employee Approver', 'Employee Registrar']
  },
  {
    id: 'attendance-reports',
    label: 'Reports',
    icon: <FileText size={20} />,
    path: '/employee-management/attendance-report',
    roles: ['Employee Approver', 'Employee Registrar']
  },
  {
    id: 'manage-shifts',
    label: 'Manage Shifts',
    icon: <Clock size={20} />,
    path: '/employee-management/bridge-shifts',
    roles: ['Employee Registrar','Employee Approver']
  },
  {
    id: 'overtime-management',
    label: 'Apply Overtime',
    icon: <Clock size={20} />,
    path: '/allowance-management/overtime-management',
    roles: ['Overtime Applicant', 'Overtime Validator', 'Overtime Reviewer', 'Overtime Accountant']
  },
  {
    id: 'manage-overtime',
    label: 'Manage Overtime',
    icon: <Clock size={20} />,
    path: '/allowance-management/manage-overtime',
    roles: ['Overtime Validator', 'Overtime Reviewer', 'Overtime Accountant']
  },
  {
    id: 'overtime-batch-management',
    label: 'Manage Batch',
    icon: <Layers size={20} />,
    path: '/allowance-management/overtime-batch-management',
    roles: ['Overtime Reviewer', 'Overtime Accountant']
  },
  {
    id: 'overtime-submission-documents',
    label: 'Upload Memo',
    icon: <FileText size={20} />,
    path: '/allowance-management/overtime-submission-documents',
    roles: ['Overtime Reviewer', 'Overtime Validator']
  },
  {
    id: 'manage-special-tasks',
    label: 'Manage Special Task',
    icon: <Briefcase size={20} />,
    path: '/allowance-management/special-tasks',
    roles: ['Overtime Validator', 'Overtime Reviewer']
  },
  {
    id: 'overtime-rates',
    label: 'Manage Rates',
    icon: <DollarSign size={20} />,
    path: '/allowance-management/overtime-rates',
    roles: ['Overtime Validator', 'Overtime Reviewer', 'Overtime Accountant']
  },
  {
    id: 'apply-special-tasks',
    label: 'Apply Special Task',
    icon: <Briefcase size={20} />,
    path: '/allowance-management/my-special-tasks',
    roles: ['Overtime Applicant', 'Overtime Validator']
  },
  {
    id: 'manage-holidays',
    label: 'Manage Holiday',
    icon: <Calendar size={20} />,
    path: '/allowance-management/public-holidays',
    roles: ['Overtime Validator','Overtime Reviewer']
  },
  {
    id: 'educational-level',
    label: 'Manage Education',
    icon: <GraduationCap size={20} />,
    path: '/allowance-management/educational-level',
    roles: []
  },

  {
    id: 'leave-management',
    label: 'Leave Management',
    icon: <Calendar size={20} />,
    path: '/leave-management',
    roles: ['Employee']
  },
  {
    id: 'payroll-runs',
    label: 'Payroll Processing',
    icon: <BarChart3 size={20} />,
    path: '/payroll-management/runs',
    roles: ['Payroll Initiator', 'Payroll Examiner','Payroll Verifier', 'Payroll Approver'],
  },
  {
    id: 'payroll-deductions',
    label: 'Manage Deductions',
    icon: <Landmark size={20} />,
    path: '/payroll-management/deductions',
    roles: ['Payroll Initiator', 'Payroll Examiner','Payroll Verifier', 'Payroll Approver'],
  },
  {
    id: 'payroll-benefits',
    label: 'Manage Benefits',
    icon: <CreditCard size={20} />,
    path: '/payroll-management/benefits',
    roles: ['Payroll Initiator', 'Payroll Examiner','Payroll Verifier', 'Payroll Approver'],
  },
  {
    id: 'payroll-loan-management',
    label: 'Manage Loan',
    icon: <Wallet size={20} />,
    path: '/payroll-management/loan-management',
    roles: ['Payroll Initiator', 'Payroll Examiner','Payroll Verifier', 'Payroll Approver'],
  },
  {
    id: 'payroll-arrears',
    label: 'Manage Arrears',
    icon: <DollarSign size={20} />,
    path: '/payroll-management/arrears',
    roles: ['Payroll Initiator', 'Payroll Examiner','Payroll Verifier', 'Payroll Approver'],
  },
  {
    id: 'payroll-payslips',
    label: 'Payslips',
    icon: <FileText size={20} />,
    path: '/payroll-management/payslips',
    // Employee self-service lives under employee-management; payroll staff keep this entry.
    roles: ['Payroll Initiator'],
  },
  {
    id: 'payroll-reports',
    label: 'Payroll Reports',
    icon: <BarChart3 size={20} />,
    path: '/payroll-management/payroll-reports',
    roles: ['Payroll Initiator', 'Payroll Examiner','Payroll Verifier', 'Payroll Approver'],
  },
  {
    id: 'notification-management',
    label: 'Notification Management',
    icon: <Bell size={20} />,
    path: '/notification-management',
    roles: ['Employee', 'Employee Approver']
  },
  {
    id: 'manage-roles',
    label: 'Manage Roles',
    icon: <Shield size={16} />,
    path: '/admin-management/roles',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  {
    id: 'department-management',
    label: 'Departments',
    icon: <Building2 size={16} />,
    path: '/admin-management/department-management',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  {
    id: 'permission-management',
    label: 'Permission',
    icon: <Shield size={16} />,
    path: '/admin-management/permission-management',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  {
    id: 'manage-modules',
    label: 'Manage Modules',
    icon: <AppWindow size={16} />,
    path: '/admin-management/manage-modules',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  {
    id: 'manage-region',
    label: 'Manage Region',
    icon: <Globe size={16} />,
    path: '/admin-management/region-district-management',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  {
    id: 'manage-employment-type',
    label: 'Manage Employment Type',
    icon: <Briefcase size={16} />,
    path: '/admin-management/employment-type-management',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  {
    id: 'manage-bank',
    label: 'Manage Bank',
    icon: <Landmark size={16} />,
    path: '/admin-management/bank-management',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  {
    id: 'manage-scheme',
    label: 'Manage Scheme',
    icon: <FileText size={16} />,
    path: '/admin-management/scheme-management',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  {
    id: 'administration-configuration',
    label: 'Configuration',
    icon: <Settings size={20} />,
    path: '/administration-management/configuration',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  {
    id: 'administration-report-engine',
    label: 'Report Engine',
    icon: <BarChart3 size={16} />,
    path: '/administration-management/report-engine',
    roles: ['Toll Administrator', 'Employee Approver']
  },
  {
    id: 'incident-management',
    label: 'Report Incident',
    icon: <AlertTriangle size={20} />,
    path: '/incident-management',
    roles: ['Toll Collector', 'Toll Supervisor', 'Toll Reviewer']
  }
];

/**
 * Helper function to normalize role name for comparison
 * Handles different formats like "Admin", "admin", "ADMIN", etc.
 */
const normalizeRoleForComparison = (roleName) => {
    if (!roleName) return '';
    return String(roleName).trim();
};

const isTollAdministrator = (roleName) =>
    normalizeRoleForComparison(roleName).toLowerCase() === 'toll administrator';

/**
 * Helper function to check if a role matches (case-insensitive)
 * If allowedRoles is empty or undefined, the item is NOT shown to any role.
 * This enforces that only items with explicit role mappings are visible.
 */
const roleMatches = (roleName, allowedRoles) => {
    // If allowedRoles is empty or undefined, do not show this item for any role
    if (!allowedRoles || allowedRoles.length === 0) {
        return false;
    }

    if (allowedRoles.some((allowedRole) => normalizeRoleForComparison(allowedRole) === '*')) {
        return true;
    }
    
    const normalizedRole = normalizeRoleForComparison(roleName);
    return allowedRoles.some(allowedRole => {
        const normalizedAllowed = normalizeRoleForComparison(allowedRole);
        // Case-insensitive comparison
        return normalizedRole.toLowerCase() === normalizedAllowed.toLowerCase();
    });
};

/**
 * Get navigation items filtered by user role
 * @param {string} roleName - The role name to filter navigation items
 * @returns {NavItem[]} Filtered array of navigation items accessible by the role
 */
export const getNavigationForRole = (roleName) => {
    // If no roleName, do not return any navigation items.
    // Navigation visibility is strictly controlled by explicit role mappings.
    if (!roleName) {
        return [];
    }

    // Toll Administrator has full visibility across all sidebar menus.
    if (isTollAdministrator(roleName)) {
        return navigationItems;
    }
    
    return navigationItems
        .map(item => {
            // Section dividers are visual markers — always pass through.
            if (item.type === 'divider') {
                return item;
            }

            // Check if the main item is accessible (case-insensitive)
            if (!roleMatches(roleName, item.roles)) {
                return null;
            }

            // If item has children, filter them based on role
            if (item.children) {
                const filteredChildren = item.children.filter(child =>
                    roleMatches(roleName, child.roles)
                );

                // Only return parent if it has accessible children
                if (filteredChildren.length > 0) {
                    return {
                        ...item,
                        children: filteredChildren
                    };
                }
                return null;
            }

            return item;
        })
        .filter(Boolean);
};

