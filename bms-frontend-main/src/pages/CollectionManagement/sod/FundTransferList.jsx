import { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { AlertCircle, ArrowRightLeft, Eye } from 'lucide-react';
import { ReloadOutlined } from '@ant-design/icons';
import { Badge, Tabs, Tag } from 'antd';
import DataTable from '../../../common/data/DataTable.jsx';
import CollectionLoader from '../components/CollectionLoader.jsx';
import { formatMoney } from '../../../common/utils/numberFormat.js';
import { apiService } from '../../../services/api.jsx';
import FundTransferModal from './components/FundTransferModal.jsx';
import FundTransferDetailsModal from './components/FundTransferDetailsModal.jsx';
import { hasFundTransferApproverRole } from './utils/fundTransferUtils.js';
import {
  FUND_TRANSFER_STATUS_TABS,
  STATUS_TAB_AFTER_SUBMIT,
  applyStatusTabToSearchParams,
  getDefaultStatusTab,
  getStatusTabFromSearchParams,
  getTransferStatusTagColor,
  isPendingStatusTab,
  resolveApiStatusFromTab,
} from './utils/fundTransferStatus.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const formatDateTime = (value) => {
  if (!value) return EMPTY_VALUE;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const extractTransfers = (data) => {
  if (!data || !Array.isArray(data.transfers)) return [];
  return data.transfers;
};

const extractPaginationTotal = (data, fallback = 0) => {
  const total = data?.pagination?.total;
  return Number.isFinite(Number(total)) ? Number(total) : fallback;
};

const resolveTransferName = (row, prefix) => row[`${prefix}_account_name`] ?? EMPTY_VALUE;

export default function FundTransferList() {
  const location = useLocation();
  const navigate = useNavigate();
  const selectedRole = useSelector((state) => state.app.selectedRole);
  const isApprover = hasFundTransferApproverRole(selectedRole);

  const [activeStatusTab, setActiveStatusTab] = useState(() => getDefaultStatusTab(isApprover));
  const [pendingCount, setPendingCount] = useState(0);
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [viewModalOpen, setViewModalOpen] = useState(false);
  const [viewTransferId, setViewTransferId] = useState(null);
  const [viewTransferRow, setViewTransferRow] = useState(null);

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
    search: '',
    sort_by: 'created_at',
    sort_order: 'desc',
  });

  const isPendingTab = isPendingStatusTab(activeStatusTab);

  const updateFundTransferSearch = useCallback(
    (mutator) => {
      const params = new URLSearchParams(location.search);
      mutator(params);
      const search = params.toString();
      navigate({ pathname: location.pathname, search: search ? `?${search}` : '' }, { replace: true });
    },
    [location.pathname, location.search, navigate]
  );

  useEffect(() => {
    const params = new URLSearchParams(location.search);
    if (params.get('tab') !== 'fund-transfer') return;

    const fromUrl = getStatusTabFromSearchParams(params);
    const nextTab = fromUrl ?? getDefaultStatusTab(isApprover);

    if (fromUrl == null) {
      updateFundTransferSearch((p) => applyStatusTabToSearchParams(p, nextTab));
    } else if (params.get('view')) {
      updateFundTransferSearch((p) => applyStatusTabToSearchParams(p, nextTab));
    }

    setActiveStatusTab(nextTab);
  }, [isApprover, location.search, updateFundTransferSearch]);

  const fetchPendingCount = useCallback(async () => {
    if (!isApprover) {
      setPendingCount(0);
      return;
    }

    try {
      const res = await apiService.getPendingFundTransfers({
        per_page: 1,
        page: 1,
      });
      if (res?.success && res.data) {
        setPendingCount(extractPaginationTotal(res.data));
      } else {
        setPendingCount(0);
      }
    } catch {
      setPendingCount(0);
    }
  }, [isApprover]);

  const fetchTransfers = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        page: filters.page,
        per_page: filters.per_page,
        sort_by: filters.sort_by,
        sort_order: filters.sort_order,
      };
      if (filters.search?.trim()) params.search = filters.search.trim();

      const res = isPendingTab
        ? await apiService.getPendingFundTransfers(params)
        : await apiService.getFundTransfers({
            ...params,
            status: resolveApiStatusFromTab(activeStatusTab),
          });
      if (res?.success && res.data) {
        const list = extractTransfers(res.data);
        setRows(list);
        setPagination(
          res.data.pagination ?? {
            current_page: 1,
            last_page: 1,
            per_page: filters.per_page,
            total: 0,
          }
        );
        if (isPendingTab && isApprover) {
          setPendingCount(extractPaginationTotal(res.data, list.length));
        }
      } else {
        setRows([]);
        setPagination((prev) => ({ ...prev, current_page: 1, total: 0, from: 0, to: 0 }));
        setError(res?.message || 'Failed to load fund transfers');
      }
    } catch (err) {
      setRows([]);
      setError(err?.message || 'An error occurred while fetching fund transfers');
    } finally {
      setLoading(false);
    }
  }, [
    activeStatusTab,
    filters.page,
    filters.per_page,
    filters.search,
    filters.sort_by,
    filters.sort_order,
    isApprover,
    isPendingTab,
  ]);

  useEffect(() => {
    fetchTransfers();
  }, [fetchTransfers]);

  useEffect(() => {
    fetchPendingCount();
  }, [fetchPendingCount]);

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value, page: key === 'page' ? value : 1 }));
  };

  const handleStatusTabChange = (nextTab) => {
    setActiveStatusTab(nextTab);
    updateFundTransferSearch((params) => applyStatusTabToSearchParams(params, nextTab));
    setFilters((prev) => ({ ...prev, page: 1 }));
  };

  const handleTransferSubmitted = useCallback(() => {
    setActiveStatusTab(STATUS_TAB_AFTER_SUBMIT);
    updateFundTransferSearch((params) => applyStatusTabToSearchParams(params, STATUS_TAB_AFTER_SUBMIT));
    setFilters((prev) => ({ ...prev, page: 1 }));
    fetchPendingCount();
  }, [fetchPendingCount, updateFundTransferSearch]);

  const handleQueueUpdated = useCallback(async () => {
    await fetchTransfers();
    await fetchPendingCount();
  }, [fetchPendingCount, fetchTransfers]);

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
        if (pageSize !== filters.per_page) handleFilterChange('per_page', pageSize);
        handleFilterChange('page', page);
      },
    }),
    [filters.per_page, pagination]
  );

  const openTransferDetail = useCallback((row) => {
    setViewTransferId(row.id);
    setViewTransferRow(row);
    setViewModalOpen(true);
  }, []);

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'serial',
        width: 80,
        align: 'center',
        render: (_, __, idx) => (
          <span className="text-sm font-medium text-gray-600">
            {(pagination.current_page - 1) * (filters.per_page || 15) + idx + 1}
          </span>
        ),
      },
      {
        title: 'From Account',
        key: 'from_account',
        render: (_, row) => (
          <div className="min-w-0">
            <div className="font-mono text-sm">{row.from_account_no ?? EMPTY_VALUE}</div>
            <div className="truncate text-xs text-slate-500">{resolveTransferName(row, 'from')}</div>
          </div>
        ),
      },
      {
        title: 'To Account',
        key: 'to_account',
        render: (_, row) => (
          <div className="min-w-0">
            <div className="font-mono text-sm">{row.to_account_no ?? EMPTY_VALUE}</div>
            <div className="truncate text-xs text-slate-500">{resolveTransferName(row, 'to')}</div>
          </div>
        ),
      },
      {
        title: 'Amount',
        dataIndex: 'amount',
        key: 'amount',
        align: 'right',
        money: false,
        render: (v) => <span className="text-sm">{formatMoney(v)}</span>,
      },
      {
        title: 'Status',
        key: 'status',
        align: 'center',
        render: (_, row) => {
          const label = row.status ?? EMPTY_VALUE;
          return (
            <Tag color={getTransferStatusTagColor(row)} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {label}
            </Tag>
          );
        },
      },
      {
        title: 'Requested By',
        key: 'requested_by',
        render: (_, row) => <span className="text-sm">{row.submitted_by ?? EMPTY_VALUE}</span>,
      },
      {
        title: 'Requested At',
        dataIndex: 'created_at',
        key: 'created_at',
        render: (v, row) => <span className="text-sm">{formatDateTime(row.created_at)}</span>,
      },
      {
        title: 'Action',
        key: 'action',
        width: 120,
        align: 'center',
        render: (_, row) => (
          <button
            type="button"
            onClick={() => openTransferDetail(row)}
            className="inline-flex items-center space-x-1 rounded-lg px-3 py-1.5 text-sm text-white"
            style={{ backgroundColor: BRAND }}
            onMouseEnter={(e) => {
              e.currentTarget.style.backgroundColor = BRAND_DARK;
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
            }}
          >
            <Eye size={16} className="text-white" />
            <span>{isPendingTab && isApprover ? 'Review' : 'View'}</span>
          </button>
        ),
      },
    ],
    [filters.per_page, isApprover, isPendingTab, openTransferDetail, pagination]
  );

  const statusTabItems = useMemo(
    () =>
      FUND_TRANSFER_STATUS_TABS.map((tab) => ({
        key: tab.key,
        label:
          tab.showPendingBadge && isApprover ? (
            <span className="inline-flex items-center gap-2">
              {tab.label}
              <Badge
                count={pendingCount}
                overflowCount={99}
                showZero={false}
                style={{ backgroundColor: BRAND }}
              />
            </span>
          ) : (
            tab.label
          ),
      })),
    [isApprover, pendingCount]
  );

  const tableToolbar = (
    <div className="flex flex-wrap items-center gap-4">
      <button
        type="button"
        onClick={() => {
          fetchTransfers();
          fetchPendingCount();
        }}
        className="btn-secondary flex items-center space-x-2 px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
        disabled={loading}
      >
        <ReloadOutlined className="text-gray-700" />
        <span>Refresh</span>
      </button>
      {!isPendingTab && !isApprover ? (
        <button
          type="button"
          onClick={() => setCreateModalOpen(true)}
          className="flex items-center space-x-2 rounded-lg px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50"
          style={{ backgroundColor: BRAND }}
          onMouseEnter={(e) => {
            if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.backgroundColor = BRAND;
          }}
        >
          <ArrowRightLeft size={16} />
          <span>Fund Transfer</span>
        </button>
      ) : null}
    </div>
  );

  return (
    <div className="space-y-4">
      <Tabs
        activeKey={activeStatusTab}
        onChange={handleStatusTabChange}
        items={statusTabItems}
        tabBarStyle={{ marginBottom: 0 }}
      />

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
          rowKey={(r) => r.id ?? r.transfer_uuid}
          showSearch
          serverSideSearch
          searchPlaceholder="Search by account number or name..."
          onSearchChange={(value) => handleFilterChange('search', value)}
          showRefresh={false}
          rightAction={tableToolbar}
        />
      </div>

      <FundTransferModal
        isOpen={createModalOpen}
        onClose={() => setCreateModalOpen(false)}
        onSubmitted={handleTransferSubmitted}
      />

      <FundTransferDetailsModal
        isOpen={viewModalOpen}
        transferId={viewTransferId}
        initialTransfer={viewTransferRow}
        onUpdated={handleQueueUpdated}
        onClose={() => {
          setViewModalOpen(false);
          setViewTransferId(null);
          setViewTransferRow(null);
        }}
      />
    </div>
  );
}
