import { ClockCircleOutlined } from '@ant-design/icons';

const BRAND = '#962E32';

const STATUS_TEXT_CLASS = {
  opened: 'text-blue-700',
  verified: 'text-violet-700',
  assigned: 'text-teal-700',
  'on hold': 'text-amber-700',
  reopened: 'text-cyan-700',
  closed: 'text-green-700',
  resolved: 'text-emerald-700',
  cancelled: 'text-red-700',
  canceled: 'text-red-700',
};

const getStatusTextClass = (status) =>
  STATUS_TEXT_CLASS[String(status || '').toLowerCase()] || 'text-[#962E32]';

const formatDateTime = (value) => {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const MinuteField = ({ label, value, valueClass }) => (
  <div>
    <span className="mb-0.5 block text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500">
      {label}
    </span>
    <div className={`break-words text-sm font-semibold ${valueClass}`}>
      {value || '—'}
    </div>
  </div>
);

const MinuteCard = ({ status, roleName, username, comment, at, isCurrent = false }) => (
  <div className="rounded-lg border border-slate-200 bg-white px-3 py-3">
    <div className="mb-2.5 flex items-center justify-between gap-2">
      <span className={`text-xs font-semibold ${getStatusTextClass(status)}`}>
        {status || 'Opened'}
      </span>
      {isCurrent ? (
        <span className="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500">
          Current
        </span>
      ) : null}
    </div>
    <div className="space-y-2">
      <MinuteField
        label="Current Status"
        value={status || 'Opened'}
        valueClass={getStatusTextClass(status)}
      />
      <MinuteField
        label="Role Name"
        value={roleName}
        valueClass="text-indigo-700"
      />
      <MinuteField
        label="User Name"
        value={username}
        valueClass="text-teal-700"
      />
      {comment ? (
        <MinuteField
          label="Comment"
          value={comment}
          valueClass="text-slate-700"
        />
      ) : null}
    </div>
    <div className="mt-2.5 text-[11px] text-slate-500">{formatDateTime(at)}</div>
  </div>
);

const SupportRequestMinutes = ({ minutes = [], currentStatus, username, roleName, requestNumber }) => {
  const history = Array.isArray(minutes) ? minutes : [];
  const resolvedStatus = currentStatus || history[history.length - 1]?.status || 'Opened';
  const resolvedUser = username || history[history.length - 1]?.username || '—';
  const resolvedRole = roleName || history[history.length - 1]?.roleName || '—';
  const last = history[history.length - 1];
  const currentMatchesLast =
    last &&
    String(last.status || '') === String(resolvedStatus) &&
    String(last.username || '') === String(resolvedUser) &&
    String(last.roleName || '') === String(resolvedRole);
  const prior = currentMatchesLast ? history.slice(0, -1) : history;

  return (
    <aside className="flex h-full min-h-[360px] min-w-0 flex-col border-t border-slate-200 bg-[#fafafa] sm:border-l sm:border-t-0">
      <div className="border-b border-slate-200 bg-white px-4 py-3">
        <h4 className="m-0 flex items-center gap-2 text-sm font-semibold text-black">
          <ClockCircleOutlined style={{ color: BRAND }} />
          Minutes
        </h4>
        <div className="mt-2">
          <span className="mb-0.5 block text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500">
            Request Number
          </span>
          <div className="font-mono text-sm font-semibold text-[#962E32]">
            {requestNumber || '—'}
          </div>
        </div>
      </div>
      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
        <MinuteCard
          status={resolvedStatus}
          roleName={resolvedRole}
          username={resolvedUser}
          comment={last?.comment}
          at={last?.at}
          isCurrent
        />
        {prior.length > 0
          ? prior
              .slice()
              .reverse()
              .map((entry, index) => (
                <MinuteCard
                  key={`${entry.at}-${index}`}
                  status={entry.status}
                  roleName={entry.roleName}
                  username={entry.username}
                  comment={entry.comment}
                  at={entry.at}
                />
              ))
          : null}
      </div>
    </aside>
  );
};

export default SupportRequestMinutes;
