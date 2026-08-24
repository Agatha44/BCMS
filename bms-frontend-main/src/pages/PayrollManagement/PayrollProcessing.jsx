import { App, Card } from 'antd';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSelector } from 'react-redux';
import '../../styles/common.css';
import PayrollRunDetailsModal from '../../components/PayrollManagement/PayrollRunDetailsModal.jsx';
import PayrollProcessingTab from '../../components/PayrollManagement/PayrollProcessing/tabs/PayrollProcessingTab.jsx';
import { hasPayrollInitiatorRole } from '../../common/utils/employeeUtils.jsx';
import { normalizePayrollRunRow } from '../../components/PayrollManagement/payrollRunUtils.js';
import { payrollService } from '../../services/payrollService.js';

const PayrollProcessing = () => {
  const { message, modal } = App.useApp();
  const selectedRole = useSelector((state) => state.app.selectedRole);
  const canPreparePayroll = useMemo(
    () => hasPayrollInitiatorRole(selectedRole),
    [selectedRole]
  );

  const [isPreparing, setIsPreparing] = useState(false);
  const [runsState, setRunsState] = useState(() => ({
    loading: false,
    rows: [],
    search: '',
    pagination: {
      current: 1,
      pageSize: 15,
      total: 0,
      showSizeChanger: true,
      showQuickJumper: true,
      showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
      pageSizeOptions: ['10', '15', '20', '50', '100'],
    },
  }));
  const [detailsState, setDetailsState] = useState(() => ({
    open: false,
    run: null,
  }));

  const normalizePayrollRuns = useCallback((payload) => {
    // Expected API shape:
    // { success, message, data: { "0": [runs...], pagination: {...} } }
    const list = payload?.['0'];
    if (!Array.isArray(list)) return [];

    return list.map(normalizePayrollRunRow);
  }, []);

  const refreshPayrollRuns = useCallback(
    async ({ page = 1, per_page = 15, search = '' } = {}) => {
      setRunsState((p) => ({ ...p, loading: true, search }));
    try {
      const response = await payrollService.getPayrollRuns({ page, per_page, search });
      if (!response?.success) {
        setRunsState((p) => ({ ...p, loading: false, rows: [] }));
        message.error(response?.message || 'Could not load payroll runs.');
        return;
      }

      const rows = normalizePayrollRuns(response.data);
      const p = response.data?.pagination;
      const totalCount = Number(p?.total ?? 0);
      const current = Number(p?.current_page ?? page);
      const perPage = Number(p?.per_page ?? per_page);

      setRunsState((prev) => ({
        ...prev,
        loading: false,
        rows,
        pagination: {
          ...prev.pagination,
          current,
          pageSize: perPage,
          total: totalCount,
        },
      }));
    } catch (e) {
      setRunsState((p) => ({ ...p, loading: false, rows: [] }));
      message.error(e?.message || 'Failed to load payroll runs');
    }
    },
    [message, normalizePayrollRuns]
  );

  useEffect(() => {
    refreshPayrollRuns({
      page: runsState.pagination.current,
      per_page: runsState.pagination.pageSize,
      search: runsState.search,
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const openDetailsForRun = (run) => {
    setDetailsState({ open: true, run });
  };

  const closeDetails = () => {
    setDetailsState({ open: false, run: null });
  };

  const preparePayroll = async () => {
    const now = new Date();
    const monthYear = now.toLocaleString(undefined, { month: 'long', year: 'numeric' });

    modal.confirm({
      title: `Prepare Payroll — ${monthYear}`,
      content: (
        <div className="space-y-2">
          <div>This will generate all pay items for this payroll period and <span className="font-semibold">lock them for processing</span>.</div>
          <div>
            After preparing, <span className="font-semibold">changes to deductions, benefits, arrears, and other inputs may be restricted</span>.
          </div>
          <div className="pt-1">Please confirm everything is correct before continuing.</div>
        </div>
      ),
      okText: 'Yes, Prepare Payroll',
      cancelText: 'Cancel',
      okButtonProps: { style: { backgroundColor: '#962E32', borderColor: '#962E32' } },
      async onOk() {
        setIsPreparing(true);
        try {
          const response = await payrollService.preparePayroll();

          if (response?.success) {
            message.success(response.message || 'Payroll prepared');
            // Ensure the UI fully reflects the newly prepared payroll state.
            setTimeout(() => window.location.reload(), 600);
            return;
          }

          message.error(response?.message || 'Failed to prepare payroll');
        } catch (e) {
          message.error(e?.message || 'Failed to prepare payroll');
        } finally {
          setIsPreparing(false);
        }
      },
    });
  };

  return (
    <Card>
      <PayrollProcessingTab
        active={true}
        isPreparing={isPreparing}
        canPreparePayroll={canPreparePayroll}
        preparePayroll={canPreparePayroll ? preparePayroll : null}
        payrollRuns={runsState.rows}
        runsLoading={runsState.loading}
        pagination={runsState.pagination}
        searchValue={runsState.search}
        onSearch={(search) =>
          refreshPayrollRuns({
            page: 1,
            per_page: runsState.pagination.pageSize,
            search,
          })
        }
        onPageChange={(page, pageSize) =>
          refreshPayrollRuns({
            page,
            per_page: pageSize,
            search: runsState.search,
          })
        }
        onViewRun={openDetailsForRun}
      />

      <PayrollRunDetailsModal
        open={detailsState.open}
        run={detailsState.run}
        onClose={closeDetails}
        onUpdated={() =>
          refreshPayrollRuns({
            page: runsState.pagination.current,
            per_page: runsState.pagination.pageSize,
            search: runsState.search,
          })
        }
      />
    </Card>
  );
};

export default PayrollProcessing;

