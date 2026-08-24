import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertCircle, Eye, Loader2, Plus } from 'lucide-react';
import { ReloadOutlined } from '@ant-design/icons';
import PropTypes from 'prop-types';

import DataTable from '../../../data/DataTable.jsx';
import PostEndOfShiftReceiptModal from '../../modals/PostEndOfShiftReceiptModal.jsx';
import EndOfShiftReceiptPreviewModal from './EndOfShiftReceiptPreviewModal.jsx';
import CollectionLoader from '../../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../../../services/api.jsx';
import { formatMoney } from '../../../utils/numberFormat.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const safeArray = (v) => (Array.isArray(v) ? v : []);

const extractRows = (payload) => {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  if (Array.isArray(payload.data?.data)) return payload.data.data;
  return [];
};

const extractPagination = (payload, requestedPage, requestedPerPage) => {
  const currentPage = Number(payload?.current_page) || Number(requestedPage) || 1;
  const perPage = Number(payload?.per_page) || Number(requestedPerPage) || 10;
  const total =
    Number(payload?.recordsFiltered) ||
    Number(payload?.total) ||
    Number(payload?.recordsTotal) ||
    0;
  return { current_page: currentPage, last_page: Math.max(1, Math.ceil(total / perPage)), per_page: perPage, total };
};

const formatDate = (value) => {
  if (!value) return EMPTY_VALUE;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

export default function EndOfShiftDetails({ hideBreadcrumb = false }) {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showPostModal, setShowPostModal] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewReceipt, setPreviewReceipt] = useState(null);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, per_page: 10, total: 0 });
  const [filters, setFilters] = useState({ search: '', page: 1, per_page: 10 });

  const fetchReceipts = useCallback(async (page = filters.page, perPage = filters.per_page, search = filters.search) => {
    setLoading(true);
    setError(null);
    try {
      const resp = await apiService.getEndOfShiftReceipts({ page, per_page: perPage, search: search || undefined });
      if (!resp.success) {
        setRows([]);
        setError(resp.message || 'Failed to load end of shift receipts');
        return;
      }
      const data = resp.data || {};
      setRows(safeArray(extractRows(data)));
      setPagination(extractPagination(data, page, perPage));
    } catch (e) {
      setRows([]);
      setError('An error occurred while loading end of shift receipts');
      console.error(e);
    } finally {
      setLoading(false);
    }
  }, [filters.page, filters.per_page, filters.search]);

  useEffect(() => {
    fetchReceipts();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

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
      { title: 'Shift Date', key: 'shift_date', render: (_, r) => <span className="text-sm">{formatDate(r.shift_date)}</span> },
      { title: 'Shift', key: 'shift_name', render: (_, r) => <span className="text-sm font-medium">{r.shift_name ?? EMPTY_VALUE}</span> },
      { title: 'Receipt No.', key: 'receipt_number', render: (_, r) => <span className="font-mono text-sm">{r.receipt_number ?? EMPTY_VALUE}</span> },
      { title: 'Bank Date', key: 'bank_date', render: (_, r) => <span className="text-sm">{formatDate(r.bank_date)}</span> },
      { title: 'Bank Receipt', key: 'bank_receipt', render: (_, r) => <span className="font-mono text-sm">{r.bank_receipt ?? EMPTY_VALUE}</span> },
      {
        title: 'Amount',
        key: 'amount',
        align: 'right',
        money: false,
        render: (_, r) => <span className="text-sm">{formatMoney(r.amount ?? r.bill_amount)}</span>,
      },
      { title: 'Accountant', key: 'accountant', render: (_, r) => <span className="text-sm">{r.accountant ?? EMPTY_VALUE}</span> },
      {
        title: 'Actions',
        key: 'action',
        width: 100,
        align: 'center',
        render: (_, r) => {
          const canView = Boolean(r.receipt_number) && (r.amount ?? r.bill_amount);

          return (
            <button
              type="button"
              className="inline-flex items-center space-x-1 rounded-lg px-3 py-1.5 text-sm text-white disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400"
              style={canView ? { backgroundColor: BRAND } : undefined}
              disabled={!canView}
              title={canView ? 'Preview miscellaneous receipt' : 'Receipt not available'}
              onMouseEnter={(e) => {
                if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
              }}
              onMouseLeave={(e) => {
                if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND;
              }}
              onClick={() => {
                setPreviewReceipt(r);
                setPreviewOpen(true);
              }}
            >
              <Eye size={16} className="text-white" />
              <span>View</span>
            </button>
          );
        },
      },
    ],
    [pagination.current_page, pagination.per_page]
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
        fetchReceipts(page, nextPerPage, filters.search);
      },
    }),
    [pagination, filters.search, filters.per_page, fetchReceipts]
  );

  const refreshList = () => fetchReceipts(filters.page, filters.per_page, filters.search);

  return (
    <div className="space-y-4">
      {!hideBreadcrumb && <div className="text-sm text-gray-600">Home &gt; Transactions &gt; End of Shift &gt; Details</div>}

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
          rowKey={(r, idx) => r.id ?? r.receipt_number ?? `eos-receipt-${idx}`}
          showSearch={true}
          showGlobalSearch={true}
          searchPlaceholder="Search processed shift receipts..."
          serverSideSearch={true}
          onSearchChange={(value) => {
            setFilters((prev) => ({ ...prev, search: value, page: 1 }));
            fetchReceipts(1, filters.per_page, value);
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
                {loading ? <Loader2 size={16} className="animate-spin" /> : <ReloadOutlined className="text-gray-700" />}
                <span>Refresh</span>
              </button>
              <button
                type="button"
                className="flex items-center space-x-2 rounded-lg px-4 py-2 text-white"
                style={{ backgroundColor: BRAND }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = BRAND_DARK;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = BRAND;
                }}
                onClick={() => setShowPostModal(true)}
              >
                <Plus size={16} />
                <span>Post Receipt</span>
              </button>
            </div>
          }
        />
      </div>

      <PostEndOfShiftReceiptModal
        isOpen={showPostModal}
        onClose={() => setShowPostModal(false)}
        onProcessed={() => {
          setShowPostModal(false);
          fetchReceipts(1, filters.per_page, filters.search);
        }}
      />

      <EndOfShiftReceiptPreviewModal
        open={previewOpen}
        receipt={previewReceipt}
        onClose={() => {
          setPreviewOpen(false);
          setPreviewReceipt(null);
        }}
      />
    </div>
  );
}

EndOfShiftDetails.propTypes = {
  hideBreadcrumb: PropTypes.bool,
};
