import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertCircle, CheckCircle2, Copy } from 'lucide-react';
import { Button, Modal, Tag, message as antMessage } from 'antd';
import PropTypes from 'prop-types';

import DataTable from '../../../common/data/DataTable.jsx';
import CreateAdvertBillModal from '../../../common/components/modals/CreateAdvertBillModal.jsx';
import AdvertBillDetailsModal from '../../../common/components/transactions/adverts/AdvertBillDetailsModal.jsx';
import CollectionLoader from '../components/CollectionLoader.jsx';
import { apiService } from '../../../services/api.jsx';
import { formatMoney } from '../../../common/utils/numberFormat.js';
const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const safeArray = (v) => (Array.isArray(v) ? v : []);

const extractRows = (payload) => {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  if (Array.isArray(payload.data?.data)) return payload.data.data;
  const legacy = payload.bills || payload.records || payload.items;
  return Array.isArray(legacy) ? legacy : [];
};

const extractPagination = (payload, requestedPage, requestedPerPage) => {
  const currentPage =
    Number(payload?.page) ||
    Number(payload?.pagination?.current_page) ||
    Number(payload?.current_page) ||
    Number(requestedPage) ||
    1;
  const perPage =
    Number(payload?.length) ||
    Number(payload?.pagination?.per_page) ||
    Number(payload?.per_page) ||
    Number(requestedPerPage) ||
    10;
  // Prefer filtered count when searching (DataTables-style payloads).
  const total =
    Number(payload?.recordsFiltered) ||
    Number(payload?.pagination?.total) ||
    Number(payload?.total) ||
    Number(payload?.recordsTotal) ||
    0;
  const lastPage = Math.max(1, Math.ceil(total / perPage));
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

const formatDate = (value) => {
  if (!value) return EMPTY_VALUE;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

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

const hasPaymentReceipt = (value) => {
  const receipt = String(value ?? '').trim();
  if (!receipt || receipt === '-' || receipt === 'N/A') return false;
  const upper = receipt.toUpperCase();
  return !upper.startsWith('CANC') && !upper.startsWith('BILL FAILED') && !upper.startsWith('BILL EXPIRED');
};

const normalizeRow = (row = {}) => {
  const customer = row.payer_name ?? row.cust_name ?? row.customer ?? row.customer_name ?? '-';
  const control = row.contr_num ?? row.control_number ?? row.control_num ?? '-';
  const receiptRaw = row.psp_receipt_num ?? row.receipt_number ?? row.receipt ?? '';
  const paid = hasPaymentReceipt(receiptRaw);
  const isCancelled =
    !paid &&
    (Number(row.is_cancelled) === 1 || String(row.bill_status || '').toUpperCase() === 'CANCELLED');
  const receipt = paid ? receiptRaw : '-';
  const amount = row.bill_amount ?? row.amount ?? row.amount_collected ?? row.amountCollected;
  const status = paid
    ? 'PAID'
    : isCancelled
      ? 'CANCELLED'
      : String(row.bill_status ?? row.status ?? row.status_text ?? '').toUpperCase();

  return {
    ...row,
    customer_display: customer,
    control_display: control,
    receipt_display: receipt,
    amount_display: formatMoney(amount),
    status_display: status,
    is_cancelled_display: isCancelled,
    generated_at_display: formatDate(row.bill_generated_at ?? row.bill_gen_at),
  };
};

export default function AdvertsBilling() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [billSuccess, setBillSuccess] = useState(null);
  const [detailsBill, setDetailsBill] = useState(null);
  const [detailsOpen, setDetailsOpen] = useState(false);

  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0,
  });

  const [filters, setFilters] = useState({
    search: '',
    page: 1,
    per_page: 10,
  });

  const fetchBills = useCallback(async (page = filters.page, perPage = filters.per_page, search = filters.search) => {
    setLoading(true);
    setError(null);
    try {
      const payload = {
        page,
        per_page: perPage,
        search: search || undefined,
      };

      const resp = await apiService.getAdvertBills(payload);
      if (!resp.success) {
        setRows([]);
        setError(resp.message || 'Failed to load advert bills');
        return;
      }

      const data = resp.data || {};
      const extracted = safeArray(extractRows(data)).map(normalizeRow);
      setRows(extracted);
      setPagination(extractPagination(data, page, perPage));
    } catch (e) {
      setRows([]);
      setError('An error occurred while loading advert bills');
      // eslint-disable-next-line no-console
      console.error(e);
    } finally {
      setLoading(false);
    }
  }, [filters.page, filters.per_page, filters.search]);

  useEffect(() => {
    fetchBills();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

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
        title: 'Customer',
        key: 'customer',
        render: (_, r) => <span className="text-sm">{r.customer_display}</span>,
      },
      {
        title: 'Control No.',
        key: 'control',
        render: (_, r) => <span className="font-mono text-sm">{r.control_display}</span>,
      },
      {
        title: 'Receipt',
        key: 'receipt',
        render: (_, r) => <span className="font-mono text-sm">{r.receipt_display}</span>,
      },
      {
        title: 'Amount',
        key: 'amount',
        align: 'right',
        money: false,
        render: (_, r) => <span className="text-sm">{r.amount_display}</span>,
      },
      {
        title: 'Bill Date',
        key: 'date',
        render: (_, r) => <span className="text-sm">{r.generated_at_display}</span>,
      },
      {
        title: 'Status',
        key: 'status',
        align: 'center',
        render: (_, r) => {
          const status = String(r.status_display || '').toUpperCase();
          const label =
            status === 'CANCELLED'
              ? 'Cancelled'
              : status === 'PAID'
                ? 'PAID'
                : status === 'PENDING' || status === '1'
                  ? 'Pending'
                  : status || EMPTY_VALUE;
          const color = status === 'CANCELLED' ? 'red' : status === 'PAID' ? 'green' : 'gold';

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
        fetchBills(page, nextPerPage, filters.search);
      },
    }),
    [pagination, filters.search, filters.per_page, fetchBills]
  );

  const refreshList = () => fetchBills(filters.page, filters.per_page, filters.search);

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
            rowKey={(r, idx) => r.id ?? r.contr_num ?? r.control_display ?? `advert-${idx}`}
            showSearch={true}
            showGlobalSearch={true}
            searchPlaceholder="Search advert bills by customer, control, receipt..."
            serverSideSearch={true}
            onSearchChange={(value) => {
              setFilters((prev) => ({ ...prev, search: value, page: 1 }));
              fetchBills(1, filters.per_page, value);
            }}
            showRefresh={false}
            rightAction={
              <div className="flex flex-wrap items-center gap-4">
                <button
                  type="button"
                  onClick={refreshList}
                  className="btn-secondary px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                  disabled={loading}
                >
                  Refresh
                </button>

                <button
                  type="button"
                  className="flex items-center space-x-2 rounded-lg px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50"
                  style={{ backgroundColor: BRAND }}
                  onMouseEnter={(e) => {
                    if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
                  }}
                  onMouseLeave={(e) => {
                    if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND;
                  }}
                  onClick={() => setShowCreateModal(true)}
                >
                  <span>Bill</span>
                </button>
              </div>
            }
          />
        </div>
      </div>

      <CreateAdvertBillModal
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        onCreated={(resp) => {
          setBillSuccess({
            title: 'Advert bill created',
            control_number: resp?.data?.control_number,
            bill_amount: resp?.data?.bill_amount,
            bill_id: resp?.data?.bill_id,
            message: resp?.message,
          });
          fetchBills(1, filters.per_page, filters.search);
        }}
      />

      <AdvertBillDetailsModal
        isOpen={detailsOpen}
        bill={detailsBill}
        onClose={closeDetails}
        onActionSuccess={() => fetchBills(filters.page, filters.per_page, filters.search)}
      />

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
            <h3 className="mt-3 text-base font-semibold text-slate-900">Advert bill created successfully</h3>
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
    </>
  );
}

