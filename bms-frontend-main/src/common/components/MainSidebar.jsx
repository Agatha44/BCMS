import {Dialog, DialogPanel, Transition, TransitionChild} from '@headlessui/react';
import {
    XMarkIcon,
    UserIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    MagnifyingGlassIcon
} from '@heroicons/react/24/outline';
import {useDispatch, useSelector} from 'react-redux';
import {Link, useLocation} from 'react-router-dom';
import {setOpenSideBarDrawer, setSelectedRole, setSideBarCollapsed} from '../../store/reducers/app.js';
import {getNavigationForRole} from './MainNavigation.jsx';
import {useState, useEffect, useRef} from 'react';
import React from 'react';
import {apiService} from '../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

// TEMPORARY: Hardcode roles in the role selector for easy switching while
// the new SoD-aligned sidebar is being implemented. Set USE_HARDCODED_ROLES
// to `false` to fall back to the existing API-driven role list.
const USE_HARDCODED_ROLES = false;
const HARDCODED_ROLES = [
    'Toll Registrar',
    'Toll Collector',
    'Toll Supervisor',
    'Toll Approver',
    'Toll Reviewer',
    'Toll Auditor',
    'Toll Administrator',
    'Toll Accountant',
    'Employee',
    'Employee Approver',
    'Employee Registrar',
    'Overtime Applicant',
    'Overtime Validator',
    'Overtime Reviewer',
    'Overtime Accountant',
    'Payroll Initiator',
    'Payroll Examiner',
    'Payroll Verifier',
    'Payroll Approver',
];

function classNames(...classes) {
    return classes.filter(Boolean).join(' ');
}

const RoleSelector = ({role, roles, isOpen, onToggle, onSelect, containerRef}) => {
    const hasMultiple = roles.length > 1;

    return (
        <div className="relative" ref={containerRef}>
            <button
                type="button"
                onClick={() => hasMultiple && onToggle()}
                className={`flex items-center gap-x-2 rounded-md border-2 border-[#962E32] bg-gray-50 px-3 py-2 w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-[#962E32]/20 ${hasMultiple ? 'cursor-pointer hover:bg-gray-100' : ''}`}
            >
                <UserIcon className="h-5 w-5 text-gray-600" />
                <span className="flex-1 text-sm font-medium text-gray-900 text-left">
                    {role || 'No Role'}
                </span>
                {hasMultiple && (
                    <ChevronDownIcon className={`h-4 w-4 text-gray-600 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
                )}
            </button>
            {hasMultiple && isOpen && (
                <div className="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-auto">
                    {roles.map((r, i) => (
                        <button
                            key={i}
                            type="button"
                            onClick={() => onSelect(r)}
                            className={`w-full text-left px-3 py-2 text-sm hover:bg-[#fff5f5] transition-colors ${
                                role === r ? 'bg-[#fff5f5] text-[#962E32] font-medium' : 'text-gray-700'
                            } focus:outline-none focus-visible:bg-[#fff5f5] focus-visible:text-[#962E32]`}
                        >
                            {r}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
};

const MainSidebar = () => {
    const dispatch = useDispatch();
    const location = useLocation();
    const openSideBarDrawer = useSelector((state) => state.app.openSideBarDrawer);
    const isSideBarCollapsed = useSelector((state) => state.app.isSideBarCollapsed);
    const selectedModule = useSelector((state) => state.app.selectedModule);
    const selectedRole = useSelector((state) => state.app.selectedRole);
    const currentUser = useSelector((state) => state.auth.user);
    const [userRoles, setUserRoles] = useState([]);
    const [isRoleDropdownOpen, setIsRoleDropdownOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const mobileRoleDropdownRef = useRef(null);
    const desktopRoleDropdownRef = useRef(null);
    const isFetchingRoles = useRef(false);
    const lastFetchKey = useRef('');

    // Fetch user roles filtered by selected module (same pattern as LandingPage)
    useEffect(() => {
        if (!selectedModule) {
            setUserRoles([]);
            return;
        }

        if (USE_HARDCODED_ROLES) {
            setUserRoles(HARDCODED_ROLES);
            const stored = sessionStorage.getItem('selectedRole');
            if (stored && HARDCODED_ROLES.includes(stored)) {
                if (selectedRole !== stored) {
                    dispatch(setSelectedRole(stored));
                }
            } else if (!selectedRole) {
                dispatch(setSelectedRole(HARDCODED_ROLES[0]));
            }
            return;
        }

        const fetchUserRolesByModule = async () => {
            if (!currentUser) {
                setUserRoles([]);
                return;
            }

            const fetchKey = `${currentUser?.id || currentUser?.user_id || 'unknown'}-${selectedModule || 'none'}`;

            if (isFetchingRoles.current || lastFetchKey.current === fetchKey) {
                return;
            }

            isFetchingRoles.current = true;
            lastFetchKey.current = fetchKey;

            try {
                const userRolesResponse = await apiService.getBridgeLoggedUserRole();

                if (!userRolesResponse.success || !userRolesResponse.data) {
                    setUserRoles([]);
                    isFetchingRoles.current = false;
                    lastFetchKey.current = '';
                    return;
                }

                let userRoles = [];
                if (userRolesResponse.data.roles && Array.isArray(userRolesResponse.data.roles)) {
                    userRoles = userRolesResponse.data.roles;
                } else if (userRolesResponse.data.employee_roles && Array.isArray(userRolesResponse.data.employee_roles)) {
                    userRoles = userRolesResponse.data.employee_roles;
                } else if (Array.isArray(userRolesResponse.data)) {
                    userRoles = userRolesResponse.data;
                } else if (userRolesResponse.data.role) {
                    userRoles = Array.isArray(userRolesResponse.data.role) ? userRolesResponse.data.role : [userRolesResponse.data.role];
                }

                const userRoleNames = userRoles.map(role => {
                    if (typeof role === 'string') {
                        return role;
                    } else if (role?.role_name) {
                        return role.role_name;
                    } else if (role?.name) {
                        return role.name;
                    } else if (role?.role) {
                        return typeof role.role === 'string' ? role.role : (role.role?.role_name || role.role?.name);
                    }
                    return null;
                }).filter(Boolean);

                const modulesResponse = await apiService.getActiveBmsModules();
                let moduleIdForApi = null;

                if (modulesResponse.success && modulesResponse.data) {
                    const modulesData = modulesResponse.data.modules || (Array.isArray(modulesResponse.data) ? modulesResponse.data : []);

                    const normalizeModuleId = (moduleId) => {
                        if (!moduleId) return '';
                        return String(moduleId).toLowerCase().trim().replace(/[_\s]+/g, '-');
                    };

                    const matchingModule = modulesData.find(module => {
                        const moduleIdentifier = module.module_identifier || module.module_id;
                        const normalizedIdentifier = normalizeModuleId(moduleIdentifier);
                        return normalizedIdentifier === selectedModule ||
                               normalizeModuleId(module.id) === selectedModule;
                    });

                    if (matchingModule) {
                        moduleIdForApi = matchingModule.module_identifier || matchingModule.module_id || matchingModule.id;
                    }
                }

                if (moduleIdForApi) {
                    const moduleRolesResponse = await apiService.getRolesByModule(moduleIdForApi);

                    if (moduleRolesResponse.success && moduleRolesResponse.data) {
                        let moduleRoles = [];
                        if (Array.isArray(moduleRolesResponse.data)) {
                            moduleRoles = moduleRolesResponse.data;
                        } else if (moduleRolesResponse.data.roles && Array.isArray(moduleRolesResponse.data.roles)) {
                            moduleRoles = moduleRolesResponse.data.roles;
                        } else if (moduleRolesResponse.data.data && Array.isArray(moduleRolesResponse.data.data)) {
                            moduleRoles = moduleRolesResponse.data.data;
                        } else if (moduleRolesResponse.data.items && Array.isArray(moduleRolesResponse.data.items)) {
                            moduleRoles = moduleRolesResponse.data.items;
                        }

                        const moduleRoleNames = moduleRoles.map(record => {
                            const role = record.role || record;
                            if (typeof role === 'string') {
                                return role;
                            } else if (role?.role_name) {
                                return role.role_name;
                            } else if (role?.name) {
                                return role.name;
                            } else if (record?.role_name) {
                                return record.role_name;
                            } else if (record?.name) {
                                return record.name;
                            }
                            return null;
                        }).filter(Boolean);

                        const filteredRoles = userRoleNames.filter(userRole =>
                            moduleRoleNames.some(moduleRole =>
                                moduleRole.toLowerCase() === userRole.toLowerCase()
                            )
                        );

                        setUserRoles(filteredRoles);

                        if (selectedRole && !filteredRoles.includes(selectedRole)) {
                            if (filteredRoles.length > 0) {
                                dispatch(setSelectedRole(filteredRoles[0]));
                            } else {
                                dispatch(setSelectedRole(null));
                            }
                        } else if (!selectedRole && filteredRoles.length > 0) {
                            const storedRole = sessionStorage.getItem('selectedRole');
                            if (storedRole && filteredRoles.includes(storedRole)) {
                                dispatch(setSelectedRole(storedRole));
                            } else {
                                dispatch(setSelectedRole(filteredRoles[0]));
                            }
                        }
                        isFetchingRoles.current = false;
                        return;
                    }
                }

                setUserRoles(userRoleNames);
                const storedRole = sessionStorage.getItem('selectedRole');
                if (storedRole && userRoleNames.includes(storedRole)) {
                    dispatch(setSelectedRole(storedRole));
                } else if (!selectedRole && userRoleNames.length > 0) {
                    dispatch(setSelectedRole(userRoleNames[0]));
                }
            } catch (error) {
                setUserRoles([]);
                lastFetchKey.current = '';
            } finally {
                isFetchingRoles.current = false;
            }
        };

        fetchUserRolesByModule();
    }, [currentUser, selectedModule, selectedRole, dispatch]);

    const effectiveRole = selectedModule
        ? (selectedRole || (userRoles.length > 0 ? userRoles[0] : null))
        : null;

    const allNavigation = getNavigationForRole(effectiveRole);

    const handleRoleSelect = (role) => {
        dispatch(setSelectedRole(role));
        setIsRoleDropdownOpen(false);
    };

    useEffect(() => {
        const handleClickOutside = (event) => {
            const isClickInsideMobile = mobileRoleDropdownRef.current && mobileRoleDropdownRef.current.contains(event.target);
            const isClickInsideDesktop = desktopRoleDropdownRef.current && desktopRoleDropdownRef.current.contains(event.target);

            if (!isClickInsideMobile && !isClickInsideDesktop) {
                setIsRoleDropdownOpen(false);
            }
        };

        if (isRoleDropdownOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [isRoleDropdownOpen]);

    useEffect(() => {
        if (!openSideBarDrawer) {
            setIsRoleDropdownOpen(false);
        }
    }, [openSideBarDrawer]);

    // Close mobile drawer when viewport crosses the desktop breakpoint
    useEffect(() => {
        const mq = window.matchMedia('(min-width: 1024px)');
        const handleChange = (event) => {
            if (event.matches) {
                dispatch(setOpenSideBarDrawer(false));
            }
        };
        if (mq.matches) {
            dispatch(setOpenSideBarDrawer(false));
        }
        mq.addEventListener('change', handleChange);
        return () => mq.removeEventListener('change', handleChange);
    }, [dispatch]);

    const cleanDividers = (items) => {
        if (!items || items.length === 0) return items;
        return items.filter((item, idx) => {
            if (item.type !== 'divider') return true;
            const prev = items[idx - 1];
            const next = items[idx + 1];
            const prevContent = prev && prev.type !== 'divider';
            const nextContent = next && next.type !== 'divider';
            return prevContent && nextContent;
        });
    };

    const rawNavigation = selectedModule ? (() => {
        const dashboardItem = allNavigation.find(item => item.id === 'dashboard');
        const filtered = dashboardItem ? [dashboardItem] : [];

        const modulePathPrefix = selectedModule === 'account-management'
            ? '/admin-management'
            : `/${selectedModule}`;

        const pathMatchesSelectedModule = (path) => {
            if (!path) return false;
            return path === modulePathPrefix || path.startsWith(`${modulePathPrefix}/`);
        };

        const moduleItems = allNavigation.filter(item => {
            if (item.id === 'dashboard') {
                return false;
            }


            if (pathMatchesSelectedModule(item.path)) {
                return true;
            }

            if (item.children) {
                return item.children.some(child =>
                    pathMatchesSelectedModule(child.path)
                );
            }

            return false;
        });

        filtered.push(...moduleItems);

        return filtered;
    })() : allNavigation;

    const navigation = cleanDividers(rawNavigation);

    const isActive = (path) => {
        if (!path) return false;
        if (path.includes('/dashboard')) {
            return location.pathname.endsWith('/dashboard');
        }
        return location.pathname === path;
    };

    const hasActiveChild = (item) => {
        if (!item.children) return false;
        return item.children.some(child => {
            if (child.path && location.pathname === child.path) return true;
            return hasActiveChild(child);
        });
    };

    const getInitialExpandedItems = () => {
        const expanded = {};
        navigation.forEach(item => {
            if (item.children && hasActiveChild(item)) {
                expanded[item.id] = true;
            }
        });
        return expanded;
    };

    const [expandedItems, setExpandedItems] = useState(getInitialExpandedItems());

    const toggleExpanded = (itemId) => {
        setExpandedItems(prev => {
            const isCurrentlyExpanded = prev[itemId];

            if (isCurrentlyExpanded) {
                return {
                    ...prev,
                    [itemId]: false
                };
            }

            const newState = {};
            navigation.forEach(item => {
                if (item.children) {
                    newState[item.id] = item.id === itemId;
                }
            });
            return newState;
        });
    };

    const getDashboardPath = () => {
        if (!selectedModule) return '/employee-management/dashboard';
        const routeModuleId = selectedModule === 'account-management' ? 'admin-management' : selectedModule;
        return `/${routeModuleId}/dashboard`;
    };

    const getItemPath = (item) => (item.id === 'dashboard' ? getDashboardPath() : item.path);

    const renderRailItem = (item) => {
        if (item.type === 'divider') {
            return (
                <div
                    key={item.id}
                    aria-hidden="true"
                    className="my-1 h-px w-6 rounded-full bg-slate-200"
                    title={item.label}
                />
            );
        }

        const hasChildren = item.children && item.children.length > 0;
        const itemPath = getItemPath(item);
        const active = isActive(itemPath) || hasActiveChild(item);
        const commonClass = classNames(
            'group relative flex h-10 w-10 items-center justify-center rounded-xl transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#962E32]/25',
            active
                ? 'text-white shadow-md'
                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900'
        );
        const commonStyle = active
            ? {background: `linear-gradient(135deg, ${BRAND}, ${BRAND_DARK})`}
            : undefined;

        const content = (
            <>
                <span className="flex h-5 w-5 items-center justify-center text-current [&_svg]:text-current [&_svg]:stroke-current">
                    {item.icon}
                </span>
                <span className="pointer-events-none absolute left-full top-1/2 z-50 ml-3 -translate-y-1/2 whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                    {item.label}
                </span>
            </>
        );

        if (hasChildren) {
            return (
                <button
                    key={item.id}
                    type="button"
                    onClick={() => {
                        toggleExpanded(item.id);
                        dispatch(setSideBarCollapsed(false));
                    }}
                    className={commonClass}
                    style={commonStyle}
                    aria-label={item.label}
                >
                    {content}
                </button>
            );
        }

        return (
            <Link
                key={item.id}
                to={itemPath || '#'}
                className={commonClass}
                style={commonStyle}
                aria-label={item.label}
            >
                {content}
            </Link>
        );
    };

    const renderNavItem = (item, depth = 0) => {
        if (item.type === 'divider') {
            return (
                <li key={item.id} className="px-1 pt-4 pb-1">
                    <div className="flex items-center gap-2">
                        <span className="h-px flex-1 bg-slate-200" />
                        <span className="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                            {item.label}
                        </span>
                        <span className="h-px flex-1 bg-slate-200" />
                    </div>
                </li>
            );
        }

        const hasChildren = item.children && item.children.length > 0;
        const isExpanded = searchQuery ? true : expandedItems[item.id];
        const itemPath = getItemPath(item);
        const active = isActive(itemPath);
        const childActive = hasActiveChild(item);
        const isChildItem = depth > 0;

        if (hasChildren) {
            return (
                <li key={item.id}>
                    <button
                        type="button"
                        onClick={() => toggleExpanded(item.id)}
                        className={classNames(
                            'group relative flex w-full items-center justify-between gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#962E32]/20',
                            childActive
                                ? 'bg-[#fff5f5] font-semibold text-[#962E32]'
                                : 'font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                        )}
                    >
                        {childActive && (
                            <span
                                className="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full"
                                style={{backgroundColor: BRAND}}
                            />
                        )}
                        <div className="flex min-w-0 items-center gap-x-3">
                            <span
                                className={classNames(
                                    'flex h-6 w-6 shrink-0 items-center justify-center text-current transition-colors [&_svg]:text-current [&_svg]:stroke-current',
                                    childActive ? 'text-[#962E32]' : 'text-slate-400 group-hover:text-slate-700'
                                )}
                            >
                                {item.icon}
                            </span>
                            <span className="truncate">{item.label}</span>
                        </div>
                        {isExpanded ? (
                            <ChevronDownIcon
                                className={classNames(
                                    'h-4 w-4 shrink-0 transition-transform',
                                    childActive ? 'text-[#962E32]' : 'text-slate-400 group-hover:text-slate-700'
                                )}
                            />
                        ) : (
                            <ChevronRightIcon
                                className={classNames(
                                    'h-4 w-4 shrink-0 transition-transform',
                                    childActive ? 'text-[#962E32]' : 'text-slate-400 group-hover:text-slate-700'
                                )}
                            />
                        )}
                    </button>
                    {isExpanded && (
                        <ul role="list" className="relative ml-[18px] mt-1 space-y-0.5 border-l border-slate-200 pl-3">
                            {item.children.map(child => (
                                <React.Fragment key={child.id}>
                                    {renderNavItem(child, depth + 1)}
                                </React.Fragment>
                            ))}
                        </ul>
                    )}
                </li>
            );
        }

        return (
            <li key={item.id} className="relative">
                <Link
                    to={itemPath || '#'}
                    className={classNames(
                        'group relative flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#962E32]/20',
                        active
                            ? 'bg-[#fff5f5] font-semibold text-[#962E32]'
                            : 'font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                    )}
                >
                    {active && (
                        <span
                            className={classNames(
                                'absolute top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full',
                                isChildItem ? '-left-[13px]' : 'left-0'
                            )}
                            style={{backgroundColor: BRAND}}
                        />
                    )}
                    <span
                        className={classNames(
                            'flex h-6 w-6 shrink-0 items-center justify-center text-current transition-colors [&_svg]:text-current [&_svg]:stroke-current',
                            active ? 'text-[#962E32]' : 'text-slate-400 group-hover:text-slate-700'
                        )}
                    >
                        {item.icon}
                    </span>
                    <span className="truncate">{item.label}</span>
                </Link>
            </li>
        );
    };

    const railItems = navigation;

    const renderToggleButton = (collapsed) => (
        <button
            type="button"
            onClick={() => {
                setIsRoleDropdownOpen(false);
                setSearchQuery('');
                dispatch(setSideBarCollapsed(!collapsed));
            }}
            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            className="absolute -right-3 top-1/2 z-30 hidden h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-md ring-2 ring-slate-100/50 transition-all hover:border-[#962E32]/40 hover:bg-[#fff5f5] hover:text-[#962E32] hover:shadow-lg focus:outline-none focus-visible:border-[#962E32]/40 focus-visible:ring-[#962E32]/20 lg:flex"
        >
            <ChevronRightIcon
                className={classNames(
                    'h-3.5 w-3.5 transition-transform duration-300',
                    collapsed ? '' : 'rotate-180'
                )}
            />
        </button>
    );

    const filterNavigation = (items, query) => {
        if (!query) return items;
        const q = query.toLowerCase();
        return items
            .map((item) => {
                // Hide dividers while filtering — labels would be misleading.
                if (item.type === 'divider') return null;

                const labelMatches = item.label?.toLowerCase().includes(q);
                const filteredChildren = item.children
                    ? filterNavigation(item.children, query)
                    : null;

                if (labelMatches) return item;
                if (filteredChildren && filteredChildren.length > 0) {
                    return {...item, children: filteredChildren};
                }
                return null;
            })
            .filter(Boolean);
    };

    const visibleNavigation = filterNavigation(navigation, searchQuery);

    const collapsedSidebar = (
        <div className="bms-sidebar-root flex h-full min-h-0">
            <aside className="relative flex h-full min-h-0 w-full shrink-0 flex-col items-center border-r border-slate-200 bg-white py-4">
                {renderToggleButton(true)}
                <div
                    className="mb-5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-[11px] font-black tracking-tight text-white shadow-sm"
                    style={{background: `linear-gradient(135deg, ${BRAND}, ${BRAND_DARK})`}}
                >
                    BMS
                </div>

                <div className="flex min-h-0 flex-1 flex-col items-center gap-2 overflow-y-auto pb-2">
                    {railItems.map((item) => renderRailItem(item))}
                </div>
            </aside>
        </div>
    );

    const expandedSidebar = (refForRoleSelector) => (
        <div className="bms-sidebar-root flex h-full min-h-0">
            <section className="relative flex h-full min-h-0 min-w-0 flex-1 flex-col overflow-hidden border-r border-slate-200 bg-white">
                {renderToggleButton(false)}
                <div className="shrink-0 px-4 pb-3 pt-4">
                    <RoleSelector
                        role={effectiveRole}
                        roles={userRoles}
                        isOpen={isRoleDropdownOpen}
                        onToggle={() => setIsRoleDropdownOpen(!isRoleDropdownOpen)}
                        onSelect={handleRoleSelect}
                        containerRef={refForRoleSelector}
                    />
                </div>

                <div className="shrink-0 px-4 pb-2">
                    <div className="group relative">
                        <MagnifyingGlassIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 transition-colors group-focus-within:text-[#962E32]" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search menu..."
                            className="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-8 text-sm text-slate-700 placeholder:text-slate-400 transition-all focus:border-[#962E32]/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#962E32]/15"
                        />
                        {searchQuery && (
                            <button
                                type="button"
                                onClick={() => setSearchQuery('')}
                                aria-label="Clear search"
                                className="absolute right-2 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-200 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#962E32]/20"
                            >
                                <XMarkIcon className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                </div>

                <nav className="mt-1 min-h-0 flex-1 overflow-y-auto px-4 pb-4 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-200 [&::-webkit-scrollbar]:w-1.5 hover:[&::-webkit-scrollbar-thumb]:bg-slate-300">
                    {visibleNavigation.length > 0 ? (
                        <ul role="list" className="space-y-0.5">
                            {visibleNavigation.map((item) => (
                                <React.Fragment key={item.id}>
                                    {renderNavItem(item)}
                                </React.Fragment>
                            ))}
                        </ul>
                    ) : (
                        <div className="mt-6 px-3 text-center">
                            <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                <MagnifyingGlassIcon className="h-5 w-5" />
                            </div>
                            <div className="mt-2 text-sm font-medium text-slate-700">No matches</div>
                            <div className="text-xs text-slate-400">Try a different keyword</div>
                        </div>
                    )}
                </nav>
            </section>
        </div>
    );

    const sidebarBody = (refForRoleSelector, collapsed = false) =>
        collapsed ? collapsedSidebar : expandedSidebar(refForRoleSelector);

    return (
        <>
            {/* Mobile Sidebar Drawer */}
            <Transition show={openSideBarDrawer}>
                <Dialog className="relative z-50 lg:hidden" onClose={() => dispatch(setOpenSideBarDrawer(false))}>
                    <TransitionChild
                        enter="transition-opacity ease-linear duration-300"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="transition-opacity ease-linear duration-300"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <div className="fixed inset-0 bg-slate-900/70 backdrop-blur-sm"/>
                    </TransitionChild>

                    <div className="fixed inset-0 flex">
                        <TransitionChild
                            enter="transition ease-in-out duration-300 transform"
                            enterFrom="-translate-x-full"
                            enterTo="translate-x-0"
                            leave="transition ease-in-out duration-300 transform"
                            leaveFrom="translate-x-0"
                            leaveTo="-translate-x-full"
                        >
                            <DialogPanel className="relative mr-16 flex w-full max-w-sm flex-1">
                                <TransitionChild
                                    enter="ease-in-out duration-300"
                                    enterFrom="opacity-0"
                                    enterTo="opacity-100"
                                    leave="ease-in-out duration-300"
                                    leaveFrom="opacity-100"
                                    leaveTo="opacity-0"
                                >
                                    <div className="absolute left-full top-0 flex w-16 justify-center pt-5">
                                        <button
                                            type="button"
                                            className="-m-2.5 rounded-full bg-white/10 p-2 backdrop-blur transition hover:bg-white/20"
                                            onClick={() => dispatch(setOpenSideBarDrawer(false))}
                                        >
                                            <span className="sr-only">Close sidebar</span>
                                            <XMarkIcon className="h-6 w-6 text-white" aria-hidden="true"/>
                                        </button>
                                    </div>
                                </TransitionChild>
                                <div className="flex grow flex-col overflow-hidden bg-transparent shadow-2xl">
                                    {sidebarBody(mobileRoleDropdownRef, false)}
                                </div>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </Dialog>
            </Transition>

            {/* Static sidebar for desktop */}
            <div className={classNames(
                'hidden lg:fixed lg:left-0 lg:top-[100px] lg:bottom-0 lg:z-40 lg:flex lg:min-h-0 lg:flex-col transition-all duration-200',
                isSideBarCollapsed ? 'lg:w-20' : 'lg:w-72'
            )}>
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                    {sidebarBody(desktopRoleDropdownRef, isSideBarCollapsed)}
                </div>
            </div>
        </>
    );
};

export default MainSidebar;
