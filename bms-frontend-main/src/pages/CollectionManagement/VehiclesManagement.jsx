import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, Eye, Plus, Link2 } from 'lucide-react';
import { ReloadOutlined } from '@ant-design/icons';
import { App } from 'antd';
import { useSearchParams } from 'react-router-dom';

import DataTable from '../../common/data/DataTable.jsx';
import CollectionLoader from './components/CollectionLoader.jsx';
import { apiService } from '../../services/api.jsx';
import AddNewVehicleModal from '../../common/components/modals/addNewVehicleModal.jsx';
import AssociateVehicleWithAccountModal from '../../common/components/modals/associateVehicleWithAccountModal.jsx';
import VehicleDetailsModal from './sod/vehicles/VehicleDetailsModal.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

export default function VehiclesManagement() {
  const { message, modal } = App.useApp();
  const [searchParams, setSearchParams] = useSearchParams();
  const [vehicles, setVehicles] = useState([]);
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

  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showAssociateModal, setShowAssociateModal] = useState(false);
  const [detailsModalOpen, setDetailsModalOpen] = useState(false);
  const [activeVehicleId, setActiveVehicleId] = useState(null);
  const [loadingViewId, setLoadingViewId] = useState(null);
  const [loadingStatusId, setLoadingStatusId] = useState(null);

  const openViewModal = (vehicle) => {
    setLoadingViewId(vehicle.id);
    setActiveVehicleId(vehicle.id);
    setDetailsModalOpen(true);
    setTimeout(() => setLoadingViewId(null), 250);
  };

  const closeDetailsModal = () => {
    setDetailsModalOpen(false);
    setActiveVehicleId(null);
  };

  const fetchVehicles = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiService.getVehicles({
        page: filters.page,
        per_page: filters.per_page,
        search: filters.search || undefined,
      });

      if (response.success) {
        // API shape varies; support common shapes
        const rows = response.data?.vehicles || response.data || [];
        setVehicles(Array.isArray(rows) ? rows : []);

        const p = response.data?.pagination || response.pagination || response.pagination;
        if (p) {
          setPagination(p);
        } else if (response.pagination) {
          setPagination(response.pagination);
        } else {
          setPagination((prev) => ({
            ...prev,
            current_page: 1,
            last_page: 1,
            total: Array.isArray(rows) ? rows.length : 0,
            from: Array.isArray(rows) && rows.length ? 1 : 0,
            to: Array.isArray(rows) ? rows.length : 0,
          }));
        }
      } else {
        setVehicles([]);
        setError(response.message || 'Failed to fetch vehicles');
      }
    } catch (err) {
      setVehicles([]);
      setError('An error occurred while fetching vehicles');
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
    const openId = searchParams.get('open');
    if (!openId) return;
    setActiveVehicleId(Number(openId));
    setDetailsModalOpen(true);
    setSearchParams({}, { replace: true });
  }, [searchParams, setSearchParams]);

  useEffect(() => {
    if (!isInitialLoad) fetchVehicles();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.page, isInitialLoad]);

  useEffect(() => {
    if (!isInitialLoad && filters.per_page !== 15) fetchVehicles();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.per_page, isInitialLoad]);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (!isInitialLoad && filters.search !== undefined) fetchVehicles();
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

  const toggleVehicleStatus = (vehicleId, currentStatus) => {
    const isActive =
      currentStatus === true ||
      currentStatus === 1 ||
      currentStatus === '1' ||
      String(currentStatus).toLowerCase() === 'active';

    const action = isActive ? 'deactivate' : 'activate';
    const actionPast = isActive ? 'deactivated' : 'activated';

    modal.confirm({
      title: 'Are you sure?',
      content: `Do you want to ${action} this vehicle?`,
      okText: `Yes, ${action} vehicle`,
      cancelText: 'Cancel',
      okButtonProps: { style: { backgroundColor: BRAND, borderColor: BRAND } },
      async onOk() {
        setLoadingStatusId(vehicleId);
        try {
          const response = isActive
            ? await apiService.deactivateVehicle(vehicleId)
            : await apiService.activateVehicle(vehicleId);

          if (response.success) {
            setVehicles((prev) =>
              prev.map((vehicle) =>
                vehicle.id === vehicleId
                  ? {
                      ...vehicle,
                      status: isActive ? '0' : '1',
                      status_text: isActive ? 'Inactive' : 'Active',
                    }
                  : vehicle
              )
            );
            message.success(`Vehicle ${actionPast} successfully.`);
          } else {
            message.error(response.message || `Failed to ${action} vehicle.`);
          }
        } catch (err) {
          message.error('An error occurred while updating vehicle status.');
          // eslint-disable-next-line no-console
          console.error('Error updating vehicle status:', err);
        } finally {
          setLoadingStatusId(null);
        }
      },
    });
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
        if (pageSize !== filters.per_page) handleFilterChange('per_page', pageSize);
        handleFilterChange('page', page);
      },
    }),
    [filters.per_page, pagination, filters]
  );

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'serial',
        width: 80,
        align: 'center',
        render: (_, record) => {
          const currentPage = pagination?.current_page || 1;
          const perPage = filters.per_page || 15;
          const idx = vehicles.findIndex((v) => v.id === record.id);
          const serial = idx >= 0 ? (currentPage - 1) * perPage + idx + 1 : 0;
          return <span className="text-sm font-medium text-slate-600">{serial}</span>;
        },
      },
      {
        title: 'Plate Number',
        dataIndex: 'plate_no',
        key: 'plate_no',
        searchable: true,
        render: (v) => <span className="font-mono text-sm font-semibold text-black">{v || '-'}</span>,
      },
      {
        title: 'Body Type',
        key: 'body',
        render: (_, v) => <span className="text-sm text-black">{v.body || v.body_type || v.name || 'N/A'}</span>,
      },
      {
        title: 'Created Date',
        key: 'created_at',
        render: (_, v) => <span className="text-sm text-black">{v.created_at ? new Date(v.created_at).toLocaleDateString() : 'N/A'}</span>,
      },
      {
        title: 'Status',
        key: 'status',
        width: 150,
        align: 'center',
        render: (_, v) => {
          const isActive = v.status === true || v.status === 1 || v.status === '1' || String(v.status).toLowerCase() === 'active';
          const isLoading = loadingStatusId === v.id;

          return (
            <button
              type="button"
              onClick={() => toggleVehicleStatus(v.id, v.status)}
              disabled={isLoading}
              className="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold text-white disabled:opacity-60 disabled:cursor-not-allowed"
              style={{ backgroundColor: isActive ? BRAND : '#64748b' }}
              title={isActive ? 'Click to deactivate vehicle' : 'Click to activate vehicle'}
            >
              <span>{isLoading ? 'Updating...' : isActive ? 'Active' : 'Inactive'}</span>
              <span className="h-3 w-3 rounded-full bg-white" />
            </button>
          );
        },
      },
      {
        title: 'Actions',
        key: 'actions',
        width: 140,
        align: 'center',
        render: (_, v) => (
          <button
            type="button"
            className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-white transition disabled:cursor-not-allowed disabled:opacity-50"
            style={{ backgroundColor: BRAND }}
            onMouseEnter={(e) => {
              if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
            }}
            onMouseLeave={(e) => {
              if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND;
            }}
            onClick={() => openViewModal(v)}
            disabled={loadingViewId === v.id}
          >
            <Eye size={13} />
            View
          </button>
        ),
      },
    ],
    [filters.per_page, pagination?.current_page, vehicles, loadingStatusId, loadingViewId]
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
            style={{ top: '4.75rem' }}
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
          onSearchChange={handleSearch}
          showSearch
          showGlobalSearch
          showRefresh={false}
          searchPlaceholder="Search vehicles by plate number, owner, account..."
          rightAction={
            <div className="flex flex-wrap items-center gap-4">
              <button
                type="button"
                onClick={fetchVehicles}
                className="btn-secondary flex items-center space-x-2 px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                disabled={loading}
              >
                <ReloadOutlined className="text-gray-700" />
                <span>Refresh</span>
              </button>

              <button
                type="button"
                className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-[#962E32] hover:bg-[#fff5f5] hover:text-[#962E32] disabled:cursor-not-allowed disabled:opacity-50"
                onClick={() => setShowAssociateModal(true)}
                disabled={showAssociateModal}
              >
                <Link2 size={16} />
                <span>Associate with account</span>
              </button>

              <button
                type="button"
                className="flex items-center space-x-2 rounded-lg px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50"
                style={{ backgroundColor: BRAND }}
                onMouseEnter={(e) => {
                  if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
                }}
                onMouseLeave={(e) => {
                  if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND;
                }}
                onClick={() => setShowCreateModal(true)}
                disabled={showCreateModal}
              >
                <Plus size={16} />
                <span>Add vehicle</span>
              </button>
            </div>
          }
        />
      </div>

      <AddNewVehicleModal
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        onSuccess={fetchVehicles}
      />

      <AssociateVehicleWithAccountModal
        isOpen={showAssociateModal}
        onClose={() => setShowAssociateModal(false)}
        onSuccess={fetchVehicles}
      />

      <VehicleDetailsModal
        open={detailsModalOpen}
        vehicleId={activeVehicleId}
        onClose={closeDetailsModal}
        onUpdated={fetchVehicles}
      />
    </div>
  );
}

