import { extractErmsFromPayrollRunResponse } from './payrollErmsUtils.js';
import { payrollRunStatusTag } from './payrollStatusUtils.js';

export const resolvePerformerDisplay = (name, id) => {
  const resolvedName = String(name ?? '').trim();
  if (resolvedName) return resolvedName;
  const resolvedId = String(id ?? '').trim();
  return resolvedId || '—';
};

export const extractRunAndHistory = (response) => {
  const data = response?.data || {};
  const run = data.run || data.payroll_run || data.payrollRun || null;
  const history = Array.isArray(data.history)
    ? data.history
    : Array.isArray(run?.history)
      ? run.history
      : [];
  const erms = extractErmsFromPayrollRunResponse(response);
  return { run, history, erms };
};

export const normalizeHistoryMinutes = (history) =>
  [...(history ?? [])]
    .sort((a, b) => new Date(a?.created_at || 0) - new Date(b?.created_at || 0))
    .map((h, idx) => {
      const actor = resolvePerformerDisplay(h?.performed_by_name, h?.performed_by);
      const occurredAt = h?.created_at || null;
      const rawStatus = h?.status || (actor !== '—' || occurredAt ? 'Actioned' : 'Pending');
      const { color: statusColor, text: status } = payrollRunStatusTag(rawStatus);

      return {
        key: h?.id ?? idx,
        title: h?.action || 'Updated',
        actor,
        occurredAt,
        meta: [h?.performed_by_role, h?.comment].filter(Boolean).join(' — ') || undefined,
        status,
        statusColor,
      };
    });

export const normalizePayrollRunRow = (r) => ({
  key: r.id,
  id: r.id,
  payroll_month: r.payroll_month,
  payroll_year: r.payroll_year,
  payroll_number: r.payroll_number,
  status: r.status,
  prepared_by: r.prepared_by,
  prepared_by_name: r.prepared_by_name,
  prepared_at: r.prepared_at,
  initiated_by: r.initiated_by,
  initiated_by_name: r.initiated_by_name,
  initiated_at: r.initiated_at,
  verified_by: r.verified_by,
  verified_by_name: r.verified_by_name,
  verified_at: r.verified_at,
  examined_by: r.examined_by,
  examined_by_name: r.examined_by_name,
  examined_at: r.examined_at,
  approved_by: r.approved_by,
  approved_by_name: r.approved_by_name,
  approved_at: r.approved_at,
  erms_status: r.erms_status,
  erms_submitted_at: r.erms_submitted_at,
  erms_reference: r.erms_reference,
});
