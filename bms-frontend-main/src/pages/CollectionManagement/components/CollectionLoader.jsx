import PropTypes from 'prop-types';
import LogoLoader from '../../../common/components/LogoLoader.jsx';

/**
 * Branded loader for Collection Management pages and modals.
 * Uses the same LogoLoader as the home dashboard module picker (logo only, no caption).
 */
export default function CollectionLoader({
  size = 80,
  tall = false,
  compact = false,
  className = '',
}) {
  const panelClass = [
    'bms-logo-loader-panel',
    tall && 'bms-logo-loader-panel--tall',
    compact && 'bms-logo-loader-panel--compact',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <div className={panelClass}>
      <LogoLoader
        size={size}
        showText={false}
        fullScreen={false}
        vibrationIntensity="normal"
      />
    </div>
  );
}

CollectionLoader.propTypes = {
  size: PropTypes.number,
  tall: PropTypes.bool,
  compact: PropTypes.bool,
  className: PropTypes.string,
};
