import { useCallback, useRef } from 'react';
import { Modal, Button, App } from 'antd';
import { useLocation, useNavigate } from 'react-router-dom';
import { useDispatch } from 'react-redux';
import PropTypes from 'prop-types';
import { hasAuthSession } from '../../modules/auth/authSession.js';
import { performSessionLogout } from '../../modules/auth/sessionLogout.js';
import { useSessionTimeout } from '../hooks/useSessionTimeout.js';

const PUBLIC_PATHS = new Set(['/login', '/forgot-password']);

const SessionTimeoutProvider = ({ children }) => {
  const { message } = App.useApp();
  const location = useLocation();
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const loggingOutRef = useRef(false);

  const isPublicRoute = PUBLIC_PATHS.has(location.pathname);
  const sessionActive = hasAuthSession() && !isPublicRoute;

  const handleLogout = useCallback(
    async (reason = 'inactivity') => {
      if (loggingOutRef.current) return;
      loggingOutRef.current = true;

      await performSessionLogout({
        dispatch,
        navigate,
        message,
        reason,
      });

      loggingOutRef.current = false;
    },
    [dispatch, navigate, message]
  );

  const { showWarning, secondsLeft, stayLoggedIn } = useSessionTimeout(
    sessionActive,
    () => handleLogout('inactivity')
  );

  const formatCountdown = (totalSeconds) => {
    const m = Math.floor(totalSeconds / 60);
    const s = totalSeconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
  };

  return (
    <>
      {children}

      <Modal
        open={showWarning && sessionActive}
        centered
        closable={false}
        maskClosable={false}
        keyboard={false}
        footer={null}
        destroyOnHidden
        title={null}
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
          <h2 className="m-0 text-sm font-semibold leading-none text-white">Session Timeout</h2>
        </div>

        <div className="px-6 py-5">
          <p className="m-0 text-sm text-slate-700">
            Are you still there? Your session will end soon due to inactivity.
          </p>
          <p className="mt-3 mb-0 text-center text-2xl font-semibold tabular-nums text-[#962E32]">
            {formatCountdown(secondsLeft)}
          </p>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            onClick={stayLoggedIn}
            className="border-[#962E32] bg-[#962E32] hover:!border-[#7A2326] hover:!bg-[#7A2326]"
          >
            Yes, I&apos;m still here
          </Button>
          <Button onClick={() => handleLogout('manual')}>Sign out </Button>
        </div>
      </Modal>
    </>
  );
};

SessionTimeoutProvider.propTypes = {
  children: PropTypes.node.isRequired,
};

export default SessionTimeoutProvider;
