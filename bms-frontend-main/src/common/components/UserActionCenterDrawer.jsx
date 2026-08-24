import { Drawer, Space, App } from 'antd';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDispatch } from 'react-redux';
import { CheckCircleIcon } from '@heroicons/react/20/solid/index.js';
import { resetAuthState } from '../../store/reducers/auth';
import { resetAppState } from '../../store/reducers/app';
import { apiService } from '../../services/api.jsx';
import { clearAuthSession } from '../../modules/auth/authSession.js';
// import imprestImage from '../../assets/images/imprest.png';
// import paymentImage from '../../assets/images/payment.png';
import PropTypes from 'prop-types';

export default function UserActionCenterDrawer({ placement, onClose, open }) {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);

  const plans = [
    { name: 'Hobby', ram: '8GB', cpus: '4 CPUs', disk: '160 GB SSD disk', price: '$40' },
    { name: 'Startup', ram: '12GB', cpus: '6 CPUs', disk: '256 GB SSD disk', price: '$80' },
    { name: 'Business', ram: '16GB', cpus: '8 CPUs', disk: '512 GB SSD disk', price: '$160' },
    { name: 'Enterprise', ram: '32GB', cpus: '12 CPUs', disk: '1024 GB SSD disk', price: '$240' }
  ];

  function classNames(...classes) {
    return classes.filter(Boolean).join(' ');
  }

  const [selected, setSelected] = useState(plans[0]);

  const getSelectedModule = (selectedPlan) => {
    console.log(selectedPlan);
    setSelected(selectedPlan);
  };

  const handleLogout = async () => {
    setLoading(true);
    try {
      // Call logout API
      await apiService.logout();

      clearAuthSession();
      dispatch(resetAuthState());
      dispatch(resetAppState());
      sessionStorage.removeItem('selectedModule');
      localStorage.removeItem('selectedModule');
      
      // Close drawer
      onClose();
      
      // Show success message
      message.success('Logged out successfully');
      
      // Redirect to login page
      navigate('/login', { replace: true });
    } catch (error) {
      console.error('Logout error:', error);
      
      // Even if API call fails, clear all local state and storage
      clearAuthSession();
      dispatch(resetAuthState());
      dispatch(resetAppState());
      sessionStorage.removeItem('selectedModule');
      localStorage.removeItem('selectedModule');
      onClose();
      navigate('/login', { replace: true });

      message.warning('Logged out locally. Please login again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Drawer
        placement={placement}
        width={350}
        onClose={onClose}
        open={open}
        extra={
          <Space>
            <button
              onClick={handleLogout}
              type="button"
              disabled={loading}
              className="inline-flex items-center gap-x-1.5 rounded-md bg-red-900 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-800 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <CheckCircleIcon className="-ml-0.5 h-5 w-5" aria-hidden="true" />
              {loading ? 'Logging out...' : 'LogOut'}
            </button>
          </Space>
        }
      >
        <h1 className="font-semibold pb-4">Switch Module</h1>
        {/*<RadioGroup value={selected} onChange={(value) => getSelectedModule(value)}>*/}
        {/*  <RadioGroup.Label className="sr-only">Server size</RadioGroup.Label>*/}
        {/*  <div className="space-y-4">*/}
        {/*    <RadioGroup.Option*/}
        {/*      key="imprest"*/}
        {/*      value="imprest"*/}
        {/*      className={({ active }) =>*/}
        {/*        classNames(*/}
        {/*          active ? 'border-gray-300 ring-2 ring-gray-300' : 'border-gray-300',*/}
        {/*          'relative block cursor-pointer rounded-lg border bg-white px-4 py-2 shadow-sm focus:outline-none sm:flex sm:justify-between'*/}
        {/*        )*/}
        {/*      }*/}
        {/*    >*/}
        {/*      {({ active, checked }) => (*/}
        {/*        <>*/}
        {/*          <div className="flex items-center">*/}
        {/*            <RadioGroup.Description as="span" className="mt-2 flex-shrink-0">*/}
        {/*              <img src={imprestImage} alt="Description of the image" className="w-8 h-8 mr-2" />*/}
        {/*            </RadioGroup.Description>*/}
        {/*            <RadioGroup.Label as="span" className="font-medium text-gray-900 ml-4">*/}
        {/*              Imprest Module*/}
        {/*            </RadioGroup.Label>*/}
        {/*          </div>*/}
        {/*          <span*/}
        {/*            className={classNames(*/}
        {/*              active ? 'border' : 'border-2',*/}
        {/*              checked ? 'border-gray-300' : 'border-transparent',*/}
        {/*              'pointer-events-none absolute -inset-px rounded-lg'*/}
        {/*            )}*/}
        {/*            aria-hidden="true"*/}
        {/*          />*/}
        {/*        </>*/}
        {/*      )}*/}
        {/*    </RadioGroup.Option>*/}
        {/*    <RadioGroup.Option*/}
        {/*      key="payment"*/}
        {/*      value="payment"*/}
        {/*      className={({ active }) =>*/}
        {/*        classNames(*/}
        {/*          active ? 'border-gray-300 ring-2 ring-gray-300' : 'border-gray-300',*/}
        {/*          'relative block cursor-pointer rounded-lg border bg-white px-4 py-2 shadow-sm focus:outline-none sm:flex sm:justify-between'*/}
        {/*        )*/}
        {/*      }*/}
        {/*    >*/}
        {/*      {({ active, checked }) => (*/}
        {/*        <>*/}
        {/*          <div className="flex items-center">*/}
        {/*            <RadioGroup.Description as="span" className="mt-2 flex-shrink-0">*/}
        {/*              <img src={paymentImage} alt="Description of the image" className="w-8 h-8 mr-2" />*/}
        {/*            </RadioGroup.Description>*/}
        {/*            <RadioGroup.Label as="span" className="font-medium text-gray-900 ml-4">*/}
        {/*              Payment Module*/}
        {/*            </RadioGroup.Label>*/}
        {/*          </div>*/}
        {/*          <span*/}
        {/*            className={classNames(*/}
        {/*              active ? 'border' : 'border-2',*/}
        {/*              checked ? 'border-gray-300' : 'border-transparent',*/}
        {/*              'pointer-events-none absolute -inset-px rounded-lg'*/}
        {/*            )}*/}
        {/*            aria-hidden="true"*/}
        {/*          />*/}
        {/*        </>*/}
        {/*      )}*/}
        {/*    </RadioGroup.Option>*/}
        {/*  </div>*/}
        {/*</RadioGroup>*/}
      </Drawer>
    </>
  );
}

UserActionCenterDrawer.propTypes = {
  placement: PropTypes.any,
  onClose: PropTypes.func,
  open: PropTypes.bool
};
