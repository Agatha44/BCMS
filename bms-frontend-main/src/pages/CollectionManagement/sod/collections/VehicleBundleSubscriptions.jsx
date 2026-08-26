import {useCallback, useEffect, useMemo, useState} from 'react';
import {Button, Tag} from 'antd';
import {Pencil} from 'lucide-react';

import DataTable from '../../../../common/data/DataTable.jsx';
import BundleTransferModal from '../../../../common/components/modals/BundleTransferModal.jsx';
import {apiService} from '../../../../services/api.jsx';
import CollectionLoader from '../../components/CollectionLoader.jsx';
import CollectionTabShell from '../components/CollectionTabShell.jsx';
import {
    defaultBundleSubscriptionPagination,
    extractServerPagination,
    resolveSubscriptionListPayload,
} from '../../../../common/components/transactions/bundles/bundleSubscriptionListUtils.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const defaultPagination = defaultBundleSubscriptionPagination;

const formatBundleDate = (value) => {
    if (!value) return EMPTY_VALUE;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d
        .toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        })
        .replace(',', '');
};

/**
 * Lists all bundle subscriptions on load (legacy behaviour) with SoD styling
 * and edit transfer.
 */
export default function VehicleBundleSubscriptions() {
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [pagination, setPagination] = useState(defaultPagination);

    const [showBundleTransferModal, setShowBundleTransferModal] = useState(false);
    const [selectedSubscription, setSelectedSubscription] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');

    const fetchBundleSubscriptions = useCallback(async (page, perPage, search = '') => {
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
                ((requestPayload) =>
                    apiService.getBundleSubscriptions?.(
                        requestPayload?.plate_no || '',
                        requestPayload
                    ));

            if (typeof getSubscriptions !== 'function') {
                throw new Error('Bundle subscriptions API method is not available');
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
    }, []);

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
                    <span className="text-sm font-medium text-slate-600">
                        {(pagination.current_page - 1) * pagination.per_page + index + 1}
                    </span>
                ),
            },
            {
                title: 'Customer',
                key: 'customer',
                render: (_, r) => (
                    <span className="text-sm text-slate-900">
                        {r.customer || r.customer_name || r.customer_fullname || EMPTY_VALUE}
                    </span>
                ),
            },
            {
                title: 'Plate Number',
                key: 'plate_no',
                render: (_, r) => (
                    <span className="font-mono text-sm text-slate-900">
                        {((r.plate_no || '').trim().toUpperCase()) || EMPTY_VALUE}
                    </span>
                ),
            },
            {
                title: 'Start Date',
                key: 'start_date',
                render: (_, r) => (
                    <span className="text-sm text-slate-700">
                        {formatBundleDate(r.start_date || r.start_time)}
                    </span>
                ),
            },
            {
                title: 'Expire Date',
                key: 'expire_date',
                render: (_, r) => (
                    <span className="text-sm text-slate-700">
                        {formatBundleDate(r.expire_date || r.end_date || r.expire_time)}
                    </span>
                ),
            },
            {
                title: 'Bundle',
                key: 'bundle',
                render: (_, r) => (
                    <span className="text-sm font-medium text-slate-900">
                        {r.bundle ||
                            r.bundle_name ||
                            r.bundle_type ||
                            r.bundle_description ||
                            EMPTY_VALUE}
                    </span>
                ),
            },
            {
                title: 'Status',
                key: 'status',
                width: 130,
                align: 'center',
                render: (_, r) => {
                    const status = (r.status || r.bundle_status || EMPTY_VALUE).toString();
                    const normalized = status.toLowerCase();
                    const normalizedKey = normalized.replace(/\s+/g, '_');
                    const color =
                        normalizedKey === 'active' || normalizedKey === 'paid' || normalizedKey === '1'
                            ? 'green'
                            : normalizedKey === 'inactive' || normalizedKey === 'in_active' || normalizedKey === '2'
                              ? 'red'
                              : normalizedKey === 'expired' || normalizedKey === 'cancelled' || normalizedKey === '3'
                                ? 'red'
                                : normalizedKey === 'pending' || normalizedKey === 'unpaid' || normalizedKey === '0'
                                  ? 'gold'
                                  : 'default';
                    return (
                        <Tag color={color} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
                            {status}
                        </Tag>
                    );
                },
            },
            {
                title: 'Actions',
                key: 'actions',
                align: 'center',
                width: 110,
                render: (_, row) => (
                    <div className="flex justify-center">
                        <Button
                            size="small"
                            type="primary"
                            icon={<Pencil size={14} />}
                            onClick={() => {
                                setSelectedSubscription(row);
                                setShowBundleTransferModal(true);
                            }}
                            style={{backgroundColor: BRAND, borderColor: BRAND}}
                            onMouseEnter={(e) => {
                                e.currentTarget.style.backgroundColor = BRAND_DARK;
                                e.currentTarget.style.borderColor = BRAND_DARK;
                            }}
                            onMouseLeave={(e) => {
                                e.currentTarget.style.backgroundColor = BRAND;
                                e.currentTarget.style.borderColor = BRAND;
                            }}
                        >
                            Edit
                        </Button>
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
                fetchBundleSubscriptions(page, pageSize, debouncedSearchTerm);
            },
        }),
        [pagination, fetchBundleSubscriptions, debouncedSearchTerm]
    );

    const refreshList = () =>
        fetchBundleSubscriptions(pagination.current_page, pagination.per_page, debouncedSearchTerm);

    return (
        <div className="space-y-4">
            <CollectionTabShell
                error={error}
                onRetry={refreshList}
                loading={false}
                empty={rows.length === 0}
                showErrorBanner={Boolean(error) && rows.length === 0 && !loading}
            >
                <div className="relative">
                    {loading ? (
                        <div
                            className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
                            style={{top: '4.75rem'}}
                        >
                            <CollectionLoader />
                        </div>
                    ) : null}
                    <DataTable
                        columns={columns}
                        data={rows}
                        loading={false}
                        pagination={paginationConfig}
                        showSearch
                        showGlobalSearch
                        serverSideSearch={true}
                        onSearchChange={setSearchTerm}
                        searchPlaceholder="Search bundle subscriptions by plate number, customer..."
                        showRefresh={false}
                        rowKey={(r) =>
                            r.id ?? r.subscription_id ?? r.bundle_subscription_id ?? `${r.plate_no}-${r.start_date}`
                        }
                        className="sod-vehicle-bundle-subscriptions-table"
                        rightAction={
                            <button
                                type="button"
                                onClick={refreshList}
                                className="btn-secondary px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                                disabled={loading}
                            >
                                Refresh
                            </button>
                        }
                    />
                </div>
            </CollectionTabShell>

            <BundleTransferModal
                isOpen={showBundleTransferModal}
                subscription={selectedSubscription}
                onClose={() => {
                    setShowBundleTransferModal(false);
                    setSelectedSubscription(null);
                }}
                onSuccess={refreshList}
            />
        </div>
    );
}
