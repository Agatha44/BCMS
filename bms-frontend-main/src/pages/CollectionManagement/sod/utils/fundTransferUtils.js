import { isTransferPending, isTransferReturned } from './fundTransferStatus.js';

export { getTransferStatusLabel, isTransferPending, isTransferReturned } from './fundTransferStatus.js';

const FUND_TRANSFER_APPROVER_ROLES = [
  'toll approver',
  'toll supervisor',
  'toll administrator',
  'toll reviewer',
];

export const hasFundTransferApproverRole = (selectedRole) => {
  const role = String(selectedRole || '').trim().toLowerCase();
  return FUND_TRANSFER_APPROVER_ROLES.includes(role);
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

export const canUserApproveTransfer = (transfer, currentUser, selectedRole) => {
  if (!transfer || !isTransferPending(transfer)) return false;
  if (!hasFundTransferApproverRole(selectedRole)) return false;
  if (transfer.can_approve === false) return false;
  if (transfer.can_approve === true) return true;

  const submitter = String(transfer.submitted_by || '').trim().toLowerCase();
  if (!submitter || !currentUser) return true;

  return !getCurrentUserIdentities(currentUser).includes(submitter);
};

/** Approvers may return pending transfers they are allowed to review (same rules as approve). */
export const canUserReturnTransfer = canUserApproveTransfer;

/** Original submitter may resubmit after an approver returns the request. */
export const canUserResubmitTransfer = (transfer, currentUser) => {
  if (!transfer || !isTransferReturned(transfer)) return false;
  return isCurrentUserTransferSubmitter(transfer, currentUser);
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
