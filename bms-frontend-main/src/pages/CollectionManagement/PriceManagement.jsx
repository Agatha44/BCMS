import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, Edit, Eye, Plus, ToggleLeft, ToggleRight } from 'lucide-react';
import Swal from 'sweetalert2';
import { Button, Modal, Tag, message as antMessage } from 'antd';

import DataTable from '../../common/data/DataTable.jsx';
import CollectionLoader from './components/CollectionLoader.jsx';
import { formatMoney } from '../../common/utils/numberFormat.js';
import { apiService } from '../../services/api.jsx';

const EMPTY_VALUE = 'N/A';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

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

const BrandModalHeader = ({ title, onClose }) => (
  <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
    <h2 className="m-0 text-sm font-semibold leading-none text-white">{title}</h2>
    <button
      type="button"
      aria-label="Close"
      onClick={onClose}
      className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
      </svg>
    </button>
  </div>
);

const PriceManagement = () => {
  const [prices, setPrices] = useState([]);
  const [bodyTypes, setBodyTypes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
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
    body_type_id: undefined,
    sort_by: 'id',
    sort_order: 'asc',
    per_page: 15,
    page: 1,
  });

  const [createForm, setCreateForm] = useState({
    body_type_id: 0,
    amount: 0,
    daily_bundle_amount: 0,
    weekly_bundle_amount: 0,
    monthly_bundle_amount: 0,
    status: true,
  });
  const [creating, setCreating] = useState(false);

  const [showViewModal, setShowViewModal] = useState(false);
  const [viewPrice, setViewPrice] = useState(null);
  const [loadingViewId, setLoadingViewId] = useState(null);
  const [wasViewModalOpen, setWasViewModalOpen] = useState(false);

  const [showEditModal, setShowEditModal] = useState(false);
  const [editForm, setEditForm] = useState({
    id: 0,
    body_type_id: 0,
    amount: 0,
    daily_bundle_amount: 0,
    weekly_bundle_amount: 0,
    monthly_bundle_amount: 0,
    status: true,
  });
  const [updating, setUpdating] = useState(false);
  const [loadingEditId, setLoadingEditId] = useState(null);
  const [loadingStatusId, setLoadingStatusId] = useState(null);
  const [statusConfirmTarget, setStatusConfirmTarget] = useState(null);
  const [statusSubmitting, setStatusSubmitting] = useState(false);

  const [isInitialLoad, setIsInitialLoad] = useState(true);

  const fetchPrices = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiService.getPricesList(filters);
      if (response.success && response.data?.prices && response.data?.pagination) {
        setPrices(response.data.prices);
        setPagination(response.data.pagination);
      } else {
        setPrices([]);
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
      setError('An error occurred while fetching prices');
      // eslint-disable-next-line no-console
      console.error('Error fetching prices:', err);
      setPrices([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchBodyTypes = async () => {
    try {
      const response = await apiService.getActiveBodyTypes();
      if (response.success && response.data) setBodyTypes(response.data);
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Error fetching body types:', err);
    }
  };

  useEffect(() => {
    fetchPrices();
    fetchBodyTypes();
    setIsInitialLoad(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!isInitialLoad) fetchPrices();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.page, isInitialLoad]);

  useEffect(() => {
    if (
      !isInitialLoad &&
      (filters.status !== undefined ||
        filters.body_type_id !== undefined ||
        filters.sort_by !== 'id' ||
        filters.per_page !== 15)
    ) {
      fetchPrices();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.per_page, filters.status, filters.body_type_id, filters.sort_by, filters.sort_order, isInitialLoad]);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (!isInitialLoad && filters.search !== undefined) {
        fetchPrices();
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

  const openViewModal = async (price) => {
    setLoadingViewId(price.id);
    try {
      const response = await apiService.getPriceById(price.id);
      if (response.success && response.data) {
        setViewPrice(response.data);
        setShowViewModal(true);
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to load price details' });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while loading price details' });
      // eslint-disable-next-line no-console
      console.error('Error loading price details:', err);
    } finally {
      setLoadingViewId(null);
    }
  };

  const openEditModal = async (price) => {
    if (showViewModal) {
      setWasViewModalOpen(true);
      setShowViewModal(false);
    }
    setLoadingEditId(price.id);
    try {
      const response = await apiService.getPriceById(price.id);
      if (response.success && response.data) {
        const priceData = response.data;
        setEditForm({
          id: priceData.id,
          body_type_id: priceData.body_type_id,
          amount: priceData.amount,
          daily_bundle_amount: priceData.daily_bundle_amount,
          weekly_bundle_amount: priceData.weekly_bundle_amount,
          monthly_bundle_amount: priceData.monthly_bundle_amount,
          status: priceData.status,
        });
        setShowEditModal(true);
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to load price details' });
        if (wasViewModalOpen) {
          setShowViewModal(true);
          setWasViewModalOpen(false);
        }
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while loading price details' });
      // eslint-disable-next-line no-console
      console.error('Error loading price details:', err);
      if (wasViewModalOpen) {
        setShowViewModal(true);
        setWasViewModalOpen(false);
      }
    } finally {
      setLoadingEditId(null);
    }
  };

  const handleCreatePrice = async (e) => {
    e.preventDefault();
    setCreating(true);
    try {
      const response = await apiService.createPrice(createForm);
      if (response.success) {
        await Swal.fire({ icon: 'success', title: 'Success!', text: 'Price created successfully', timer: 2000, showConfirmButton: false });
        setShowCreateModal(false);
        setCreateForm({
          body_type_id: 0,
          amount: 0,
          daily_bundle_amount: 0,
          weekly_bundle_amount: 0,
          monthly_bundle_amount: 0,
          status: true,
        });
        fetchPrices();
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Failed to create price' });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while creating price' });
      // eslint-disable-next-line no-console
      console.error('Error creating price:', err);
    } finally {
      setCreating(false);
    }
  };

  const handleEditPrice = async (e) => {
    e.preventDefault();
    setUpdating(true);
    try {
      const response = await apiService.updatePrice(editForm.id, editForm);
      if (response.success) {
        await Swal.fire({ icon: 'success', title: 'Success!', text: 'Price updated successfully', timer: 2000, showConfirmButton: false });
        setShowEditModal(false);
        setEditForm({
          id: 0,
          body_type_id: 0,
          amount: 0,
          daily_bundle_amount: 0,
          weekly_bundle_amount: 0,
          monthly_bundle_amount: 0,
          status: true,
        });
        fetchPrices();
        if (wasViewModalOpen && viewPrice) {
          const priceResponse = await apiService.getPriceById(viewPrice.id);
          if (priceResponse.success && priceResponse.data) setViewPrice(priceResponse.data);
          setShowViewModal(true);
          setWasViewModalOpen(false);
        }
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Failed to update price' });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while updating price' });
      // eslint-disable-next-line no-console
      console.error('Error updating price:', err);
    } finally {
      setUpdating(false);
    }
  };

  const openStatusConfirm = (priceId, currentStatus) => {
    const viewModalWasOpen = showViewModal;
    setWasViewModalOpen(viewModalWasOpen);
    if (viewModalWasOpen) setShowViewModal(false);
    setStatusConfirmTarget({ priceId, currentStatus, viewModalWasOpen });
  };

  const closeStatusConfirm = () => {
    if (statusSubmitting) return;
    const viewModalWasOpen = statusConfirmTarget?.viewModalWasOpen;
    setStatusConfirmTarget(null);
    if (viewModalWasOpen) setShowViewModal(true);
  };

  const confirmTogglePriceStatus = async () => {
    if (!statusConfirmTarget) return;

    const { priceId, currentStatus, viewModalWasOpen } = statusConfirmTarget;
    const action = currentStatus ? 'deactivate' : 'activate';

    setStatusSubmitting(true);
    setLoadingStatusId(priceId);
    try {
      const response = await apiService.togglePriceStatus(priceId);
      if (response.success) {
        antMessage.success(`Price ${action}d successfully`);
        setPrices((prev) =>
          prev.map((price) =>
            price.id === priceId
              ? { ...price, status: !currentStatus, status_text: !currentStatus ? 'Active' : 'Inactive' }
              : price
          )
        );
        if (viewPrice && viewPrice.id === priceId) {
          setViewPrice((prev) =>
            prev ? { ...prev, status: !currentStatus, status_text: !currentStatus ? 'Active' : 'Inactive' } : null
          );
        }
        setStatusConfirmTarget(null);
        if (viewModalWasOpen) setShowViewModal(true);
      } else {
        antMessage.error(response.message || 'Failed to update price status');
        setStatusConfirmTarget(null);
        if (viewModalWasOpen) setShowViewModal(true);
      }
    } catch (err) {
      antMessage.error('An error occurred while updating price status');
      // eslint-disable-next-line no-console
      console.error('Error updating price status:', err);
      setStatusConfirmTarget(null);
      if (viewModalWasOpen) setShowViewModal(true);
    } finally {
      setStatusSubmitting(false);
      setLoadingStatusId(null);
    }
  };

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
        title: 'Body Type',
        dataIndex: 'body_type_id',
        key: 'body_type_id',
        render: (_, price) => (
          <span className="text-sm">{price.body_type?.name || EMPTY_VALUE}</span>
        ),
      },
      {
        title: 'Regular Amount',
        dataIndex: 'amount',
        key: 'amount',
        align: 'right',
        money: false,
        render: (v) => <span className="text-sm">{formatMoney(v)}</span>,
      },
      {
        title: 'Daily Bundle',
        dataIndex: 'daily_bundle_amount',
        key: 'daily_bundle_amount',
        align: 'right',
        money: false,
        render: (v) => <span className="text-sm">{formatMoney(v)}</span>,
      },
      {
        title: 'Weekly Bundle',
        dataIndex: 'weekly_bundle_amount',
        key: 'weekly_bundle_amount',
        align: 'right',
        money: false,
        render: (v) => <span className="text-sm">{formatMoney(v)}</span>,
      },
      {
        title: 'Monthly Bundle',
        dataIndex: 'monthly_bundle_amount',
        key: 'monthly_bundle_amount',
        align: 'right',
        money: false,
        render: (v) => <span className="text-sm">{formatMoney(v)}</span>,
      },
      {
        title: 'Status',
        dataIndex: 'status',
        key: 'status',
        align: 'center',
        render: (status, price) => {
          const isActive = status === true || status === 1 || status === '1';
          const label = price?.status_text || (isActive ? 'Active' : 'Inactive');
          return (
            <Tag color={isActive ? 'green' : 'red'} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {label}
            </Tag>
          );
        },
      },
      {
        title: 'Action',
        key: 'actions',
        width: 120,
        align: 'center',
        render: (_, price) => (
          <button
            type="button"
            onClick={() => openViewModal(price)}
            disabled={loadingViewId === price.id}
            className="inline-flex items-center space-x-1 rounded-lg px-3 py-1.5 text-sm text-white disabled:cursor-not-allowed disabled:opacity-50"
            style={{ backgroundColor: BRAND }}
            onMouseEnter={(e) => {
              if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
            }}
            title="View Price Details"
          >
            <Eye size={16} className="text-white" />
            <span>View</span>
          </button>
        ),
      },
    ],
    [filters.per_page, loadingViewId, pagination]
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
    [filters.per_page, pagination, filters]
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
          data={prices || []}
          loading={false}
          pagination={paginationConfig}
          onSearchChange={handleSearch}
          serverSideSearch={true}
          showSearch
          showRefresh={false}
          searchPlaceholder="Search prices by body type..."
          rightAction={
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
              <span>Add price</span>
            </button>
          }
        />
      </div>

      <Modal
        open={showCreateModal}
        onCancel={() => !creating && setShowCreateModal(false)}
        footer={null}
        width={640}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!creating}
        keyboard={!creating}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Add price" onClose={() => !creating && setShowCreateModal(false)} />
        <div className="max-h-[min(70vh,560px)] overflow-y-auto px-6 py-5">
          <h4
            className="mb-4 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
            style={{ color: BRAND }}
          >
            Price details
          </h4>
          <form id="create-price-form" onSubmit={handleCreatePrice} className="space-y-4">
            <div>
              <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                Body type <span className="text-red-500">*</span>
              </label>
              <select
                required
                value={createForm.body_type_id}
                onChange={(e) => setCreateForm((prev) => ({ ...prev, body_type_id: Number(e.target.value) }))}
                className="w-full rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20"
              >
                <option value={0}>Select body type</option>
                {bodyTypes.map((bodyType) => (
                  <option key={bodyType.id} value={bodyType.id}>
                    {bodyType.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Regular amount <span className="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  value={createForm.amount}
                  onChange={(e) => setCreateForm((prev) => ({ ...prev, amount: Number(e.target.value) || 0 }))}
                  className="w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Daily bundle amount <span className="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  value={createForm.daily_bundle_amount}
                  onChange={(e) => setCreateForm((prev) => ({ ...prev, daily_bundle_amount: Number(e.target.value) || 0 }))}
                  className="w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20"
                  placeholder="0.00"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Weekly bundle amount <span className="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  value={createForm.weekly_bundle_amount}
                  onChange={(e) => setCreateForm((prev) => ({ ...prev, weekly_bundle_amount: Number(e.target.value) || 0 }))}
                  className="w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Monthly bundle amount <span className="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  value={createForm.monthly_bundle_amount}
                  onChange={(e) => setCreateForm((prev) => ({ ...prev, monthly_bundle_amount: Number(e.target.value) || 0 }))}
                  className="w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20"
                  placeholder="0.00"
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
                  Active price
                </span>
              </label>
            </div>
          </form>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            htmlType="submit"
            form="create-price-form"
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
          if (wasViewModalOpen && viewPrice) {
            setShowViewModal(true);
            setWasViewModalOpen(false);
          }
        }}
        footer={null}
        width={640}
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
          title={loadingEditId ? 'Loading Price...' : 'Edit Price'}
          onClose={() => {
            if (updating) return;
            setShowEditModal(false);
            if (wasViewModalOpen && viewPrice) {
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
                Price Information
              </h4>
              <form id="edit-price-form" onSubmit={handleEditPrice} className="space-y-4">
                <div>
                  <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Body Type <span className="text-red-500">*</span>
                  </label>
                  <select
                    required
                    value={editForm.body_type_id}
                    onChange={(e) => setEditForm((prev) => ({ ...prev, body_type_id: Number(e.target.value) }))}
                    className="w-full rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20"
                  >
                    <option value={0}>Select body type</option>
                    {bodyTypes.map((bodyType) => (
                      <option key={bodyType.id} value={bodyType.id}>
                        {bodyType.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                      Regular Amount <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      required
                      value={editForm.amount}
                      onChange={(e) => setEditForm((prev) => ({ ...prev, amount: Number(e.target.value) || 0 }))}
                      className="w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20"
                      placeholder="0.00"
                    />
                  </div>
                  <div>
                    <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                      Daily Bundle Amount <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      required
                      value={editForm.daily_bundle_amount}
                      onChange={(e) => setEditForm((prev) => ({ ...prev, daily_bundle_amount: Number(e.target.value) || 0 }))}
                      className="w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20"
                      placeholder="0.00"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                      Weekly Bundle Amount <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      required
                      value={editForm.weekly_bundle_amount}
                      onChange={(e) => setEditForm((prev) => ({ ...prev, weekly_bundle_amount: Number(e.target.value) || 0 }))}
                      className="w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20"
                      placeholder="0.00"
                    />
                  </div>
                  <div>
                    <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                      Monthly Bundle Amount <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      required
                      value={editForm.monthly_bundle_amount}
                      onChange={(e) => setEditForm((prev) => ({ ...prev, monthly_bundle_amount: Number(e.target.value) || 0 }))}
                      className="w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20"
                      placeholder="0.00"
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
                      Active price
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
              form="edit-price-form"
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
                if (wasViewModalOpen && viewPrice) {
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
        open={showViewModal && !!viewPrice}
        onCancel={() => setShowViewModal(false)}
        footer={null}
        width={680}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!loadingStatusId}
        keyboard={!loadingStatusId}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Price Details" onClose={() => setShowViewModal(false)} />

        {viewPrice && (
          <>
            <div className="max-h-[70vh] overflow-y-auto px-6 py-5">
              <div className="mb-5 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
                  <div>
                    <span
                      className="text-[11px] font-semibold uppercase tracking-[0.06em]"
                      style={{ color: BRAND }}
                    >
                      Body Type
                    </span>
                    <div className="mt-0.5 text-sm font-semibold text-black">
                      {viewPrice.body_type?.name || EMPTY_VALUE}
                    </div>
                  </div>
                  <Tag
                    color={viewPrice.status ? 'green' : 'red'}
                    className="!m-0 px-2.5 py-0.5 text-xs font-medium"
                  >
                    {viewPrice.status_text || (viewPrice.status ? 'Active' : 'Inactive')}
                  </Tag>
                </div>
                <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                  <ReadOnlyField label="Regular Amount" value={formatMoney(viewPrice.amount || 0)} mono />
                  <ReadOnlyField label="Daily Bundle Amount" value={formatMoney(viewPrice.daily_bundle_amount || 0)} mono />
                  <ReadOnlyField label="Weekly Bundle Amount" value={formatMoney(viewPrice.weekly_bundle_amount || 0)} mono />
                  <ReadOnlyField label="Monthly Bundle Amount" value={formatMoney(viewPrice.monthly_bundle_amount || 0)} mono />
                </div>
              </div>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
              <Button
                icon={<Edit size={14} />}
                onClick={() => openEditModal(viewPrice)}
                style={{ borderColor: BRAND, color: BRAND }}
                className="hover:!bg-[#fff5f5]"
              >
                Edit
              </Button>
              <Button
                type="primary"
                icon={viewPrice.status ? <ToggleLeft size={14} /> : <ToggleRight size={14} />}
                loading={loadingStatusId === viewPrice.id}
                onClick={() => openStatusConfirm(viewPrice.id, viewPrice.status)}
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
                {viewPrice.status ? 'Deactivate' : 'Activate'}
              </Button>
              <Button onClick={() => setShowViewModal(false)} disabled={loadingStatusId === viewPrice.id}>
                Close
              </Button>
            </div>
          </>
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
          title={statusConfirmTarget?.currentStatus ? 'Deactivate Price' : 'Activate Price'}
          onClose={closeStatusConfirm}
        />
        <div className="px-6 py-5">
          <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
            <p className="text-sm text-amber-900">
              {statusConfirmTarget?.currentStatus
                ? 'This will deactivate the price. Continue?'
                : 'This will activate the price. Continue?'}
            </p>
          </div>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            loading={statusSubmitting}
            onClick={confirmTogglePriceStatus}
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
    </div>
  );
};

export default PriceManagement;
