import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AlertCircle, CheckCircle2, Copy, Eye, Plus, Receipt } from 'lucide-react';
import { ReloadOutlined } from '@ant-design/icons';
import { AutoComplete, Button, Form, Input, InputNumber, Modal, Spin, Tag, message as antMessage } from 'antd';
import PropTypes from 'prop-types';
import Swal from 'sweetalert2';

import DataTable from '../../../common/data/DataTable.jsx';
import PrepaymentBillDetailsModal from '../../../common/components/transactions/prepayments/PrepaymentBillDetailsModal.jsx';
import CollectionLoader from '../components/CollectionLoader.jsx';
import { apiService } from '../../../services/api.jsx';
import { formatMoney } from '../../../common/utils/numberFormat.js';
import { formatBillDate } from '../../../common/utils/dateFormat.js';
const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const safeArray = (v) => (Array.isArray(v) ? v : []);

const cleanName = (value) =>
  String(value ?? '')
    .replace(/_/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const buildFullName = (account) =>
  [account?.first_name, account?.middle_name, account?.surname]
    .map(cleanName)
    .filter(Boolean)
    .join(' ');

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

// Server returns rows at `data.data` (alongside `data.summary`).
const extractRows = (payload) => {
  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload?.data?.data)) return payload.data.data;
  if (Array.isArray(payload?.bills)) return payload.bills;
  return [];
};

// Server contract: pagination metadata is returned under `data.summary` as
// { total, per_page, current_page, last_page }.
const extractPagination = (payload, requestedPage, requestedPerPage) => {
  const summary = payload?.summary || {};

  const toPositive = (value, fallback = 0) => {
    const n = Number(value);
    return Number.isFinite(n) && n > 0 ? n : fallback;
  };

  const currentPage = toPositive(summary.current_page, toPositive(requestedPage, 1));
  const perPage = toPositive(summary.per_page, toPositive(requestedPerPage, 15));
  const total = toPositive(summary.total, 0);
  const lastPage = toPositive(summary.last_page, Math.max(1, Math.ceil(total / perPage)));

  const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
  const to = total === 0 ? 0 : Math.min(currentPage * perPage, total);

  return {
    current_page: currentPage,
    last_page: lastPage,
    per_page: perPage,
    total,
    from,
    to,
  };
};

const normalizeRow = (row = {}) => {
  // Strict mapping (no fallbacks) based on provided API response fields.
  const account = row.account_no ?? '-';
  const customer = row.cust_name ?? '-';
  const amount = formatMoney(row.bill_amount);
  const control = row.contr_num ?? '-';
  const receipt = row.psp_receipt_num ?? '-';
  const status = row.bill_status ?? '-';
  const isCancelled = row.is_cancelled;

  return {
    ...row,
    account_display: account,
    customer_display: customer,
    amount_display: amount,
    control_display: control,
    receipt_display: receipt,
    bill_date_display: formatBillDate(row.bill_gen_at),
    status_display: status,
    is_cancelled_display: isCancelled,
  };
};

const extractControlNumber = (data = {}) =>
  data.control_number ||
  data.gepg_control_number ||
  data.api_control_number ||
  data.contr_num ||
  null;

export default function TopUps() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showTopUpModal, setShowTopUpModal] = useState(false);
  const [creatingTopUp, setCreatingTopUp] = useState(false);
  const [accountOptions, setAccountOptions] = useState([]);
  const [accountSearchLoading, setAccountSearchLoading] = useState(false);
  const [selectedAccount, setSelectedAccount] = useState(null);
  const [billSuccess, setBillSuccess] = useState(null);
  const [detailsBill, setDetailsBill] = useState(null);
  const [detailsOpen, setDetailsOpen] = useState(false);
  const accountSearchDebounceRef = useRef(null);
  const [topUpForm] = Form.useForm();

  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  });

  const [filters, setFilters] = useState({
    page: 1,
    per_page: 15,
  });
  const [searchTerm, setSearchTerm] = useState('');
  const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');

  const fetchTopUpBills = useCallback(async (page = filters.page, perPage = filters.per_page, search = debouncedSearchTerm) => {
    setLoading(true);
    setError(null);
    try {
      const searchValue = String(search ?? '').trim();
      const payload = {
        page,
        per_page: perPage,
        draw: page,
        start: (page - 1) * perPage,
        length: perPage,
        ...(searchValue ? { q: searchValue, search: searchValue, search_term: searchValue } : {}),
      };

      const resp = await apiService.getTopUpBills(payload);
      if (!resp.success) {
        setRows([]);
        setError(resp.message || 'Failed to load top up bills');
        return;
      }

      const data = resp.data || {};
      const extracted = safeArray(extractRows(data)).map(normalizeRow);
      setRows(extracted);
      setPagination(extractPagination(data, page, perPage));
    } catch (e) {
      setRows([]);
      setError('An error occurred while loading top up bills');
      // eslint-disable-next-line no-console
      console.error(e);
    } finally {
      setLoading(false);
    }
  }, [filters.page, filters.per_page, debouncedSearchTerm]);

  useEffect(() => {
    const timeout = setTimeout(() => {
      setDebouncedSearchTerm(searchTerm.trim());
    }, 350);

    return () => clearTimeout(timeout);
  }, [searchTerm]);

  useEffect(() => {
    setFilters((prev) => ({ ...prev, page: 1 }));
    fetchTopUpBills(1, filters.per_page, debouncedSearchTerm);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedSearchTerm]);

  useEffect(
    () => () => {
      if (accountSearchDebounceRef.current) {
        clearTimeout(accountSearchDebounceRef.current);
      }
    },
    []
  );

  const closeTopUpModal = () => {
    if (creatingTopUp) return;
    setShowTopUpModal(false);
    setSelectedAccount(null);
    setAccountOptions([]);
    topUpForm.resetFields();
  };

  const openTopUpModal = () => {
    topUpForm.resetFields();
    setSelectedAccount(null);
    setAccountOptions([]);
    setShowTopUpModal(true);
  };

  const handleAccountSearch = (value) => {
    const search = String(value || '').trim();
    setSelectedAccount((prev) => {
      if (!prev) return prev;
      return String(prev.account_no || '').trim() === search ? prev : null;
    });

    if (accountSearchDebounceRef.current) {
      clearTimeout(accountSearchDebounceRef.current);
    }

    if (search.length < 2) {
      setAccountOptions([]);
      setAccountSearchLoading(false);
      return;
    }

    setAccountSearchLoading(true);
    accountSearchDebounceRef.current = setTimeout(async () => {
      try {
        const res = await apiService.searchAccounts({ search, page: 1, per_page: 20 });
        const accounts = safeArray(res?.data?.accounts || res?.data?.data || res?.data);
        setAccountOptions(
          accounts.map((account) => {
            const accountNo = String(account.account_no || '').trim();
            const owner = buildFullName(account);
            return {
              value: accountNo,
              account,
              label: (
                <div className="flex min-w-0 flex-col py-1">
                  <span className="font-mono text-sm font-semibold text-black">{accountNo || EMPTY_VALUE}</span>
                  <span className="truncate text-xs text-slate-500">
                    {owner || account.phone || account.email || EMPTY_VALUE}
                  </span>
                </div>
              ),
            };
          })
        );
      } catch {
        setAccountOptions([]);
      } finally {
        setAccountSearchLoading(false);
      }
    }, 300);
  };

  const handleSubmitTopUp = async (values) => {
    const accountNo = String(values.account_no || selectedAccount?.account_no || '').trim();
    if (!accountNo) {
      await Swal.fire({ icon: 'warning', title: 'Account required', text: 'Please select or enter an account number.' });
      return;
    }

    setCreatingTopUp(true);
    try {
      const res = await apiService.requestTopUpBill({
        bill_amount: Number(values.bill_amount),
        account_no: accountNo,
        tin: values.tin || undefined,
      });

      if (res?.success) {
        const data = res.data || {};
        setBillSuccess({
          type: 'topup',
          title: 'Top-up bill created',
          control_number: extractControlNumber(data),
          bill_amount: data.bill_amount || values.bill_amount,
          bill_id: data.bill_id,
          message: res.message,
        });
        closeTopUpModal();
        fetchTopUpBills(1, filters.per_page, debouncedSearchTerm);
      } else {
        const outstanding = res?.data?.outstanding_bill;
        await Swal.fire({
          icon: 'error',
          title: 'Could not create top-up',
          html: outstanding?.control_number
            ? `${res?.message || 'Outstanding bill exists.'}<br/><br/><b>Control Number:</b> ${outstanding.control_number}`
            : res?.message || 'Request failed',
        });
      }
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Top-up request error:', err);
      await Swal.fire({ icon: 'error', title: 'Error', text: err?.message || 'Unexpected error' });
    } finally {
      setCreatingTopUp(false);
    }
  };

  const copyToClipboard = async (text) => {
    if (!text) return;
    try {
      await navigator.clipboard.writeText(String(text));
      antMessage.success('Copied');
    } catch {
      antMessage.error('Could not copy');
    }
  };

  const openDetails = (row) => {
    setDetailsBill(row);
    setDetailsOpen(true);
  };

  const closeDetails = () => {
    setDetailsOpen(false);
    setDetailsBill(null);
  };

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'sn',
        width: 80,
        align: 'center',
        render: (_, __, idx) => (
          <span className="text-sm font-medium text-gray-600">
            {(pagination.current_page - 1) * pagination.per_page + idx + 1}
          </span>
        ),
      },
      {
        title: 'Account',
        key: 'account',
        render: (_, r) => <span className="font-mono text-sm">{r.account_display}</span>,
      },
      {
        title: 'Owner',
        key: 'owner',
        render: (_, r) => <span className="text-sm">{r.customer_display}</span>,
      },
      {
        title: 'Amount',
        key: 'amount',
        align: 'right',
        render: (_, r) => <span className="text-sm">{r.amount_display}</span>,
      },
      {
        title: 'Bill Date',
        key: 'bill_date',
        render: (_, r) => <span className="text-sm">{r.bill_date_display}</span>,
      },
      {
        title: 'Control No.',
        key: 'control',
        render: (_, r) => <span className="text-sm">{r.control_display}</span>,
      },
      {
        title: 'Receipt',
        key: 'receipt',
        render: (_, r) => <span className="text-sm">{r.receipt_display}</span>,
      },
      {
        title: 'Status',
        key: 'status',
        width: 130,
        align: 'center',
        render: (_, r) => {
          const status = String(r.status_display || '').toUpperCase();
          const isCancelled = r.is_cancelled_display != null && r.is_cancelled_display !== false;
          const label = isCancelled
            ? 'Cancelled'
            : status === 'PAID'
              ? 'Paid'
              : status === 'PENDING'
                ? 'Pending'
                : status || EMPTY_VALUE;
          const color = isCancelled ? 'red' : status === 'PAID' ? 'green' : status === 'PENDING' ? 'gold' : 'default';

          return (
            <Tag color={color} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {label}
            </Tag>
          );
        },
      },
      {
        title: 'Action',
        key: 'action',
        width: 120,
        align: 'center',
        render: (_, r) => (
          <div className="flex items-center justify-center">
            <button
              type="button"
              className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-white"
              style={{ backgroundColor: BRAND }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
              }}
              onClick={() => openDetails(r)}
            >
              <Eye size={14} />
              View
            </button>
          </div>
        ),
      },
    ],
    [pagination]
  );

  const paginationConfig = useMemo(
    () => ({
      current: pagination.current_page,
      pageSize: pagination.per_page,
      total: pagination.total,
      showTotal: () => null,
      showQuickJumper: false,
      showSizeChanger: true,
      pageSizeOptions: ['10', '15', '25', '50', '100'],
      onChange: (page, pageSize) => {
        const nextPerPage = pageSize ?? filters.per_page;
        setFilters((prev) => ({ ...prev, page, per_page: nextPerPage }));
        fetchTopUpBills(page, nextPerPage, debouncedSearchTerm);
      },
    }),
    [pagination, debouncedSearchTerm, filters.per_page, fetchTopUpBills]
  );

  const refreshList = () => fetchTopUpBills(filters.page, filters.per_page, debouncedSearchTerm);

  return (
    <>
      <div className="space-y-4">
        {error && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-4">
            <div className="flex">
              <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-red-400" />
              <div className="ml-3">
                <h3 className="text-sm font-medium text-red-800">Error</h3>
                <p className="mt-1 text-sm text-red-700">{error}</p>
              </div>
            </div>
          </div>
        )}

        <div className="relative">
          {loading ? (
            <div
              className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
              style={{ top: '4.75rem' }}
            >
              <CollectionLoader />
            </div>
          ) : null}
          <DataTable
            columns={columns}
            data={rows}
            loading={false}
            pagination={paginationConfig}
            rowKey={(r, idx) => r.id ?? r.contr_num ?? r.control_display ?? `topup-${idx}`}
            showSearch={true}
            serverSideSearch={true}
            searchPlaceholder="Search by account number, customer name, control number, or receipt..."
            onSearchChange={(value) => {
              setSearchTerm(value);
            }}
            showRefresh={false}
            rightAction={
              <div className="flex flex-wrap items-center gap-4">
                <button
                  type="button"
                  onClick={refreshList}
                  className="btn-secondary flex items-center space-x-2 px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                  disabled={loading}
                >
                  <ReloadOutlined className="text-gray-700" />
                  <span>Refresh</span>
                </button>

                <button
                  type="button"
                  onClick={openTopUpModal}
                  className="flex items-center space-x-2 rounded-lg px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50"
                  style={{ backgroundColor: BRAND }}
                  onMouseEnter={(e) => {
                    if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
                  }}
                  onMouseLeave={(e) => {
                    if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND;
                  }}
                >
                  <Plus size={16} />
                  <span>Request Top-up</span>
                </button>
              </div>
            }
          />
        </div>
      </div>

      <Modal
        open={showTopUpModal}
        onCancel={closeTopUpModal}
        footer={null}
        width={560}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!creatingTopUp}
        keyboard={!creatingTopUp}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Request Top-up" onClose={closeTopUpModal} />
        <Form
          id="manage-collection-topup-form"
          form={topUpForm}
          layout="vertical"
          onFinish={handleSubmitTopUp}
          requiredMark={false}
          className="px-6 py-5"
        >
          <Form.Item
            name="account_no"
            label={<span className="text-sm font-medium" style={{ color: BRAND }}>Account</span>}
            rules={[{ required: true, message: 'Account is required' }]}
          >
            <AutoComplete
              options={accountOptions}
              onSearch={handleAccountSearch}
              onSelect={(_, option) => {
                const account = option.account || null;
                setSelectedAccount(account);
                topUpForm.setFieldsValue({
                  account_no: account?.account_no || option.value,
                  tin: topUpForm.getFieldValue('tin') || account?.tin || account?.tax_identification_number || '',
                });
              }}
              onChange={(value) => {
                if (selectedAccount && String(selectedAccount.account_no || '').trim() !== String(value || '').trim()) {
                  setSelectedAccount(null);
                }
              }}
              disabled={creatingTopUp}
              filterOption={false}
              placeholder="Search by account number, name, phone..."
              notFoundContent={accountSearchLoading ? <div className="py-3 text-center"><Spin size="small" /></div> : null}
              allowClear
              className="w-full [&_.ant-select-selector]:rounded-md"
            />
          </Form.Item>

          {selectedAccount ? (
            <div className="mb-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Account No</div>
                  <div className="font-mono text-sm text-black">{selectedAccount.account_no || EMPTY_VALUE}</div>
                </div>
                <div>
                  <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Account Name</div>
                  <div className="truncate text-sm text-black">{buildFullName(selectedAccount) || EMPTY_VALUE}</div>
                </div>
              </div>
            </div>
          ) : null}

          <Form.Item
            name="bill_amount"
            label={<span className="text-sm font-medium" style={{ color: BRAND }}>Amount (TZS)</span>}
            rules={[
              { required: true, message: 'Amount is required' },
              { type: 'number', min: 1, message: 'Amount must be at least 1' },
            ]}
          >
            <InputNumber
              size="large"
              min={1}
              step={1000}
              style={{ width: '100%' }}
              placeholder="Enter amount to top up"
              disabled={creatingTopUp}
              formatter={(value) => `${value ?? ''}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
              parser={(value) => (value || '').replace(/[^\d.]/g, '')}
            />
          </Form.Item>

          <Form.Item
            name="tin"
            label={<span className="text-sm font-medium" style={{ color: BRAND }}>TIN (Optional)</span>}
          >
            <Input size="large" placeholder="Tax Identification Number (optional)" disabled={creatingTopUp} />
          </Form.Item>

          <div className="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
            <Button
              type="primary"
              htmlType="submit"
              loading={creatingTopUp}
              icon={<Receipt size={14} />}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
            >
              Request Top-up
            </Button>
            <Button onClick={closeTopUpModal} disabled={creatingTopUp}>Close</Button>
          </div>
        </Form>
      </Modal>

      <Modal
        open={!!billSuccess}
        onCancel={() => setBillSuccess(null)}
        footer={null}
        width={520}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title={billSuccess?.title || 'Bill Created'} onClose={() => setBillSuccess(null)} />
        <div className="px-6 py-6">
          <div className="mb-5 flex flex-col items-center text-center">
            <div className="flex h-14 w-14 items-center justify-center rounded-full" style={{ backgroundColor: '#ECFDF5', color: '#16A34A' }}>
              <CheckCircle2 size={28} />
            </div>
            <h3 className="mt-3 text-base font-semibold text-slate-900">Top-up bill created successfully</h3>
            {billSuccess?.message && (
              <p className="mt-1 max-w-sm text-xs text-slate-500">{billSuccess.message}</p>
            )}
          </div>

          <div className="space-y-3 rounded-md border border-slate-200 bg-slate-50 p-4">
            <div>
              <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Control Number</div>
              <div className="mt-1 flex items-center gap-2">
                <span className="font-mono text-lg font-bold tracking-wider text-black">
                  {billSuccess?.control_number || EMPTY_VALUE}
                </span>
                {billSuccess?.control_number ? (
                  <Button size="small" icon={<Copy size={12} />} onClick={() => copyToClipboard(billSuccess.control_number)}>
                    Copy
                  </Button>
                ) : null}
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3 border-t border-slate-200 pt-1">
              <div>
                <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Amount (TZS)</div>
                <div className="mt-0.5 font-mono text-sm text-black">
                  {billSuccess?.bill_amount != null ? `TZS ${Number(billSuccess.bill_amount).toLocaleString()}` : EMPTY_VALUE}
                </div>
              </div>
              <div>
                <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Bill ID</div>
                <div className="mt-0.5 font-mono text-sm text-black">
                  {billSuccess?.bill_id || EMPTY_VALUE}
                </div>
              </div>
            </div>
          </div>

          <p className="mt-4 text-center text-xs text-slate-500">
            Use the control number to complete payment via bank or mobile money.
          </p>

          <div className="flex items-center justify-end gap-2 pt-5">
            <Button onClick={() => setBillSuccess(null)}>Close</Button>
          </div>
        </div>
      </Modal>

      <PrepaymentBillDetailsModal
        isOpen={detailsOpen}
        bill={detailsBill}
        onClose={closeDetails}
        onActionSuccess={() => fetchTopUpBills(filters.page, filters.per_page, debouncedSearchTerm)}
      />
    </>
  );
}

