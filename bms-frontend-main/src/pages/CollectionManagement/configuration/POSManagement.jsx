import React, { useEffect, useMemo, useState } from 'react';
import { Edit3, Eye, Filter, Plus, Power, PowerOff } from 'lucide-react';
import { Tag } from 'antd';
import TerminalRegistrationForm from './components/TerminalRegistrationForm.jsx';
import TerminalDetailsModal from './components/TerminalDetailsModal.jsx';
import TerminalEditModal from './components/TerminalEditModal.jsx';
import BreadCrumb from '../../../common/components/BreadCrumb.jsx';
import DataTable from '../../../common/data/DataTable.jsx';
import CollectionLoader from '../components/CollectionLoader.jsx';
import { apiService } from '../../../services/api.jsx';
import { BRAND, BRAND_DARK, FormLabel, INPUT_CLASS } from './components/posTerminalModalUi.jsx';

const statusTag = (status) => {
  const value = String(status || '').toLowerCase();
  const map = {
    active: { color: 'green', label: 'Active' },
    inactive: { color: 'red', label: 'Inactive' },
    maintenance: { color: 'gold', label: 'Maintenance' },
    pending: { color: 'orange', label: 'Pending lane' },
  };
  const entry = map[value] || { color: 'default', label: status || 'Unknown' };
  return (
    <Tag color={entry.color} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
      {entry.label}
    </Tag>
  );
};

export default function POSManagement({ hideBreadcrumb = false }) {
  const [terminals, setTerminals] = useState([]);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  });
  const [loading, setLoading] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [showFilters, setShowFilters] = useState(false);
  const [showAddModal, setShowAddModal] = useState(false);
  const [showDetailsModal, setShowDetailsModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [selectedTerminalId, setSelectedTerminalId] = useState(null);
  const [selectedTerminal, setSelectedTerminal] = useState(null);
  const [loadingStatusId, setLoadingStatusId] = useState(null);

  const fetchTerminals = async (page = pagination.current_page, perPage = pagination.per_page) => {
    setLoading(true);
    try {
      const params = {
        page,
        per_page: perPage,
        ...(statusFilter ? { status: statusFilter } : {}),
        ...(typeFilter ? { terminal_type: typeFilter } : {}),
        ...(searchTerm ? { search: searchTerm } : {}),
      };

      const response = await apiService.getPosTerminals(params);
      if (response?.success) {
        setTerminals(response?.data?.terminals || []);
        setPagination(
          response?.data?.pagination || {
            current_page: 1,
            last_page: 1,
            per_page: perPage,
            total: 0,
          }
        );
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTerminals(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [statusFilter, typeFilter, searchTerm]);

  const isOnline = (terminal) => {
    if (!terminal?.last_heartbeat) return false;
    const diffMinutes = (Date.now() - new Date(terminal.last_heartbeat).getTime()) / (1000 * 60);
    return diffMinutes <= 5;
  };

  const formatLastSeen = (lastHeartbeat) => {
    if (!lastHeartbeat) return 'Never';
    const diffMinutes = Math.floor((Date.now() - new Date(lastHeartbeat).getTime()) / (1000 * 60));
    if (diffMinutes < 1) return 'Just now';
    if (diffMinutes < 60) return `${diffMinutes}m ago`;
    if (diffMinutes < 1440) return `${Math.floor(diffMinutes / 60)}h ago`;
    return new Date(lastHeartbeat).toLocaleDateString();
  };

  const handleStatusUpdate = async (terminalId, newStatus) => {
    setLoadingStatusId(terminalId);
    try {
      const response = await apiService.updatePosTerminalStatus(terminalId, newStatus);
      if (response?.success) {
        fetchTerminals(pagination.current_page);
      } else {
        // eslint-disable-next-line no-alert
        alert(`Failed to update terminal status: ${response?.message || 'Unknown error'}`);
      }
    } finally {
      setLoadingStatusId(null);
    }
  };

  const handleViewDetails = (terminalId) => {
    setSelectedTerminalId(terminalId);
    setShowDetailsModal(true);
  };

  const handleEditTerminal = (terminal) => {
    setSelectedTerminal(terminal);
    setShowEditModal(true);
  };

  const handleEditFromDetails = (terminal) => {
    setShowDetailsModal(false);
    handleEditTerminal(terminal);
  };

  const pendingCount = terminals.filter(
    (t) => t.status === 'pending' || !t.lane_id || !t.lane_number
  ).length;

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'serial',
        width: 72,
        align: 'center',
        render: (_, __, index) => (
          <span className="text-sm font-medium text-slate-600">
            {(pagination.current_page - 1) * pagination.per_page + index + 1}
          </span>
        ),
      },
      {
        title: 'Device name',
        dataIndex: 'name',
        key: 'name',
        searchable: true,
        render: (name) => <span className="text-sm font-medium text-black">{name}</span>,
      },
      {
        title: 'Lane',
        dataIndex: 'lane_number',
        key: 'lane_number',
        searchable: true,
        render: (lane) => (
          <span className="text-sm text-black">{lane ? `Lane ${lane}` : 'Unassigned'}</span>
        ),
      },
      {
        title: 'Type',
        dataIndex: 'terminal_type',
        key: 'terminal_type',
        render: (type) => <span className="text-sm text-slate-700">{type || 'POS'}</span>,
      },
      {
        title: 'Status',
        dataIndex: 'status',
        key: 'status',
        render: (status) => statusTag(status),
      },
      {
        title: 'Online',
        key: 'online',
        render: (_, terminal) => {
          const online = isOnline(terminal);
          return (
            <Tag color={online ? 'green' : 'default'} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {online ? 'Online' : 'Offline'}
            </Tag>
          );
        },
      },
      {
        title: 'MAC address',
        dataIndex: 'mac_address',
        key: 'mac_address',
        searchable: true,
        render: (mac) => <span className="text-sm font-mono text-slate-700">{mac}</span>,
      },
      {
        title: 'IP address',
        dataIndex: 'ip_address',
        key: 'ip_address',
        searchable: true,
        render: (ip) => <span className="text-sm font-mono text-slate-700">{ip || '—'}</span>,
      },
      {
        title: 'Last seen',
        key: 'last_seen',
        render: (_, terminal) => (
          <span className="text-sm text-slate-600">{formatLastSeen(terminal.last_heartbeat)}</span>
        ),
      },
      {
        title: 'Actions',
        key: 'actions',
        fixed: 'right',
        width: 280,
        render: (_, terminal) => (
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => handleViewDetails(terminal.id)}
              className="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm text-white"
              style={{ backgroundColor: BRAND }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
              }}
              title="View details"
            >
              <Eye size={14} />
              <span>View</span>
            </button>
            <button
              type="button"
              onClick={() => handleEditTerminal(terminal)}
              className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 hover:border-[#962E32]/30 hover:bg-[#fff5f5]"
              title="Configure"
            >
              <Edit3 size={14} />
              <span>Edit</span>
            </button>
            {terminal.status !== 'active' ? (
              <button
                type="button"
                onClick={() => handleStatusUpdate(terminal.id, 'active')}
                disabled={loadingStatusId === terminal.id}
                className="inline-flex items-center rounded-lg p-1.5 text-green-600 hover:bg-green-50 disabled:opacity-50"
                title="Activate"
              >
                <Power size={16} />
              </button>
            ) : (
              <button
                type="button"
                onClick={() => handleStatusUpdate(terminal.id, 'inactive')}
                disabled={loadingStatusId === terminal.id}
                className="inline-flex items-center rounded-lg p-1.5 text-red-600 hover:bg-red-50 disabled:opacity-50"
                title="Deactivate"
              >
                <PowerOff size={16} />
              </button>
            )}
          </div>
        ),
      },
    ],
    [pagination.current_page, pagination.per_page, loadingStatusId]
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
        fetchTerminals(page, pageSize);
      },
    }),
    [pagination]
  );

  return (
    <div className="space-y-4">
      {!hideBreadcrumb && (
        <BreadCrumb
          crumbs={[
            { label: 'Toll Management', link: '/collection-management/dashboard' },
            { label: 'Manage Settings', link: '/collection-management/configuration' },
            { label: 'POS Configuration', link: '/collection-management/configuration', current: true },
          ]}
        />
      )}

      {pendingCount > 0 && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          <span className="font-medium">{pendingCount}</span> device{pendingCount === 1 ? '' : 's'} awaiting lane
          configuration. Open a device and assign a lane to activate it.
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
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
              <FormLabel>Status</FormLabel>
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className={INPUT_CLASS}
              >
                <option value="">All status</option>
                <option value="pending">Pending lane</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="maintenance">Maintenance</option>
              </select>
            </div>
            <div>
              <FormLabel>Terminal type</FormLabel>
              <select value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)} className={INPUT_CLASS}>
                <option value="">All types</option>
                <option value="POS">POS</option>
                <option value="Kiosk">Kiosk</option>
                <option value="Mobile">Mobile</option>
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
          data={terminals}
          loading={false}
          pagination={paginationConfig}
          rowKey={(record) => record.id}
          onSearchChange={setSearchTerm}
          showSearch
          showRefresh={false}
          searchPlaceholder="Search by name, MAC, IP, lane..."
          scroll={{ x: 1200 }}
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
                onClick={() => setShowAddModal(true)}
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
                <span>Register POS</span>
              </button>
            </div>
          }
        />
      </div>

      <TerminalRegistrationForm
        isOpen={showAddModal}
        onClose={() => setShowAddModal(false)}
        onSuccess={() => fetchTerminals(pagination.current_page)}
      />

      <TerminalDetailsModal
        isOpen={showDetailsModal}
        onClose={() => setShowDetailsModal(false)}
        terminalId={selectedTerminalId}
        onEdit={handleEditFromDetails}
      />

      <TerminalEditModal
        isOpen={showEditModal}
        onClose={() => setShowEditModal(false)}
        onSuccess={() => fetchTerminals(pagination.current_page)}
        terminal={selectedTerminal}
      />
    </div>
  );
}
