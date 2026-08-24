import { App, Button, Switch } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { extractArrayFromResponse } from '../../../../common/utils/employeeUtils.jsx';
import { DataTable } from '../../../../common/data';
import { formatMoney } from '../../../../common/utils/numberFormat.js';
import CollectionLoader from '../../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { payrollService } from '../../../../services/payrollService.js';
import AddEmployeeLoanModal from '../AddEmployeeLoanModal.jsx';
import EmployeeLoanDetailsModal from '../EmployeeLoanDetailsModal.jsx';
import { BRAND_PRIMARY } from '../employeeLoanConstants.js';
import { isEmployeeLoanActive, getEmployeeLoanId } from '../employeeLoanUtils.js';

const EmployeeLoansTab = ({ canEdit = false, active = false }) => {
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
        const response = await payrollService.getEmployeeLoans({ page, per_page: pageSize });

        if (!response.success) {
          setRows([]);
          setPagination((p) => ({ ...p, current: page, pageSize, total: 0 }));
          message.error(response.message);
          return;
        }

        const { items, pagination: p } = extractArrayFromResponse(response.data || {});
        const paginationMeta = response.pagination || p || {};
        const totalCount = Number(paginationMeta.total ?? items.length);
        const current = Number(paginationMeta.current_page || page);
        const perPage = Number(paginationMeta.per_page || pageSize);

        setRows(
          items.map((it) => ({
            key: getEmployeeLoanId(it),
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
      const employeeLoanId = getEmployeeLoanId(record);
      if (!employeeLoanId) {
        message.error('Employee loan ID is missing.');
        return;
      }

      const currentActive = isEmployeeLoanActive(record);
      const action = currentActive ? 'deactivate' : 'activate';

      modal.confirm({
        title: `Are you sure you want to ${action} this employee loan?`,
        content: record?.loan_name ? `"${record.loan_name}" will be ${action}d.` : undefined,
        okText: `Yes, ${action}`,
        cancelText: 'Cancel',
        okType: currentActive ? 'default' : 'primary',
        async onOk() {
          setRows((prev) =>
            prev.map((r) => {
              const rid = getEmployeeLoanId(r);
              if (rid === employeeLoanId) {
                return { ...r, is_active: currentActive ? 0 : 1 };
              }
              return r;
            })
          );

          const response = await payrollService.toggleEmployeeLoanStatus(employeeLoanId, {
            is_active: currentActive ? 0 : 1,
          });
          if (!response?.success) {
            setRows((prev) =>
              prev.map((r) => {
                const rid = getEmployeeLoanId(r);
                if (rid === employeeLoanId) {
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
        title: 'Employee Name',
        dataIndex: 'employee_name',
        key: 'employee_name',
        searchable: true,
        ellipsis: true,
      },
      {
        title: 'Loan Name',
        dataIndex: 'loan_name',
        key: 'loan_name',
        searchable: true,
        ellipsis: true,
      },
      {
        title: 'Principal Amount',
        dataIndex: 'principal_amount',
        key: 'principal_amount',
        align: 'right',
        render: (v, record) => formatMoney(v ?? record?.loan_amount),
      },
      {
        title: 'Monthly Repayment',
        dataIndex: 'monthly_total_repayment_amount',
        key: 'monthly_total_repayment_amount',
        align: 'right',
        render: (v) => formatMoney(v),
      },
      {
        title: 'Outstanding Balance',
        dataIndex: 'outstanding_balance_amount',
        key: 'outstanding_balance_amount',
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
          const activeStatus = isEmployeeLoanActive(record);
          return (
            <Switch
              size="small"
              checked={activeStatus}
              checkedChildren="Active"
              unCheckedChildren="Inactive"
              disabled={!canEdit}
              onChange={() => toggleStatus(record)}
              style={{
                backgroundColor: activeStatus ? BRAND_PRIMARY : '#d9d9d9',
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
            style={{ backgroundColor: BRAND_PRIMARY, borderColor: BRAND_PRIMARY }}
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
            style={{ backgroundColor: BRAND_PRIMARY, borderColor: BRAND_PRIMARY }}
          >
            Add Employee Loan
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

      <AddEmployeeLoanModal
        open={addState.open}
        onClose={() => setAddState({ open: false })}
        onSaved={() => {
          setAddState({ open: false });
          refresh(pagination.current, pagination.pageSize);
        }}
      />

      <EmployeeLoanDetailsModal
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

EmployeeLoansTab.propTypes = {
  canEdit: PropTypes.bool,
  active: PropTypes.bool,
};

export default EmployeeLoansTab;
