import { useEffect, useMemo, useState } from 'react';
import { X } from 'lucide-react';
import { Select, Tag } from 'antd';
import Swal from 'sweetalert2';

import DataTable from '../../../common/data/DataTable.jsx';
import CreateOverloadChargeModal from '../../../common/components/modals/CreateOverloadChargeModal.jsx';
import OverloadFineDetailsModal from '../../../common/components/transactions/overloads/OverloadFineDetailsModal.jsx';
import CollectionLoader from '../components/CollectionLoader.jsx';
import { formatMoney } from '../../../common/utils/numberFormat.js';
import { formatBillDate } from '../../../common/utils/dateFormat.js';
import { overloadFineService } from '../../../services/overloadFineService.js';
import { getOverloadStatus } from './overloadFine.status.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

export default function OverloadFines() {
  const [overloads, setOverloads] = useState([]);
  const [pagination, setPagination] = useState({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
    from: null,
    to: null,
    has_more: false,
  });
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all'); // all | paid | not_paid
  const [sortBy, setSortBy] = useState('created_at');
  const [sortOrder, setSortOrder] = useState('desc');
  const [showNewModal, setShowNewModal] = useState(false);
  const [showDetailsModal, setShowDetailsModal] = useState(false);
  const [selectedOverload, setSelectedOverload] = useState(null);
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  useEffect(() => {
    loadOverloads();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pagination.current_page, pagination.per_page, statusFilter, sortBy, sortOrder]);

  useEffect(() => {
    const timer = setTimeout(() => {
      if (pagination.current_page === 1) {
        loadOverloads();
      } else {
        setPagination((prev) => ({ ...prev, current_page: 1 }));
      }
    }, 500);

    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchQuery]);

  const loadOverloads = async () => {
    try {
      setLoading(true);

      // Map UI sort field to API sort field.
      // When the user selects "Created At" (created_at), use bill_gen_at for the API
      // so that we align with the backend default while keeping the UI label intact.
      const apiSortBy = sortBy === 'created_at' ? 'bill_gen_at' : sortBy;

      const params = {
        page: pagination.current_page,
        per_page: pagination.per_page,
        search: searchQuery || undefined,
        status:
          statusFilter !== 'all' ? (statusFilter === 'not_paid' ? 'unpaid' : statusFilter) : undefined,
        sort_by: apiSortBy,
        sort_order: sortOrder,
      };

      const response = await overloadFineService.getAll(params);

      // Normalize API shapes defensively (mirrors IncidentFine):
      // - service may return an array directly
      // - or { data: [] }
      // - or { data: { data: [], pagination: {...} } }
      const overloadItems =
        (Array.isArray(response) ? response : null) ||
        (Array.isArray(response?.data) ? response.data : null) ||
        (Array.isArray(response?.data?.data) ? response.data.data : null) ||
        [];

      const pager =
        response?.pagination ||
        response?.data?.pagination ||
        {};

      setOverloads(overloadItems);
      setPagination((prev) => ({ ...prev, ...pager }));
    } catch (error) {
      setErrorMessage('Failed to load overload fines');
      // eslint-disable-next-line no-console
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const openDetails = (overload) => {
    setSelectedOverload(overload);
    setShowDetailsModal(true);
  };

  const closeDetails = () => {
    setShowDetailsModal(false);
    setSelectedOverload(null);
  };

  const getOwnerName = (overload) =>
    `${overload.first_name || ''} ${overload.middle_name || ''} ${overload.surname || ''}`.trim();

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
        setPagination((prev) => ({
          ...prev,
          current_page: page,
          per_page: pageSize ?? prev.per_page,
        }));
      },
    }),
    [pagination]
  );

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'sn',
        width: 80,
        align: 'center',
        render: (_, __, idx) => (
          <span className="text-sm font-medium text-gray-600">
            {(pagination.current_page - 1) * pagination.per_page + idx + 1}
          </span>
        ),
      },
      {
        title: 'Owner',
        key: 'owner',
        width: 300,
        onCell: () => ({
          style: {
            whiteSpace: 'normal',
            wordBreak: 'break-word',
            verticalAlign: 'top',
          },
        }),
        render: (_, row) => (
          <span className="block min-w-0 text-sm leading-snug text-slate-900">
            {getOwnerName(row) || EMPTY_VALUE}
          </span>
        ),
      },
      {
        title: 'Vehicle',
        key: 'vehicle',
        render: (_, row) => <span className="font-mono text-sm">{row.vehicle_num || EMPTY_VALUE}</span>,
      },
      {
        title: 'Amount',
        key: 'amount',
        align: 'right',
        money: false,
        render: (_, row) => <span className="text-sm">{formatMoney(row.bill_amount)}</span>,
      },
      {
        title: 'Bill Date',
        key: 'bill_date',
        render: (_, row) => (
          <span className="text-sm">{formatBillDate(row.bill_gen_at, { empty: EMPTY_VALUE })}</span>
        ),
      },
      {
        title: 'Control No.',
        key: 'control',
        render: (_, row) => <span className="font-mono text-sm">{row.contr_num || EMPTY_VALUE}</span>,
      },
      {
        title: 'Receipt',
        key: 'receipt',
        render: (_, row) => <span className="font-mono text-sm">{row.psp_receipt_num || EMPTY_VALUE}</span>,
      },
      {
        title: 'Status',
        key: 'status',
        align: 'center',
        render: (_, row) => {
          const { label, color } = getOverloadStatus(row);
          return (
            <Tag color={color} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {label}
            </Tag>
          );
        },
      },
      {
        title: 'Action',
        key: 'action',
        width: 120,
        align: 'center',
        render: (_, overload) => (
          <div className="flex items-center justify-center">
            <button
              type="button"
              onClick={() => openDetails(overload)}
              className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-white"
              style={{ backgroundColor: BRAND }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
              }}
            >
              View
            </button>
          </div>
        ),
      },
    ],
  // eslint-disable-next-line react-hooks/exhaustive-deps
    [pagination.current_page, pagination.per_page]
  );

  return (
    <div className="space-y-4">
      {successMessage && (
        <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
          <span className="block sm:inline">{successMessage}</span>
          <button onClick={() => setSuccessMessage('')} className="absolute top-0 bottom-0 right-0 px-4 py-3">
            <X size={16} />
          </button>
        </div>
      )}

      {errorMessage && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
          <span className="block sm:inline">{errorMessage}</span>
          <button onClick={() => setErrorMessage('')} className="absolute top-0 bottom-0 right-0 px-4 py-3">
            <X size={16} />
          </button>
        </div>
      )}

      <div className="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div className="min-w-[140px]">
          <label className="mb-1 block text-xs font-medium text-slate-600">Status</label>
          <Select
            value={statusFilter}
            onChange={(value) => {
              setStatusFilter(value);
              setPagination((prev) => ({ ...prev, current_page: 1 }));
            }}
            className="w-full min-w-[140px]"
            options={[
              { value: 'all', label: 'All Status' },
              { value: 'paid', label: 'Paid' },
              { value: 'not_paid', label: 'Not Paid' },
            ]}
          />
        </div>
        <div className="min-w-[160px]">
          <label className="mb-1 block text-xs font-medium text-slate-600">Sort by</label>
          <Select
            value={sortBy}
            onChange={setSortBy}
            className="w-full min-w-[160px]"
            options={[
              { value: 'created_at', label: 'Date' },
              { value: 'bill_gen_at', label: 'Bill Date' },
              { value: 'bill_amount', label: 'Amount' },
              { value: 'vehicle_num', label: 'Vehicle' },
            ]}
          />
        </div>
        <div className="min-w-[140px]">
          <label className="mb-1 block text-xs font-medium text-slate-600">Order</label>
          <Select
            value={sortOrder}
            onChange={setSortOrder}
            className="w-full min-w-[140px]"
            options={[
              { value: 'desc', label: 'Descending' },
              { value: 'asc', label: 'Ascending' },
            ]}
          />
        </div>
      </div>

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
          data={overloads}
          columns={columns}
          rowKey="id"
          loading={false}
          pagination={paginationConfig}
          searchPlaceholder="Search by owner, vehicle, control number..."
          showSearch
          serverSideSearch
          onSearchChange={setSearchQuery}
          rightAction={
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={() => loadOverloads()}
                disabled={loading}
                className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
              >
                Refresh
              </button>
              <button
                type="button"
                onClick={() => setShowNewModal(true)}
                className="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-white"
                style={{ backgroundColor: BRAND }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = BRAND_DARK;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = BRAND;
                }}
              >
                Bill
              </button>
            </div>
          }
        />
      </div>

      <CreateOverloadChargeModal
        isOpen={showNewModal}
        onClose={() => setShowNewModal(false)}
        onCreated={() => {
          setSuccessMessage('Overload charge created successfully');
          loadOverloads();
          Swal.fire({ icon: 'success', title: 'Success', text: 'Overload charge created successfully' });
        }}
      />

      <OverloadFineDetailsModal
        isOpen={showDetailsModal}
        overload={selectedOverload}
        onClose={closeDetails}
        onActionSuccess={() => {
          loadOverloads();
        }}
      />
    </div>
  );
}
