import { App, Button, Switch } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { DataTable } from '../../../../common/data';
import CollectionLoader from '../../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { payrollService } from '../../../../services/payrollService.js';
import AddArrearsReasonModal from '../AddArrearsReasonModal.jsx';
import ArrearsReasonDetailsModal from '../ArrearsReasonDetailsModal.jsx';

const ArrearsReasonsTab = ({ canEdit = false, active = false }) => {
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
        const response = await payrollService.getArrearsReasons({ page, per_page: pageSize });

        if (!response.success) {
          setRows([]);
          setPagination((p) => ({ ...p, current: page, pageSize, total: 0 }));
          message.error(response.message);
          return;
        }

        // No fallbacks: API returns { data: { "0": [...], pagination: {...} } }
        const dataRows = response.data['0'];
        const p = response.data.pagination;

        const totalCount = Number(p.total);
        const current = Number(p.current_page);
        const perPage = Number(p.per_page);

        setRows(
          (dataRows || []).map((it) => ({
            key: it.arrears_reason_id,
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
        message.error(e.message);
      } finally {
        setLoading(false);
      }
    },
    [message]
  );

  const toggleStatus = useCallback(
    async (record) => {
      const arrearsReasonId = record?.arrears_reason_id;
      if (!arrearsReasonId) {
        message.error('Arrears reason ID is missing.');
        return;
      }

      const currentActive = record?.is_active === 1 || record?.is_active === true || record?.is_active === '1';
      const action = currentActive ? 'deactivate' : 'activate';

      modal.confirm({
        title: `Are you sure you want to ${action} this arrears reason?`,
        content: record?.reason_name ? `"${record.reason_name}" will be ${action}d.` : undefined,
        okText: `Yes, ${action}`,
        cancelText: 'Cancel',
        okType: currentActive ? 'default' : 'primary',
        async onOk() {
          setRows((prev) =>
            prev.map((r) => {
              const rid = r?.arrears_reason_id;
              if (rid === arrearsReasonId) {
                return { ...r, is_active: currentActive ? 0 : 1 };
              }
              return r;
            })
          );

          const response = await payrollService.toggleArrearsReasonStatus(arrearsReasonId, {
            is_active: currentActive ? 0 : 1,
          });
          if (!response?.success) {
            setRows((prev) =>
              prev.map((r) => {
                const rid = r?.arrears_reason_id;
                if (rid === arrearsReasonId) {
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
      { title: 'Reason Name', dataIndex: 'reason_name', key: 'reason_name', searchable: true, ellipsis: true },
      { title: 'Code', dataIndex: 'reason_code', key: 'reason_code', searchable: true, width: 110 },
      { title: 'Start Date', dataIndex: 'start_date', key: 'start_date' },
      { title: 'End Date', dataIndex: 'end_date', key: 'end_date' },
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
      <div className="mt-2 w-full min-w-0">
        <div className="relative mt-3 w-full min-w-0">
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
              <Button type="primary" onClick={() => setAddState({ open: true })} disabled={!canEdit}>
                Add Arrears Reason
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
            tableLayout="fixed"
            style={{ width: '100%' }}
          />
        </div>
      </div>

      <AddArrearsReasonModal
        open={addState.open}
        onClose={() => setAddState({ open: false })}
        onSaved={() => {
          setAddState({ open: false });
          refresh(pagination.current, pagination.pageSize);
        }}
      />

      <ArrearsReasonDetailsModal
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

ArrearsReasonsTab.propTypes = {
  canEdit: PropTypes.bool,
  active: PropTypes.bool,
};

export default ArrearsReasonsTab;

