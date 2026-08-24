/**
 * Central status definitions for fund transfer list views, API filters, and UI tags.
 */

/** @typedef {'pending' | 'posted' | 'rejected' | 'returned'} FundTransferStatusKey */

/** @typedef {'all' | 'pending' | 'posted' | 'rejected' | 'returned'} FundTransferStatusTabKey */

export const FUND_TRANSFER_STATUS = Object.freeze({
  PENDING: 'pending',
  POSTED: 'posted',
  REJECTED: 'rejected',
  RETURNED: 'returned',
});

/** Tab key for the full unfiltered history list. */
export const FUND_TRANSFER_STATUS_TAB_ALL = 'all';

/** API value for unfiltered history — backend requires explicit `status=all`. */
export const FUND_TRANSFER_HISTORY_API_STATUS_ALL = 'all';

export const FUND_TRANSFER_STATUS_TABS = Object.freeze([
  { key: FUND_TRANSFER_STATUS_TAB_ALL, label: 'All', apiStatus: FUND_TRANSFER_HISTORY_API_STATUS_ALL },
  { key: FUND_TRANSFER_STATUS.PENDING, label: 'Pending', apiStatus: FUND_TRANSFER_STATUS.PENDING, showPendingBadge: true },
  { key: FUND_TRANSFER_STATUS.POSTED, label: 'Posted', apiStatus: FUND_TRANSFER_STATUS.POSTED },
  { key: FUND_TRANSFER_STATUS.REJECTED, label: 'Rejected', apiStatus: FUND_TRANSFER_STATUS.REJECTED },
  { key: FUND_TRANSFER_STATUS.RETURNED, label: 'Returned', apiStatus: FUND_TRANSFER_STATUS.RETURNED },
]);

/** @deprecated Use FUND_TRANSFER_STATUS_TABS — kept for select/options compatibility */
export const FUND_TRANSFER_HISTORY_STATUS_OPTIONS = Object.freeze([
  { value: FUND_TRANSFER_HISTORY_API_STATUS_ALL, label: 'All' },
  ...FUND_TRANSFER_STATUS_TABS.filter((tab) => tab.key !== FUND_TRANSFER_STATUS_TAB_ALL).map((tab) => ({
    value: tab.apiStatus,
    label: tab.label,
  })),
]);

const STATUS_TAB_KEYS = new Set(FUND_TRANSFER_STATUS_TABS.map((tab) => tab.key));

const normalizeStatusText = (value) =>
  String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_');

/**
 * Raw label from API / transfer row (preserves server casing when present).
 * @param {object|string|null|undefined} transferOrLabel
 * @returns {string}
 */
export const getTransferStatusLabel = (transferOrLabel) => {
  if (transferOrLabel == null) return '';
  if (typeof transferOrLabel === 'string') return transferOrLabel.trim();
  if (transferOrLabel.status == null) return '';
  return String(transferOrLabel.status).trim();
};

/**
 * Canonical status key used for filters, guards, and tag colors.
 * @param {object|string|null|undefined} transferOrLabel
 * @returns {FundTransferStatusKey | 'unknown'}
 */
export const normalizeTransferStatus = (transferOrLabel) => {
  const raw = getTransferStatusLabel(transferOrLabel);
  const normalized = normalizeStatusText(raw);

  if (!normalized) return 'unknown';
  if (
    normalized === FUND_TRANSFER_STATUS.POSTED ||
    normalized.includes('posted') ||
    normalized === 'approved' ||
    (normalized.includes('approv') && !normalized.includes('pending'))
  ) {
    return FUND_TRANSFER_STATUS.POSTED;
  }
  if (
    normalized === FUND_TRANSFER_STATUS.REJECTED ||
    normalized.includes('reject') ||
    normalized.includes('declin') ||
    normalized.includes('fail')
  ) {
    return FUND_TRANSFER_STATUS.REJECTED;
  }
  if (normalized === FUND_TRANSFER_STATUS.RETURNED || normalized.includes('return')) {
    return FUND_TRANSFER_STATUS.RETURNED;
  }
  if (
    normalized === FUND_TRANSFER_STATUS.PENDING ||
    normalized.includes('pending') ||
    normalized.includes('await') ||
    normalized.includes('submitted')
  ) {
    return FUND_TRANSFER_STATUS.PENDING;
  }

  return 'unknown';
};

export const isTransferStatus = (transferOrLabel, statusKey) =>
  normalizeTransferStatus(transferOrLabel) === statusKey;

export const isTransferPending = (transferOrLabel) =>
  isTransferStatus(transferOrLabel, FUND_TRANSFER_STATUS.PENDING);

export const isTransferPosted = (transferOrLabel) =>
  isTransferStatus(transferOrLabel, FUND_TRANSFER_STATUS.POSTED);

/** @deprecated Use isTransferPosted — approval sets status to posted, not approved */
export const isTransferApproved = isTransferPosted;

export const isTransferRejected = (transferOrLabel) =>
  isTransferStatus(transferOrLabel, FUND_TRANSFER_STATUS.REJECTED);

export const isTransferReturned = (transferOrLabel) =>
  isTransferStatus(transferOrLabel, FUND_TRANSFER_STATUS.RETURNED);

/**
 * Ant Design Tag color for a transfer status.
 * @param {object|string|null|undefined} transferOrLabel
 * @returns {string}
 */
export const getTransferStatusTagColor = (transferOrLabel) => {
  const status = normalizeTransferStatus(transferOrLabel);

  switch (status) {
    case FUND_TRANSFER_STATUS.POSTED:
      return 'green';
    case FUND_TRANSFER_STATUS.REJECTED:
      return 'red';
    case FUND_TRANSFER_STATUS.RETURNED:
      return 'orange';
    case FUND_TRANSFER_STATUS.PENDING:
      return 'gold';
    default:
      return 'default';
  }
};

/**
 * User-facing status label from API `status` field.
 * @param {object|string|null|undefined} transferOrLabel
 * @returns {string}
 */
export const getTransferStatusDisplayLabel = (transferOrLabel) => getTransferStatusLabel(transferOrLabel);

/**
 * @param {string|null|undefined} raw
 * @returns {FundTransferStatusTabKey}
 */
export const parseStatusTabFromSearch = (raw) => {
  const value = String(raw || '').trim().toLowerCase();
  if (!value || value === FUND_TRANSFER_STATUS_TAB_ALL) {
    return FUND_TRANSFER_STATUS_TAB_ALL;
  }
  // Legacy URLs may use status=approved; workflow uses posted after approval.
  if (value === 'approved') {
    return FUND_TRANSFER_STATUS.POSTED;
  }
  return STATUS_TAB_KEYS.has(value) ? value : FUND_TRANSFER_STATUS_TAB_ALL;
};

/**
 * Reads active status tab from URL search params (`status`, legacy `view`).
 * @param {URLSearchParams} params
 * @returns {FundTransferStatusTabKey}
 */
export const getStatusTabFromSearchParams = (params) => {
  const statusParam = params.get('status');
  if (statusParam) {
    return parseStatusTabFromSearch(statusParam);
  }

  const legacyView = params.get('view');
  if (legacyView === FUND_TRANSFER_STATUS.PENDING) {
    return FUND_TRANSFER_STATUS.PENDING;
  }
  if (legacyView === 'history') {
    return FUND_TRANSFER_STATUS_TAB_ALL;
  }

  return null;
};

/**
 * Default tab when no status is present in the URL.
 * @param {boolean} isApprover
 * @returns {FundTransferStatusTabKey}
 */
export const getDefaultStatusTab = (isApprover) =>
  isApprover ? FUND_TRANSFER_STATUS.PENDING : FUND_TRANSFER_STATUS_TAB_ALL;

/**
 * @param {FundTransferStatusTabKey} statusTab
 * @returns {string}
 */
export const resolveApiStatusFromTab = (statusTab) => {
  const tab = FUND_TRANSFER_STATUS_TABS.find((item) => item.key === statusTab);
  return tab?.apiStatus ?? FUND_TRANSFER_HISTORY_API_STATUS_ALL;
};

export const isPendingStatusTab = (statusTab) => statusTab === FUND_TRANSFER_STATUS.PENDING;

/** Status tab to open after a successful new transfer submit. */
export const STATUS_TAB_AFTER_SUBMIT = FUND_TRANSFER_STATUS.PENDING;

/** API status param for pending-approvals badge count. */
export const PENDING_QUEUE_API_STATUS = FUND_TRANSFER_STATUS.PENDING;

/**
 * Build URL search mutation for a status tab.
 * @param {URLSearchParams} params
 * @param {FundTransferStatusTabKey} statusTab
 */
export const applyStatusTabToSearchParams = (params, statusTab) => {
  params.set('tab', 'fund-transfer');
  params.delete('view');
  params.set(
    'status',
    statusTab === FUND_TRANSFER_STATUS_TAB_ALL ? FUND_TRANSFER_HISTORY_API_STATUS_ALL : statusTab
  );
};
