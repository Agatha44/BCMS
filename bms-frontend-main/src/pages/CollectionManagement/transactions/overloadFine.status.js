// Single source of truth for overload fine status derivation.
// Shared by the OverloadFines list page and the OverloadFineDetailsModal so the
// status label/colour logic lives in exactly one place.

export const OVERLOAD_STATUS = {
  CANCELLED: { label: 'Cancelled', color: 'red' },
  PAID: { label: 'Paid', color: 'green' },
  EXPIRED: { label: 'Expired', color: 'volcano' },
  FAILED: { label: 'Failed', color: 'red' },
  PENDING: { label: 'Pending', color: 'gold' },
  UNKNOWN: { label: 'N/A', color: 'default' },
};

// A bill is expired when it is unpaid and either the backend flags it as
// expired or its expiry timestamp is in the past.
export const isExpiredOverloadBill = (o) => {
  if (!o || o.psp_receipt_num) return false;
  if (String(o.bill_status ?? '').trim().toLowerCase() === 'expired') return true;
  const raw = o.bill_exp_dt;
  if (!raw) return false;
  const d = new Date(String(raw).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return false;
  return d.getTime() < Date.now();
};

export const getOverloadStatus = (o) => {
  if (!o) return OVERLOAD_STATUS.UNKNOWN;
  if (o.is_cancelled === '1') return OVERLOAD_STATUS.CANCELLED;
  if (o.bill_status === '1') return OVERLOAD_STATUS.PAID;
  if (isExpiredOverloadBill(o)) return OVERLOAD_STATUS.EXPIRED;
  if (o.bill_status === '0') return OVERLOAD_STATUS.FAILED;
  return OVERLOAD_STATUS.PENDING;
};
