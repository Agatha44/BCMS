import { Modal } from 'antd';

import BrandModalHeader from '../sod/components/BrandModalHeader.jsx';

const BRAND = '#962E32';

const formatDateTime = (value) => {
  if (!value) return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const ReadOnlyField = ({ label, value }) => (
  <div className="min-w-0 py-1.5">
    <span
      className="block text-xs font-semibold tracking-[0.01em]"
      style={{ color: BRAND }}
    >
      {label}
    </span>
    <div className="mt-0.5 text-sm font-medium text-black">
      {value != null && value !== '' ? value : <span className="text-slate-400">N/A</span>}
    </div>
  </div>
);

const SectionHeading = ({ children }) => (
  <h4
    className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
    style={{ color: BRAND }}
  >
    {children}
  </h4>
);

export default function ShiftRecordDetailModal({ open, record, onClose }) {
  if (!record) return null;

  const counterDate = record.open_counter
    ? new Date(record.open_counter).toISOString().slice(0, 10)
    : null;

  return (
    <Modal
      open={open}
      onCancel={onClose}
      footer={null}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      width={560}
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="Shift Record Details" onClose={onClose} />

      <div className="space-y-5 px-6 py-5">
        <div>
          <SectionHeading>Counter Session</SectionHeading>
          <div className="grid gap-x-6 sm:grid-cols-2">
            <ReadOnlyField label="Record Id" value={record.id} />
            <ReadOnlyField label="Counter Date" value={counterDate} />
            <ReadOnlyField label="Open Counter" value={formatDateTime(record.open_counter)} />
            <ReadOnlyField label="Close Counter" value={formatDateTime(record.close_counter)} />
            <ReadOnlyField label="Shift" value={record.name ?? record.shift_name} />
            <ReadOnlyField label="Lane" value={record.lane_no} />
          </div>
        </div>
      </div>

      <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
        <button
          type="button"
          onClick={onClose}
          className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
        >
          Close
        </button>
      </div>
    </Modal>
  );
}
