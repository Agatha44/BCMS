import { Button } from 'antd';
import PropTypes from 'prop-types';

const DashboardInfoCard = ({ label, children, onClick, actionLabel }) => (
  <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
    <span className="block text-xs font-medium text-slate-500">{label}</span>
    <div className="mt-1.5 text-sm font-semibold text-black">{children}</div>
    {actionLabel && onClick ? (
      <Button type="link" className="mt-2 h-auto p-0" onClick={onClick}>
        {actionLabel}
      </Button>
    ) : null}
  </div>
);

DashboardInfoCard.propTypes = {
  label: PropTypes.string.isRequired,
  children: PropTypes.node,
  onClick: PropTypes.func,
  actionLabel: PropTypes.string,
};

export default DashboardInfoCard;
