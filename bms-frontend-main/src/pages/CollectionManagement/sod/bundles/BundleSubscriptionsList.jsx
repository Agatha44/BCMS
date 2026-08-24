import { useEffect, useMemo, useState } from 'react';
import { Button, DatePicker, Input, Select, Space, Tag } from 'antd';
import { Filter } from 'lucide-react';

import DataTable from '../../../../common/data/DataTable.jsx';
import CollectionLoader from '../../components/CollectionLoader.jsx';
import { apiService } from '../../../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY = 'N/A';

const BUNDLE_OPTIONS = [
  { value: '1', label: 'Daily' },
  { value: '2', label: 'Weekly' },
  { value: '3', label: 'Monthly' },
];

const STATUS_OPTIONS = [
  { value: '0', label: 'Pending' },
  { value: '1', label: 'Active' },
  { value: '2', label: 'Inactive' },
  { value: '3', label: 'Cancelled' },
];

const formatDate = (v) => {
  if (!v) return EMPTY;
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return String(v);
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const paymentTagColor = (s) => {
  const x = String(s || '').toLowerCase().replace(/\s+/g, '_');
  if (x === 'paid') return 'green';
  if (x === 'pending' || x === 'unpaid' || x === 'not_paid') return 'gold';
  if (x === 'expired') return 'red';
  if (x === 'cancelled') return 'red';
  if (x === 'no_bill') return 'default';
  return 'default';
};

const subscriptionTagColor = (s) => {
  const x = String(s || '').toLowerCase().replace(/\s+/g, '_');
  if (x === '2' || x.includes('inactive') || x === 'in_active') return 'red';
  if (x === '3' || x.includes('cancel')) return 'red';
  if (x === '0' || x.includes('pending')) return 'gold';
  if (x === '1' || x === 'active') return 'green';
  if (x.includes('expired')) return 'red';
  return 'default';
};

export default function BundleSubscriptionsList() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
    from: 0,
    to: 0,
  });

  const [draft, setDraft] = useState({
    account_id: '',
    plate_no: '',
    vehicle_id: '',
    bundle_id: '',
    status: '',
    dateRange: null,
  });

  const [query, setQuery] = useState({
    page: 1,
    per_page: 25,
    sort_by: 'created_at',
    sort_order: 'desc',
    account_id: '',
    plate_no: '',
    vehicle_id: '',
    bundle_id: '',
    status: '',
    date_from: '',
    date_to: '',
  });

  useEffect(() => {
    let cancelled = false;
    const run = async () => {
      setLoading(true);
      setError(null);
      try {
        const params = {
          page: query.page,
          per_page: query.per_page,
          sort_by: query.sort_by,
          sort_order: query.sort_order,
        };
        if (query.account_id) params.account_id = String(query.account_id).trim();
        if (query.plate_no) params.plate_no = String(query.plate_no).trim();
        if (query.vehicle_id) params.vehicle_id = String(query.vehicle_id).trim();
        if (query.bundle_id !== '' && query.bundle_id != null) params.bundle_id = query.bundle_id;
        if (query.status !== '' && query.status != null) params.status = query.status;
        if (query.date_from) params.date_from = query.date_from;
        if (query.date_to) params.date_to = query.date_to;

        const res = await apiService.getBundlePurchases(params);
        if (cancelled) return;
        if (res?.success && res.data) {
          const data = res.data;
          const list = data.purchases ?? data.data ?? [];
          setRows(Array.isArray(list) ? list : []);
          setPagination(
            data.pagination || {
              current_page: 1,
              last_page: 1,
              per_page: query.per_page || 25,
              total: 0,
              from: 0,
              to: 0,
            }
          );
        } else {
          setRows([]);
          setError(res?.message || 'Could not load bundle subscriptions');
        }
      } catch (e) {
        if (!cancelled) {
          setRows([]);
          setError(e?.message || 'Failed to load bundle subscriptions');
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    run();
    return () => {
      cancelled = true;
    };
  }, [
    query.page,
    query.per_page,
    query.sort_by,
    query.sort_order,
    query.account_id,
    query.plate_no,
    query.vehicle_id,
    query.bundle_id,
    query.status,
    query.date_from,
    query.date_to,
  ]);

  const applyFilters = () => {
    let date_from = '';
    let date_to = '';
    if (draft.dateRange && draft.dateRange[0] && draft.dateRange[1]) {
      date_from = draft.dateRange[0].format('YYYY-MM-DD');
      date_to = draft.dateRange[1].format('YYYY-MM-DD');
    }
    setQuery((prev) => ({
      ...prev,
      page: 1,
      account_id: draft.account_id.trim(),
      plate_no: draft.plate_no.trim(),
      vehicle_id: draft.vehicle_id.trim(),
      bundle_id: draft.bundle_id,
      status: draft.status,
      date_from,
      date_to,
    }));
  };

  const resetFilters = () => {
    setDraft({
      account_id: '',
      plate_no: '',
      vehicle_id: '',
      bundle_id: '',
      status: '',
      dateRange: null,
    });
    setQuery((prev) => ({
      ...prev,
      page: 1,
      account_id: '',
      plate_no: '',
      vehicle_id: '',
      bundle_id: '',
      status: '',
      date_from: '',
      date_to: '',
    }));
  };

  const pag = useMemo(
    () => ({
      current: pagination.current_page,
      pageSize: pagination.per_page,
      total: pagination.total,
      showTotal: () => null,
      showQuickJumper: false,
      showSizeChanger: true,
      pageSizeOptions: ['10', '25', '50', '100'],
      onChange: (page, pageSize) => {
        setQuery((prev) => ({
          ...prev,
          page,
          per_page: pageSize || prev.per_page,
        }));
      },
    }),
    [pagination]
  );

  const columns = useMemo(
    () => [
      {
        title: '#',
        key: 'idx',
        width: 56,
        align: 'center',
        render: (_, __, i) => (
          <span className="text-sm font-medium text-slate-600">
            {(pagination.current_page - 1) * (pagination.per_page || 25) + i + 1}
          </span>
        ),
      },
      {
        title: 'Plate number',
        key: 'plate_no',
        render: (_, r) => (
          <span className="font-mono text-sm font-semibold text-black">
            {((r.plate_no || '').trim().toUpperCase()) || EMPTY}
          </span>
        ),
      },
      {
        title: 'Account',
        key: 'account_id',
        render: (_, r) => (
          <span className="font-mono text-sm text-black">{r.account_id || EMPTY}</span>
        ),
      },
      {
        title: 'Bundle',
        key: 'bundle',
        render: (_, r) => (
          <div className="min-w-0">
            <div className="truncate text-sm text-black">{r.bundle_description || EMPTY}</div>
            {r.bundle_id != null && (
              <div className="text-xs text-slate-500">ID: {r.bundle_id}</div>
            )}
          </div>
        ),
      },
      {
        title: 'Start',
        key: 'start_date',
        render: (_, r) => <span className="text-sm text-black">{formatDate(r.start_date)}</span>,
      },
      {
        title: 'Expire',
        key: 'expire_date',
        render: (_, r) => <span className="text-sm text-black">{formatDate(r.expire_date)}</span>,
      },
      {
        title: 'Subscription',
        key: 'sub_status',
        align: 'center',
        render: (_, r) => {
          const label = r.subscription_status_label || String(r.subscription_status ?? EMPTY);
          return (
            <Tag color={subscriptionTagColor(label)} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {label}
            </Tag>
          );
        },
      },
      {
        title: 'Amount (TZS)',
        key: 'amount',
        align: 'right',
        render: (_, r) => {
          const n = Number(r.amount);
          return (
            <span className="block text-right font-mono text-sm text-black">
              {Number.isFinite(n) ? n.toLocaleString() : EMPTY}
            </span>
          );
        },
      },
      {
        title: 'Payment',
        key: 'payment_status',
        align: 'center',
        render: (_, r) => {
          const ps = r.payment_status || 'unknown';
          return (
            <Tag color={paymentTagColor(ps)} className="!m-0 px-2.5 py-0.5 text-xs font-medium uppercase">
              {String(ps).replace(/_/g, ' ')}
            </Tag>
          );
        },
      },
      {
        title: 'Contract',
        key: 'contract_number',
        render: (_, r) => (
          <span className="font-mono text-xs text-black">{r.contract_number || EMPTY}</span>
        ),
      },
    ],
    [pagination.current_page, pagination.per_page]
  );

  return (
    <div className="space-y-4">
      {error && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{error}</div>
      )}

      <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <Filter size={16} className="text-slate-500" />
          <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: BRAND }}>
            Filters
          </span>
        </div>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
          <Input
            allowClear
            placeholder="Account number"
            value={draft.account_id}
            onChange={(e) => setDraft((d) => ({ ...d, account_id: e.target.value }))}
          />
          <Input
            allowClear
            placeholder="Plate (partial)"
            value={draft.plate_no}
            onChange={(e) => setDraft((d) => ({ ...d, plate_no: e.target.value }))}
          />
          <Input
            allowClear
            placeholder="Vehicle ID"
            value={draft.vehicle_id}
            onChange={(e) => setDraft((d) => ({ ...d, vehicle_id: e.target.value }))}
          />
          <Select
            allowClear
            placeholder="Bundle tier"
            options={BUNDLE_OPTIONS}
            value={draft.bundle_id === '' ? undefined : draft.bundle_id}
            onChange={(v) => setDraft((d) => ({ ...d, bundle_id: v ?? '' }))}
            className="w-full"
          />
          <Select
            allowClear
            placeholder="Subscription status"
            options={STATUS_OPTIONS}
            value={draft.status === '' ? undefined : draft.status}
            onChange={(v) => setDraft((d) => ({ ...d, status: v ?? '' }))}
            className="w-full"
          />
          <DatePicker.RangePicker
            className="w-full min-w-0"
            value={draft.dateRange}
            onChange={(v) => setDraft((d) => ({ ...d, dateRange: v }))}
            format="DD/MM/YYYY"
          />
        </div>
        <Space className="mt-3" wrap>
          <Button
            type="primary"
            onClick={applyFilters}
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
            Apply
          </Button>
          <Button onClick={resetFilters}>Reset</Button>
        </Space>
      </div>

      <div className="rounded-md border border-slate-200 bg-white">
        {loading ? (
          <CollectionLoader />
        ) : (
        <DataTable
          columns={columns}
          data={rows}
          loading={false}
          pagination={pag}
          rowKey={(r) => r.bundle_subscription_id ?? r.id ?? `${r.plate_no}-${r.account_id}-${r.start_date}`}
          showSearch={false}
          showRefresh={false}
        />
        )}
      </div>
    </div>
  );
}
