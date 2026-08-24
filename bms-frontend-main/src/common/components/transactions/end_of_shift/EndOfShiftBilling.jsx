import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertCircle, Eye, Loader2, Plus } from 'lucide-react';
import { ReloadOutlined } from '@ant-design/icons';
import { Tag } from 'antd';
import PropTypes from 'prop-types';
import Swal from 'sweetalert2';

import DataTable from '../../../data/DataTable.jsx';
import BmsDatePicker, { BmsDatePickerField } from '../../forms/BmsDatePicker.jsx';
import { formatMoney } from '../../../utils/numberFormat.js';
import CreateEndOfShiftBillModal from '../../modals/CreateEndOfShiftBillModal.jsx';
import EndOfShiftBillViewModal from './EndOfShiftBillViewModal.jsx';
import EndOfShiftOrderFormModal from './EndOfShiftOrderFormModal.jsx';
import CollectionLoader from '../../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const inputClassName =
  'w-full min-w-0 border border-slate-300 rounded-lg px-3 py-2 bg-white text-slate-700 text-sm focus:ring-2 focus:ring-[#962E32] focus:border-[#962E32] transition-colors';

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
  const currentPage = Number(payload?.current_page) || Number(requestedPage) || 1;
  const perPage = Number(payload?.per_page) || Number(requestedPerPage) || 10;
  const total =
    Number(payload?.recordsFiltered) ||
    Number(payload?.total) ||
    Number(payload?.recordsTotal) ||
    0;
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  return { current_page: currentPage, last_page: lastPage, per_page: perPage, total };
};

const formatDate = (value) => {
  if (!value) return EMPTY_VALUE;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const normalizeRow = (row = {}) => {
  const control = row.contr_num ?? row.control_number ?? row.control_num ?? EMPTY_VALUE;
  const receipt = row.psp_receipt_num ?? row.receipt_number ?? row.receipt_no ?? EMPTY_VALUE;
  const bankReceipt = row.bank_receipt ?? row.psp_receipt_num ?? EMPTY_VALUE;
  const amount = row.bill_amount ?? row.amount;
  const status = row.bill_status ?? row.status ?? '';
  const isCancelled = row.is_cancelled ?? false;

  return {
    ...row,
    shift_date_display: formatDate(row.shift_date),
    shift_name_display: row.shift_name ?? EMPTY_VALUE,
    control_display: control,
    receipt_display: receipt,
    bank_receipt_display: bankReceipt,
    amount_display: formatMoney(amount),
    status_display: String(status || '').toUpperCase(),
    is_cancelled_display: isCancelled,
  };
};

export default function EndOfShiftBilling({ hideBreadcrumb = false }) {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [shifts, setShifts] = useState([]);
  const [shiftId, setShiftId] = useState('');
  const [shiftDate, setShiftDate] = useState('');
  const [showBillModal, setShowBillModal] = useState(false);
  const [viewBillOpen, setViewBillOpen] = useState(false);
  const [selectedBill, setSelectedBill] = useState(null);
  const [orderFormOpen, setOrderFormOpen] = useState(false);
  const [orderFormBill, setOrderFormBill] = useState(null);
  const [loadingOrderForm, setLoadingOrderForm] = useState(false);

  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  });

  const [filters, setFilters] = useState({ search: '', page: 1, per_page: 10 });

  const fetchBills = useCallback(async (page = filters.page, perPage = filters.per_page, search = filters.search) => {
    setLoading(true);
    setError(null);
    try {
      const resp = await apiService.getEndOfShiftBills({ page, per_page: perPage, search: search || undefined });
      if (!resp.success) {
        setRows([]);
        setError(resp.message || 'Failed to load end of shift bills');
        return;
      }
      const data = resp.data || {};
      setRows(safeArray(extractRows(data)).map(normalizeRow));
      setPagination(extractPagination(data, page, perPage));
    } catch (e) {
      setRows([]);
      setError('An error occurred while loading end of shift bills');
      console.error(e);
    } finally {
      setLoading(false);
    }
  }, [filters.page, filters.per_page, filters.search]);

  useEffect(() => {
    fetchBills();
    apiService.getEndOfShiftShifts().then((resp) => {
      if (resp.success) {
        setShifts(Array.isArray(resp.data) ? resp.data : resp.data?.data ?? []);
      }
    });
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
      { title: 'Shift Date', key: 'shift_date', render: (_, r) => <span className="text-sm">{r.shift_date_display}</span> },
      { title: 'Shift', key: 'shift_name', render: (_, r) => <span className="text-sm font-medium">{r.shift_name_display}</span> },
      { title: 'Control No.', key: 'control', render: (_, r) => <span className="font-mono text-sm">{r.control_display}</span> },
      { title: 'Receipt', key: 'receipt', render: (_, r) => <span className="font-mono text-sm">{r.receipt_display}</span> },
      { title: 'Bank Receipt', key: 'bank_receipt', render: (_, r) => <span className="font-mono text-sm">{r.bank_receipt_display}</span> },
      {
        title: 'Amount',
        key: 'amount',
        align: 'right',
        money: false,
        render: (_, r) => <span className="text-sm">{r.amount_display}</span>,
      },
      {
        title: 'Status',
        key: 'status',
        align: 'center',
        render: (_, r) => {
          const isCancelled = r.is_cancelled_display != null && r.is_cancelled_display !== false;
          const status = isCancelled ? 'CANCELLED' : String(r.status_display || '').toUpperCase();
          const label = isCancelled
            ? 'Cancelled'
            : status === 'PAID'
              ? 'Paid'
              : status === 'PENDING'
                ? 'Pending'
                : status || EMPTY_VALUE;
          const color = isCancelled ? 'red' : status === 'PAID' ? 'green' : 'gold';

          return (
            <Tag color={color} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {label}
            </Tag>
          );
        },
      },
      {
        title: 'Actions',
        key: 'action',
        width: 100,
        align: 'center',
        render: (_, r) => (
          <button
            type="button"
            className="inline-flex items-center space-x-1 rounded-lg px-3 py-1.5 text-sm text-white"
            style={{ backgroundColor: BRAND }}
            onMouseEnter={(e) => {
              e.currentTarget.style.backgroundColor = BRAND_DARK;
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
            }}
            onClick={() => {
              setSelectedBill(r);
              setViewBillOpen(true);
            }}
          >
            <Eye size={16} className="text-white" />
            <span>View</span>
          </button>
        ),
      },
    ],
    [pagination.current_page, pagination.per_page]
  );

  const openOrderForm = async (bill) => {
    if (!bill) return;
    setOrderFormBill(bill);
    setOrderFormOpen(true);
    setLoadingOrderForm(true);
    try {
      const resp = await apiService.getEndOfShiftOrderForm({ id: bill.id });
      if (!resp.success) {
        await Swal.fire({ icon: 'error', title: 'Failed', text: resp.message || 'Failed to fetch order form' });
        setOrderFormOpen(false);
        return;
      }
      setOrderFormBill({ ...bill, ...(resp.data || {}) });
    } finally {
      setLoadingOrderForm(false);
    }
  };

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

  const openBillShift = async () => {
    if (!shiftId || !shiftDate) {
      await Swal.fire({ icon: 'warning', title: 'Required', text: 'Select shift date and shift before billing.' });
      return;
    }
    setShowBillModal(true);
  };

  return (
    <>
      <div className="space-y-4">
        {!hideBreadcrumb && (
          <div className="text-sm text-gray-600">Home &gt; Transactions &gt; End of Shift &gt; Billing</div>
        )}

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

        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
          <p className="mb-3 text-xs font-semibold uppercase tracking-[0.12em]" style={{ color: BRAND }}>
            Bill a shift
          </p>
          <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div className="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
              <BmsDatePickerField label="Shift Date" className="min-w-0">
                <BmsDatePicker value={shiftDate} onChange={setShiftDate} size="large" />
              </BmsDatePickerField>
              <div className="min-w-0">
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Shift
                </label>
                <select value={shiftId} onChange={(e) => setShiftId(e.target.value)} className={inputClassName}>
                  <option value="">Select shift</option>
                  {shifts.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <button
              type="button"
              className="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white transition lg:mb-0"
              style={{ backgroundColor: BRAND }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
              }}
              onClick={openBillShift}
            >
              <Plus size={16} />
              Bill Shift
            </button>
          </div>
        </div>

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
            rowKey={(r, idx) => r.id ?? r.control_display ?? `eos-bill-${idx}`}
            showSearch={true}
            showGlobalSearch={true}
            searchPlaceholder="Search by control number, receipt, shift..."
            serverSideSearch={true}
            onSearchChange={(value) => {
              setFilters((prev) => ({ ...prev, search: value, page: 1 }));
              fetchBills(1, filters.per_page, value);
            }}
            showRefresh={false}
            rightAction={
              <button
                type="button"
                onClick={refreshList}
                className="btn-secondary flex items-center space-x-2 px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                disabled={loading}
              >
                {loading ? <Loader2 size={16} className="animate-spin" /> : <ReloadOutlined className="text-gray-700" />}
                <span>Refresh</span>
              </button>
            }
          />
        </div>
      </div>

      <CreateEndOfShiftBillModal
        isOpen={showBillModal}
        shiftId={shiftId}
        shiftDate={shiftDate}
        onClose={() => setShowBillModal(false)}
        onCreated={() => {
          setShowBillModal(false);
          fetchBills(1, filters.per_page, filters.search);
        }}
      />

      <EndOfShiftBillViewModal
        open={viewBillOpen}
        bill={selectedBill}
        onClose={() => {
          setViewBillOpen(false);
          setSelectedBill(null);
        }}
        onUpdated={() => fetchBills(filters.page, filters.per_page, filters.search)}
        onOpenOrderForm={openOrderForm}
      />

      <EndOfShiftOrderFormModal
        open={orderFormOpen}
        bill={orderFormBill}
        loading={loadingOrderForm}
        onClose={() => {
          setOrderFormOpen(false);
          setOrderFormBill(null);
        }}
      />
    </>
  );
}

EndOfShiftBilling.propTypes = {
  hideBreadcrumb: PropTypes.bool,
};
