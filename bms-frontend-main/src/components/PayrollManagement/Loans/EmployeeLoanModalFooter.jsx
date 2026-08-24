import { Button } from 'antd';
import PropTypes from 'prop-types';
import { BRAND_PRIMARY } from './employeeLoanConstants.js';

const EmployeeLoanModalFooter = ({
  saving,
  onClose,
  closeLabel = 'Close',
  primaryLabel,
  onPrimary,
  showPrimary = true,
}) => (
  <div className="flex flex-wrap items-center justify-end gap-4">
    <Button onClick={onClose} disabled={saving}>
      {closeLabel}
    </Button>
    {showPrimary ? (
      <Button
        type="primary"
        onClick={onPrimary}
        loading={saving}
        style={{ backgroundColor: BRAND_PRIMARY, borderColor: BRAND_PRIMARY }}
      >
        {primaryLabel}
      </Button>
    ) : null}
  </div>
);

EmployeeLoanModalFooter.propTypes = {
  saving: PropTypes.bool,
  onClose: PropTypes.func.isRequired,
  closeLabel: PropTypes.string,
  primaryLabel: PropTypes.string,
  onPrimary: PropTypes.func,
  showPrimary: PropTypes.bool,
};

export default EmployeeLoanModalFooter;
