import { useEffect, useState } from 'react';
import { ArrowRightLeft, Save } from 'lucide-react';
import { UploadOutlined } from '@ant-design/icons';
import { Button, Form, Input, InputNumber, Modal, Upload } from 'antd';
import Swal from 'sweetalert2';
import { formatCurrency } from '../../../../common/utils/numberFormat.js';
import BrandModalHeader from './BrandModalHeader.jsx';
import CollectionLoader from '../../components/CollectionLoader.jsx';
import { apiService } from '../../../../services/api.jsx';
import { formatApiValidationErrors } from '../utils/fundTransferUtils.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';


const getAccountName = (account) => {
  if (!account) return EMPTY_VALUE;
  return String(account.account_name || '').trim() || EMPTY_VALUE;
};

const MAX_SUPPORTING_FILES = 1;
const MAX_APPROVAL_PDF_SIZE_BYTES = 10 * 1024 * 1024;
const ACCEPTED_FILE_TYPES = '.pdf,application/pdf';

const validateApprovalPdf = (file) => {
  if (!file) return 'An approval PDF document is required';
  const isPdf =
    file.type === 'application/pdf' ||
    String(file.name || '')
      .toLowerCase()
      .endsWith('.pdf');
  if (!isPdf) return 'Only PDF files are allowed';
  if (file.size > MAX_APPROVAL_PDF_SIZE_BYTES) return 'File size must not exceed 10MB';
  return null;
};

const normalizeAccount = (payload) => {
  if (!payload) return null;
  if (payload.account) return normalizeAccount(payload.account);
  if (payload.account_details) return normalizeAccount(payload.account_details);
  if (payload.account_no || payload.id != null || payload.account_id != null) return payload;
  return null;
};

/** Backend uses `id` on accounts list; search-account-details may omit it. */
const resolveAccountId = (account) => {
  if (!account) return null;
  const raw = account.account_no;
  if (!raw) return null;
  return raw;
};

const mergeAccountRecords = (details, listRow) => {
  if (!details && !listRow) return null;
  if (!details) return listRow;
  if (!listRow) return details;
  const id = resolveAccountId(listRow) ?? resolveAccountId(details);
  return {
    ...listRow,
    ...details,
    id: id ?? listRow.id ?? details.id,
    account_id: id ?? listRow.account_id ?? details.account_id,
    account_no: details.account_no || listRow.account_no,
    account_name: details.account_name || listRow.account_name,
    account_balance: details.account_balance ?? listRow.account_balance,
  };
};

const fetchAccountFromList = async (accountNo) => {
  const listRes = await apiService.getAccountsList({ search: accountNo, per_page: 10, page: 1 });
  if (!listRes?.success) return null;
  const accounts = listRes.data?.accounts ?? [];
  return accounts.find((a) => String(a.account_no || '').trim() === accountNo) || null;
};

const fetchAccountByNumber = async (accountNo) => {
  const trimmed = String(accountNo || '').trim();
  if (!trimmed) {
    return { account: null, error: 'Account number is required' };
  }

  try {
    let listAccount = null;
    let detailsAccount = null;

    try {
      listAccount = await fetchAccountFromList(trimmed);
    } catch {
      // continue with search-account-details only
    }

    try {
      const res = await apiService.searchAccount(trimmed);
      if (res?.success) {
        detailsAccount = normalizeAccount(res.data);
      }
    } catch {
      // continue with list result only
    }

    const account = mergeAccountRecords(detailsAccount, listAccount);
    if (!account) {
      return { account: null, error: 'Account not found' };
    }
    return { account, error: null };
  } catch {
    return { account: null, error: 'Failed to fetch account details' };
  }
};

const AccountDetailsCard = ({ account, loading, error }) => {
  if (loading) {
    return (
      <CollectionLoader size={56} compact />
    );
  }

  if (error) {
    return (
      <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {error}
      </div>
    );
  }

  if (!account) {
    return (
      <div className="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
        Account details will appear after you enter an account number.
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-slate-200 bg-[#fffafa] px-4 py-3">
      <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <div>
          <p className="m-0 text-[11px] font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>
            Account Number
          </p>
          <p className="m-0 mt-0.5 font-mono text-sm font-medium text-black">
            {account.account_no || EMPTY_VALUE}
          </p>
        </div>
        <div>
          <p className="m-0 text-[11px] font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>
            Account Name
          </p>
          <p className="m-0 mt-0.5 text-sm font-medium text-black">
            {getAccountName(account) || EMPTY_VALUE}
          </p>
        </div>
        <div className="sm:col-span-2">
          <p className="m-0 text-[11px] font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>
            Account Balance
          </p>
          <p className="m-0 mt-0.5 font-mono text-sm font-semibold text-black">
            {formatCurrency(account.account_balance)}
          </p>
        </div>
      </div>
    </div>
  );
};

const LOOKUP_DEBOUNCE_MS = 500;

const AccountLookupField = ({ label, accountNo, onAccountNoChange, account, loading, error, disabled }) => (
  <div className="space-y-2">
    <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <Input
      value={accountNo}
      onChange={(e) => onAccountNoChange(e.target.value)}
      placeholder="Enter account number"
      disabled={disabled}
      className="!rounded-lg font-mono"
      allowClear
    />
    <AccountDetailsCard account={account} loading={loading} error={error} />
  </div>
);

const useAccountLookup = (accountNo, isActive) => {
  const [account, setAccount] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!isActive) {
      setAccount(null);
      setError(null);
      setLoading(false);
      return undefined;
    }

    const trimmed = String(accountNo || '').trim();
    if (!trimmed) {
      setAccount(null);
      setError(null);
      setLoading(false);
      return undefined;
    }

    let cancelled = false;
    const timer = setTimeout(async () => {
      setLoading(true);
      setError(null);
      setAccount(null);

      const result = await fetchAccountByNumber(trimmed);
      if (cancelled) return;

      setAccount(result.account);
      setError(result.error);
      setLoading(false);
    }, LOOKUP_DEBOUNCE_MS);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [accountNo, isActive]);

  return { account, loading, error, setAccount, setError, setLoading };
};

export default function FundTransferModal({
  isOpen,
  onClose,
  onSubmitted,
  mode = 'create',
  transferId = null,
  initialTransfer = null,
}) {
  const isResubmit = mode === 'resubmit';
  const [form] = Form.useForm();
  const [submitting, setSubmitting] = useState(false);
  const [fileList, setFileList] = useState([]);

  const [fromAccountNo, setFromAccountNo] = useState('');
  const [toAccountNo, setToAccountNo] = useState('');

  const {
    account: fromAccount,
    loading: fromLoading,
    error: fromError,
    setAccount: setFromAccount,
    setError: setFromError,
    setLoading: setFromLoading,
  } = useAccountLookup(fromAccountNo, isOpen);

  const {
    account: toAccount,
    loading: toLoading,
    error: toError,
    setAccount: setToAccount,
    setError: setToError,
    setLoading: setToLoading,
  } = useAccountLookup(toAccountNo, isOpen);

  const resetState = () => {
    form.resetFields();
    setFileList([]);
    setFromAccountNo('');
    setToAccountNo('');
    setFromAccount(null);
    setToAccount(null);
    setFromError(null);
    setToError(null);
    setFromLoading(false);
    setToLoading(false);
  };

  useEffect(() => {
    if (!isOpen) return;

    if (isResubmit && initialTransfer) {
      form.resetFields();
      setFileList([]);
      setFromError(null);
      setToError(null);
      setFromLoading(false);
      setToLoading(false);
      setFromAccountNo(String(initialTransfer.from_account_no ?? ''));
      setToAccountNo(String(initialTransfer.to_account_no ?? ''));
      form.setFieldsValue({
        amount: initialTransfer.amount != null ? Number(initialTransfer.amount) : undefined,
        narration: initialTransfer.narration ?? '',
      });
      return;
    }

    resetState();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, isResubmit, transferId, initialTransfer]);

  useEffect(() => {
    if (!isOpen || !fromAccount) return;
    form.validateFields(['amount']).catch(() => {});
  }, [fromAccount, form, isOpen]);

  const handleClose = () => {
    if (submitting) return;
    resetState();
    onClose?.();
  };

  const handleSubmit = async (values) => {
    if (fromLoading || toLoading) {
      await Swal.fire({
        icon: 'info',
        title: 'Please wait',
        text: 'Account details are still loading.',
      });
      return;
    }

    if (!fromAccount) {
      await Swal.fire({
        icon: 'warning',
        title: 'From account required',
        text: fromError || 'Enter a valid source account number.',
      });
      return;
    }
    if (!toAccount) {
      await Swal.fire({
        icon: 'warning',
        title: 'To account required',
        text: toError || 'Enter a valid destination account number.',
      });
      return;
    }

    const narration = values.narration?.trim();
    if (!narration) {
      await Swal.fire({
        icon: 'warning',
        title: 'Narration required',
        text: 'Please enter a narration for this transfer.',
      });
      return;
    }

    const fromNo = String(fromAccount.account_no || '').trim();
    const toNo = String(toAccount.account_no || '').trim();
    if (fromNo === toNo || (fromAccount.account_no != null && toAccount.account_no != null && String(fromAccount.account_no) === String(toAccount.account_no))) {
      await Swal.fire({
        icon: 'warning',
        title: 'Invalid transfer',
        text: 'Source and destination accounts must be different.',
      });
      return;
    }

    const amount = Number(values.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
      await Swal.fire({ icon: 'warning', title: 'Invalid amount', text: 'Please enter a valid transfer amount.' });
      return;
    }

    const fromBalance = Number(fromAccount.account_balance ?? 0);
    if (amount > fromBalance) {
      await Swal.fire({
        icon: 'warning',
        title: 'Insufficient balance',
        text: `Transfer amount cannot exceed the source account balance of ${formatCurrency(fromBalance)}.`,
      });
      return;
    }

    const files = fileList.map((f) => f.originFileObj).filter((f) => f instanceof File);
    const pdfError = validateApprovalPdf(files[0]);
    if (pdfError) {
      await Swal.fire({
        icon: 'warning',
        title: 'Approval document required',
        text: pdfError,
      });
      return;
    }

    if (isResubmit && transferId == null && initialTransfer?.id == null) {
      await Swal.fire({
        icon: 'error',
        title: 'Resubmission failed',
        text: 'Transfer reference is missing.',
      });
      return;
    }

    setSubmitting(true);
    try {
      const payload = {
        from_account_id: fromAccount.account_no,
        to_account_id: toAccount.account_no,
        amount,
        narration,
      };

      const res = isResubmit
        ? await apiService.resubmitFundTransfer(transferId ?? initialTransfer?.id, payload, files)
        : await apiService.submitFundTransfer(payload, files);
      if (!res?.success) {
        await Swal.fire({
          icon: 'error',
          title: isResubmit ? 'Resubmission failed' : 'Submission failed',
          text: formatApiValidationErrors(
            { responseData: res, validationErrors: res?.data },
            res?.message ||
              (isResubmit
                ? 'Could not resubmit fund transfer for approval.'
                : 'Could not submit fund transfer for approval.')
          ),
        });
        return;
      }

      await Swal.fire({
        icon: 'success',
        title: isResubmit ? 'Resubmitted' : 'Submitted',
        text:
          res.message ||
          (isResubmit
            ? 'Fund transfer request resubmitted for approval.'
            : 'Fund transfer request submitted for approval.'),
        timer: 2500,
        showConfirmButton: false,
      });

      onSubmitted?.(res);
      handleClose();
    } catch (err) {
      await Swal.fire({
        icon: 'error',
        title: 'Error',
        text: formatApiValidationErrors(err, err?.message || 'An unexpected error occurred.'),
      });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal
      open={isOpen}
      onCancel={handleClose}
      footer={null}
      width={960}
      style={{ maxWidth: '95vw' }}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      maskClosable={!submitting}
      keyboard={!submitting}
      className="brand-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader
        title={isResubmit ? 'Resubmit Fund Transfer' : 'Fund Transfer'}
        onClose={handleClose}
      />

      <Form
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        requiredMark={false}
        className="flex flex-col"
      >
        <div className="max-h-[70vh] overflow-y-auto px-6 py-5">
          <div className="mb-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            <ArrowRightLeft size={16} className="shrink-0" />
            <span>
              {isResubmit
                ? 'Update the transfer details and upload a new approval PDF, then resubmit for review.'
                : 'Transfer balance between accounts. The request requires approval before funds are moved.'}
            </span>
          </div>

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <AccountLookupField
              label="From Account"
              accountNo={fromAccountNo}
              onAccountNoChange={setFromAccountNo}
              account={fromAccount}
              loading={fromLoading}
              error={fromError}
              disabled={submitting}
            />
            <AccountLookupField
              label="To Account"
              accountNo={toAccountNo}
              onAccountNoChange={setToAccountNo}
              account={toAccount}
              loading={toLoading}
              error={toError}
              disabled={submitting}
            />
          </div>

          <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Form.Item
              name="amount"
              label={
                <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Transfer Amount
                </span>
              }
              rules={[
                { required: true, message: 'Please enter the transfer amount' },
                {
                  validator: (_, value) => {
                    const n = Number(value);
                    if (!Number.isFinite(n) || n <= 0) {
                      return Promise.reject(new Error('Amount must be greater than zero'));
                    }
                    if (fromAccount) {
                      const fromBalance = Number(fromAccount.account_balance);
                      if (n > fromBalance) {
                        return Promise.reject(
                          new Error(
                            `Amount cannot exceed source balance of ${formatCurrency(fromBalance)}`
                          )
                        );
                      }
                    }
                    return Promise.resolve();
                  },
                },
              ]}
            >
              <InputNumber
                className="!w-full !rounded-lg"
                min={1}
                precision={0}
                placeholder="Enter amount"
                disabled={submitting}
                formatter={(v) => `${v}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                parser={(v) => v.replace(/,/g, '')}
              />
            </Form.Item>

            <Form.Item
              name="narration"
              label={
                <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Narration
                </span>
              }
              rules={[
                { required: true, message: 'Please enter narration for this transfer' },
                { whitespace: true, message: 'Please enter narration for this transfer' },
              ]}
            >
              <Input.TextArea
                rows={2}
                placeholder="Reason or description for this transfer"
                disabled={submitting}
                className="!rounded-lg"
              />
            </Form.Item>
          </div>

          <div className="mt-2">
            <span className="mb-2 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
              Approval Document <span className="text-red-600">*</span>
            </span>
            <p className="mb-2 text-xs text-slate-500">
              Upload the approval PDF (max 10MB). Required before {isResubmit ? 'resubmission' : 'submission'}.
            </p>
            <Upload
              fileList={fileList}
              beforeUpload={(file) => {
                const pdfValidationError = validateApprovalPdf(file);
                if (pdfValidationError) {
                  Swal.fire({ icon: 'warning', title: 'Invalid file', text: pdfValidationError });
                  return Upload.LIST_IGNORE;
                }
                return false;
              }}
              onChange={({ fileList: nextList }) => setFileList(nextList.slice(0, MAX_SUPPORTING_FILES))}
              onRemove={(file) => {
                setFileList((prev) => prev.filter((f) => f.uid !== file.uid));
              }}
              accept={ACCEPTED_FILE_TYPES}
              disabled={submitting}
              maxCount={MAX_SUPPORTING_FILES}
            >
              <Button icon={<UploadOutlined />} disabled={submitting || fileList.length >= MAX_SUPPORTING_FILES}>
                Select PDF
              </Button>
            </Upload>
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            htmlType="submit"
            loading={submitting}
            icon={<Save size={14} />}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => {
              e.currentTarget.style.backgroundColor = BRAND_DARK;
              e.currentTarget.style.borderColor = BRAND_DARK;
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
              e.currentTarget.style.borderColor = BRAND;
            }}
          >
            {isResubmit ? 'Resubmit Transfer' : 'Submit Transfer'}
          </Button>
          <Button onClick={handleClose} disabled={submitting}>
            Close
          </Button>
        </div>
      </Form>
    </Modal>
  );
}
