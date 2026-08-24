import { Link } from 'react-router-dom';
import logo from '../../assets/images/logo.png';
import { Bars3Icon, BellIcon, UserIcon } from '@heroicons/react/24/outline/index.js';
import { BiSupport } from 'react-icons/bi';
import {
  setOpenSideBarDrawer,
  setOpenSupportDeskDrawer,
  setOpenUserActionCenterDrawer,
  setSelectedModule,
} from '../../store/reducers/app.js';
import { useDispatch, useSelector } from 'react-redux';
import SupportDeskDrawer from './SupportDeskDrawer.jsx';
import UserActionCenterDrawer from './UserActionCenterDrawer.jsx';
import ThemeToggle from './ThemeToggle.jsx';

const getModuleDisplayName = (moduleKey) => {
  const moduleNameMap = {
    'employee-management': 'Employee Management',
    'allowance-management': 'Allowance Management',
    'collection-management': 'Toll Management',
    'leave-management': 'Leave Management',
    'payroll-management': 'Payroll Management',
    'account-management': 'Account Management',
    'administration-management': 'Administration Management',
    'incident-management': 'Report Incident',
    'notification-management': 'Notification Management',
  };
  return moduleNameMap[moduleKey] || '';
};

const MainHeader = () => {
  const dispatch = useDispatch();
  const { openSupportDeskDrawer, openUserActionCenterDrawer, selectedModule } = useSelector(
    (state) => state.app
  );
  const { user } = useSelector((state) => state.auth);

  const getUserDisplayName = () => {
    if (!user) return 'User';

    const firstName = user.first_name || user.firstName || user.firstname || '';
    const lastName = user.surname || user.last_name || user.lastName || user.lastname || '';
    const fullName = [firstName, lastName].filter(Boolean).join(' ');

    return fullName || user.username || 'User';
  };

  const handleHomeClick = () => {
    dispatch(setSelectedModule(null));
  };

  return (
    <>
      <header className="sticky top-0 z-50 h-[100px] shrink-0 border-b border-gray-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:shadow-[0_2px_16px_rgba(0,0,0,0.35)]">
        <div className="flex h-[60px] items-center gap-x-4 bg-[#902d30] px-4 sm:gap-x-6 sm:px-6 lg:px-8">
          <div className="relative flex flex-1 items-center space-x-5 font-bold">
            <div className="left-[5px] flex w-full justify-start pl-1.5 md:px-0 lg:left-4 lg:px-4">
              <div className="mr-4 flex h-[60px] items-start">
                <Link to="/" onClick={handleHomeClick}>
                  <img
                    style={{ boxShadow: '0 1px 4px rgba(0,0,0,.3), inset 0 0 40px rgba(0,0,0,.1)' }}
                    src={logo}
                    className="mr-[10px] h-[100px] lg:mr-7 lg:flex"
                    alt="BMS Logo"
                  />
                </Link>
                <span className="flex h-[60px] max-w-[200px] items-center text-[14px] font-medium text-white lg:whitespace-nowrap lg:text-xl">
                  <h2>BRIDGE MANAGEMENT SYSTEM (BMS)</h2>
                </span>
              </div>
            </div>
          </div>
          <div className="flex items-center gap-x-2 lg:gap-x-4">
            <button type="button" className="-m-2.5 p-2.5 text-gray-400 hover:text-gray-500">
              <span className="sr-only">View notifications</span>
              <BellIcon className="h-6 w-6" aria-hidden="true" />
            </button>

            <ThemeToggle variant="burgundy" />

            <div className="relative">
              <button
                onClick={() => dispatch(setOpenUserActionCenterDrawer(true))}
                className="mr-[5px] flex items-center p-1.5 lg:-m-1.5"
              >
                <span className="sr-only">Open user menu</span>
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gray-50">
                  <UserIcon className="h-6 w-6 text-gray-600" aria-hidden="true" />
                </div>
                <span className="hidden lg:flex lg:items-center">
                  <span className="ml-4 text-sm font-semibold leading-6 text-white" aria-hidden="true">
                    {getUserDisplayName()}
                  </span>
                </span>
              </button>
            </div>
          </div>
        </div>

        <div className="flex h-[40px] items-center gap-x-4 bg-[#f9c000] px-4 sm:gap-x-6 sm:px-6 lg:px-8">
          <div className="ml-[88px] flex w-full items-center justify-between md:ml-[60px] lg:ml-[95px]">
            <div className="flex items-center space-x-2">
              <button
                type="button"
                className="z-50 text-gray-700 lg:hidden"
                onClick={() => dispatch(setOpenSideBarDrawer(true))}
              >
                <span className="sr-only">Open sidebar</span>
                <Bars3Icon className="h-6 w-6" aria-hidden="true" />
              </button>
              {selectedModule && (
                <div className="lg:whitespace-nowrap">
                  <span className="text-sm font-semibold text-gray-900 lg:text-base">
                    {getModuleDisplayName(selectedModule)}
                  </span>
                </div>
              )}
            </div>
            <button
              type="button"
              onClick={() => dispatch(setOpenSupportDeskDrawer(true))}
              className="inline-flex items-center gap-x-1.5 rounded-md px-2.5 py-1.5 text-sm font-semibold text-primary shadow-sm hover:bg-yellow-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow-600"
            >
              <BiSupport className="-ml-0.5 text-primary" size="20px" />
              <span className="hidden font-medium md:flex lg:flex">Support Desk</span>
            </button>
          </div>
        </div>
      </header>
      <SupportDeskDrawer
        open={openSupportDeskDrawer}
        onClose={() => dispatch(setOpenSupportDeskDrawer(false))}
      />
      <UserActionCenterDrawer
        open={openUserActionCenterDrawer}
        onClose={() => dispatch(setOpenUserActionCenterDrawer(false))}
      />
    </>
  );
};

export default MainHeader;
