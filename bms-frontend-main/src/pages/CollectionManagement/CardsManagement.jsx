import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, Eye, Printer } from 'lucide-react';
import Swal from 'sweetalert2';

import DataTable from '../../common/data/DataTable.jsx';
import CollectionLoader from './components/CollectionLoader.jsx';
import PrintCustomerCardModal from '../../common/components/modals/PrintCustomerCardModal.jsx';
import { apiService } from '../../services/api.jsx';

const emptyPagination = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0,
};

function extractCardsAndPagination(data) {
  if (!data || typeof data !== 'object') return { cards: [], pagination: emptyPagination };
  const cards = data.cards ?? data.customer_cards ?? [];
  const pagination = data.pagination || emptyPagination;
  return {
    cards: Array.isArray(cards) ? cards : [],
    pagination: { ...emptyPagination, ...pagination },
  };
}

export default function CardsManagement() {
  const [cards, setCards] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showPrintModal, setShowPrintModal] = useState(false);
  const [isInitialLoad, setIsInitialLoad] = useState(true);

  const [pagination, setPagination] = useState(emptyPagination);

  const [filters, setFilters] = useState({
    q: '',
    status: '',
    from: '',
    to: '',
    request_source: '', // omit = office default; 'all' = every source
    sort_by: 'created_at',
    sort_order: 'desc',
    per_page: 15,
    page: 1,
  });

  const fetchCards = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        page: filters.page,
        per_page: filters.per_page,
        sort_by: filters.sort_by,
        sort_order: filters.sort_order,
      };
      if (filters.q) params.q = filters.q;
      if (filters.status) params.status = filters.status;
      if (filters.from) params.from = filters.from;
      if (filters.to) params.to = filters.to;
      if (filters.request_source === 'all') params.request_source = 'all';

      const response = await apiService.getCustomerCards(params);
      if (response.success && response.data) {
        const { cards: rows, pagination: pag } = extractCardsAndPagination(response.data);
        setCards(rows);
        setPagination(pag);
      } else {
        setCards([]);
        setPagination(emptyPagination);
        setError(response.message || 'Unexpected data format received from server');
      }
    } catch (err) {
      setCards([]);
      setPagination(emptyPagination);
      setError('An error occurred while fetching customer cards');
      // eslint-disable-next-line no-console
      console.error('Error fetching customer cards:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCards();
    setIsInitialLoad(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!isInitialLoad) fetchCards();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.page, isInitialLoad]);

  useEffect(() => {
    if (!isInitialLoad) fetchCards();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    filters.per_page,
    filters.sort_by,
    filters.sort_order,
    filters.status,
    filters.from,
    filters.to,
    filters.request_source,
    isInitialLoad,
  ]);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (!isInitialLoad) fetchCards();
    }, 500);
    return () => clearTimeout(timeoutId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.q, isInitialLoad]);

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value, page: key === 'page' ? value : 1 }));
  };

  const handleSearch = (searchTerm) => handleFilterChange('q', searchTerm);

  const openPreview = (row) => {
    const lines = [
      row.card_reference && `<p><strong>Card reference:</strong> ${row.card_reference}</p>`,
      row.account_no && `<p><strong>Account no.:</strong> ${row.account_no}</p>`,
      row.customer_name && `<p><strong>Customer:</strong> ${row.customer_name}</p>`,
      row.status && `<p><strong>Status:</strong> ${row.status}</p>`,
      row.preview_qr_payload && `<p class="text-xs break-all"><strong>Preview QR payload:</strong> ${row.preview_qr_payload}</p>`,
    ]
      .filter(Boolean)
      .join('');
    Swal.fire({
      title: 'Card request',
      html: lines || '<p>No details</p>',
      width: 560,
      confirmButtonText: 'Close',
    });
  };

  const columns = useMemo(
    () => [
      {
        title: '#',
        key: 'serial',
        width: 70,
        render: (_, row) => {
          const currentPage = pagination?.current_page || 1;
          const perPage = filters.per_page || 15;
          const idx = cards.findIndex((c) => c.id === row.id);
          const serialNumber = idx >= 0 ? (currentPage - 1) * perPage + idx + 1 : 0;
          return <span className="text-sm text-gray-600 font-medium">{serialNumber}</span>;
        },
      },
      {
        title: 'Account No.',
        dataIndex: 'account_no',
        key: 'account_no',
        searchable: true,
        render: (v) => <span className="font-mono text-sm">{v || '-'}</span>,
      },
      {
        title: 'Customer',
        dataIndex: 'customer_name',
        key: 'customer_name',
        render: (v) => <span className="text-sm">{v || '-'}</span>,
      },
      {
        title: 'Card reference',
        dataIndex: 'card_reference',
        key: 'card_reference',
        searchable: true,
        render: (v) => <span className="font-mono text-sm">{v || '-'}</span>,
      },
      {
        title: 'Status',
        dataIndex: 'status',
        key: 'status',
        render: (v) => (
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
            {v || '-'}
          </span>
        ),
      },
      {
        title: 'Created',
        dataIndex: 'created_at',
        key: 'created_at',
        render: (v) => <span className="text-sm text-gray-600">{v ? new Date(v).toLocaleString() : '-'}</span>,
      },
      {
        title: 'Created by',
        dataIndex: 'created_by_name',
        key: 'created_by_name',
        render: (v) => <span className="text-sm">{v || '-'}</span>,
      },
      {
        title: 'Actions',
        key: 'actions',
        render: (_, row) => (
          <button
            type="button"
            onClick={() => openPreview(row)}
            className="btn-primary flex items-center space-x-1 px-3 py-1.5 text-sm"
            style={{ backgroundColor: '#962E32', borderColor: '#962E32', color: '#ffffff' }}
          >
            <Eye size={16} className="text-white" />
            <span>View</span>
          </button>
        ),
      },
    ],
    [cards, filters.per_page, pagination]
  );

  const paginationConfig = useMemo(
    () => ({
      current: pagination.current_page,
      pageSize: pagination.per_page,
      total: pagination.total,
      showTotal: () => null,
      showQuickJumper: false,
      onChange: (page, pageSize) => {
        if (pageSize !== filters.per_page) handleFilterChange('per_page', pageSize);
        handleFilterChange('page', page);
      },
    }),
    [filters.per_page, pagination]
  );

  return (
    <div className="space-y-6">
      <PrintCustomerCardModal
        isOpen={showPrintModal}
        onClose={() => setShowPrintModal(false)}
        onCardCreated={() => fetchCards()}
      />

      <div className="flex items-center justify-end gap-3 flex-wrap">
        <button
          type="button"
          onClick={() => setShowPrintModal(true)}
          className="btn-primary flex items-center space-x-2"
          style={{ backgroundColor: '#962E32', borderColor: '#962E32', color: '#ffffff' }}
        >
          <Printer size={16} />
          <span>Print card</span>
        </button>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex">
            <AlertCircle className="h-5 w-5 text-red-400 mt-0.5 shrink-0" />
            <div className="ml-3">
              <h3 className="text-sm font-medium text-red-800">Error</h3>
              <p className="text-sm text-red-700 mt-1">{error}</p>
            </div>
          </div>
        </div>
      )}

      <div className="bg-white rounded-lg shadow">
        {loading ? (
          <CollectionLoader />
        ) : (
        <DataTable
          columns={columns}
          data={cards}
          loading={false}
          pagination={paginationConfig}
          onSearchChange={handleSearch}
          showSearch
          showRefresh={false}
          searchPlaceholder="Search by account no. or card reference..."
        />
        )}
      </div>
    </div>
  );
}
