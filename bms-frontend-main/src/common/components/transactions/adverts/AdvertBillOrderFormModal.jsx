import {useCallback, useMemo, useRef} from 'react';
import {Button, Modal, Spin} from 'antd';
import {Printer} from 'lucide-react';
import PropTypes from 'prop-types';
import {useSelector} from 'react-redux';

import nssfLogo from '../../../../assets/images/nssf-order-logo.png';
import tanzaniaEmblem from '../../../../assets/images/tanzania-emblem.png';
import BrandModalHeader from '../../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const CELL_BORDER = '1px solid #cbd5e1';

const sectionHeaderStyle = {
  background: BRAND,
  color: '#fff',
  fontWeight: 700,
  fontSize: 13,
  textAlign: 'left',
  padding: '8px 12px',
  border: `1px solid ${BRAND}`,
};

const labelCellStyle = {
  width: '42%',
  fontWeight: 700,
  fontSize: 12,
  padding: '8px 12px',
  border: CELL_BORDER,
  verticalAlign: 'top',
  background: '#fff',
};

const valueCellStyle = {
  fontSize: 12,
  padding: '8px 12px',
  border: CELL_BORDER,
  verticalAlign: 'top',
  background: '#fff',
};

const pick = (...values) => {
  for (const value of values) {
    if (value !== null && value !== undefined && String(value).trim() !== '') return value;
  }
  return null;
};

const display = (value) => {
  if (value === null || value === undefined || String(value).trim() === '') {
    return <span style={{color: '#94a3b8'}}>—</span>;
  }
  return value;
};

const formatAmount = (value) => {
  if (value === null || value === undefined || value === '') return null;
  const amount = Number(String(value).replace(/,/g, ''));
  if (Number.isNaN(amount)) return String(value);
  return `TSH ${amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
};

const formatDate = (value) => {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
};

const formatShortDate = (value) => {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return `${String(date.getDate()).padStart(2, '0')}-${months[date.getMonth()]}-${date.getFullYear()}`;
};

const extractPeriod = (description = '') => {
  const match = String(description).match(/period from\s+(.*?)\s+to\s+(.*)$/i);
  if (!match) return {start: null, end: null};
  return {start: match[1], end: match[2]};
};

const buildAdvertOrderForm = (bill = {}, printedBy = '') => {
  const period = extractPeriod(bill.bill_desc);
  const payerName = pick(bill.payer_name, bill.customer_name);
  const phoneNumber = pick(bill.phone_number, bill.payer_phone);
  const description = pick(bill.bill_desc, 'Advertisement Charges');

  return {
    remitter: {
      payerName,
      phoneNumber,
    },
    beneficiary: {
      accountName: 'NATIONAL SOCIAL SECURITY FUND',
      currency: 'TANZANIAN SHILLINGS',
    },
    advert: {
      type: pick(bill.advertisement_type, bill.dist_param),
      periodStart: period.start,
      periodEnd: period.end,
    },
    bill: {
      controlNumber: pick(bill.control_number, bill.contr_num),
      billedTo: payerName,
      billedAmount: formatAmount(pick(bill.bill_amount, bill.amount)),
      beingPaymentFor: description,
      billDueDate: formatDate(pick(bill.bill_expiry_at, bill.bill_exp_dt)),
      receiptNumber: pick(bill.psp_receipt_num, bill.receipt_number),
    },
    meta: {
      printedBy: printedBy || '—',
      printedOn: formatShortDate(new Date()),
    },
  };
};

const DetailTable = ({title, rows}) => (
  <table className="order-form-detail-table" style={{width: '100%', borderCollapse: 'collapse', marginBottom: 16}}>
    <thead>
    <tr>
      <th colSpan={2} style={sectionHeaderStyle}>
        {title}
      </th>
    </tr>
    </thead>
    <tbody>
    {rows.map(({label, value, boldValue = false}) => (
      <tr key={label}>
        <td style={labelCellStyle}>{label}</td>
        <td style={{...valueCellStyle, fontWeight: boldValue ? 700 : 400}}>{display(value)}</td>
      </tr>
    ))}
    </tbody>
  </table>
);

DetailTable.propTypes = {
  title: PropTypes.string.isRequired,
  rows: PropTypes.arrayOf(
    PropTypes.shape({
      label: PropTypes.string.isRequired,
      value: PropTypes.node,
      boldValue: PropTypes.bool,
    })
  ).isRequired,
};

const OrderFormDocument = ({form, documentRef}) => (
  <div
    ref={documentRef}
    className="order-form-document"
    style={{
      fontFamily: 'Arial, Helvetica, sans-serif',
      color: '#000',
      background: '#fff',
      padding: '28px 40px 24px',
      maxWidth: 720,
      margin: '0 auto',
      lineHeight: 1.4,
    }}
  >
    <div style={{display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 20}}>
      <img
        src={nssfLogo}
        alt="NSSF"
        className="order-form-logo"
        style={{height: 72, width: 'auto', maxWidth: 120, objectFit: 'contain', display: 'block'}}
      />
      <img
        src={tanzaniaEmblem}
        alt="Coat of Arms"
        className="order-form-logo"
        style={{height: 72, width: 'auto', maxWidth: 100, objectFit: 'contain', display: 'block'}}
      />
    </div>

    <DetailTable
      title="1. Remitter Details:"
      rows={[
        {label: 'Payer Name:', value: form.remitter.payerName},
        {label: 'Phone Number:', value: form.remitter.phoneNumber},
      ]}
    />

    <DetailTable
      title="2. Beneficiary Details"
      rows={[
        {label: 'Account Name:', value: form.beneficiary.accountName},
        {label: 'Currency:', value: form.beneficiary.currency},
      ]}
    />

    <DetailTable
      title="3. Advertisement Details"
      rows={[
        {label: 'Advertisement Type:', value: form.advert.type},
        {label: 'Start Date:', value: form.advert.periodStart},
        {label: 'End Date:', value: form.advert.periodEnd},
      ]}
    />

    <DetailTable
      title="4. Bill Details"
      rows={[
        {label: 'CONTROL NUMBER:', value: form.bill.controlNumber, boldValue: true},
        {label: 'Billed To:', value: form.bill.billedTo},
        {label: 'Billed Amount:', value: form.bill.billedAmount},
        {label: 'Being payment for:', value: form.bill.beingPaymentFor},
        {label: 'Bill Due Date:', value: form.bill.billDueDate},
        {label: 'Receipt Number:', value: form.bill.receiptNumber},
        {label: 'Printed By:', value: form.meta.printedBy},
        {label: 'Printed on:', value: form.meta.printedOn, boldValue: true},
      ]}
    />

    <table className="order-form-detail-table" style={{width: '100%', borderCollapse: 'collapse', marginBottom: 0}}>
      <thead>
      <tr>
        <th colSpan={2} style={sectionHeaderStyle}>
          Note to Commercial Banks:
        </th>
      </tr>
      </thead>
      <tbody>
      <tr>
        <td colSpan={2} style={{...valueCellStyle, fontSize: 12, lineHeight: 1.5}}>
          1. Field &ldquo;Control Number&rdquo; with value:{' '}
          <strong>{form.bill.controlNumber || '—'}</strong> must be captured correctly.
        </td>
      </tr>
      </tbody>
    </table>
  </div>
);

OrderFormDocument.propTypes = {
  form: PropTypes.shape({
    remitter: PropTypes.object,
    beneficiary: PropTypes.object,
    advert: PropTypes.object,
    bill: PropTypes.object,
    meta: PropTypes.object,
  }).isRequired,
  documentRef: PropTypes.shape({current: PropTypes.any}).isRequired,
};

const printStyles = `
  @page { size: A4; margin: 14mm; }
  body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; }
  .order-form-logo {
    max-height: 72px;
    background: transparent !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .order-form-detail-table th {
    background: ${BRAND} !important;
    color: #fff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .order-form-detail-table td,
  .order-form-detail-table th {
    border: 1px solid #cbd5e1 !important;
  }
`;

export default function AdvertBillOrderFormModal({open, bill = null, loading = false, onClose}) {
  const documentRef = useRef(null);
  const user = useSelector((state) => state.auth?.user);

  const printedBy = useMemo(() => {
    if (!user) return '';
    return (
      user.full_name ||
      user.name ||
      [user.first_name, user.surname].filter(Boolean).join(' ') ||
      user.username ||
      ''
    );
  }, [user]);

  const form = useMemo(() => (bill ? buildAdvertOrderForm(bill, printedBy) : null), [bill, printedBy]);

  const handlePrint = useCallback(() => {
    if (!documentRef.current || !form) return;
    const html = documentRef.current.outerHTML;
    const win = window.open('', '_blank', 'noopener,noreferrer');
    if (!win) return;
    win.document.write(
      `<!DOCTYPE html><html><head><title>Advertisement Payment Order Form</title><style>${printStyles}</style></head><body>${html}</body></html>`
    );
    win.document.close();
    win.focus();
    win.onload = () => {
      win.print();
    };
  }, [form]);

  return (
    <Modal
      open={open && !!bill}
      onCancel={onClose}
      footer={null}
      width={800}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      className="brand-modal"
      styles={{body: {padding: 0}, content: {padding: 0, overflow: 'hidden'}}}
    >
      <BrandModalHeader title="Advertisement Payment Order Form" onClose={onClose} />

      <div className="relative max-h-[78vh] overflow-y-auto bg-slate-100 py-5">
        {loading && (
          <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/70">
            <Spin tip="Loading order form..." />
          </div>
        )}
        {form && <OrderFormDocument form={form} documentRef={documentRef} />}
      </div>

      <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
        <Button
          type="primary"
          icon={<Printer size={14} />}
          onClick={handlePrint}
          disabled={loading || !form}
          style={{backgroundColor: BRAND, borderColor: BRAND}}
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
        <Button onClick={onClose}>Close</Button>
      </div>
    </Modal>
  );
}

AdvertBillOrderFormModal.propTypes = {
  open: PropTypes.bool.isRequired,
  bill: PropTypes.object,
  loading: PropTypes.bool,
  onClose: PropTypes.func.isRequired,
};
