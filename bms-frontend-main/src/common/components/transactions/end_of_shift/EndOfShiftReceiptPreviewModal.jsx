import { useEffect, useMemo, useState } from 'react';
import { Download, Printer } from 'lucide-react';
import { Button, Modal, Spin } from 'antd';
import PropTypes from 'prop-types';

import { apiService } from '../../../../services/api.jsx';
import { formatMoney } from '../../../utils/numberFormat.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

const BrandModalHeader = ({ title, onClose }) => (
  <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
    <h2 className="m-0 text-sm font-semibold leading-none text-white">{title}</h2>
    <button
      type="button"
      aria-label="Close"
      onClick={onClose}
      className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
      </svg>
    </button>
  </div>
);

BrandModalHeader.propTypes = {
  title: PropTypes.string.isRequired,
  onClose: PropTypes.func.isRequired,
};

const formatDate = (value) => {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const SummaryField = ({ label, value }) => (
  <div className="min-w-0">
    <span className="block text-[11px] font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <div className="mt-0.5 truncate text-sm font-medium text-black" title={value ?? undefined}>
      {value != null && value !== '' ? value : <span className="font-normal text-slate-400">N/A</span>}
    </div>
  </div>
);

SummaryField.propTypes = {
  label: PropTypes.string.isRequired,
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
};

export default function EndOfShiftReceiptPreviewModal({ open, receipt = null, onClose }) {
  const [loading, setLoading] = useState(false);
  const [pdfUrl, setPdfUrl] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!open || !receipt?.id) {
      return undefined;
    }

    let cancelled = false;
    let objectUrl = null;

    const load = async () => {
      setLoading(true);
      setError(null);
      setPdfUrl(null);

      try {
        const resp = await apiService.getEndOfShiftReceiptPrint({ id: receipt.id });
        if (cancelled) return;

        if (!resp.success) {
          setError(resp.message || 'Failed to load receipt');
          return;
        }

        const url = resp.data?.url ?? resp.data;
        if (typeof url !== 'string') {
          setError('Receipt preview is not available.');
          return;
        }

        objectUrl = url;
        setPdfUrl(url);
      } catch (e) {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : 'Failed to load receipt');
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    load();

    return () => {
      cancelled = true;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [open, receipt?.id]);

  const handleClose = () => {
    if (pdfUrl) URL.revokeObjectURL(pdfUrl);
    setPdfUrl(null);
    setError(null);
    onClose?.();
  };

  const summary = useMemo(
    () => ({
      receiptNumber: receipt?.receipt_number ?? null,
      shiftName: receipt?.shift_name ?? null,
      shiftDate: formatDate(receipt?.shift_date),
      bankReceipt: receipt?.bank_receipt ?? null,
      bankDate: formatDate(receipt?.bank_date),
      amount: formatMoney(receipt?.amount ?? receipt?.bill_amount),
    }),
    [receipt]
  );

  const receiptTitle = summary.receiptNumber ? `Receipt ${summary.receiptNumber}` : 'Miscellaneous Receipt';
  const downloadName = `${(summary.receiptNumber || 'eos-receipt').replace(/[^\w.-]+/g, '_')}.pdf`;

  const handlePrint = () => {
    if (!pdfUrl) return;
    const win = window.open(pdfUrl, '_blank', 'noopener,noreferrer');
    if (win) {
      win.onload = () => win.print();
    }
  };

  return (
    <Modal
      open={open && !!receipt}
      onCancel={handleClose}
      footer={null}
      width={920}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      maskClosable={!loading}
      keyboard={!loading}
      className="brand-modal eos-receipt-preview-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title={receiptTitle} onClose={handleClose} />

      <div className="border-b border-slate-200 bg-white px-6 py-4">
        <h4
          className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
          style={{ color: BRAND }}
        >
          Receipt Summary
        </h4>
        <div className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
          <SummaryField label="Receipt Number" value={summary.receiptNumber} />
          <SummaryField label="Shift" value={summary.shiftName} />
          <SummaryField label="Shift Date" value={summary.shiftDate} />
          <SummaryField label="Bank Receipt" value={summary.bankReceipt} />
          <SummaryField label="Bank Date" value={summary.bankDate} />
          <SummaryField label="Amount" value={summary.amount} />
        </div>
      </div>

      <div className="relative bg-slate-100 px-6 py-5">
        {loading && (
          <div className="flex min-h-[420px] flex-col items-center justify-center gap-3 rounded-lg border border-slate-200 bg-white">
            <Spin size="large" />
            <p className="text-sm text-slate-600">Generating receipt preview…</p>
          </div>
        )}

        {error && !loading && (
          <div className="flex min-h-[320px] items-center justify-center rounded-lg border border-red-200 bg-red-50 px-6 py-10">
            <p className="max-w-md text-center text-sm text-red-700">{error}</p>
          </div>
        )}

        {pdfUrl && !error && !loading && (
          <div className="mx-auto max-w-[820px] overflow-hidden rounded-lg border border-slate-300 bg-white shadow-md">
            <iframe
              title={receiptTitle}
              src={`${pdfUrl}#view=FitH`}
              className="block h-[min(68vh,720px)] w-full border-0"
            />
          </div>
        )}
      </div>

      <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
        <Button
          icon={<Printer size={14} />}
          onClick={handlePrint}
          disabled={!pdfUrl || loading}
          style={{ backgroundColor: BRAND, borderColor: BRAND, color: '#fff' }}
          onMouseEnter={(e) => {
            if (!e.currentTarget.disabled) {
              e.currentTarget.style.backgroundColor = BRAND_DARK;
              e.currentTarget.style.borderColor = BRAND_DARK;
            }
          }}
          onMouseLeave={(e) => {
            if (!e.currentTarget.disabled) {
              e.currentTarget.style.backgroundColor = BRAND;
              e.currentTarget.style.borderColor = BRAND;
            }
          }}
        >
          Print
        </Button>
        {pdfUrl ? (
          <a
            href={pdfUrl}
            download={downloadName}
            className="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-medium transition hover:bg-[#fff5f5]"
            style={{ borderColor: BRAND, color: BRAND }}
          >
            <Download size={14} />
            Download
          </a>
        ) : (
          <Button icon={<Download size={14} />} disabled>
            Download
          </Button>
        )}
        <Button onClick={handleClose}>Close</Button>
      </div>
    </Modal>
  );
}

EndOfShiftReceiptPreviewModal.propTypes = {
  open: PropTypes.bool.isRequired,
  receipt: PropTypes.object,
  onClose: PropTypes.func.isRequired,
};
