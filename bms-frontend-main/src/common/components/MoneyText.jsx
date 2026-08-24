import PropTypes from 'prop-types';
import clsx from 'clsx';
import { formatMoney } from '../utils/numberFormat.js';

/**
 * Inline money value for tables and detail views.
 */
export default function MoneyText({ value, className, nullLabel, decimals }) {
  return (
    <span
      className={clsx('text-sm font-mono tabular-nums text-black', className)}
      title={value != null && value !== '' ? String(value) : undefined}
    >
      {formatMoney(value, { nullLabel, decimals })}
    </span>
  );
}

MoneyText.propTypes = {
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  className: PropTypes.string,
  nullLabel: PropTypes.string,
  decimals: PropTypes.number,
};
