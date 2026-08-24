import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, Loader2, Pencil } from 'lucide-react';
import { ReloadOutlined } from '@ant-design/icons';

import DataTable from '../../../data/DataTable.jsx';
import BundleTransferModal from '../../modals/BundleTransferModal.jsx';
import { apiService } from '../../../../services/api.jsx';
import {
  defaultBundleSubscriptionPagination,
  extractServerPagination,
  resolveSubscriptionListPayload,
} from './bundleSubscriptionListUtils.js';

const defaultPagination = defaultBundleSubscriptionPagination;

const toDateTime = (value) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
  });
};

export default function BundleSubscriptions() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState(defaultPagination);
  const [showBundleTransferModal, setShowBundleTransferModal] = useState(false);
  const [selectedSubscription, setSelectedSubscription] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');

  const fetchBundleSubscriptions = async (
    page = pagination.current_page,
    perPage = pagination.per_page,
    search = debouncedSearchTerm
  ) => {
    setLoading(true);
    setError(null);
    try {
      const payload = {
        page,
        per_page: perPage,
        draw: page,
        start: (page - 1) * perPage,
        length: perPage,
        search,
        search_term: search,
      };

      const getSubscriptions =
        apiService.getTollBundleSubscriptions?.bind(apiService) ??
        ((requestPayload) => apiService.getBundleSubscriptions?.(requestPayload?.plate_no || '', requestPayload));

      if (typeof getSubscriptions !== 'function') {
        throw new Error('Bundle subscriptions API method is not available on apiService');
      }

      const response = await getSubscriptions(payload);
      const resolved = resolveSubscriptionListPayload(response);

      if (resolved.failed) {
        setRows([]);
        setPagination(defaultPagination);
        setError(resolved.message || 'Failed to load bundle subscriptions');
      } else {
        setRows(resolved.rows);
        setPagination(extractServerPagination(resolved.meta, page, perPage));
      }
    } catch (err) {
      setRows([]);
      setError('An error occurred while fetching bundle subscriptions');
      // eslint-disable-next-line no-console
      console.error('Error fetching bundle subscriptions:', err);
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
    fetchBundleSubscriptions(1, pagination.per_page, debouncedSearchTerm);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedSearchTerm]);

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'serial',
        width: 80,
        align: 'center',
        render: (_, __, index) => (
          <span className="text-sm text-gray-600 font-medium">
            {(pagination.current_page - 1) * pagination.per_page + index + 1}
          </span>
        ),
      },
      {
        title: 'Customer',
        key: 'customer',
        render: (_, r) => <span className="text-sm">{r.customer || r.customer_name || r.customer_fullname || '-'}</span>,
      },
      {
        title: 'Plate Number',
        key: 'plate_no',
        render: (_, r) => <span className="font-mono text-sm">{r.plate_no || '-'}</span>,
      },
      {
        title: 'Start Date',
        key: 'start_date',
        render: (_, r) => <span className="text-sm">{r.start_date }</span>,
      },
      {
        title: 'Expire Date',
        key: 'expire_date',
        render: (_, r) => <span className="text-sm">{r.expire_date || '-'}</span>,
      },
      {
        title: 'Bundle',
        key: 'bundle',
        render: (_, r) => (
          <span className="text-sm">{r.bundle || r.bundle_name || r.bundle_type || r.bundle_description || '-'}</span>
        ),
      },
      {
        title: 'Status',
        key: 'status',
        width: 130,
        align: 'center',
        render: (_, r) => {
          const status = (r.status || r.bundle_status || 'N/A').toString();
          const normalized = status.toLowerCase();
          const classes =
            normalized === 'active'
              ? 'bg-green-100 text-green-700'
              : normalized === 'expired'
                ? 'bg-red-100 text-red-700'
                : 'bg-gray-100 text-gray-700';

          return <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${classes}`}>{status}</span>;
        },
      },
      {
        title: 'Action',
        key: 'action',
        width: 120,
        align: 'center',
        render: (_, row) => (
          <button
            type="button"
            onClick={() => {
              setSelectedSubscription(row);
              setShowBundleTransferModal(true);
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
            <Pencil size={16} className="text-white" />
            <span>Edit</span>
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
      hideOnSinglePage: false,
      pageSizeOptions: ['10', '15', '25', '50', '100'],
      onChange: (page, pageSize) => {
        fetchBundleSubscriptions(page, pageSize, debouncedSearchTerm);
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

      <DataTable
        columns={columns}
        data={rows}
        rowKey={(r) => r.id ?? r.subscription_id ?? r.key}
        loading={loading}
        pagination={paginationConfig}
        showSearch={true}
        showGlobalSearch={true}
        serverSideSearch={true}
        onSearchChange={setSearchTerm}
        searchPlaceholder="Search bundle subscriptions by plate number, customer..."
        tableCard
        rightAction={
          <div className="flex items-center gap-4">
            <button
              type="button"
              onClick={() =>
                fetchBundleSubscriptions(pagination.current_page, pagination.per_page, debouncedSearchTerm)
              }
              className="btn-secondary flex items-center space-x-2 px-3 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
              disabled={loading}
            >
              {loading ? <Loader2 size={16} className="animate-spin" /> : <ReloadOutlined className="text-gray-700" />}
              <span>Refresh</span>
            </button>
          </div>
        }
      />

      <BundleTransferModal
        isOpen={showBundleTransferModal}
        subscription={selectedSubscription}
        onClose={() => {
          setShowBundleTransferModal(false);
          setSelectedSubscription(null);
        }}
        onSuccess={() =>
          fetchBundleSubscriptions(pagination.current_page, pagination.per_page, debouncedSearchTerm)
        }
      />
    </div>
  );
}

