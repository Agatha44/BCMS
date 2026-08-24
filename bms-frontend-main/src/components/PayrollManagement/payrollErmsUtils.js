const executionStatusLabel = (status) => {
  const n = Number(status);
  if (n === 1) return 'success';
  if (n === 2) return 'failed';
  return 'unknown';
};

const normalizeExecution = (row) => ({
  id: row?.id,
  execution_type: row?.execution_type,
  bank_batch: row?.bank_batch,
  label: row?.label || row?.execution_type || '—',
  source_ref: row?.source_ref,
  status: row?.status,
  status_label: row?.status_label || executionStatusLabel(row?.status),
  erms_reference: row?.erms_reference,
  http_status: row?.http_status,
  error_message: row?.error_message,
  response_payload: row?.response_payload,
  submitted_at: row?.submitted_at,
});

export const normalizeErmsPayload = (source) => {
  if (!source || typeof source !== 'object') return null;

  const executions = (Array.isArray(source.executions) ? source.executions : []).map(normalizeExecution);
  const failedFromRows = executions.filter((e) => e.status_label === 'failed' || Number(e.status) === 2).length;
  const failed_count = Number(source.failed_count) || failedFromRows;

  return {
    payroll_run_id: source.payroll_run_id,
    erms_status: source.erms_status,
    miscellaneous_ok: source.miscellaneous_ok,
    can_repost: Boolean(source.can_repost),
    total_executions: Number(source.total_executions) || executions.length,
    failed_count,
    executions,
  };
};

export const extractErmsFromPayrollRunResponse = (response) => {
  const data = response?.data || {};
  if (data.erms) return normalizeErmsPayload(data.erms);

  const run = data.run || data.payroll_run || null;
  if (!run) return null;

  const executions = (Array.isArray(run.erms_executions) ? run.erms_executions : []).map(normalizeExecution);
  const failed_count = executions.filter((e) => Number(e.status) === 2).length;

  return {
    payroll_run_id: run.id,
    erms_status: run.erms_status,
    miscellaneous_ok: executions.some((e) => e.execution_type === 'miscellaneous' && Number(e.status) === 1),
    can_repost: Number(run.erms_status) === 2 && failed_count > 0,
    total_executions: executions.length,
    failed_count,
    executions,
  };
};

export const extractErmsFromExecutionsResponse = (response) => {
  const data = response?.data || {};
  if (data.erms) return normalizeErmsPayload(data.erms);
  if (data.executions || Array.isArray(data)) {
    const executions = Array.isArray(data) ? data : data.executions;
    return normalizeErmsPayload({
      ...data,
      executions,
      failed_count: data.failed_count,
      can_repost: data.can_repost,
      total_executions: data.total_executions ?? executions?.length,
      erms_status: data.erms_status,
      miscellaneous_ok: data.miscellaneous_ok,
    });
  }
  return normalizeErmsPayload(data);
};

export const ermsOverallTag = (ermsStatus) => {
  const n = Number(ermsStatus);
  if (n === 1) return { color: 'green', text: 'Submitted' };
  if (n === 2) return { color: 'red', text: 'Failed' };
  return { color: 'gold', text: 'Not Submitted' };
};

export const formatErmsDateTime = (value) => {
  if (!value) return null;
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? String(value) : d.toLocaleString();
};
