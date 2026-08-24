import { useCallback, useEffect, useMemo, useState } from 'react';
import { ArrowRightLeft, Eye, Search } from 'lucide-react';
import AsyncSelect from 'react-select/async';
import Swal from 'sweetalert2';
import { Button } from 'antd';

import DataTable from '../../../common/data/DataTable.jsx';
import CollectionLoader from '../components/CollectionLoader.jsx';
import ShiftRecordDetailModal from './ShiftRecordDetailModal.jsx';
import ShiftTransferModal from './ShiftTransferModal.jsx';
import { apiService } from '../../../services/api.jsx';
import { extractApiList } from '../../../common/utils/apiList.js';

const BRAND = '#962E32';

const inputClassName =
  'w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-800 focus:border-[#962E32] focus:outline-none focus:ring-1 focus:ring-[#962E32]/20';

const asyncSelectStyles = {
  control: (base, state) => ({
    ...base,
    borderColor: state.isFocused ? BRAND : '#e2e8f0',
    borderRadius: '8px',
    minHeight: '40px',
    boxShadow: state.isFocused ? '0 0 0 3px rgba(150, 46, 50, 0.12)' : 'none',
    '&:hover': { borderColor: BRAND },
  }),
  menuPortal: (base) => ({ ...base, zIndex: 9999 }),
  menu: (base) => ({ ...base, zIndex: 9999 }),
};

const formatDateTime = (value) => {
  if (!value) return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const loadOperatorOptions = async (inputValue) => {
  const query = (inputValue || '').trim();
  if (query.length < 2) return [];

  try {
    const response = await apiService.getUsers({ search: query, per_page: 20, page: 1 });
    const users = response?.data?.users ?? extractApiList(response?.data);
    return (Array.isArray(users) ? users : []).map((user) => {
      const name = [user.first_name, user.middle_name, user.surname].filter(Boolean).join(' ');
      return {
        value: user.id,
        label: name ? `${name} (${user.username ?? user.id})` : String(user.username ?? user.id),
        user,
      };
    });
  } catch {
    return [];
  }
};

export default function ShiftRecordsTab() {
  const [selectedOperator, setSelectedOperator] = useState(null);
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const [detailRecord, setDetailRecord] = useState(null);
  const [transferRecord, setTransferRecord] = useState(null);

  const handleSearch = useCallback(async () => {
    if (!selectedOperator?.value) {
      await Swal.fire({ icon: 'warning', title: 'Select an operator', text: 'Choose an operator before searching.' });
      return;
    }

    setLoading(true);
    setSearched(true);
    try {
      const response = await apiService.queryShiftRecords({ user_id: selectedOperator.value });
      const rows = extractApiList(response?.data);
      setRecords(Array.isArray(rows) ? rows : []);
    } catch (error) {
      setRecords([]);
      await Swal.fire({
        icon: 'error',
        title: 'Search failed',
        text: error instanceof Error ? error.message : 'Unable to load shift records.',
      });
    } finally {
      setLoading(false);
    }
  }, [selectedOperator]);

  const columns = useMemo(
    () => [
      {
        title: '#',
        key: 'index',
        width: 56,
        render: (_, __, index) => index + 1,
      },
      {
        title: 'Open Counter',
        dataIndex: 'open_counter',
        key: 'open_counter',
        render: (value) => formatDateTime(value) ?? <span className="text-slate-400">N/A</span>,
      },
      {
        title: 'Close Counter',
        dataIndex: 'close_counter',
        key: 'close_counter',
        render: (value) => formatDateTime(value) ?? <span className="text-slate-400">N/A</span>,
      },
      {
        title: 'Shift',
        dataIndex: 'name',
        key: 'name',
        render: (value) => value ?? <span className="text-slate-400">N/A</span>,
      },
      {
        title: 'Lane',
        dataIndex: 'lane_no',
        key: 'lane_no',
        render: (value) => value ?? <span className="text-slate-400">N/A</span>,
      },
      {
        title: 'Actions',
        key: 'actions',
        width: 200,
        render: (_, record) => (
          <div className="flex flex-wrap gap-2">
            <Button
              size="small"
              icon={<Eye size={14} />}
              onClick={() => setDetailRecord(record)}
            >
              View
            </Button>
            <Button
              size="small"
              type="primary"
              icon={<ArrowRightLeft size={14} />}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
              onClick={() => setTransferRecord(record)}
            >
              Transfer
            </Button>
          </div>
        ),
      },
    ],
    []
  );

  useEffect(() => {
    if (!selectedOperator) {
      setRecords([]);
      setSearched(false);
    }
  }, [selectedOperator]);

  return (
    <div className="space-y-4">
      <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
        <div className="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
          <div>
            <label
              className="mb-1 block text-xs font-semibold tracking-[0.01em]"
              style={{ color: BRAND }}
            >
              Operator
            </label>
            <AsyncSelect
              cacheOptions
              defaultOptions
              loadOptions={loadOperatorOptions}
              value={selectedOperator}
              onChange={setSelectedOperator}
              placeholder="Search operator by name or username…"
              isClearable
              styles={asyncSelectStyles}
              menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
              className={inputClassName}
            />
          </div>
          <Button
            type="primary"
            icon={<Search size={16} />}
            loading={loading}
            onClick={handleSearch}
            style={{ backgroundColor: BRAND, borderColor: BRAND, height: 40 }}
          >
            Search
          </Button>
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
          columns={columns}
          data={records}
          loading={false}
          showSearch={false}
          showRefresh={false}
          rowKey={(record) => record.id}
          pagination={{
            pageSize: 10,
            showTotal: () => null,
            showQuickJumper: false,
          }}
        />
      </div>

      {!loading && searched && records.length === 0 ? (
        <p className="text-center text-sm text-slate-500">No shift records found for this operator.</p>
      ) : null}

      <ShiftRecordDetailModal
        open={Boolean(detailRecord)}
        record={detailRecord}
        onClose={() => setDetailRecord(null)}
      />

      <ShiftTransferModal
        open={Boolean(transferRecord)}
        record={transferRecord}
        operator={selectedOperator}
        onClose={() => setTransferRecord(null)}
        onTransferred={() => {
          setTransferRecord(null);
          handleSearch();
        }}
      />
    </div>
  );
}
