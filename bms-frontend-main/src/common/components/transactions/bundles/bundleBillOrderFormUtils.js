const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const pick = (...values) => {
  for (const v of values) {
    if (v != null && String(v).trim() !== '') return v;
  }
  return null;
};

export const formatOrderFormDate = (value) => {
  if (value == null || value === '') return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  const day = String(d.getDate()).padStart(2, '0');
  const month = MONTHS[d.getMonth()];
  return `${day}-${month}-${d.getFullYear()}`;
};

export const formatOrderFormDateTime = (value) => {
  if (value == null || value === '') return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

export const formatOrderFormAmount = (value) => {
  if (value == null || value === '') return null;
  const num = typeof value === 'number' ? value : Number(String(value).replace(/,/g, ''));
  if (Number.isNaN(num)) return String(value);
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const defaultBeneficiary = {
  accountName: 'NATIONAL SOCIAL SECURITY FUND',
  currency: 'TANZANIAN SHILLINGS',
};

/**
 * Build order-form field map from bundle bill row + optional API payload.
 */
export function buildBundleBillOrderForm(bill = {}, apiPayload = {}, printedBy = '') {
  const src = apiPayload?.order_form ?? apiPayload ?? {};

  const payerName = pick(
    src.payer_name,
    src.payerName,
    bill.payer_name,
    bill.customer_name,
    src.billed_to,
  );

  const phoneNumber = pick(
    src.phone_number,
    src.phoneNumber,
    src.payer_phone,
    bill.phone_number,
    bill.phone,
    bill.mobile,
  );

  const controlNumber = pick(src.control_number, bill.control_number);
  const billAmount = pick(src.billed_amount, src.bill_amount, src.transfer_amount, bill.bill_amount, bill.amount_display);
  const formattedAmount = formatOrderFormAmount(billAmount);
  const billedAmount = formattedAmount ? `TSH ${formattedAmount}` : null;

  return {
    remitter: {
      payerName,
      phoneNumber,
    },
    beneficiary: {
      accountName:
        pick(src.beneficiary_account_name, src.beneficiary_name, src.account_name) ??
        defaultBeneficiary.accountName,
      currency: pick(src.currency) ?? defaultBeneficiary.currency,
    },
    bill: {
      controlNumber,
      billedTo: pick(src.billed_to, payerName),
      billedAmount,
      beingPaymentFor: pick(src.being_payment_for, bill.bill_description),
      billDueDate: formatOrderFormDateTime(
        pick(src.bill_due_date, src.bill_expiry_date, bill.bill_expiry_at, bill.bill_generated_at),
      ),
    },
    meta: {
      printedBy: pick(src.printed_by, printedBy) || '—',
      printedOn: formatOrderFormDate(pick(src.printed_on, new Date().toISOString())) ?? formatOrderFormDate(new Date()),
    },
  };
}
