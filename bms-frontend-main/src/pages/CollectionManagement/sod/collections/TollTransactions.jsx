import {useEffect, useMemo, useState} from 'react';
import {AlertCircle, Download} from 'lucide-react';
import {ReloadOutlined} from '@ant-design/icons';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import {saveAs} from 'file-saver';

import DataTable from '../../../../common/data/DataTable.jsx';
import CollectionLoader from '../../components/CollectionLoader.jsx';
import {tollService} from '../../../../services/tollService.js';
import {formatMoney} from '../../../../common/utils/numberFormat.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const extractRowsAndPagination = (payload) => {
    if (!payload) return {rows: [], pagination: null};

    if (Array.isArray(payload)) {
        return {rows: payload, pagination: null};
    }

    const candidateKeys = ['transactions', 'passages', 'records', 'items', 'data', 'rows'];
    let rows = [];
    for (const key of candidateKeys) {
        if (Array.isArray(payload[key])) {
            rows = payload[key];
            break;
        }
    }

    const pagination = payload.pagination || null;
    return {rows, pagination};
};

export default function TollTransactions() {
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0,
    });

    const [filters, setFilters] = useState({
        search: '',
        per_page: 15,
        page: 1,
    });

    const [isInitialLoad, setIsInitialLoad] = useState(true);
    const [exporting, setExporting] = useState(false);

    const fetchTollTransactions = async () => {
        setLoading(true);
        setError(null);
        try {
            const payload = {
                page: filters.page,
                per_page: filters.per_page,
                search: filters.search || undefined,
            };

            const response = await tollService.getTollTransactions(payload);
            if (response.success && response.data) {
                const {rows: extractedRows, pagination: extractedPagination} =
                    extractRowsAndPagination(response.data);
                const finalPagination = extractedPagination || response.pagination || null;
                setRows(extractedRows);

                if (finalPagination) {
                    setPagination(finalPagination);
                } else {
                    setPagination((prev) => ({
                        ...prev,
                        current_page: 1,
                        last_page: 1,
                        total: extractedRows.length,
                        from: extractedRows.length > 0 ? 1 : 0,
                        to: extractedRows.length,
                    }));
                }
            } else {
                setRows([]);
                setError(response.message || 'Failed to load toll transactions');
            }
        } catch (err) {
            setRows([]);
            setError('An error occurred while fetching toll transactions');
            // eslint-disable-next-line no-console
            console.error('Error fetching toll transactions:', err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchTollTransactions();
        setIsInitialLoad(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!isInitialLoad) fetchTollTransactions();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters.page, isInitialLoad]);

    useEffect(() => {
        if (!isInitialLoad && filters.per_page !== 15) fetchTollTransactions();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters.per_page, isInitialLoad]);

    useEffect(() => {
        const timeoutId = setTimeout(() => {
            if (!isInitialLoad && filters.search !== undefined) {
                fetchTollTransactions();
            }
        }, 500);
        return () => clearTimeout(timeoutId);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters.search, isInitialLoad]);

    const handleFilterChange = (key, value) => {
        setFilters((prev) => ({
            ...prev,
            [key]: value,
            page: key === 'page' ? value : 1,
        }));
    };

    const handleSearch = (searchTerm) => handleFilterChange('search', searchTerm);

    const exportToExcel = async () => {
        setExporting(true);
        try {
            const exportData = rows.map((r, index) => ({
                'Serial No.': index + 1,
                'Plate No.': r.plate_no || '',
                'Lane No.': r.lane_id || '',
                'Pass Time': r.created_at || '',
                'Receipt No.': r.receipt_num || '',
                Amount: r.charged_amount ?? '',
                Status: r.status || '',
                'Payment Method': r.trans_type || '',
            }));

            const workbook = XLSX.utils.book_new();
            const worksheet = XLSX.utils.json_to_sheet(exportData);
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Toll Transactions');

            const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
            const filename = `toll_transactions_${timestamp}.xlsx`;
            const excelBuffer = XLSX.write(workbook, {bookType: 'xlsx', type: 'array'});
            saveAs(
                new Blob([excelBuffer], {
                    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                }),
                filename
            );

            await Swal.fire({
                icon: 'success',
                title: 'Export Successful!',
                text: `Exported to ${filename}`,
                timer: 2500,
                showConfirmButton: false,
            });
        } catch (err) {
            // eslint-disable-next-line no-console
            console.error('Export error:', err);
            await Swal.fire({
                icon: 'error',
                title: 'Export Failed',
                text: 'An error occurred while exporting. Please try again.',
            });
        } finally {
            setExporting(false);
        }
    };

    const columns = useMemo(
        () => [
            {
                title: 'S/N',
                key: 'serial',
                width: 80,
                align: 'center',
                render: (_, __, idx) => {
                    const currentPage = pagination?.current_page || 1;
                    const perPage = filters.per_page || 15;
                    return (
                        <span className="text-sm font-medium text-gray-600">
                            {(currentPage - 1) * perPage + idx + 1}
                        </span>
                    );
                },
            },
            {
                title: 'Plate Number',
                dataIndex: 'plate_no',
                key: 'plate_no',
                searchable: true,
                render: (_, r) => (
                    <span className="font-mono text-sm">{(r.plate_no || '').trim() || EMPTY_VALUE}</span>
                ),
            },
            {
                title: 'Lane No.',
                dataIndex: 'lane_id',
                key: 'lane_id',
                searchable: true,
                render: (_, r) => (
                    <span className="font-mono text-sm">{(r.lane_id || '').trim() || EMPTY_VALUE}</span>
                ),
            },
            {
                title: 'Pass Time',
                key: 'pass_time',
                render: (_, r) => <span className="text-sm">{r.created_at || EMPTY_VALUE}</span>,
            },
            {
                title: 'Receipt No.',
                key: 'receipt_num',
                render: (_, r) => (
                    <span className="font-mono text-sm">{r.receipt_num || EMPTY_VALUE}</span>
                ),
            },
            {
                title: 'Amount',
                key: 'charged_amount',
                align: 'right',
                money: false,
                render: (_, r) => (
                    <span className="text-sm">
                        {r.charged_amount != null && r.charged_amount !== ''
                            ? formatMoney(r.charged_amount)
                            : EMPTY_VALUE}
                    </span>
                ),
            },
            {
                title: 'Payment Method',
                key: 'trans_type',
                render: (_, r) => <span className="text-sm">{r.trans_type || EMPTY_VALUE}</span>,
            },
        ],
        [filters.per_page, pagination]
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
                if (pageSize !== filters.per_page) handleFilterChange('per_page', pageSize);
                handleFilterChange('page', page);
            },
        }),
        [filters.per_page, pagination]
    );

    return (
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
                        style={{top: '4.75rem'}}
                    >
                        <CollectionLoader />
                    </div>
                ) : null}
                <DataTable
                    columns={columns}
                    data={rows || []}
                    loading={false}
                    pagination={paginationConfig}
                    rowKey={(r, idx) => r.id ?? r.receipt_num ?? `${r.plate_no}-${idx}`}
                    onSearchChange={handleSearch}
                    showSearch
                    showGlobalSearch
                    searchPlaceholder="Search transactions by plate number, receipt..."
                    showRefresh={false}
                    rightAction={
                        <div className="flex flex-wrap items-center gap-4">
                            <button
                                type="button"
                                onClick={fetchTollTransactions}
                                className="btn-secondary flex items-center space-x-2 px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                                disabled={loading}
                            >
                                <ReloadOutlined className="text-gray-700" />
                                <span>Refresh</span>
                            </button>

                            <button
                                type="button"
                                onClick={exportToExcel}
                                disabled={exporting || rows.length === 0}
                                className="flex items-center space-x-2 rounded-lg px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50"
                                style={{backgroundColor: BRAND}}
                                onMouseEnter={(e) => {
                                    if (!e.currentTarget.disabled) {
                                        e.currentTarget.style.backgroundColor = BRAND_DARK;
                                    }
                                }}
                                onMouseLeave={(e) => {
                                    if (!e.currentTarget.disabled) {
                                        e.currentTarget.style.backgroundColor = BRAND;
                                    }
                                }}
                            >
                                <Download size={16} />
                                <span>{exporting ? 'Exporting...' : 'Export'}</span>
                            </button>
                        </div>
                    }
                />
            </div>
        </div>
    );
}
