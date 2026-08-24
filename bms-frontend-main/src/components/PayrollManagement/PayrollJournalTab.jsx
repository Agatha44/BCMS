import { App, Card, Col, Row, Table, Tag } from 'antd';
import PropTypes from 'prop-types';
import { useEffect, useMemo, useState } from 'react';
import { DataTable } from '../../common/data';
import { formatPayrollMoney } from '../../common/utils/numberFormat.js';
import CollectionLoader from '../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { payrollService } from '../../services/payrollService.js';

const PayrollJournalTab = ({
  active = false,
  runId,
}) => {
  if (!active) return null;

  const { message } = App.useApp();
  const [journalState, setJournalState] = useState(() => ({
    loading: false,
    rows: [],
    status: 'Pending',
    lastUpdatedAt: null,
  }));

  const toNumberOrNull = (v) => {
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
  };

  const formatTZS = (v) => formatPayrollMoney(v);

  const normalizeJournalVoucherRows = (payload) => {
    const root = payload?.data ?? payload;
    const list =
      root?.lines ||
      root?.journalVoucher ||
      root?.journal_voucher ||
      root?.voucher ||
      root?.items ||
      root?.entries ||
      root?.rows ||
      (Array.isArray(root) ? root : []);

    if (!Array.isArray(list)) return [];

    return list.map((it, idx) => {
      const component = it?.component ?? it?.name ?? it?.description ?? it?.title ?? `Item ${idx + 1}`;
      const sideRaw = String(it?.side ?? it?.type ?? it?.dc ?? it?.drcr ?? it?.direction ?? '').toLowerCase();
      const debitRaw = toNumberOrNull(it?.debit ?? it?.dr ?? it?.debit_amount);
      const creditRaw = toNumberOrNull(it?.credit ?? it?.cr ?? it?.credit_amount);
      const amountRaw = toNumberOrNull(it?.amount ?? it?.value);

      const isDebit =
        sideRaw === 'debit' ||
        sideRaw === 'dr' ||
        sideRaw === 'd' ||
        (debitRaw !== null && (creditRaw === null || creditRaw === 0));
      const isCredit =
        sideRaw === 'credit' ||
        sideRaw === 'cr' ||
        sideRaw === 'c' ||
        (creditRaw !== null && (debitRaw === null || debitRaw === 0));

      const debit = debitRaw ?? (isDebit ? amountRaw : 0) ?? 0;
      const credit = creditRaw ?? (isCredit ? amountRaw : 0) ?? 0;

      return {
        key: it?.id ?? it?.key ?? `${idx}`,
        component,
        debit: toNumberOrNull(debit) ?? 0,
        credit: toNumberOrNull(credit) ?? 0,
      };
    });
  };

  const refreshJournalVoucher = async () => {
    if (!runId && runId !== 0) return;
    setJournalState((p) => ({ ...p, loading: true }));
    try {
      const response = await payrollService.getPayrollJournal(runId);
      if (!response?.success) {
        setJournalState({ loading: false, rows: [], status: 'Error', lastUpdatedAt: new Date().toISOString() });
        message.error(response?.message || 'Could not load payroll journal.');
        return;
      }

      const rows = normalizeJournalVoucherRows(response.data);
      const apiRoot = response?.data ?? {};
      const apiStatusRaw = String(apiRoot?.journal_status ?? apiRoot?.journalStatus ?? '').toLowerCase();

      const statusFromApi =
        apiStatusRaw === 'balanced'
          ? 'Balanced'
          : apiStatusRaw === 'unbalanced'
            ? 'Unbalanced'
            : apiStatusRaw === 'posted'
              ? 'Posted'
              : apiStatusRaw === 'error'
                ? 'Error'
                : '';

      const totalDebit = rows.reduce((acc, r) => acc + (toNumberOrNull(r.debit) ?? 0), 0);
      const totalCredit = rows.reduce((acc, r) => acc + (toNumberOrNull(r.credit) ?? 0), 0);
      const shortage = Math.abs(totalDebit - totalCredit);
      const status = statusFromApi || (shortage < 0.01 ? 'Balanced' : 'Unbalanced');

      setJournalState({
        loading: false,
        rows,
        status,
        lastUpdatedAt: new Date().toISOString(),
      });
    } catch (e) {
      setJournalState({ loading: false, rows: [], status: 'Error', lastUpdatedAt: new Date().toISOString() });
      message.error(e?.message || 'Failed to load payroll journal.');
    }
  };

  useEffect(() => {
    if (!active) return;
    refreshJournalVoucher();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [active, runId]);

  const journalColumns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'sn',
        width: 80,
        align: 'center',
        render: (_, __, index) => index + 1,
      },
      {
        title: 'Component',
        dataIndex: 'component',
        key: 'component',
        render: (v) => <span className="font-medium text-gray-900">{v || '—'}</span>,
      },
      {
        title: 'Debit',
        dataIndex: 'debit',
        key: 'debit',
        align: 'right',
        width: 170,
        render: (v) => (Number(v) > 0 ? formatTZS?.(v) ?? '—' : '0.00'),
      },
      {
        title: 'Credit',
        dataIndex: 'credit',
        key: 'credit',
        align: 'right',
        width: 170,
        render: (v) => (Number(v) > 0 ? formatTZS?.(v) ?? '—' : '0.00'),
      },
      { title: 'Shortage', key: 'shortage', width: 130, align: 'center', render: () => '—' },
    ],
    [formatTZS]
  );

  return (
    <div className="mt-2">
      <div className="mt-3">
        <Row gutter={[16, 16]}>
          <Col xs={24} lg={16}>
            <Card size="small">
              <div className="relative">
                {journalState.loading ? (
                  <div
                    className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
                    style={{ top: '4.75rem' }}
                  >
                    <CollectionLoader />
                  </div>
                ) : null}
              <DataTable
                columns={journalColumns}
                data={journalState.rows}
                loading={false}
                pagination={false}
                showSearch={false}
                showRefresh={true}
                onRefresh={refreshJournalVoucher}
                rowKey="key"
                size="middle"
                bordered={true}
                summary={(pageData) => {
                  const totalDebit = pageData.reduce((acc, r) => acc + (toNumberOrNull?.(r.debit) ?? 0), 0);
                  const totalCredit = pageData.reduce((acc, r) => acc + (toNumberOrNull?.(r.credit) ?? 0), 0);
                  const shortage = Math.abs(totalDebit - totalCredit);
                  return (
                    <Table.Summary fixed>
                      <Table.Summary.Row>
                        <Table.Summary.Cell index={0} align="center">
                          —
                        </Table.Summary.Cell>
                        <Table.Summary.Cell index={1}>
                          <span className="font-semibold">Total Amount</span>
                        </Table.Summary.Cell>
                        <Table.Summary.Cell index={2} align="right">
                          <span className="font-semibold">{formatTZS?.(totalDebit) ?? '—'}</span>
                        </Table.Summary.Cell>
                        <Table.Summary.Cell index={3} align="right">
                          <span className="font-semibold">{formatTZS?.(totalCredit) ?? '—'}</span>
                        </Table.Summary.Cell>
                        <Table.Summary.Cell index={4} align="center">
                          <span className={shortage < 0.01 ? 'text-gray-600' : 'text-red-600 font-semibold'}>
                            {shortage < 0.01 ? '—' : formatTZS?.(shortage) ?? '—'}
                          </span>
                        </Table.Summary.Cell>
                      </Table.Summary.Row>
                    </Table.Summary>
                  );
                }}
              />
              </div>
            </Card>
          </Col>

          <Col xs={24} lg={8}>
            <Card title="Alerts" size="small">
              {(() => {
                const totalDebit = journalState.rows.reduce((acc, r) => acc + (toNumberOrNull?.(r.debit) ?? 0), 0);
                const totalCredit = journalState.rows.reduce((acc, r) => acc + (toNumberOrNull?.(r.credit) ?? 0), 0);
                const shortage = Math.abs(totalDebit - totalCredit);
                const statusLower = String(journalState.status || '').toLowerCase();
                const statusColor =
                  statusLower === 'posted' || statusLower === 'balanced'
                    ? 'green'
                    : statusLower === 'unbalanced'
                      ? 'gold'
                      : statusLower === 'error'
                        ? 'red'
                        : 'gold';

                return (
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <div className="text-sm text-gray-600">
                        Journal Status
                        {journalState.lastUpdatedAt ? ` (Updated: ${new Date(journalState.lastUpdatedAt).toLocaleString()})` : ''}
                        :
                      </div>
                      <Tag color={statusColor}>{journalState.status || '—'}</Tag>
                    </div>

                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-3">
                      <div className="text-sm font-semibold text-gray-900">Shortage:</div>
                      <div className="mt-1 text-xl font-bold text-[#1d4ed8] select-all">
                        {shortage < 0.01 ? '0.00' : formatTZS?.(shortage) ?? '—'}
                      </div>
                    </div>

                    <div className="text-xs text-gray-500">
                      This table aggregates journal voucher lines (debit/credit) from Payroll modules (Benefits, Deductions, Loans,
                      etc) when the backend provides them.
                    </div>
                  </div>
                );
              })()}
            </Card>
          </Col>
        </Row>
      </div>
    </div>
  );
};

PayrollJournalTab.propTypes = {
  active: PropTypes.bool,
  runId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
};

export default PayrollJournalTab;

