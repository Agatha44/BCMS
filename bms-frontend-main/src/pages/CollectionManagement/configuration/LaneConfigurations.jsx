import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, Download, Edit, Eye, Filter, Plus, ToggleLeft, ToggleRight, Trash2 } from 'lucide-react';
import { Button, Modal, Tag, message as antMessage } from 'antd';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';

import BreadCrumb from '../../../common/components/BreadCrumb.jsx';
import DataTable from '../../../common/data/DataTable.jsx';
import CollectionLoader from '../components/CollectionLoader.jsx';
import BrandModalHeader from '../sod/components/BrandModalHeader.jsx';
import { apiService } from '../../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';
const INPUT_CLASS =
  'w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20';

const ReadOnlyField = ({ label, value, mono = false }) => (
  <div className="min-w-0 py-1.5">
    <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <div className={`mt-0.5 text-sm font-medium text-black ${mono ? 'font-mono' : ''}`}>
      {value != null && value !== '' ? value : <span className="font-normal text-slate-400">{EMPTY_VALUE}</span>}
    </div>
  </div>
);

const FormLabel = ({ children, required = false }) => (
  <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
    {children}
    {required && <span className="text-red-500"> *</span>}
  </label>
);

export default function LaneConfigurations({ hideBreadcrumb = false }) {
  const [lanes, setLanes] = useState([]);
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showFilters, setShowFilters] = useState(false);
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
    status: undefined,
    payment_method: undefined,
    sort_by: 'lane_no',
    sort_order: 'asc',
    per_page: 15,
    page: 1,
  });

  const [createForm, setCreateForm] = useState({
    lane_no: '',
    camera_ip: '',
    reader_ip: '',
    com_port: '',
    payment_method: 1,
    reader_port: undefined,
    mac_address: '',
    gate_ip: '',
    status: true,
  });
  const [creating, setCreating] = useState(false);

  const [showViewModal, setShowViewModal] = useState(false);
  const [viewLane, setViewLane] = useState(null);
  const [loadingViewId, setLoadingViewId] = useState(null);
  const [wasViewModalOpen, setWasViewModalOpen] = useState(false);

  const [showEditModal, setShowEditModal] = useState(false);
  const [editForm, setEditForm] = useState({
    id: 0,
    lane_no: '',
    camera_ip: '',
    reader_ip: '',
    com_port: '',
    payment_method: 1,
    reader_port: undefined,
    mac_address: '',
    gate_ip: '',
    status: true,
  });
  const [updating, setUpdating] = useState(false);
  const [loadingEditId, setLoadingEditId] = useState(null);
  const [loadingStatusId, setLoadingStatusId] = useState(null);
  const [statusConfirmTarget, setStatusConfirmTarget] = useState(null);
  const [deleteConfirmTarget, setDeleteConfirmTarget] = useState(null);
  const [statusSubmitting, setStatusSubmitting] = useState(false);
  const [deleteSubmitting, setDeleteSubmitting] = useState(false);

  const [exporting, setExporting] = useState(false);
  const [isInitialLoad, setIsInitialLoad] = useState(true);

  const getPaymentMethodName = (methodId) => {
    const method = paymentMethods.find((m) => m.id === methodId);
    return method ? method.name : 'Unknown';
  };

  const fetchPaymentMethods = async () => {
    try {
      const response = await apiService.getPaymentMethods();
      if (response.success && response.data) {
        setPaymentMethods(response.data);
      }
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Error fetching payment methods:', err);
    }
  };

  const fetchLanes = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiService.getLanesList(filters);
      if (response.success && response.data?.lanes && response.data?.pagination) {
        setLanes(response.data.lanes);
        setPagination(response.data.pagination);
      } else {
        setLanes([]);
        setPagination({
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 0,
          from: 0,
          to: 0,
        });
        setError(response.message || 'Unexpected data format received from server');
      }
    } catch (err) {
      setError('An error occurred while fetching lanes');
      // eslint-disable-next-line no-console
      console.error('Error fetching lanes:', err);
      setLanes([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLanes();
    fetchPaymentMethods();
    setIsInitialLoad(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!isInitialLoad) fetchLanes();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.page, isInitialLoad]);

  useEffect(() => {
    if (
      !isInitialLoad &&
      (filters.status !== undefined ||
        filters.payment_method !== undefined ||
        filters.sort_by !== 'lane_no' ||
        filters.per_page !== 15)
    ) {
      fetchLanes();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.per_page, filters.status, filters.payment_method, filters.sort_by, filters.sort_order, isInitialLoad]);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (!isInitialLoad && filters.search !== undefined) {
        fetchLanes();
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

  const openViewModal = async (lane) => {
    setLoadingViewId(lane.id);
    try {
      const response = await apiService.getLaneById(lane.id);
      if (response.success && response.data) {
        setViewLane(response.data);
        setShowViewModal(true);
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to load lane details' });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while loading lane details' });
      // eslint-disable-next-line no-console
      console.error('Error loading lane details:', err);
    } finally {
      setLoadingViewId(null);
    }
  };

  const openEditModal = async (lane) => {
    if (showViewModal) {
      setWasViewModalOpen(true);
      setShowViewModal(false);
    }
    setLoadingEditId(lane.id);
    try {
      const response = await apiService.getLaneById(lane.id);
      if (response.success && response.data) {
        const laneData = response.data;
        setEditForm({
          id: laneData.id,
          lane_no: laneData.lane_no || '',
          camera_ip: laneData.camera_ip || '',
          reader_ip: laneData.reader_ip || '',
          com_port: laneData.com_port || '',
          payment_method: typeof laneData.payment_method === 'object' ? laneData.payment_method.id : laneData.payment_method,
          reader_port: laneData.reader_port,
          mac_address: laneData.mac_address || '',
          gate_ip: laneData.gate_ip || '',
          status: laneData.status,
        });
        setShowEditModal(true);
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to load lane details' });
        if (wasViewModalOpen) {
          setShowViewModal(true);
          setWasViewModalOpen(false);
        }
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while loading lane details' });
      // eslint-disable-next-line no-console
      console.error('Error loading lane details:', err);
      if (wasViewModalOpen) {
        setShowViewModal(true);
        setWasViewModalOpen(false);
      }
    } finally {
      setLoadingEditId(null);
    }
  };

  const handleCreateLane = async (e) => {
    e.preventDefault();
    setCreating(true);
    try {
      const response = await apiService.createLane(createForm);
      if (response.success) {
        await Swal.fire({ icon: 'success', title: 'Success!', text: 'Lane created successfully', timer: 2000, showConfirmButton: false });
        setShowCreateModal(false);
        setCreateForm({
          lane_no: '',
          camera_ip: '',
          reader_ip: '',
          com_port: '',
          payment_method: 1,
          reader_port: undefined,
          mac_address: '',
          gate_ip: '',
          status: true,
        });
        fetchLanes();
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Failed to create lane' });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while creating lane' });
      // eslint-disable-next-line no-console
      console.error('Error creating lane:', err);
    } finally {
      setCreating(false);
    }
  };

  const handleEditLane = async (e) => {
    e.preventDefault();
    setUpdating(true);
    try {
      const response = await apiService.updateLane(editForm.id, editForm);
      if (response.success) {
        await Swal.fire({ icon: 'success', title: 'Success!', text: 'Lane updated successfully', timer: 2000, showConfirmButton: false });
        setShowEditModal(false);
        setEditForm({
          id: 0,
          lane_no: '',
          camera_ip: '',
          reader_ip: '',
          com_port: '',
          payment_method: 1,
          reader_port: undefined,
          mac_address: '',
          gate_ip: '',
          status: true,
        });
        fetchLanes();

        if (wasViewModalOpen && viewLane) {
          const laneResponse = await apiService.getLaneById(viewLane.id);
          if (laneResponse.success && laneResponse.data) {
            setViewLane(laneResponse.data);
          }
          setShowViewModal(true);
          setWasViewModalOpen(false);
        }
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Failed to update lane' });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while updating lane' });
      // eslint-disable-next-line no-console
      console.error('Error updating lane:', err);
    } finally {
      setUpdating(false);
    }
  };

  const openStatusConfirm = (laneId, currentStatus) => {
    const viewModalWasOpen = showViewModal;
    setWasViewModalOpen(viewModalWasOpen);
    if (viewModalWasOpen) setShowViewModal(false);
    setStatusConfirmTarget({ laneId, currentStatus, viewModalWasOpen });
  };

  const closeStatusConfirm = () => {
    if (statusSubmitting) return;
    const viewModalWasOpen = statusConfirmTarget?.viewModalWasOpen;
    setStatusConfirmTarget(null);
    if (viewModalWasOpen) setShowViewModal(true);
  };

  const confirmToggleLaneStatus = async () => {
    if (!statusConfirmTarget) return;

    const { laneId, currentStatus, viewModalWasOpen } = statusConfirmTarget;
    const action = currentStatus ? 'deactivate' : 'activate';

    setStatusSubmitting(true);
    setLoadingStatusId(laneId);
    try {
      const response = await apiService.toggleLaneStatus(laneId);
      if (response.success) {
        antMessage.success(`Lane ${action}d successfully`);
        setLanes((prev) =>
          prev.map((lane) =>
            lane.id === laneId ? { ...lane, status: !currentStatus, status_text: !currentStatus ? 'Active' : 'Inactive' } : lane
          )
        );
        if (viewLane && viewLane.id === laneId) {
          setViewLane((prev) =>
            prev ? { ...prev, status: !currentStatus, status_text: !currentStatus ? 'Active' : 'Inactive' } : null
          );
        }
        setStatusConfirmTarget(null);
        if (viewModalWasOpen) setShowViewModal(true);
      } else {
        antMessage.error(response.message || 'Failed to update lane status');
        setStatusConfirmTarget(null);
        if (viewModalWasOpen) setShowViewModal(true);
      }
    } catch (err) {
      antMessage.error('An error occurred while updating lane status');
      // eslint-disable-next-line no-console
      console.error('Error updating lane status:', err);
      setStatusConfirmTarget(null);
      if (viewModalWasOpen) setShowViewModal(true);
    } finally {
      setStatusSubmitting(false);
      setLoadingStatusId(null);
    }
  };

  const openDeleteConfirm = (laneId, laneNo) => {
    const viewModalWasOpen = showViewModal;
    setWasViewModalOpen(viewModalWasOpen);
    if (viewModalWasOpen) setShowViewModal(false);
    setDeleteConfirmTarget({ laneId, laneNo, viewModalWasOpen });
  };

  const closeDeleteConfirm = () => {
    if (deleteSubmitting) return;
    const viewModalWasOpen = deleteConfirmTarget?.viewModalWasOpen;
    setDeleteConfirmTarget(null);
    if (viewModalWasOpen) setShowViewModal(true);
  };

  const confirmDeleteLane = async () => {
    if (!deleteConfirmTarget) return;

    const { laneId, laneNo, viewModalWasOpen } = deleteConfirmTarget;

    setDeleteSubmitting(true);
    try {
      const response = await apiService.deleteLane(laneId);
      if (response.success) {
        antMessage.success(`Lane ${laneNo} deleted successfully`);
        setDeleteConfirmTarget(null);
        setShowViewModal(false);
        setViewLane(null);
        fetchLanes();
      } else {
        antMessage.error(response.message || 'Failed to delete lane');
        setDeleteConfirmTarget(null);
        if (viewModalWasOpen) setShowViewModal(true);
      }
    } catch (err) {
      antMessage.error('An error occurred while deleting lane');
      // eslint-disable-next-line no-console
      console.error('Error deleting lane:', err);
      setDeleteConfirmTarget(null);
      if (viewModalWasOpen) setShowViewModal(true);
    } finally {
      setDeleteSubmitting(false);
    }
  };

  const exportLanesToExcel = async () => {
    setExporting(true);
    try {
      const exportData = lanes.map((lane, index) => ({
        'Serial No.': index + 1,
        'Lane No.': lane.lane_no || '',
        'Camera IP': lane.camera_ip || '',
        'Reader IP': lane.reader_ip || '',
        'Payment Method':
          typeof lane.payment_method === 'object' && lane.payment_method?.description
            ? lane.payment_method.description
            : lane.payment_method_text || getPaymentMethodName(typeof lane.payment_method === 'number' ? lane.payment_method : lane.payment_method?.id),
        Status: lane.status_text || (lane.status ? 'Active' : 'Inactive'),
        'COM Port': lane.com_port || '',
        'Reader Port': lane.reader_port || '',
        'MAC Address': lane.mac_address || '',
        'Gate IP': lane.gate_ip || '',
        'Created At': lane.created_at ? new Date(lane.created_at).toLocaleDateString() : '',
        'Updated At': lane.updated_at ? new Date(lane.updated_at).toLocaleDateString() : '',
      }));

      const workbook = XLSX.utils.book_new();
      const worksheet = XLSX.utils.json_to_sheet(exportData);
      XLSX.utils.book_append_sheet(workbook, worksheet, 'Lanes');

      const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
      const filename = `lanes_export_${timestamp}.xlsx`;
      const excelBuffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
      saveAs(new Blob([excelBuffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), filename);

      await Swal.fire({ icon: 'success', title: 'Export Successful!', text: `Lanes exported to ${filename}`, timer: 3000, showConfirmButton: false });
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Export error:', err);
      await Swal.fire({ icon: 'error', title: 'Export Failed', text: 'An error occurred while exporting lanes. Please try again.' });
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
        render: (_, __, index) => (
          <span className="text-sm font-medium text-slate-600">
            {(pagination.current_page - 1) * (filters.per_page || 15) + index + 1}
          </span>
        ),
      },
      { title: 'Lane No.', dataIndex: 'lane_no', key: 'lane_no', searchable: true },
      {
        title: 'Camera IP',
        dataIndex: 'camera_ip',
        key: 'camera_ip',
        searchable: true,
        render: (camera_ip) => <span className="text-sm font-mono">{camera_ip}</span>,
      },
      {
        title: 'Reader IP',
        dataIndex: 'reader_ip',
        key: 'reader_ip',
        searchable: true,
        render: (reader_ip) => <span className="text-sm font-mono">{reader_ip}</span>,
      },
      {
        title: 'Status',
        dataIndex: 'status',
        key: 'status',
        render: (status, lane) => {
          const isActive = status === true || status === 1 || status === '1';
          const label = lane?.status_text || (isActive ? 'Active' : 'Inactive');
          return (
            <Tag color={isActive ? 'green' : 'red'} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {label}
            </Tag>
          );
        },
      },
      {
        title: 'Actions',
        key: 'actions',
        render: (_, lane) => (
          <div className="flex items-center space-x-2">
            <button
              type="button"
              onClick={() => openViewModal(lane)}
              disabled={loadingViewId === lane.id}
              className="inline-flex items-center space-x-1 rounded-lg px-3 py-1.5 text-sm text-white disabled:cursor-not-allowed disabled:opacity-50"
              style={{ backgroundColor: BRAND }}
              onMouseEnter={(e) => {
                if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
              }}
              title="View Lane Details"
            >
              <Eye size={16} className="text-white" />
              <span>View</span>
            </button>
          </div>
        ),
      },
    ],
    [filters.per_page, loadingViewId, loadingEditId, loadingStatusId, pagination]
  );

  const paginationConfig = useMemo(
    () => ({
      current: pagination.current_page,
      pageSize: pagination.per_page,
      total: pagination.total,
      showTotal: () => null,
      showQuickJumper: false,
      showSizeChanger: true,
      pageSizeOptions: ['10', '15', '25', '50'],
      onChange: (page, pageSize) => {
        if (pageSize !== filters.per_page) handleFilterChange('per_page', pageSize);
        handleFilterChange('page', page);
      },
    }),
    [filters.per_page, pagination, filters]
  );

  return (
    <div className="space-y-4">
      {!hideBreadcrumb && (
        <BreadCrumb
          crumbs={[
            { label: 'Toll Management', link: '/collection-management/dashboard' },
            { label: 'Manage Settings', link: '/collection-management/configuration' },
            { label: 'Lane Configuration', link: '/collection-management/configuration', current: true },
          ]}
        />
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

      {showFilters && (
        <div className="rounded-lg border border-slate-200 bg-white p-6">
          <h4
            className="mb-4 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
            style={{ color: BRAND }}
          >
            Filters
          </h4>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
              <FormLabel>Status</FormLabel>
              <select
                value={filters.status === undefined ? '' : filters.status.toString()}
                onChange={(e) => handleFilterChange('status', e.target.value === '' ? undefined : e.target.value === 'true')}
                className={INPUT_CLASS}
              >
                <option value="">All Status</option>
                <option value="true">Active</option>
                <option value="false">Inactive</option>
              </select>
            </div>

            <div>
              <FormLabel>Payment Method</FormLabel>
              <select
                value={filters.payment_method || ''}
                onChange={(e) => handleFilterChange('payment_method', e.target.value === '' ? undefined : Number(e.target.value))}
                className={INPUT_CLASS}
              >
                <option value="">All Payment Methods</option>
                {paymentMethods.map((method) => (
                  <option key={method.id} value={method.id}>
                    {method.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <FormLabel>Sort By</FormLabel>
              <select
                value={filters.sort_by || 'lane_no'}
                onChange={(e) => handleFilterChange('sort_by', e.target.value)}
                className={INPUT_CLASS}
              >
                <option value="lane_no">Lane Number</option>
                <option value="camera_ip">Camera IP</option>
                <option value="reader_ip">Reader IP</option>
                <option value="payment_method">Payment Method</option>
                <option value="status">Status</option>
                <option value="created_at">Created Date</option>
              </select>
            </div>

            <div>
              <FormLabel>Per Page</FormLabel>
              <select
                value={filters.per_page || 15}
                onChange={(e) => handleFilterChange('per_page', Number(e.target.value))}
                className={INPUT_CLASS}
              >
                <option value={10}>10</option>
                <option value={15}>15</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
              </select>
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
          data={lanes || []}
          loading={false}
          pagination={paginationConfig}
          rowKey={(record) => record.id}
          onSearchChange={handleSearch}
          showSearch
          showRefresh={false}
          searchPlaceholder="Search lanes by number, IP addresses..."
          rightAction={
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={() => setShowFilters(!showFilters)}
                className="btn-secondary flex items-center space-x-2"
              >
                <Filter size={16} />
                <span>Filters</span>
              </button>
              <button
                type="button"
                onClick={exportLanesToExcel}
                disabled={exporting || lanes.length === 0}
                className="btn-secondary flex items-center space-x-2 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Download size={16} />
                <span>{exporting ? 'Exporting...' : 'Export'}</span>
              </button>
              <button
                type="button"
                onClick={() => setShowCreateModal(true)}
                className="flex items-center space-x-2 rounded-lg px-4 py-2 text-white"
                style={{ backgroundColor: BRAND }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = BRAND_DARK;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = BRAND;
                }}
              >
                <Plus size={16} />
                <span>Create lane</span>
              </button>
            </div>
          }
        />
      </div>

      <Modal
        open={showCreateModal}
        onCancel={() => !creating && setShowCreateModal(false)}
        footer={null}
        width={720}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!creating}
        keyboard={!creating}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Create lane" onClose={() => !creating && setShowCreateModal(false)} />
        <div className="max-h-[min(70vh,560px)] overflow-y-auto px-6 py-5">
          <h4
            className="mb-4 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
            style={{ color: BRAND }}
          >
            Lane details
          </h4>
          <form id="create-lane-form" onSubmit={handleCreateLane} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <FormLabel required>Lane Number</FormLabel>
                    <input
                      type="text"
                      required
                      value={createForm.lane_no}
                      onChange={(e) => setCreateForm((prev) => ({ ...prev, lane_no: e.target.value }))}
                      className={INPUT_CLASS}
                      placeholder="e.g., LANE-001"
                    />
                  </div>

                  <div>
                    <FormLabel required>Payment Method</FormLabel>
                    <select
                      required
                      value={createForm.payment_method}
                      onChange={(e) => setCreateForm((prev) => ({ ...prev, payment_method: Number(e.target.value) }))}
                      className={INPUT_CLASS}
                    >
                      {paymentMethods.map((method) => (
                        <option key={method.id} value={method.id}>
                          {method.name}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <FormLabel required>Camera IP</FormLabel>
                    <input
                      type="text"
                      required
                      value={createForm.camera_ip}
                      onChange={(e) => setCreateForm((prev) => ({ ...prev, camera_ip: e.target.value }))}
                      className={INPUT_CLASS}
                      placeholder="192.168.1.100"
                    />
                  </div>

                  <div>
                    <FormLabel required>Reader IP</FormLabel>
                    <input
                      type="text"
                      required
                      value={createForm.reader_ip}
                      onChange={(e) => setCreateForm((prev) => ({ ...prev, reader_ip: e.target.value }))}
                      className={INPUT_CLASS}
                      placeholder="192.168.1.101"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <FormLabel>COM Port</FormLabel>
                    <input
                      type="text"
                      value={createForm.com_port || ''}
                      onChange={(e) => setCreateForm((prev) => ({ ...prev, com_port: e.target.value }))}
                      className={INPUT_CLASS}
                      placeholder="COM1"
                    />
                  </div>

                  <div>
                    <FormLabel>Reader Port</FormLabel>
                    <input
                      type="number"
                      value={createForm.reader_port || ''}
                      onChange={(e) => setCreateForm((prev) => ({ ...prev, reader_port: e.target.value ? Number(e.target.value) : undefined }))}
                      className={INPUT_CLASS}
                      placeholder="8080"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <FormLabel>MAC Address</FormLabel>
                    <input
                      type="text"
                      value={createForm.mac_address || ''}
                      onChange={(e) => setCreateForm((prev) => ({ ...prev, mac_address: e.target.value }))}
                      className={INPUT_CLASS}
                      placeholder="00:11:22:33:44:55"
                    />
                  </div>

                  <div>
                    <FormLabel>Gate IP</FormLabel>
                    <input
                      type="text"
                      value={createForm.gate_ip || ''}
                      onChange={(e) => setCreateForm((prev) => ({ ...prev, gate_ip: e.target.value }))}
                      className={INPUT_CLASS}
                      placeholder="192.168.1.102"
                    />
                  </div>
                </div>

                <div className="pt-1">
                  <label className="flex cursor-pointer items-center gap-2">
                    <input
                      type="checkbox"
                      checked={createForm.status}
                      onChange={(e) => setCreateForm((prev) => ({ ...prev, status: e.target.checked }))}
                      className="h-4 w-4 rounded border-slate-300 text-[#962E32] focus:ring-[#962E32]"
                    />
                    <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                      Active lane
                    </span>
                  </label>
                </div>
              </form>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            htmlType="submit"
            form="create-lane-form"
            loading={creating}
            icon={<Plus size={14} />}
            disabled={creating}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => {
              if (!creating) {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
                e.currentTarget.style.borderColor = BRAND_DARK;
              }
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
              e.currentTarget.style.borderColor = BRAND;
            }}
          >
            Save
          </Button>
          <Button onClick={() => setShowCreateModal(false)} disabled={creating}>
            Close
          </Button>
        </div>
      </Modal>

      <Modal
        open={showEditModal}
        onCancel={() => {
          if (updating) return;
          setShowEditModal(false);
          if (wasViewModalOpen && viewLane) {
            setShowViewModal(true);
            setWasViewModalOpen(false);
          }
        }}
        footer={null}
        width={720}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!updating && !loadingEditId}
        keyboard={!updating && !loadingEditId}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
        zIndex={1100}
      >
        <BrandModalHeader
          title={loadingEditId ? 'Loading lane...' : 'Edit lane'}
          onClose={() => {
            if (updating) return;
            setShowEditModal(false);
            if (wasViewModalOpen && viewLane) {
              setShowViewModal(true);
              setWasViewModalOpen(false);
            }
          }}
        />
        <div className="max-h-[min(70vh,560px)] overflow-y-auto px-6 py-5">
          {loadingEditId ? (
            <CollectionLoader size={64} compact />
          ) : (
            <>
              <h4
                className="mb-4 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
                style={{ color: BRAND }}
              >
                Lane information
              </h4>
              <form id="edit-lane-form" onSubmit={handleEditLane} className="space-y-4">
                  <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                      <FormLabel required>Lane Number</FormLabel>
                      <input
                        type="text"
                        required
                        value={editForm.lane_no}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, lane_no: e.target.value }))}
                        className={INPUT_CLASS}
                        placeholder="e.g., LANE-001"
                      />
                    </div>

                    <div>
                      <FormLabel required>Payment Method</FormLabel>
                      <select
                        required
                        value={editForm.payment_method}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, payment_method: Number(e.target.value) }))}
                        className={INPUT_CLASS}
                      >
                        {paymentMethods.map((method) => (
                          <option key={method.id} value={method.id}>
                            {method.name}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                      <FormLabel required>Camera IP</FormLabel>
                      <input
                        type="text"
                        required
                        value={editForm.camera_ip}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, camera_ip: e.target.value }))}
                        className={INPUT_CLASS}
                        placeholder="192.168.1.100"
                      />
                    </div>

                    <div>
                      <FormLabel required>Reader IP</FormLabel>
                      <input
                        type="text"
                        required
                        value={editForm.reader_ip}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, reader_ip: e.target.value }))}
                        className={INPUT_CLASS}
                        placeholder="192.168.1.101"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                      <FormLabel>COM Port</FormLabel>
                      <input
                        type="text"
                        value={editForm.com_port || ''}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, com_port: e.target.value }))}
                        className={INPUT_CLASS}
                        placeholder="COM1"
                      />
                    </div>

                    <div>
                      <FormLabel>Reader Port</FormLabel>
                      <input
                        type="number"
                        value={editForm.reader_port || ''}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, reader_port: e.target.value ? Number(e.target.value) : undefined }))}
                        className={INPUT_CLASS}
                        placeholder="8080"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                      <FormLabel>MAC Address</FormLabel>
                      <input
                        type="text"
                        value={editForm.mac_address || ''}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, mac_address: e.target.value }))}
                        className={INPUT_CLASS}
                        placeholder="00:11:22:33:44:55"
                      />
                    </div>

                    <div>
                      <FormLabel>Gate IP</FormLabel>
                      <input
                        type="text"
                        value={editForm.gate_ip || ''}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, gate_ip: e.target.value }))}
                        className={INPUT_CLASS}
                        placeholder="192.168.1.102"
                      />
                    </div>
                  </div>

                  <div className="pt-1">
                    <label className="flex cursor-pointer items-center gap-2">
                      <input
                        type="checkbox"
                        checked={editForm.status}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, status: e.target.checked }))}
                        className="h-4 w-4 rounded border-slate-300 text-[#962E32] focus:ring-[#962E32]"
                      />
                      <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                        Active lane
                      </span>
                    </label>
                  </div>
                </form>
            </>
          )}
        </div>
        {!loadingEditId && (
          <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
            <Button
              type="primary"
              htmlType="submit"
              form="edit-lane-form"
              loading={updating}
              icon={<Edit size={14} />}
              disabled={updating}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
              onMouseEnter={(e) => {
                if (!updating) {
                  e.currentTarget.style.backgroundColor = BRAND_DARK;
                  e.currentTarget.style.borderColor = BRAND_DARK;
                }
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
                e.currentTarget.style.borderColor = BRAND;
              }}
            >
              Update
            </Button>
            <Button
              onClick={() => {
                setShowEditModal(false);
                if (wasViewModalOpen && viewLane) {
                  setShowViewModal(true);
                  setWasViewModalOpen(false);
                }
              }}
              disabled={updating}
            >
              Close
            </Button>
          </div>
        )}
      </Modal>

      <Modal
        open={showViewModal && !!viewLane}
        onCancel={() => setShowViewModal(false)}
        footer={null}
        width={800}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Lane details" onClose={() => setShowViewModal(false)} />
        <div className="max-h-[min(70vh,560px)] overflow-y-auto px-6 py-5">
          {loadingViewId ? (
            <CollectionLoader size={64} compact />
          ) : viewLane ? (
            <>
              <h4
                className="mb-4 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
                style={{ color: BRAND }}
              >
                Lane information
              </h4>
              <div className="grid grid-cols-1 gap-x-6 md:grid-cols-2">
                <ReadOnlyField label="Lane Number" value={viewLane.lane_no} mono />
                <div className="min-w-0 py-1.5">
                  <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Status
                  </span>
                  <div className="mt-1">
                    <Tag color={viewLane.status ? 'green' : 'red'} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
                      {viewLane.status_text || (viewLane.status ? 'Active' : 'Inactive')}
                    </Tag>
                  </div>
                </div>
                <ReadOnlyField label="Camera IP" value={viewLane.camera_ip} mono />
                <ReadOnlyField label="Reader IP" value={viewLane.reader_ip} mono />
                <ReadOnlyField label="COM Port" value={viewLane.com_port} />
                <ReadOnlyField label="Reader Port" value={viewLane.reader_port} />
                <ReadOnlyField label="MAC Address" value={viewLane.mac_address} mono />
                <ReadOnlyField label="Gate IP" value={viewLane.gate_ip} mono />
                <ReadOnlyField
                  label="Payment Method"
                  value={
                    typeof viewLane.payment_method === 'object' && viewLane.payment_method?.description
                      ? viewLane.payment_method.description
                      : viewLane.payment_method_text ||
                        getPaymentMethodName(
                          typeof viewLane.payment_method === 'number' ? viewLane.payment_method : viewLane.payment_method?.id
                        )
                  }
                />
              </div>
            </>
          ) : null}
        </div>
        {viewLane && !loadingViewId && (
          <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
            <Button
              type="primary"
              icon={<Edit size={14} />}
              onClick={() => openEditModal(viewLane)}
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
              Edit
            </Button>
            <Button
              icon={viewLane.status ? <ToggleLeft size={14} /> : <ToggleRight size={14} />}
              loading={loadingStatusId === viewLane.id}
              onClick={() => openStatusConfirm(viewLane.id, viewLane.status)}
              style={{ backgroundColor: BRAND, borderColor: BRAND, color: '#fff' }}
            >
              {viewLane.status ? 'Deactivate' : 'Activate'}
            </Button>
            <Button
              type="primary"
              icon={<Trash2 size={14} />}
              onClick={() => openDeleteConfirm(viewLane.id, viewLane.lane_no)}
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
              Delete
            </Button>
            <Button onClick={() => setShowViewModal(false)}>Close</Button>
          </div>
        )}
      </Modal>

      <Modal
        open={!!statusConfirmTarget}
        onCancel={closeStatusConfirm}
        footer={null}
        width={480}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!statusSubmitting}
        keyboard={!statusSubmitting}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
        zIndex={1200}
      >
        <BrandModalHeader
          title={statusConfirmTarget?.currentStatus ? 'Deactivate lane' : 'Activate lane'}
          onClose={closeStatusConfirm}
        />
        <div className="px-6 py-5">
          <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
            <p className="text-sm text-amber-900">
              {statusConfirmTarget?.currentStatus
                ? 'This will deactivate the lane. Continue?'
                : 'This will activate the lane. Continue?'}
            </p>
          </div>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            loading={statusSubmitting}
            onClick={confirmToggleLaneStatus}
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
            {statusConfirmTarget?.currentStatus ? 'Yes, deactivate' : 'Yes, activate'}
          </Button>
          <Button onClick={closeStatusConfirm} disabled={statusSubmitting}>
            Close
          </Button>
        </div>
      </Modal>

      <Modal
        open={!!deleteConfirmTarget}
        onCancel={closeDeleteConfirm}
        footer={null}
        width={480}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!deleteSubmitting}
        keyboard={!deleteSubmitting}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
        zIndex={1200}
      >
        <BrandModalHeader title="Delete lane" onClose={closeDeleteConfirm} />
        <div className="px-6 py-5">
          <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3">
            <p className="text-sm text-red-900">
              Do you want to delete lane <strong>{deleteConfirmTarget?.laneNo}</strong>? This action cannot be undone.
            </p>
          </div>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            loading={deleteSubmitting}
            onClick={confirmDeleteLane}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => {
              if (!deleteSubmitting) {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
                e.currentTarget.style.borderColor = BRAND_DARK;
              }
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
              e.currentTarget.style.borderColor = BRAND;
            }}
          >
            Yes, delete
          </Button>
          <Button onClick={closeDeleteConfirm} disabled={deleteSubmitting}>
            Close
          </Button>
        </div>
      </Modal>
    </div>
  );
}


