import {
  isTransferPending,
  isTransferReviewed,
  isTransferReturned,
  isTransferVerified,
} from './fundTransferStatus.js';

export {
  getTransferStatusDisplayLabel,
  getTransferStatusLabel,
  isTransferPending,
  isTransferReviewed,
  isTransferReturned,
  isTransferVerified,
} from './fundTransferStatus.js';

export const FUND_TRANSFER_REQUEST_TYPE_OPTIONS = [
  { value: 'fund_transfer', label: 'Update Receipt' },
];

export const FUND_TRANSFER_ACTION_OPTIONS = [
  { value: 'cashless_to_cashless', label: 'From Cashless Account-to Cashless Account' },
  
  
  
];

export const getFundTransferOptionLabel = (options, value) => {
  if (value == null || value === '') return '';
  const match = options.find((option) => option.value === value);
  return match?.label ?? String(value);
};

const normalizeRole = (selectedRole) => String(selectedRole || '').trim().toLowerCase();

export const FUND_TRANSFER_ROLE = Object.freeze({
  REGISTRAR: 'toll registrar',
  SUPERVISOR: 'toll supervisor',
  ACCOUNTANT: 'toll accountant',
  APPROVER: 'toll approver',
});

export const hasFundTransferInitiatorRole = (selectedRole) =>
  normalizeRole(selectedRole) === FUND_TRANSFER_ROLE.REGISTRAR;

export const hasFundTransferReviewerRole = (selectedRole) =>
  normalizeRole(selectedRole) === FUND_TRANSFER_ROLE.SUPERVISOR;

export const hasFundTransferVerifierRole = (selectedRole) =>
  normalizeRole(selectedRole) === FUND_TRANSFER_ROLE.ACCOUNTANT;

export const hasFundTransferApproverRole = (selectedRole) =>
  normalizeRole(selectedRole) === FUND_TRANSFER_ROLE.APPROVER;

export const hasFundTransferCheckerRole = (selectedRole) =>
  hasFundTransferReviewerRole(selectedRole) ||
  hasFundTransferVerifierRole(selectedRole) ||
  hasFundTransferApproverRole(selectedRole);

export const getFundTransferQueueActionLabel = (selectedRole) => {
  if (hasFundTransferReviewerRole(selectedRole)) return 'Review';
  if (hasFundTransferVerifierRole(selectedRole)) return 'Verify';
  if (hasFundTransferApproverRole(selectedRole)) return 'Approve';
  return 'View';
};

const getCurrentUserIdentities = (currentUser) =>
  [
    currentUser?.name,
    currentUser?.full_name,
    currentUser?.username,
    currentUser?.email,
    [currentUser?.first_name, currentUser?.middle_name, currentUser?.surname].filter(Boolean).join(' '),
  ]
    .filter(Boolean)
    .map((v) => String(v).trim().toLowerCase());

export const isCurrentUserTransferSubmitter = (transfer, currentUser) => {
  const submitter = String(transfer?.submitted_by || '').trim().toLowerCase();
  if (!submitter || !currentUser) return false;
  return getCurrentUserIdentities(currentUser).includes(submitter);
};

const isOwnTransferBlocked = (transfer, currentUser) => {
  if (transfer?.can_approve === false) return true;
  if (isCurrentUserTransferSubmitter(transfer, currentUser)) return true;
  return false;
};

export const canUserReviewTransfer = (transfer, currentUser, selectedRole) => {
  if (!transfer || !isTransferPending(transfer)) return false;
  if (!hasFundTransferReviewerRole(selectedRole)) return false;
  return !isOwnTransferBlocked(transfer, currentUser);
};

export const canUserVerifyTransfer = (transfer, currentUser, selectedRole) => {
  if (!transfer || !isTransferReviewed(transfer)) return false;
  if (!hasFundTransferVerifierRole(selectedRole)) return false;
  return !isOwnTransferBlocked(transfer, currentUser);
};

export const canUserApproveTransfer = (transfer, currentUser, selectedRole) => {
  if (!transfer || !isTransferVerified(transfer)) return false;
  if (!hasFundTransferApproverRole(selectedRole)) return false;
  if (transfer.can_approve === false) return false;
  if (transfer.can_approve === true) return true;

  const submitter = String(transfer.submitted_by || '').trim().toLowerCase();
  if (!submitter || !currentUser) return true;

  return !getCurrentUserIdentities(currentUser).includes(submitter);
};

export const canUserActOnTransferStage = (transfer, currentUser, selectedRole) =>
  canUserReviewTransfer(transfer, currentUser, selectedRole) ||
  canUserVerifyTransfer(transfer, currentUser, selectedRole) ||
  canUserApproveTransfer(transfer, currentUser, selectedRole);

/** The role that owns the current stage may reject or return the request. */
export const canUserReturnTransfer = canUserActOnTransferStage;

export const canUserRejectTransfer = canUserActOnTransferStage;

/** Original registrar may resubmit after a checker returns the request. */
export const canUserResubmitTransfer = (transfer, currentUser, selectedRole) => {
  if (!transfer || !isTransferReturned(transfer)) return false;
  if (!hasFundTransferInitiatorRole(selectedRole)) return false;
  return isCurrentUserTransferSubmitter(transfer, currentUser);
};

export const getFundTransferAwaitingMessage = (transfer, currentUser, selectedRole) => {
  if (!transfer) return '';
  if (isOwnTransferBlocked(transfer, currentUser) && isTransferPending(transfer) && hasFundTransferReviewerRole(selectedRole)) {
    return 'You cannot review your own transfer request.';
  }
  if (isOwnTransferBlocked(transfer, currentUser) && isTransferReviewed(transfer) && hasFundTransferVerifierRole(selectedRole)) {
    return 'You cannot verify your own transfer request.';
  }
  if (isOwnTransferBlocked(transfer, currentUser) && isTransferVerified(transfer) && hasFundTransferApproverRole(selectedRole)) {
    return 'You cannot approve your own transfer request.';
  }
  if (isTransferPending(transfer)) return 'Awaiting Toll Supervisor review.';
  if (isTransferReviewed(transfer)) return 'Awaiting Toll Accountant verification.';
  if (isTransferVerified(transfer)) return 'Awaiting Toll Approver approval.';
  return '';
};

export const formatApiValidationErrors = (err, fallback = 'Request failed') => {
  const fields = err?.validationErrors || err?.responseData?.data;
  if (!fields || typeof fields !== 'object' || Array.isArray(fields)) {
    return err?.message || fallback;
  }
  const lines = Object.entries(fields).flatMap(([key, messages]) => {
    const list = Array.isArray(messages) ? messages : [messages];
    return list.filter(Boolean).map((msg) => `${key.replace(/_/g, ' ')}: ${msg}`);
  });
  return lines.length ? lines.join('\n') : err?.message || fallback;
};
