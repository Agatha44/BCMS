import { Button, Input } from 'antd';
import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';

const { TextArea } = Input;

/**
 * Reusable approval action form.
 * - Comment textarea
 * - Return / Reject / Approve / Close buttons in one row
 *
 * Can be used inside panels/modals (e.g. below MinutesPanel).
 */
const ApprovalActionForm = ({
  comment,
  defaultComment = '',
  onCommentChange,
  onApprove,
  onReturn,
  onReject,
  onClose,
  approveText = 'Approve',
  returnText = 'Return',
  rejectText = 'Reject',
  closeText = 'Close',
  loadingApprove = false,
  loadingReturn = false,
  loadingReject = false,
  disabled = false,
  placeholder = 'Add a comment...',
  rows = 3,
}) => {
  const isControlled = comment !== undefined;
  const [draft, setDraft] = useState(defaultComment || '');

  useEffect(() => {
    if (!isControlled) return;
    setDraft(comment || '');
  }, [comment, isControlled]);

  const value = isControlled ? comment || '' : draft;

  const setValue = (next) => {
    if (!isControlled) setDraft(next);
    onCommentChange?.(next);
  };

  return (
    <div className="space-y-2">
      <TextArea
        value={value}
        onChange={(e) => setValue(e.target.value)}
        placeholder={placeholder}
        rows={rows}
        disabled={disabled}
      />

      <div className="flex w-full flex-nowrap items-center justify-end gap-1.5 overflow-x-auto">
        {onReturn ? (
          <Button
            size="small"
            onClick={() => onReturn(value)}
            loading={loadingReturn}
            disabled={disabled}
            className="shrink-0"
          >
            {returnText}
          </Button>
        ) : null}
        {onReject ? (
          <Button
            size="small"
            danger
            onClick={() => onReject(value)}
            loading={loadingReject}
            disabled={disabled}
            className="shrink-0"
          >
            {rejectText}
          </Button>
        ) : null}
        {onApprove ? (
          <Button
            size="small"
            type="primary"
            onClick={() => onApprove(value)}
            loading={loadingApprove}
            disabled={disabled}
            className="shrink-0"
            style={{ backgroundColor: '#962E32', borderColor: '#962E32' }}
          >
            {approveText}
          </Button>
        ) : null}
        {onClose ? (
          <Button size="small" onClick={onClose} disabled={disabled} className="shrink-0">
            {closeText}
          </Button>
        ) : null}
      </div>
    </div>
  );
};

ApprovalActionForm.propTypes = {
  comment: PropTypes.string,
  defaultComment: PropTypes.string,
  onCommentChange: PropTypes.func,
  onApprove: PropTypes.func,
  onReturn: PropTypes.func,
  onReject: PropTypes.func,
  onClose: PropTypes.func,
  approveText: PropTypes.string,
  returnText: PropTypes.string,
  rejectText: PropTypes.string,
  closeText: PropTypes.string,
  loadingApprove: PropTypes.bool,
  loadingReturn: PropTypes.bool,
  loadingReject: PropTypes.bool,
  disabled: PropTypes.bool,
  placeholder: PropTypes.string,
  rows: PropTypes.number,
};

export default ApprovalActionForm;

