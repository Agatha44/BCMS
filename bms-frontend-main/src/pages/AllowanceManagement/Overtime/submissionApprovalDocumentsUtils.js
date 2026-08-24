import dayjs from 'dayjs';

export const BRAND = '#962E32';
export const BRAND_DARK = '#7A2326';
export const MAX_PDF_SIZE_BYTES = 10 * 1024 * 1024;

export const resolveSubmissionDocumentTypeValue = (type) => {
  if (type == null || type === '') return null;
  if (typeof type === 'string' || typeof type === 'number') return String(type);
  return type.value ?? type.code ?? type.document_type ?? type.id ?? null;
};

export const getSubmissionDocumentTypeLabel = (value, record) => {
  const source = record ?? (typeof value === 'object' && value !== null ? value : null);
  if (source) {
    return (
      source.document_name ??
      source.document_type_name ??
      source.label ??
      source.name ??
      resolveSubmissionDocumentTypeValue(source) ??
      ''
    );
  }
  return resolveSubmissionDocumentTypeValue(value) ?? '';
};

export const formatReadinessText = (value) => {
  if (value == null || value === '') return '';
  if (typeof value === 'string' || typeof value === 'number') return String(value);
  if (typeof value === 'object') {
    if (value.message) return String(value.message);
    return getSubmissionDocumentTypeLabel(value) || resolveSubmissionDocumentTypeValue(value) || '';
  }
  return '';
};

export const extractSubmissionDocumentTypes = (responseData) => {
  if (!responseData) return [];
  if (Array.isArray(responseData)) return responseData;
  if (Array.isArray(responseData.types)) return responseData.types;
  return [];
};

export const extractSubmissionDocumentStatuses = (responseData) => {
  if (!responseData) return [];
  if (Array.isArray(responseData.statuses)) return responseData.statuses;
  if (Array.isArray(responseData.status_options)) return responseData.status_options;
  return [];
};

export const toSubmissionDocumentTypeOptions = (documentTypes) =>
  extractSubmissionDocumentTypes(documentTypes).map((type) => {
    const value = resolveSubmissionDocumentTypeValue(type);
    return {
      value,
      label: getSubmissionDocumentTypeLabel(type) || value,
      defaultMonths: type.default_period_months ?? type.period_months ?? null,
      periodMonthOptions: type.period_month_options ?? type.allowed_period_months ?? type.period_months_options ?? [],
      raw: type,
    };
  }).filter((option) => option.value);

export const toSubmissionDocumentStatusOptions = (statuses) =>
  extractSubmissionDocumentStatuses(statuses).map((status) => {
    if (typeof status === 'string' || typeof status === 'number') {
      const value = String(status);
      return { value, label: value };
    }
    const value = status.value ?? status.code ?? status.status ?? status.id;
    const label = status.label ?? status.name ?? status.status_label ?? value;
    return { value, label };
  }).filter((option) => option.value);

export const normalizeDocStatus = (status) => String(status || '').trim().toLowerCase();

export const isExpiredDocument = (record) => normalizeDocStatus(record?.status) === 'expired';

export const isActiveDocument = (record) => normalizeDocStatus(record?.status) === 'active';

export const isInactiveDocument = (record) => normalizeDocStatus(record?.status) === 'inactive';

export const formatDocDate = (value) => {
  if (!value) return null;
  return dayjs(value).format('DD-MMM-YYYY');
};

export const computePeriodEnd = (periodStart, months) => {
  if (!periodStart || !months) return null;
  return dayjs(periodStart).add(Number(months), 'month').subtract(1, 'day');
};

const STATUS_TAG_COLORS = {
  active: 'green',
  inactive: 'orange',
  expired: 'default',
};

export const getStatusTagProps = (record) => {
  const status = normalizeDocStatus(record?.status);
  return {
    color: record?.status_color ?? STATUS_TAG_COLORS[status] ?? 'blue',
    label: record?.status_label ?? record?.status ?? '',
  };
};

export const triggerBlobDownload = (blob, fileName) => {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName || 'document.pdf';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
};

export const getSubmissionReadinessBlockingMessages = (readiness) =>
  (readiness?.blocking_reasons || [])
    .map((reason) => formatReadinessText(reason))
    .filter(Boolean);

export const getSubmissionReadinessRequiredDocuments = (readiness) =>
  Array.isArray(readiness?.required_documents) ? readiness.required_documents : [];

export const resolveSubmissionDocumentId = (doc) =>
  doc?.matched_document_id ??
  doc?.submission_document_id ??
  doc?.document_id ??
  doc?.id ??
  null;

export const isRequiredDocumentMissing = (doc) => {
  if (!doc) return true;
  const status = normalizeDocStatus(doc?.status);
  if (status === 'missing') return true;
  return !resolveSubmissionDocumentId(doc);
};

export const isRequiredDocumentAvailable = (doc) => {
  if (!doc || isRequiredDocumentMissing(doc)) return false;
  const status = normalizeDocStatus(doc?.status);
  return status !== 'expired' && status !== 'inactive';
};

export const getAvailableRequiredDocuments = (readiness) =>
  getSubmissionReadinessRequiredDocuments(readiness).filter(isRequiredDocumentAvailable);

export const canSubmitBatchToEoffice = (readiness) =>
  !readiness || readiness.can_submit_to_eoffice !== false;
