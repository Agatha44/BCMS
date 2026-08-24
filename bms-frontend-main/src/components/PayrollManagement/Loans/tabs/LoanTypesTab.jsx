import { App, Button, Switch, Tag } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { DataTable } from '../../../../common/data';
import { formatMoney } from '../../../../common/utils/numberFormat.js';
import CollectionLoader from '../../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { payrollService } from '../../../../services/payrollService.js';
import AddLoanTypeModal from '../AddLoanTypeModal.jsx';
import LoanTypeDetailsModal from '../LoanTypeDetailsModal.jsx';

const LoanTypesTab = ({ canEdit = false, active = false }) => {
  const { message, modal } = App.useApp();

  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState([]);
  const [pagination, setPagination] = useState(() => ({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: true,
    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
    pageSizeOptions: ['10', '15', '20', '50', '100'],
  }));

  const [addState, setAddState] = useState(() => ({ open: false }));
  const [viewState, setViewState] = useState(() => ({ open: false, record: null }));

  const refresh = useCallback(
    async (page = 1, pageSize = 15) => {
      setLoading(true);
      try {
        const response = await payrollService.getLoanTypes({ page, per_page: pageSize });

        if (!response?.success) {
          setRows([]);
          setPagination((p) => ({ ...p, current: page, pageSize, total: 0 }));
          message.error(response?.message || 'Failed to fetch loan types');
          return;
        }

        const dataRows = response.data?.['0'] || [];
        const p = response.data?.pagination || {};
        const totalCount = Number(p.total || 0);
        const current = Number(p.current_page || page);
        const perPage = Number(p.per_page || pageSize);

        setRows(
          dataRows.map((it) => ({
            key: it.loan_type_id,
            ...it,
          }))
        );
        setPagination((prev) => ({
          ...prev,
          current,
          pageSize: perPage,
          total: totalCount,
        }));
      } catch (e) {
        setRows([]);
        message.error(e?.message || 'Failed to fetch loan types');
      } finally {
        setLoading(false);
      }
    },
    [message]
  );

  const toggleStatus = useCallback(
    async (record) => {
      const loanTypeId = record?.loan_type_id;
      if (!loanTypeId) {
        message.error('Loan type ID is missing.');
        return;
      }

      const currentActive = record?.is_active === 1 || record?.is_active === true || record?.is_active === '1';
      const action = currentActive ? 'deactivate' : 'activate';

      modal.confirm({
        title: `Are you sure you want to ${action} this loan type?`,
        content: record?.loan_name ? `"${record.loan_name}" will be ${action}d.` : undefined,
        okText: `Yes, ${action}`,
        cancelText: 'Cancel',
        okType: currentActive ? 'default' : 'primary',
        async onOk() {
          setRows((prev) =>
            prev.map((r) => {
              const rid = r?.loan_type_id;
              if (rid === loanTypeId) {
                return { ...r, is_active: currentActive ? 0 : 1 };
              }
              return r;
            })
          );

          const response = await payrollService.toggleLoanTypeStatus(loanTypeId, { is_active: currentActive ? 0 : 1 });
          if (!response?.success) {
            setRows((prev) =>
              prev.map((r) => {
                const rid = r?.loan_type_id;
                if (rid === loanTypeId) {
                  return { ...r, is_active: currentActive ? 1 : 0 };
                }
                return r;
              })
            );
            message.error(response?.message || 'Failed to update status.');
            return;
          }

          message.success(response?.message || 'Status updated.');
          refresh(pagination.current, pagination.pageSize);
        },
      });
    },
    [message, modal, refresh, pagination.current, pagination.pageSize]
  );

  useEffect(() => {
    if (!active) return;
    refresh(pagination.current, pagination.pageSize);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [active]);

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'sn',
        width: 70,
        align: 'center',
        render: (_, __, index) => {
          const current = pagination.current || 1;
          const pageSize = pagination.pageSize || 15;
          return (current - 1) * pageSize + index + 1;
        },
      },
      {
        title: 'Loan Name',
        dataIndex: 'loan_name',
        key: 'loan_name',
        searchable: true,
        ellipsis: true,
      },
      {
        title: 'Interest',
        dataIndex: 'has_interest',
        key: 'has_interest',
        width: 110,
        align: 'center',
        render: (v) => {
          const enabled = Number(v) === 1 || v === true || v === '1';
          return enabled ? <Tag color="gold">Yes</Tag> : <Tag> No </Tag>;
        },
      },
      {
        title: 'Interest %',
        dataIndex: 'interest_percentage',
        key: 'interest_percentage',
        width: 110,
        align: 'right',
        render: (v, record) => {
          const enabled = Number(record?.has_interest) === 1 || record?.has_interest === true || record?.has_interest === '1';
          if (!enabled) return '—';
          return v != null && v !== '' ? `${v}%` : '—';
        },
      },
      {
        title: 'Min Amount',
        dataIndex: 'minimum_loan_amount',
        key: 'minimum_loan_amount',
        width: 140,
        align: 'right',
        render: (v) => formatMoney(v),
      },
      {
        title: 'Max Amount',
        dataIndex: 'maximum_loan_amount',
        key: 'maximum_loan_amount',
        width: 140,
        align: 'right',
        render: (v) => formatMoney(v),
      },
      {
        title: 'Status',
        dataIndex: 'is_active',
        key: 'is_active',
        width: 110,
        align: 'center',
        render: (_, record) => {
          const activeStatus = record?.is_active === 1 || record?.is_active === true || record?.is_active === '1';
          return (
            <Switch
              size="small"
              checked={activeStatus}
              checkedChildren="Active"
              unCheckedChildren="Inactive"
              disabled={!canEdit}
              onChange={() => toggleStatus(record)}
              style={{
                backgroundColor: activeStatus ? '#962E32' : '#d9d9d9',
              }}
            />
          );
        },
      },
      {
        title: 'Action',
        key: 'action',
        width: 110,
        align: 'center',
        render: (_, record) => (
          <Button
            type="primary"
            size="small"
            onClick={() => setViewState({ open: true, record })}
            style={{ backgroundColor: '#962E32', borderColor: '#962E32' }}
          >
            View
          </Button>
        ),
      },
    ],
    [pagination.current, pagination.pageSize, canEdit, toggleStatus]
  );

  return (
    <>
      <div className="mt-2">
        <div className="relative mt-3">
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
            data={rows}
            loading={false}
            pagination={pagination}
            showSearch={true}
            showRefresh={true}
            onRefresh={() => refresh(pagination.current, pagination.pageSize)}
            rightAction={
              <Button
                type="primary"
                onClick={() => setAddState({ open: true })}
                disabled={!canEdit}
                style={{ backgroundColor: '#962E32', borderColor: '#962E32' }}
              >
                Add Loan Type
              </Button>
            }
            onChange={(paginationInfo) => {
              const newPage = paginationInfo?.current || 1;
              const newPageSize = Number(paginationInfo?.pageSize) || 15;
              refresh(newPage, newPageSize);
            }}
            rowKey="key"
            size="middle"
            bordered={true}
          />
        </div>
      </div>

      <AddLoanTypeModal
        open={addState.open}
        onClose={() => setAddState({ open: false })}
        onSaved={() => {
          setAddState({ open: false });
          refresh(pagination.current, pagination.pageSize);
        }}
      />

      <LoanTypeDetailsModal
        open={viewState.open}
        record={viewState.record}
        canEdit={canEdit}
        onClose={() => setViewState({ open: false, record: null })}
        onSaved={() => {
          setViewState({ open: false, record: null });
          refresh(pagination.current, pagination.pageSize);
        }}
      />
    </>
  );
};

LoanTypesTab.propTypes = {
  canEdit: PropTypes.bool,
  active: PropTypes.bool,
};

export default LoanTypesTab;

