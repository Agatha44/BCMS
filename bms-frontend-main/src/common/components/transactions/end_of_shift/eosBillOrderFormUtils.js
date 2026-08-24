import {
  formatOrderFormAmount,
  formatOrderFormDate,
  formatOrderFormDateTime,
} from '../bundles/bundleBillOrderFormUtils.js';

const pick = (...values) => {
  for (const v of values) {
    if (v != null && String(v).trim() !== '') return v;
  }
  return null;
};

/**
 * Build order-form fields for end-of-shift bills (bridge-app _print_order_form_eof).
 */
export function buildEosBillOrderForm(bill = {}, printedBy = '') {
  const payerName = pick(bill.payer_name, bill.customer_name);
  const phoneNumber = pick(bill.pyr_cell_num, bill.phone_number, bill.payer_phone);
  const controlNumber = pick(bill.control_number, bill.contr_num, bill.control_num);
  const billAmount = pick(bill.bill_amount, bill.amount);
  const formattedAmount = formatOrderFormAmount(billAmount);
  const billedAmount = formattedAmount ? `TSH ${formattedAmount}` : null;

  const shiftLabel = pick(bill.shift_name);
  const shiftDate = pick(bill.shift_date);
  const defaultPaymentFor =
    shiftLabel && shiftDate
      ? `End of shift bill for ${shiftLabel} on ${shiftDate}`
      : 'Nyerere Bridge Toll Collection';

  return {
    remitter: {
      payerName,
      phoneNumber,
    },
    beneficiary: {
      accountName: 'NATIONAL SOCIAL SECURITY FUND',
      currency: 'TANZANIAN SHILLINGS',
    },
    bill: {
      controlNumber,
      billedTo: payerName,
      billedAmount,
      beingPaymentFor: pick(bill.bill_desc, defaultPaymentFor),
      billDueDate: formatOrderFormDateTime(pick(bill.bill_exp_dt, bill.bill_expiry_at, bill.bill_generated_at)),
    },
    meta: {
      printedBy: printedBy || '—',
      printedOn: formatOrderFormDate(new Date().toISOString()),
    },
  };
}
