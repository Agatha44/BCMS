import { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert, Button, Input, Select } from 'antd';
import { PlusOutlined, SearchOutlined } from '@ant-design/icons';
import { Loader2, Settings2 } from 'lucide-react';

import DataTable from '../../../common/data/DataTable.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../../services/api.jsx';
import RoleManageModal from './RoleManageModal.jsx';
import { apiMessage, BRAND, BRAND_DARK, isRoleActive } from './roleUtils.js';

const LegacyRolesList = () => {
  const [roles, setRoles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isInitialLoad, setIsInitialLoad] = useState(true);

  const [showManageModal, setShowManageModal] = useState(false);
  const [manageRoleId, setManageRoleId] = useState(null);
  const [loadingManageId, setLoadingManageId] = useState(null);

  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  });

  const [filters, setFilters] = useState({
    search: '',
    status: undefined,
    per_page: 15,
    page: 1,
  });

  const fetchRoles = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        page: filters.page,
        per_page: filters.per_page,
      };
      if (filters.search?.trim()) params.search = filters.search.trim();
      if (filters.status !== undefined && filters.status !== null && filters.status !== '') {
        params.status = filters.status;
      }

      const response = await apiService.getRolesList(params);
      if (response.success && response.data?.roles && response.data?.pagination) {
        setRoles(response.data.roles);
        setPagination(response.data.pagination);
      } else {
        setRoles([]);
        setPagination((prev) => ({ ...prev, total: 0 }));
        setError(apiMessage(response, 'Failed to load roles'));
      }
    } catch (err) {
      setRoles([]);
      setError('An error occurred while fetching roles');
      console.error('Error fetching roles:', err);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => {
    fetchRoles();
    setIsInitialLoad(false);
  }, []);

  useEffect(() => {
    if (!isInitialLoad) fetchRoles();
  }, [filters.page, isInitialLoad, fetchRoles]);

  useEffect(() => {
    if (!isInitialLoad && (filters.status !== undefined || filters.per_page !== 15)) {
      fetchRoles();
    }
  }, [filters.per_page, filters.status, isInitialLoad, fetchRoles]);

  useEffect(() => {
    const t = setTimeout(() => {
      if (!isInitialLoad) fetchRoles();
    }, 500);
    return () => clearTimeout(t);
  }, [filters.search, isInitialLoad, fetchRoles]);

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({
      ...prev,
      [key]: value,
      page: key === 'page' ? value : 1,
    }));
  };

  const openCreateModal = () => {
    setManageRoleId(null);
    setShowManageModal(true);
  };

  const openManageModal = (record) => {
    setLoadingManageId(record.id);
    setManageRoleId(record.id);
    setShowManageModal(true);
    setLoadingManageId(null);
  };

  const closeManageModal = () => {
    setShowManageModal(false);
    setManageRoleId(null);
  };

  const columns = useMemo(
    () => [
      {
        title: '#',
        key: 'serial',
        width: 70,
        align: 'center',
        render: (_, record) => {
          const page = pagination.current_page || 1;
          const perPage = filters.per_page || 15;
          const idx = roles.findIndex((r) => r.id === record.id);
          return (
            <span className="text-sm font-medium text-slate-600">
              {idx >= 0 ? (page - 1) * perPage + idx + 1 : '—'}
            </span>
          );
        },
      },
      {
        title: 'Name',
        dataIndex: 'name',
        key: 'name',
        render: (v) => <span className="text-sm font-semibold text-black">{v}</span>,
      },
      {
        title: 'Description',
        dataIndex: 'description',
        key: 'description',
        render: (v) => (
          <span className="block max-w-md truncate text-sm text-slate-700" title={v || ''}>
            {v || '—'}
          </span>
        ),
      },
      {
        title: 'Status',
        key: 'status',
        width: 120,
        align: 'center',
        render: (_, record) => {
          const active = isRoleActive(record.is_active);
          return (
            <div className="flex justify-center">
              <span
                className={`inline-flex min-w-[84px] items-center justify-center rounded-full px-2.5 py-1 text-xs font-semibold ${
                  active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                }`}
              >
                {active ? 'Active' : 'Inactive'}
              </span>
            </div>
          );
        },
      },
      {
        title: 'Actions',
        key: 'actions',
        align: 'center',
        width: 120,
        render: (_, record) => (
          <div className="flex items-center justify-center">
            <button
              type="button"
              onClick={() => openManageModal(record)}
              disabled={loadingManageId === record.id}
              className="inline-flex items-center space-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
              title="Manage role and modules"
            >
              {loadingManageId === record.id ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <Settings2 size={16} />
              )}
              <span>Manage</span>
            </button>
          </div>
        ),
      },
    ],
    [roles, filters.per_page, pagination, loadingManageId]
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

  const addRoleButton = (
    <Button
      type="primary"
      icon={<PlusOutlined />}
      onClick={openCreateModal}
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
      Add Role
    </Button>
  );

  return (
    <div className="space-y-4">
      {error ? <Alert type="error" showIcon message={error} closable onClose={() => setError(null)} /> : null}

      <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Input
            placeholder="Search roles..."
            prefix={<SearchOutlined />}
            value={filters.search}
            onChange={(e) => handleFilterChange('search', e.target.value)}
            allowClear
          />
          <Select
            placeholder="Filter by status"
            allowClear
            value={filters.status}
            onChange={(v) => handleFilterChange('status', v)}
            options={[
              { value: 1, label: 'Active' },
              { value: 0, label: 'Inactive' },
            ]}
            className="w-full"
          />
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
          columns={columns}
          data={roles}
          loading={false}
          pagination={paginationConfig}
          showSearch={false}
          showRefresh
          onRefresh={fetchRoles}
          rightAction={addRoleButton}
          rowKey="id"
        />
        </div>
      </div>

      <RoleManageModal
        open={showManageModal}
        roleId={manageRoleId}
        onClose={closeManageModal}
        onSaved={() => fetchRoles()}
      />
    </div>
  );
};

export default LegacyRolesList;
