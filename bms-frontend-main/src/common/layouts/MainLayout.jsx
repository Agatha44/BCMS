import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import Breadcrumb from '../components/BreadCrumb.jsx';
import MainHeader from '../components/MainHeader.jsx';
import MainSidebar from '../components/MainSidebar.jsx';
import PropTypes from 'prop-types';
import { setSelectedModule } from '../../store/reducers/app.js';

// Map routes to modules
const getModuleFromRoute = (pathname) => {
  // Route to module mapping
  const routeModuleMap = {
    '/employee-management/dashboard': 'employee-management',
    '/employee-management/registration': 'employee-management',
    '/employee-management/manage-employee': 'employee-management',
    '/employee-management/profile': 'employee-management',
    '/employee-management/attendance': 'employee-management',
    '/employee-management/attendance-report': 'employee-management',
    '/allowance-management/dashboard': 'allowance-management',
    '/allowance-management/overtime-management': 'allowance-management',
    '/allowance-management/manage-overtime': 'allowance-management',
    '/allowance-management/educational-level': 'allowance-management',
    '/allowance-management/overtime-rates': 'allowance-management',
    '/allowance-management/overtime-batch-management': 'allowance-management',
    '/allowance-management/public-holidays': 'allowance-management',
    '/admin-management/dashboard': 'account-management',
    '/admin-management/manage-users': 'account-management',
    '/admin-management/roles': 'account-management',
    '/admin-management/role-management': 'account-management',
    '/admin-management/permission-management': 'account-management',
    '/admin-management/grant-role': 'account-management',
    '/admin-management/manage-modules': 'account-management',
    '/admin-management/region-district-management': 'account-management',
    '/admin-management/bank-management': 'account-management',
    '/admin-management/employment-type-management': 'account-management',
    '/admin-management/scheme-management': 'account-management',
    '/administration-management/dashboard': 'administration-management',
    '/administration-management/configuration': 'administration-management',
    '/administration-management/report-engine': 'administration-management',
    '/administration-management': 'administration-management',
    '/notification-management/dashboard': 'notification-management',
    '/notification-management': 'notification-management',
    '/collection-management/dashboard': 'collection-management',
    '/collection-management/collections': 'collection-management',
    '/collection-management': 'collection-management',
    '/leave-management/dashboard': 'leave-management',
    '/leave-management': 'leave-management',
    '/payroll-management/dashboard': 'payroll-management',
    '/payroll-management': 'payroll-management',
    '/incident-management/dashboard': 'incident-management',
    '/incident-management': 'incident-management',
    '/support': 'support',
  };

  // Check exact match first
  if (routeModuleMap[pathname]) {
    return routeModuleMap[pathname];
  }

  // Check if pathname starts with any route key
  for (const [route, module] of Object.entries(routeModuleMap)) {
    if (pathname.startsWith(route)) {
      return module;
    }
  }

  return null;
};

const Layout = ({ children }) => {
    const location = useLocation();
    const dispatch = useDispatch();
    const isSideBarCollapsed = useSelector((state) => state.app.isSideBarCollapsed);

    useEffect(() => {
        // If user navigates to landing page (including browser back/forward), clear module selection
        if (location.pathname === '/') {
            dispatch(setSelectedModule(null));
            return;
        }

        // Sync selectedModule with current route
        const module = getModuleFromRoute(location.pathname);
        const currentModule = sessionStorage.getItem('selectedModule');
        
        if (module !== null) {
            // If route maps to a module, set it (only if different)
            if (currentModule !== module) {
                dispatch(setSelectedModule(module));
            }
        } else if (location.pathname.endsWith('/dashboard') && currentModule) {
            // If on any module dashboard and we have a stored module, restore it
            // This ensures the sidebar shows filtered menus after refresh
            dispatch(setSelectedModule(currentModule));
        }
    }, [location.pathname, dispatch]);

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-[var(--bms-bg-page)]">
            <MainHeader/>
            <MainSidebar />
            <div className="min-h-[calc(100vh-100px)] w-full">
                <div className={isSideBarCollapsed ? 'lg:pl-20' : 'lg:pl-72'}>
                    <main className="p-4 sm:p-6 lg:p-8">
                        <Breadcrumb/>
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
};

Layout.propTypes = {
    children: PropTypes.node,
};

export default Layout;
