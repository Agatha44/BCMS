import { Navigate, useParams, useRoutes } from 'react-router-dom';

const VehicleDetailsRouteRedirect = () => {
  const { id } = useParams();
  return <Navigate to={`/collection-management/vehicles?open=${id}`} replace />;
};
import Layout from "../common/layouts/MainLayout.jsx";
import SupportPage from "../modules/support/index.jsx";
import LandingPage from '../common/layouts/LandingPage.jsx';
import { LoginPage, ForgotPasswordPage, ChangePasswordPage } from '../modules/auth/index.jsx';
import Dashboard from '../pages/Dashboard/Dashboard.jsx';
import EmployeeManagement from '../pages/EmployeeManagement/Registration/EmployeeManagement.jsx';
import BridgeShiftManagement from '../pages/EmployeeManagement/Registration/BridgeShiftManagement.jsx';
import PendingApprovalsDashboard from '../pages/EmployeeManagement/PendingApprovals/PendingApprovalsDashboard.jsx';
import EmployeeProfile from '../pages/EmployeeManagement/Profile/EmployeeProfile.jsx';
import ManageRolesList from '../pages/AdminManagement/roles/ManageRolesList.jsx';
import DepartmentManagement from '../pages/AdminManagement/DepartmentManagement.jsx';
import PermissionManagement from '../pages/AdminManagement/PermissionManagement.jsx';
import ManageUsers from '../pages/AdminManagement/ManageUsers.jsx';
import ManageModules from '../pages/AdminManagement/ManageModules.jsx';
import BridgeModuleRole from '../pages/AdminManagement/BridgeModuleRole.jsx';
import RegionDistrictManagement from '../pages/AdminManagement/RegionDistrictManagement.jsx';
import BankManagement from '../pages/AdminManagement/BankManagement.jsx';
import EmploymentTypeManagement from '../pages/AdminManagement/EmploymentTypeManagement.jsx';
import SchemeManagement from '../pages/AdminManagement/SchemeManagement.jsx';
import ReportEngineManagement from '../pages/AdminManagement/ReportEngineManagement.jsx';
import ConfigurationPage from '../pages/AdministrationManagement/ConfigurationPage.jsx';
import StaffAttendance from '../pages/EmployeeManagement/Attendance/StaffAttendance.jsx';
import AttendanceReport from '../pages/EmployeeManagement/Attendance/AttendanceReport.jsx';
import OvertimeManagement from '../pages/AllowanceManagement/Overtime/OvertimeManagement.jsx';
import ManageOvertime from '../pages/AllowanceManagement/Overtime/ManageOvertime.jsx';
import EducationalLevel from '../pages/AllowanceManagement/Overtime/EducationalLevel.jsx';
import OvertimeRates from '../pages/AllowanceManagement/Overtime/OvertimeRates.jsx';
import OvertimeBatchManagement from '../pages/AllowanceManagement/Overtime/OvertimeBatchManagement.jsx';
import SubmissionApprovalDocumentsManagement from '../pages/AllowanceManagement/Overtime/SubmissionApprovalDocumentsManagement.jsx';
import SpecialTasksManagement from '../pages/AllowanceManagement/Overtime/SpecialTasksManagement.jsx';
import MySpecialTasks from '../pages/AllowanceManagement/Overtime/MySpecialTasks.jsx';
import PublicHolidaysManagement from '../pages/AdminManagement/PublicHolidaysManagement.jsx';
import NotificationManagement from '../pages/NotificationManagement/NotificationManagement.jsx';
import IncidentManagement from '../pages/IncidentManagement/IncidentManagement.jsx';
import ProtectedRoute from '../common/components/ProtectedRoute.jsx';
import CollectionDashboard from '../pages/CollectionManagement/CollectionDashboard.jsx';
import CollectionsManagementPage from '../pages/CollectionManagement/sod/CollectionsManagementPage.jsx';
import ManageAccounts from '../pages/CollectionManagement/ManageAccounts.jsx';
import VehiclesManagement from '../pages/CollectionManagement/VehiclesManagement.jsx';
import BridgeReports from '../pages/CollectionManagement/BridgeReports.jsx';
import ConfigurationManagement from '../pages/CollectionManagement/ConfigurationManagement.jsx';
import Payslips from '../pages/PayrollManagement/Payslips.jsx';
import PayrollProcessing from '../pages/PayrollManagement/PayrollProcessing.jsx';
import Deductions from '../pages/PayrollManagement/Deductions.jsx';
import Benefits from '../pages/PayrollManagement/Benefits.jsx';
import LoanManagement from '../pages/PayrollManagement/LoanManagement.jsx';
import Arrears from '../pages/PayrollManagement/Arrears.jsx';
import PayrollReports from '../pages/PayrollManagement/PayrollReports.jsx';
import AccountManagementPage from '../pages/CollectionManagement/sod/AccountManagementPage.jsx';
import VehicleManagementPage from '../pages/CollectionManagement/sod/VehicleManagementPage.jsx';
import TollManagementPage from '../pages/CollectionManagement/sod/TollManagementPage.jsx';
import ShiftRecordTransferPage from '../pages/CollectionManagement/sod/ShiftRecordTransferPage.jsx';
import BundleManagementPage from '../pages/CollectionManagement/sod/BundleManagementPage.jsx';
import PrepaymentManagementPage from '../pages/CollectionManagement/sod/PrepaymentManagementPage.jsx';
import PaymentManagementPage from '../pages/CollectionManagement/sod/PaymentManagementPage.jsx';
import ReportManagementPage from '../pages/CollectionManagement/sod/ReportManagementPage.jsx';
import SystemSettingsPage from '../pages/CollectionManagement/sod/SystemSettingsPage.jsx';
import PriceManagementPage from '../pages/CollectionManagement/sod/PriceManagementPage.jsx';
import ReceiptManagementPage from '../pages/CollectionManagement/sod/ReceiptManagementPage.jsx';
import PreReceiptManagementPage from '../pages/CollectionManagement/sod/PreReceiptManagementPage.jsx';
import ReconciliationManagementPage from '../pages/CollectionManagement/sod/ReconciliationManagementPage.jsx';

export default function AppRoutes() {
    return useRoutes([
      {
        path: '/login',
        element: <LoginPage />
      },
      {
        path: '/forgot-password',
        element: <ForgotPasswordPage />
      },
      {
        path: '/change-password',
        element: (
          <ProtectedRoute>
            <ChangePasswordPage />
          </ProtectedRoute>
        )
      },
      {
        path: '/',
        element: (
          <ProtectedRoute>
            <LandingPage />
          </ProtectedRoute>
        )
      },
      {
        path: '/employee-management/dashboard',
        element: (
          <ProtectedRoute>
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/allowance-management/dashboard',
        element: (
          <ProtectedRoute>
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/dashboard',
        element: (
          <ProtectedRoute>
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/administration-management/dashboard',
        element: (
          <ProtectedRoute>
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/dashboard',
        element: (
          <ProtectedRoute>
            <Layout>
              <CollectionDashboard />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/collections',
        element: (
          <ProtectedRoute>
            <Layout>
              <CollectionsManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/accounts',
        element: (
          <ProtectedRoute>
            <Layout>
              <ManageAccounts />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/vehicles',
        element: (
          <ProtectedRoute>
            <Layout>
              <VehiclesManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/vehicles/:id',
        element: (
          <ProtectedRoute>
            <VehicleDetailsRouteRedirect />
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/prices',
        element: (
          <ProtectedRoute>
            <Navigate to="/collection-management/price-management" replace />
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/reconciliation',
        element: (
          <ProtectedRoute>
            <Navigate to="/collection-management/reconciliation-management" replace />
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/reports',
        element: (
          <ProtectedRoute>
            <Layout>
              <BridgeReports />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/configuration',
        element: (
          <ProtectedRoute>
            <Layout>
              <ConfigurationManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/leave-management/dashboard',
        element: (
          <ProtectedRoute>
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/payroll-management/dashboard',
        element: (
          <ProtectedRoute>
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/payroll-management/payslips',
        element: (
          <ProtectedRoute>
            <Layout>
              <Payslips />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/payroll-management/runs',
        element: (
          <ProtectedRoute>
            <Layout>
              <PayrollProcessing />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/payroll-management/deductions',
        element: (
          <ProtectedRoute>
            <Layout>
              <Deductions />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/payroll-management/benefits',
        element: (
          <ProtectedRoute>
            <Layout>
              <Benefits />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/payroll-management/loan-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <LoanManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/payroll-management/arrears',
        element: (
          <ProtectedRoute>
            <Layout>
              <Arrears />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/payroll-management/payroll-reports',
        element: (
          <ProtectedRoute>
            <Layout>
              <PayrollReports />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/incident-management/dashboard',
        element: (
          <ProtectedRoute>
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/support',
        element: (
          <ProtectedRoute>
            <Layout>
              <SupportPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/employee-management/registration',
        element: (
          <ProtectedRoute>
            <Layout>
              <EmployeeManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/employee-management/manage-employee',
        element: (
          <ProtectedRoute>
            <Layout>
              <EmployeeManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/employee-management/profile',
        element: (
          <ProtectedRoute>
            <Layout>
              <EmployeeProfile />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/employee-management/payslips',
        element: (
          <ProtectedRoute>
            <Layout>
              <Payslips selfOnly />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/employee-management/pending-approvals',
        element: (
          <ProtectedRoute>
            <Layout>
              <PendingApprovalsDashboard />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/employee-management/bridge-shifts',
        element: (
          <ProtectedRoute>
            <Layout>
              <BridgeShiftManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/manage-users',
        element: (
          <ProtectedRoute>
            <Layout>
              <ManageUsers />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/roles',
        element: (
          <ProtectedRoute>
            <Layout>
              <ManageRolesList />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/roles/new',
        element: <Navigate to="/admin-management/roles" replace />
      },
      {
        path: '/admin-management/roles/:id',
        element: <Navigate to="/admin-management/roles" replace />
      },
      {
        path: '/admin-management/role-management',
        element: <Navigate to="/admin-management/roles" replace />
      },
      {
        path: '/admin-management/department-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <DepartmentManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/permission-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <PermissionManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/grant-role',
        element: <Navigate to="/admin-management/manage-users?tab=bms-users" replace />
      },
      {
        path: '/admin-management/manage-modules',
        element: (
          <ProtectedRoute>
            <Layout>
              <ManageModules />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/register-module',
        element: <Navigate to="/admin-management/manage-modules?tab=register-module" replace />
      },
      {
        path: '/admin-management/module-menu',
        element: <Navigate to="/admin-management/manage-modules?tab=module-menu" replace />
      },
      {
        path: '/admin-management/role-module-menu',
        element: <Navigate to="/admin-management/manage-modules?tab=role-module-menu" replace />
      },
      {
        path: '/admin-management/bridge-module-role',
        element: (
          <ProtectedRoute>
            <Layout>
              <BridgeModuleRole />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/region-district-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <RegionDistrictManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/bank-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <BankManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/employment-type-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <EmploymentTypeManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/scheme-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <SchemeManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/admin-management/report-engine',
        element: <Navigate to="/administration-management/report-engine" replace />
      },
      {
        path: '/administration-management/configuration',
        element: (
          <ProtectedRoute>
            <Layout>
              <ConfigurationPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/administration-management/report-engine',
        element: (
          <ProtectedRoute>
            <Layout>
              <ReportEngineManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/notification-management/dashboard',
        element: (
          <ProtectedRoute>
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/notification-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <NotificationManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/employee-management/attendance',
        element: (
          <ProtectedRoute>
            <Layout>
              <StaffAttendance />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/employee-management/attendance-report',
        element: (
          <ProtectedRoute>
            <Layout>
              <AttendanceReport />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/allowance-management/overtime-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <OvertimeManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/allowance-management/manage-overtime',
        element: (
          <ProtectedRoute>
            <Layout>
              <ManageOvertime />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/allowance-management/educational-level',
        element: (
          <ProtectedRoute>
            <Layout>
              <EducationalLevel />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/allowance-management/overtime-rates',
        element: (
          <ProtectedRoute>
            <Layout>
              <OvertimeRates />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/allowance-management/overtime-batch-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <OvertimeBatchManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/allowance-management/overtime-submission-documents',
        element: (
          <ProtectedRoute>
            <Layout>
              <SubmissionApprovalDocumentsManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/allowance-management/special-tasks',
        element: (
          <ProtectedRoute>
            <Layout>
              <SpecialTasksManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/allowance-management/my-special-tasks',
        element: (
          <ProtectedRoute>
            <Layout>
              <MySpecialTasks />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/allowance-management/public-holidays',
        element: (
          <ProtectedRoute>
            <Layout>
              <PublicHolidaysManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/incident-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <IncidentManagement />
            </Layout>
          </ProtectedRoute>
        )
      },
      // --- Collection management (SoD-style tabbed modules; paths avoid legacy collisions) ---
      {
        path: '/collection-management/vehicle-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <VehicleManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/account-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <AccountManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/price-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <PriceManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/toll-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <TollManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/receipt-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <ReceiptManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/pre-receipt-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <PreReceiptManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/reconciliation-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <ReconciliationManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/shift-record-transfer',
        element: (
          <ProtectedRoute>
            <Layout>
              <ShiftRecordTransferPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/bundle-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <BundleManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/prepayment-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <PrepaymentManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/payment-management',
        element: (
          <ProtectedRoute>
            <Layout>
              <PaymentManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/collection-reports',
        element: (
          <ProtectedRoute>
            <Layout>
              <ReportManagementPage />
            </Layout>
          </ProtectedRoute>
        )
      },
      {
        path: '/collection-management/system-settings',
        element: (
          <ProtectedRoute>
            <Layout>
              <SystemSettingsPage />
            </Layout>
          </ProtectedRoute>
        )
      }
    ]); // Register routes here
}
