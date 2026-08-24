import { HomeIcon } from '@heroicons/react/20/solid';
import { useLocation, Link } from 'react-router-dom';
import { navigationItems } from './MainNavigation.jsx';
import { useDispatch } from 'react-redux';
import { setSelectedModule } from '../../store/reducers/app.js';

// Map module IDs to display names
const getModuleDisplayName = (moduleId) => {
    const moduleNames = {
        'employee-management': 'Employee Management',
        'allowance-management': 'Allowance Management',
        'account-management': 'Admin Management',
        'admin-management': 'Admin Management',
        'administration-management': 'Administration Management',
        'collection-management': 'Toll Management',
        'leave-management': 'Leave Management',
        'payroll-management': 'Payroll Management',
        'incident-management': 'Report Incident',
        'notification-management': 'Notification Management',
        'support': 'Support',
    };
    return moduleNames[moduleId] || moduleId;
};

// Extract module name from route path
const getModuleFromPath = (pathname) => {
    const routeModuleMap = {
        '/employee-management': 'employee-management',
        '/allowance-management': 'allowance-management',
        '/admin-management': 'account-management',
        '/administration-management': 'administration-management',
        '/collection-management': 'collection-management',
        '/leave-management': 'leave-management',
        '/payroll-management': 'payroll-management',
        '/incident-management': 'incident-management',
        '/notification-management': 'notification-management',
        '/support': 'support',
    };

    for (const [route, module] of Object.entries(routeModuleMap)) {
        if (pathname.startsWith(route)) {
            return module;
        }
    }
    return null;
};

// Function to find navigation item by path and return it with its parent
const findNavItemByPath = (path, items = navigationItems, parent = null) => {
    for (const item of items) {
        if (item.path === path) {
            return { item, parent };
        }
        if (item.children) {
            const found = findNavItemByPath(path, item.children, item);
            if (found) {
                return found;
            }
        }
    }
    return null;
};

// Function to format label - returns label as is (no module prefix)
const formatLabel = (label) => {
    return label;
};

// Function to generate breadcrumb items from pathname
const generateBreadcrumbs = (pathname) => {
    const breadcrumbs = [{ name: 'Home', path: '/' }];
    
    // If we're on the home page, return just home
    if (pathname === '/') {
        return breadcrumbs;
    }
    
    // Get module from path
    const moduleId = getModuleFromPath(pathname);
    const moduleName = moduleId ? getModuleDisplayName(moduleId) : null;
    
    // Always add module name as a breadcrumb item if module exists
    if (moduleId && moduleName) {
        // Get the module dashboard path
        const moduleRoute = pathname.split('/').slice(0, 2).join('/');
        const moduleDashboardPath = `${moduleRoute}/dashboard`;
        breadcrumbs.push({
            name: moduleName,
            path: moduleDashboardPath,
            isLast: false,
        });
    }
    
    // Handle overtime detail page specifically
    if ((pathname.startsWith('/allowance-management/overtime-management/') && pathname !== '/allowance-management/overtime-management')) {
        breadcrumbs.push({
            name: formatLabel('Apply Overtime'),
            path: '/allowance-management/overtime-management',
            isLast: false,
        });
        breadcrumbs.push({
            name: formatLabel('Overtime Details'),
            path: pathname,
            isLast: true,
        });
        return breadcrumbs;
    }
    
    // Find the navigation item for the current path
    const navResult = findNavItemByPath(pathname);
    
    // Check if we're already on a module dashboard path (to avoid duplicates)
    const moduleDashboardPath = moduleId ? `/${pathname.split('/')[1]}/dashboard` : null;
    const isOnModuleDashboard = pathname === moduleDashboardPath;
    
    if (navResult && navResult.item) {
        // Add the current item without module prefix
        // Skip if this is a dashboard path and we already added the module breadcrumb
        if (!isOnModuleDashboard || navResult.item.path !== pathname) {
            breadcrumbs.push({
                name: formatLabel(navResult.item.label),
                path: navResult.item.path || pathname,
                isLast: true,
            });
        } else {
            // If we're on dashboard, mark the module breadcrumb as last
            if (breadcrumbs.length > 1) {
                breadcrumbs[breadcrumbs.length - 1].isLast = true;
            }
        }
    } else {
        // Fallback: if not found in navigation, use path segments
        const pathSegments = pathname.split('/').filter(Boolean);
        
        // Skip the first segment (module name) as we already added it
        // Only process the remaining segments
        if (pathSegments.length > 1) {
            let currentPath = '';
            pathSegments.forEach((segment, index) => {
                currentPath += `/${segment}`;
                
                // Skip the first segment (module name) - we already added it
                if (index === 0) {
                    return;
                }
                
                // Skip adding dashboard segment if we're already on the module dashboard
                // and this would create a duplicate path
                if (isOnModuleDashboard && currentPath === moduleDashboardPath) {
                    // Mark the module breadcrumb as last instead
                    if (breadcrumbs.length > 1) {
                        breadcrumbs[breadcrumbs.length - 1].isLast = true;
                    }
                    return;
                }
                
                const label = segment
                    .split('-')
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                    .join(' ');
                
                const isLast = index === pathSegments.length - 1;
                
                breadcrumbs.push({
                    name: formatLabel(label),
                    path: currentPath,
                    isLast: isLast,
                });
            });
        }
    }
    
    return breadcrumbs;
};

export default function BreadCrumb() {
    const location = useLocation();
    const dispatch = useDispatch();
    const breadcrumbs = generateBreadcrumbs(location.pathname);

    const handleHomeClick = () => {
        // Clear selected module when navigating back to home/landing page
        // This also clears it from localStorage via the reducer
        dispatch(setSelectedModule(null));
    };
    
    return (
        <nav className="mb-4 flex sm:mb-6 lg:mb-8" aria-label="BreadCrumb">
            <ol role="list" className="bms-breadcrumb flex space-x-4 rounded-md bg-white px-6 shadow">
                <li className="flex">
                    <div className="flex items-center">
                        <Link
                            to="/"
                            onClick={handleHomeClick}
                            className="bms-breadcrumb-home text-gray-400 hover:text-gray-500"
                        >
                            <HomeIcon className="h-5 w-5 flex-shrink-0" aria-hidden="true"/>
                            <span className="sr-only">Home</span>
                        </Link>
                    </div>
                </li>
                {breadcrumbs.slice(1).map((breadcrumb) => (
                    <li key={breadcrumb.path} className="flex">
                        <div className="flex items-center">
                            <svg
                                className="bms-breadcrumb-separator h-full w-6 flex-shrink-0 text-gray-200"
                                viewBox="0 0 24 44"
                                preserveAspectRatio="none"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path d="M.293 0l22 22-22 22h1.414l22-22-22-22H.293z"/>
                            </svg>
                            {breadcrumb.isLast ? (
                                <span
                                    className="bms-breadcrumb-current ml-4 text-sm font-medium text-gray-900"
                                    aria-current="page"
                                >
                                    {breadcrumb.name}
                                </span>
                            ) : (
                                <Link
                                    to={breadcrumb.path}
                                    className="bms-breadcrumb-link ml-4 text-sm font-medium text-gray-500 hover:text-gray-700"
                                >
                                    {breadcrumb.name}
                                </Link>
                            )}
                        </div>
                    </li>
                ))}
            </ol>
        </nav>
    );
}
