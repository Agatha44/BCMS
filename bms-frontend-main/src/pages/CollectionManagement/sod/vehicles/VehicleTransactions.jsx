import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {App, Button, Input} from 'antd';
import {ReloadOutlined, SearchOutlined} from '@ant-design/icons';
import {AlertCircle, Eye} from 'lucide-react';

import DataTable from '../../../../common/data/DataTable.jsx';
import CollectionLoader from '../../components/CollectionLoader.jsx';
import {apiService} from '../../../../services/api.jsx';
import VehicleDetailsModal from './VehicleDetailsModal.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

const formatDate = (value) => {
    if (!value) return '-';
    const normalized = typeof value === 'string' ? value.replace(' ', 'T') : value;
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatMoney = (value) => {
    if (value === null || value === undefined || value === '') return '-';
    const amount = Number(value);
    if (Number.isNaN(amount)) return String(value);
    return amount.toLocaleString('en-TZ', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const getShiftLabel = (value) => {
    const shift = Number(value);
    if (shift === 1) return 'Morning';
    if (shift === 2) return 'Afternoon';
    if (shift === 3) return 'Evening';
    return value ?? '-';
};

export default function VehicleTransactions() {
    const {message} = App.useApp();
    const [transactions, setTransactions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const hasLoadedOnceRef = useRef(false);

    const [detailsOpen, setDetailsOpen] = useState(false);
    const [activeVehicleId, setActiveVehicleId] = useState(null);
    const [loadingViewId, setLoadingViewId] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');

    const fetchTransactions = useCallback(async ({silent = false} = {}) => {
        if (!silent) setLoading(true);
        try {
            const response = await apiService.getRecentVehicleTransactions();
            if (response?.success) {
                const data = response.data || {};
                const rows = Array.isArray(data.transactions) ? data.transactions : [];
                setTransactions(rows);
                setError(null);
                hasLoadedOnceRef.current = true;
            } else if (!silent || !hasLoadedOnceRef.current) {
                setTransactions([]);
                setError(response?.message || 'Failed to fetch vehicle transactions');
            }
        } catch (err) {
            // On silent refreshes (e.g. tab refocus), keep prior data and stay quiet.
            if (!silent || !hasLoadedOnceRef.current) {
                setTransactions([]);
                setError(err?.message || 'Failed to fetch vehicle transactions');
            }
        } finally {
            if (!silent) setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchTransactions();
    }, [fetchTransactions]);

    // Auto-refresh silently when the tab becomes visible again so an
    // idle screen never lingers on a stale error after a token refresh.
    useEffect(() => {
        const onVisibility = () => {
            if (document.visibilityState === 'visible') {
                fetchTransactions({silent: true});
            }
        };
        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('focus', onVisibility);
        return () => {
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('focus', onVisibility);
        };
    }, [fetchTransactions]);

    const openVehicleDetails = (transaction) => {
        if (!transaction.vehicle_id) {
            message.warning('This transaction is not linked to a vehicle profile.');
            return;
        }
        setLoadingViewId(transaction.vehicle_id);
        setActiveVehicleId(transaction.vehicle_id);
        setDetailsOpen(true);
        setTimeout(() => setLoadingViewId(null), 250);
    };

    const filteredTransactions = useMemo(() => {
        const q = String(searchTerm || '').trim().toLowerCase();
        if (!q) return transactions;
        return transactions.filter((row) => {
            const haystack = [
                row.plate_number,
                row.body_type_name,
                row.lane_passed,
                row.lane_reference,
                row.trans_type,
                row.receipt_num,
                getShiftLabel(row.shift_id),
            ]
                .map((v) => String(v ?? '').toLowerCase())
                .join(' ');
            return haystack.includes(q);
        });
    }, [transactions, searchTerm]);

    const columns = useMemo(
        () => [
            {
                title: '#',
                key: 'serial',
                width: 70,
                render: (_, __, index) => (
                    <span className="text-sm font-medium text-slate-600">
                        {index + 1}
                    </span>
                ),
            },
            {
                title: 'Plate Number',
                dataIndex: 'plate_number',
                key: 'plate_number',
                render: (value) => (
                    <span className="font-mono text-sm text-slate-900">
                        {(value || '').trim() || '-'}
                    </span>
                ),
            },
            {
                title: 'Body Type',
                dataIndex: 'body_type_name',
                key: 'body_type_name',
                render: (value) => (
                    <span className="text-sm text-slate-700">{value || '-'}</span>
                ),
            },
            {
                title: 'Lane Name',
                key: 'lane_name',
                render: (_, record) => (
                    <span className="text-sm text-slate-700">
                        {record.lane_passed || record.lane_reference || '-'}
                    </span>
                ),
            },
            {
                title: 'Transaction Type',
                dataIndex: 'trans_type',
                key: 'trans_type',
                render: (value) => (
                    <span className="text-sm text-slate-700">{value || '-'}</span>
                ),
            },
            {
                title: 'Charged Amount (TZS)',
                dataIndex: 'charged_amount',
                key: 'charged_amount',
                align: 'right',
                render: (value) => (
                    <span className="font-mono text-sm text-slate-900">
                        {formatMoney(value)}
                    </span>
                ),
            },
            {
                title: 'Receipt',
                dataIndex: 'receipt_num',
                key: 'receipt_num',
                render: (value) => (
                    <span className="font-mono text-sm text-slate-700">{value || '-'}</span>
                ),
            },
            {
                title: 'Shift',
                dataIndex: 'shift_id',
                key: 'shift_id',
                render: (value) => (
                    <span className="text-sm text-slate-700">{getShiftLabel(value)}</span>
                ),
            },
            {
                title: 'Passage Time',
                dataIndex: 'crossed_at',
                key: 'crossed_at',
                render: (value) => (
                    <span className="text-sm text-slate-700">{formatDate(value)}</span>
                ),
            },
            {
                title: 'Actions',
                key: 'actions',
                align: 'center',
                width: 110,
                render: (_, record) => {
                    const hasVehicle = Boolean(record.vehicle_id);
                    const isLoading = loadingViewId === record.vehicle_id;
                    return (
                        <div className="flex justify-center">
                            <button
                                type="button"
                                disabled={!hasVehicle || isLoading}
                                onClick={() => openVehicleDetails(record)}
                                title={hasVehicle ? 'View vehicle details' : 'No linked vehicle profile'}
                                className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-white transition disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400"
                                style={hasVehicle ? {backgroundColor: BRAND} : undefined}
                                onMouseEnter={(e) => {
                                    if (!e.currentTarget.disabled) {
                                        e.currentTarget.style.backgroundColor = BRAND_DARK;
                                    }
                                }}
                                onMouseLeave={(e) => {
                                    if (!e.currentTarget.disabled && hasVehicle) {
                                        e.currentTarget.style.backgroundColor = BRAND;
                                    }
                                }}
                            >
                                <Eye size={13} />
                                View
                            </button>
                        </div>
                    );
                },
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [loadingViewId]
    );

    const showErrorBanner = error && transactions.length === 0 && !loading;

    return (
        <div className="space-y-4">
            <style>{`
                .vehicle-tx-lookup-input.ant-input-affix-wrapper:hover,
                .vehicle-tx-lookup-input .ant-input:hover {
                    border-color: #962E32;
                }
                .vehicle-tx-lookup-input.ant-input-affix-wrapper-focused,
                .vehicle-tx-lookup-input.ant-input-affix-wrapper:focus-within {
                    border-color: #962E32 !important;
                    box-shadow: 0 0 0 3px rgba(150, 46, 50, 0.12) !important;
                }
            `}</style>

            {showErrorBanner && (
                <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                    <div className="flex items-start justify-between gap-3">
                        <div className="flex">
                            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-red-400" />
                            <div className="ml-3">
                                <h3 className="text-sm font-medium text-red-800">
                                    Could not load transactions
                                </h3>
                                <p className="mt-1 text-sm text-red-700">{error}</p>
                            </div>
                        </div>
                        <Button size="small" onClick={() => fetchTransactions()}>
                            Retry
                        </Button>
                    </div>
                </div>
            )}

            <div className="mb-4 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 sm:gap-4">
                <div className="w-full max-w-sm">
                    <Input
                        size="middle"
                        allowClear
                        placeholder="Search by plate, receipt, lane..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        prefix={<SearchOutlined />}
                        className="vehicle-tx-lookup-input"
                    />
                </div>

                <div className="flex flex-wrap items-center justify-end gap-4">
                    <button
                        type="button"
                        onClick={() => fetchTransactions()}
                        className="btn-secondary flex items-center space-x-2 px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                        disabled={loading}
                    >
                        <ReloadOutlined className="text-gray-700" />
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

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
                    data={filteredTransactions}
                    loading={false}
                    pagination={{
                        pageSize: 15,
                        showTotal: () => null,
                        showQuickJumper: false,
                        showSizeChanger: true,
                        pageSizeOptions: ['10', '15', '25', '50', '100'],
                    }}
                    showSearch={false}
                    showRefresh={false}
                    rowKey={(record) =>
                        record.toll_transaction_id ||
                        `${record.plate_number || 'plate'}-${record.crossed_at || 'time'}`
                    }
                    scroll={{x: 1200}}
                    className="sod-vehicle-transactions-table"
                />
            </div>

            <VehicleDetailsModal
                open={detailsOpen}
                vehicleId={activeVehicleId}
                onClose={() => setDetailsOpen(false)}
                onUpdated={() => fetchTransactions({silent: true})}
            />
        </div>
    );
}
