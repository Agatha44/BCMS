import { useEffect, useMemo, useState } from 'react';
import { AlertCircle } from 'lucide-react';

import DataTable from '../../../data/DataTable.jsx';
import BundleBillDetailsModal from './BundleBillDetailsModal.jsx';
import RequestBundleModal from '../../../../pages/CollectionManagement/sod/components/RequestBundleModal.jsx';
import BundleBillSuccessModal from '../../../../pages/CollectionManagement/sod/components/BundleBillSuccessModal.jsx';
import CollectionLoader from '../../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../../../services/api.jsx';
import { formatMoney } from '../../../utils/numberFormat.js';
import { formatBillDate } from '../../../utils/dateFormat.js';

/** Supports DataTables-style payload: { recordsTotal, recordsFiltered, data: [...] }. */
const extractRows = (payload) => {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  if (Array.isArray(payload.data?.data)) return payload.data.data;
  const legacy =
    payload.passages || payload.transactions || payload.records || payload.items;
  return Array.isArray(legacy) ? legacy : [];
};

const extractServerPagination = (payload, requestedPage, requestedPerPage) => {
  const currentPage =
    Number(payload?.page) ||
    Number(payload?.pagination?.current_page) ||
    Number(requestedPage) ||
    1;
  const perPage =
    Number(payload?.length) ||
    Number(payload?.pagination?.per_page) ||
    Number(requestedPerPage) ||
    15;
  const total =
    Number(payload?.recordsFiltered) ||
    Number(payload?.recordsTotal) ||
    Number(payload?.pagination?.total) ||
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

const billStatusLabel = (code) => {
  const c = String(code ?? '').trim();
  if (c === '1') return 'Active';
  if (c === '0') return 'Inactive';
  return c || '—';
};

const normalizeBundleBillRow = (row = {}) => ({
  ...row,
  account_display: row.account_no || '-',
  customer_display: row.customer_name || '-',
  plate_no_display: row.plate_no ? String(row.plate_no).trim().toUpperCase() : '-',
  c_number_display: row.control_number ?? '-',
  receipt_display: row.psp_receipt_num || row.receipt_number || '-',
  amount_display: formatMoney(row.bill_amount) ?? '-',
  bill_date_display: formatBillDate(row.bill_generated_at),
  bill_status_display: billStatusLabel(row.bill_status),
  can_cancel: Boolean(row.can_cancel),
  can_repost: Boolean(row.can_repost),
  can_print: Boolean(row.can_print),
});

const defaultPagination = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0,
};

export default function BundleBills() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState(defaultPagination);
  const [searchTerm, setSearchTerm] = useState('');
  const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');
  const [showRequestBundleModal, setShowRequestBundleModal] = useState(false);
  const [billSuccess, setBillSuccess] = useState(null);
  const [detailBill, setDetailBill] = useState(null);
  const [detailModalOpen, setDetailModalOpen] = useState(false);

  const fetchBundleBills = async (
    page = pagination.current_page,
    perPage = pagination.per_page,
    search = debouncedSearchTerm
  ) => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiService.getTollBundleBills({
        page,
        per_page: perPage,
        draw: page,
        start: (page - 1) * perPage,
        length: perPage,
        search,
        search_term: search,
      });
      if (response.success && response.data) {
        const extractedRows = extractRows(response.data);
        const normalizedRows = Array.isArray(extractedRows) ? extractedRows.map(normalizeBundleBillRow) : [];
        setRows(normalizedRows);
        setPagination(extractServerPagination(response.data, page, perPage));
      } else {
        setRows([]);
        setError(response.message || 'Failed to load bundle bills');
      }
    } catch (err) {
      setRows([]);
      setError('An error occurred while fetching bundle bills');
      // eslint-disable-next-line no-console
      console.error('Error fetching bundle bills:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const timeout = setTimeout(() => {
      setDebouncedSearchTerm(searchTerm.trim());
    }, 350);

    return () => clearTimeout(timeout);
  }, [searchTerm]);

  useEffect(() => {
    fetchBundleBills(1, pagination.per_page, debouncedSearchTerm);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedSearchTerm]);

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'serial',
        width: 80,
        align: 'center',
        render: (_, r, index) => (
          <span className="text-sm text-gray-600 font-medium">
            {r.sn || (pagination.current_page - 1) * pagination.per_page + index + 1}
          </span>
        ),
      },
      {
        title: 'Account',
        dataIndex: 'account_display',
        key: 'account',
        render: (v) => <span className="font-mono text-sm">{v}</span>,
      },
      {
        title: 'Customer',
        dataIndex: 'customer_display',
        key: 'customer',
        render: (v) => <span className="text-sm">{v}</span>,
      },
      {
        title: 'Plate Number',
        dataIndex: 'plate_no_display',
        key: 'plate_no',
        render: (v) => <span className="font-mono text-sm">{v}</span>,
      },
      {
        title: 'C-Number',
        dataIndex: 'c_number_display',
        key: 'c_number',
        render: (v) => <span className="font-mono text-sm">{v}</span>,
      },
      {
        title: 'Receipt',
        dataIndex: 'receipt_display',
        key: 'receipt',
        render: (v) => <span className="font-mono text-sm">{v}</span>,
      },
      {
        title: 'Amount',
        dataIndex: 'amount_display',
        key: 'amount',
        align: 'right',
        render: (v) => <span className="text-sm">{v}</span>,
      },
      {
        title: 'Bill Date',
        dataIndex: 'bill_date_display',
        key: 'bill_date',
        render: (v) => <span className="text-sm">{v}</span>,
      },
      {
        title: 'Action',
        key: 'action',
        width: 120,
        align: 'center',
        render: (_, r) => (
          <button
            type="button"
            onClick={() => {
              setDetailBill(r);
              setDetailModalOpen(true);
            }}
            className="px-3 py-1.5 text-white rounded-lg text-sm inline-flex items-center space-x-1"
            style={{ backgroundColor: '#902D30' }}
            onMouseEnter={(e) => {
              e.currentTarget.style.backgroundColor = '#7a2528';
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = '#902D30';
            }}
          >
            View
          </button>
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
      showSizeChanger: true,
      showQuickJumper: true,
      onChange: (page, pageSize) => {
        fetchBundleBills(page, pageSize, debouncedSearchTerm);
      },
    }),
    [pagination, debouncedSearchTerm]
  );

  return (
    <div className="space-y-4">
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex">
            <AlertCircle className="h-5 w-5 text-red-400 mt-0.5" />
            <div className="ml-3">
              <h3 className="text-sm font-medium text-red-800">Error</h3>
              <p className="text-sm text-red-700 mt-1">{error}</p>
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
          rowKey={(r) => r.id ?? r.key}
          loading={false}
          pagination={paginationConfig}
          showSearch={true}
          showGlobalSearch={true}
          serverSideSearch={true}
          onSearchChange={setSearchTerm}
          searchPlaceholder="Search bundle bills by plate number, account, customer..."
          tableCard
          rightAction={
            <div className="flex items-center gap-4">
              <button
                type="button"
                onClick={() =>
                  fetchBundleBills(pagination.current_page, pagination.per_page, debouncedSearchTerm)
                }
                className="btn-secondary px-3 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
                disabled={loading}
              >
                Refresh
              </button>

              <button
                type="button"
                onClick={() => setShowRequestBundleModal(true)}
                className="px-4 py-2 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2"
                style={{ backgroundColor: '#902D30' }}
                onMouseEnter={(e) => {
                  if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = '#7a2528';
                }}
                onMouseLeave={(e) => {
                  if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = '#902D30';
                }}
              >
                <span>Bill</span>
              </button>
            </div>
          }
        />
      </div>

      <RequestBundleModal
        open={showRequestBundleModal}
        onClose={() => setShowRequestBundleModal(false)}
        title="Bill"
        submitLabel="Request Bundle"
        showAccountSummary={false}
        onSuccess={(success) => {
          setBillSuccess(success);
          fetchBundleBills(pagination.current_page, pagination.per_page, debouncedSearchTerm);
        }}
      />

      <BundleBillSuccessModal
        open={!!billSuccess}
        billSuccess={billSuccess}
        onClose={() => setBillSuccess(null)}
      />

      <BundleBillDetailsModal
        isOpen={detailModalOpen}
        bill={detailBill}
        onClose={() => {
          setDetailModalOpen(false);
          setDetailBill(null);
        }}
        onActionSuccess={() =>
          fetchBundleBills(pagination.current_page, pagination.per_page, debouncedSearchTerm)
        }
      />
    </div>
  );
}

