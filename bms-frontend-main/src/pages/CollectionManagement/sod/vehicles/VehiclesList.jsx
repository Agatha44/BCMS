import {useEffect, useMemo, useState} from 'react';
import {App, Input} from 'antd';
import {AlertCircle, Search} from 'lucide-react';

import DataTable from '../../../../common/data/DataTable.jsx';
import CollectionLoader from '../../components/CollectionLoader.jsx';
import {apiService} from '../../../../services/api.jsx';
import VehicleCreateModal from './VehicleCreateModal.jsx';
import VehicleDetailsModal from './VehicleDetailsModal.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

const formatDate = (value) => {
    if (!value) return '-';
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

export default function VehiclesList() {
    const {message} = App.useApp();
    const [vehicles, setVehicles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [isInitialLoad, setIsInitialLoad] = useState(true);

    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0,
    });

    const [filters, setFilters] = useState({
        per_page: 15,
        page: 1,
    });

    const [modalOpen, setModalOpen] = useState(false);
    const [activeVehicleId, setActiveVehicleId] = useState(null);
    const [loadingViewId, setLoadingViewId] = useState(null);

    const [searchTerm, setSearchTerm] = useState('');
    const [searchLoading, setSearchLoading] = useState(false);

    const [createOpen, setCreateOpen] = useState(false);

    const fetchVehicles = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await apiService.getCollectionVehicles({
                page: filters.page,
                per_page: filters.per_page,
            });

            if (response?.success) {
                const data = response.data || {};
                const rows = Array.isArray(data.vehicles) ? data.vehicles : [];
                setVehicles(rows);

                if (data.pagination) {
                    setPagination(data.pagination);
                } else {
                    setPagination((prev) => ({
                        ...prev,
                        current_page: filters.page,
                        last_page: 1,
                        per_page: filters.per_page,
                        total: rows.length,
                        from: rows.length ? 1 : 0,
                        to: rows.length,
                    }));
                }
            } else {
                setVehicles([]);
                setError(response?.message || 'Failed to fetch vehicles');
            }
        } catch (err) {
            setVehicles([]);
            setError(err?.message || 'An error occurred while fetching vehicles');
            // eslint-disable-next-line no-console
            console.error('Error fetching vehicles:', err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchVehicles();
        setIsInitialLoad(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!isInitialLoad) fetchVehicles();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters.page, filters.per_page, isInitialLoad]);

    const handleFilterChange = (key, value) => {
        setFilters((prev) => ({
            ...prev,
            [key]: value,
            page: key === 'page' ? value : 1,
        }));
    };

    const openViewModal = (vehicle) => {
        setLoadingViewId(vehicle.id);
        setActiveVehicleId(vehicle.id);
        setModalOpen(true);
        // The modal fetches its own data; clear the loading marker once it's open.
        setTimeout(() => setLoadingViewId(null), 250);
    };

    const closeModal = () => {
        setModalOpen(false);
    };

    const handleLookup = async (value) => {
        const raw = value !== undefined ? value : searchTerm;
        const trimmed = String(raw || '').trim();
        if (!trimmed) return;

        setSearchLoading(true);
        try {
            const response = await apiService.lookupVehicleByPlate(trimmed);
            const vehicleId =
                response?.data?.vehicleID ??
                response?.data?.id ??
                response?.data?.vehicle_id;
            if (response?.success && vehicleId) {
                setActiveVehicleId(vehicleId);
                setModalOpen(true);
            } else {
                message.error(
                    response?.message || `No vehicle found with plate number ${trimmed}`
                );
            }
        } catch (err) {
            const msg = err?.message || '';
            if (/not\s*found/i.test(msg) || err?.status === 404) {
                message.error(`No vehicle found with plate number ${trimmed}`);
            } else {
                message.error(msg || 'Failed to lookup vehicle');
            }
        } finally {
            setSearchLoading(false);
        }
    };

    const columns = useMemo(
        () => [
            {
                title: '#',
                key: 'serial',
                width: 70,
                render: (_, vehicle) => {
                    const currentPage = pagination?.current_page || 1;
                    const perPage = filters.per_page || 15;
                    const idx = vehicles.findIndex((v) => v.id === vehicle.id);
                    const serialNumber = idx >= 0 ? (currentPage - 1) * perPage + idx + 1 : 0;
                    return <span className="text-sm text-gray-600 font-medium">{serialNumber}</span>;
                },
            },
            {
                title: 'Plate No.',
                dataIndex: 'plate_no',
                key: 'plate_no',
                searchable: true,
                render: (v) => (
                    <span className="font-mono text-sm">{(v || '').trim() || '-'}</span>
                ),
            },
            {
                title: 'Body Type',
                dataIndex: 'body_type',
                key: 'body_type',
                searchable: true,
                render: (v) => <span className="text-sm">{v || '-'}</span>,
            },
            {
                title: 'Registration Date',
                dataIndex: 'registration_date',
                key: 'registration_date',
                render: (v) => (
                    <span className="text-sm text-gray-600">{formatDate(v)}</span>
                ),
            },
            {
                title: 'Status',
                dataIndex: 'status',
                key: 'status',
                render: (_, vehicle) => {
                    const isActive =
                        vehicle.status === '1' || vehicle.status === 1 || vehicle.status === true;
                    return (
                        <span
                            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                            }`}
                        >
                            {vehicle.status_label || (isActive ? 'Active' : 'Inactive')}
                        </span>
                    );
                },
            },
            {
                title: 'Exemption',
                dataIndex: 'exemption',
                key: 'exemption',
                render: (_, vehicle) => {
                    const isExempt =
                        vehicle.exemption === '1' ||
                        vehicle.exemption === 1 ||
                        vehicle.exemption === true;
                    return (
                        <span
                            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                isExempt
                                    ? 'bg-[#fff5f5] text-[#962E32]'
                                    : 'bg-gray-100 text-gray-700'
                            }`}
                        >
                            {vehicle.exemption_label || (isExempt ? 'Exempted' : 'Not exempted')}
                        </span>
                    );
                },
            },
            {
                title: 'Actions',
                key: 'actions',
                align: 'center',
                render: (_, vehicle) => (
                    <div className="flex items-center justify-center">
                        <button
                            type="button"
                            onClick={() => openViewModal(vehicle)}
                            disabled={loadingViewId === vehicle.id}
                            className="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium text-white transition disabled:cursor-not-allowed disabled:opacity-50"
                            title="View Vehicle Details"
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
                            View
                        </button>
                    </div>
                ),
            },
        ],
        [vehicles, filters.per_page, loadingViewId, pagination]
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
            <style>{`
                .vehicle-lookup-input .ant-input-affix-wrapper:hover,
                .vehicle-lookup-input .ant-input:hover {
                    border-color: #962E32;
                }
                .vehicle-lookup-input .ant-input-affix-wrapper-focused,
                .vehicle-lookup-input .ant-input-affix-wrapper:focus-within {
                    border-color: #962E32 !important;
                    box-shadow: 0 0 0 3px rgba(150, 46, 50, 0.12) !important;
                }
                .vehicle-lookup-input .ant-input-search-button {
                    background-color: #962E32 !important;
                    border-color: #962E32 !important;
                }
                .vehicle-lookup-input .ant-input-search-button:hover,
                .vehicle-lookup-input .ant-input-search-button:focus {
                    background-color: #7A2326 !important;
                    border-color: #7A2326 !important;
                }
            `}</style>

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

            <div className="mb-4 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 sm:gap-4">
                <div className="w-full max-w-sm">
                    <Input.Search
                        size="middle"
                        allowClear
                        placeholder="Search vehicle by plate number..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        onSearch={handleLookup}
                        loading={searchLoading}
                        enterButton={
                            <span className="inline-flex items-center justify-center">
                                <Search size={16} />
                            </span>
                        }
                        className="vehicle-lookup-input"
                    />
                </div>

                <div className="flex flex-wrap items-center justify-end gap-4">
                    <button
                        type="button"
                        onClick={fetchVehicles}
                        className="btn-secondary flex items-center px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                        disabled={loading}
                    >
                        Refresh
                    </button>

                    <button
                        type="button"
                        className="flex items-center rounded-lg px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50"
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
                        onClick={() => setCreateOpen(true)}
                    >
                        Add
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
                    data={vehicles || []}
                    loading={false}
                    pagination={paginationConfig}
                    rowKey={(record) => record.id ?? record.uuid}
                    showSearch={false}
                    showRefresh={false}
                    className="sod-vehicles-table"
                />
            </div>

            <VehicleDetailsModal
                open={modalOpen}
                vehicleId={activeVehicleId}
                onClose={closeModal}
                onUpdated={fetchVehicles}
            />

            <VehicleCreateModal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                onCreated={() => {
                    setCreateOpen(false);
                    fetchVehicles();
                }}
            />
        </div>
    );
}
