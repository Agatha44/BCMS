import { useEffect, useMemo, useState } from 'react';
import { BellIcon } from '@heroicons/react/24/outline/index.js';
import { useSelector } from 'react-redux';
import { getStoredUser } from '../../modules/auth/authSession.js';
import {
  listNotificationsForUser,
  markAccountRequestNotificationRead,
  markAllAccountRequestNotificationsRead,
  subscribeAccountRequestNotifications,
  unreadNotificationCountForUser,
} from '../../pages/AdminManagement/accountRequestNotifications.js';

const formatWhen = (value) => {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const AccountRequestNotificationBell = () => {
  const reduxUser = useSelector((state) => state.auth.user);
  const user = reduxUser || getStoredUser();
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState(() => listNotificationsForUser(user));

  useEffect(() => {
    setItems(listNotificationsForUser(user));
    return subscribeAccountRequestNotifications(() => {
      setItems(listNotificationsForUser(user));
    });
  }, [user]);

  const unreadCount = useMemo(() => unreadNotificationCountForUser(user), [items, user]);

  return (
    <div className="relative">
      <button
        type="button"
        className="relative -m-2.5 p-2.5 text-white/80 hover:text-white"
        onClick={() => setOpen((value) => !value)}
      >
        <span className="sr-only">View notifications</span>
        <BellIcon className="h-6 w-6" aria-hidden="true" />
        {unreadCount > 0 ? (
          <span className="absolute right-1 top-1 inline-flex min-w-[16px] items-center justify-center rounded-full bg-[#f9c000] px-1 text-[10px] font-bold leading-4 text-[#902d30]">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        ) : null}
      </button>
      {open ? (
        <div className="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
          <div className="flex items-center justify-between border-b border-slate-100 px-3 py-2">
            <span className="text-sm font-semibold text-slate-900">Notifications</span>
            {unreadCount > 0 ? (
              <button
                type="button"
                className="text-xs font-medium text-[#902d30] hover:underline"
                onClick={() => {
                  markAllAccountRequestNotificationsRead(user);
                  setItems(listNotificationsForUser(user));
                }}
              >
                Mark all read
              </button>
            ) : null}
          </div>
          <div className="max-h-80 overflow-y-auto">
            {items.length === 0 ? (
              <p className="px-3 py-6 text-center text-sm text-slate-500">No notifications</p>
            ) : (
              items.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  className={`block w-full border-b border-slate-100 px-3 py-2.5 text-left last:border-b-0 ${
                    item.read ? 'bg-white' : 'bg-[#fff8f8]'
                  }`}
                  onClick={() => {
                    markAccountRequestNotificationRead(item.id);
                    setItems(listNotificationsForUser(user));
                  }}
                >
                  <div className="flex items-start justify-between gap-2">
                    <span className="text-sm font-semibold text-slate-900">{item.title}</span>
                    <span className="shrink-0 text-[11px] text-slate-400">{formatWhen(item.created_at)}</span>
                  </div>
                  <p className="mt-1 text-xs leading-5 text-slate-600">{item.message}</p>
                  {item.comment ? (
                    <p className="mt-1 text-xs italic text-slate-500">
                      {item.commentLabel || 'Comment'}: {item.comment}
                    </p>
                  ) : null}
                </button>
              ))
            )}
          </div>
        </div>
      ) : null}
    </div>
  );
};

export default AccountRequestNotificationBell;
