import React from 'react';
import { REPORT_ENGINE_BRAND } from './reportEngineModal.styles.js';

const SmallActionButton = React.forwardRef(
  (
    {
      onClick,
      children,
      disabled = false,
      loading = false,
      variant = 'primary',
      htmlType = 'button',
      size = 'small',
      style = {},
      ...rest
    },
    ref
  ) => {
    const sizeConfig = {
      small: { padding: '4px 12px', fontSize: '12px', height: '28px' },
      medium: { padding: '6px 16px', fontSize: '14px', height: '32px' },
      large: { padding: '8px 20px', fontSize: '16px', height: '36px' },
    };

    const colorConfig = {
      primary: { normal: REPORT_ENGINE_BRAND, hover: '#7A2326', text: 'white' },
      default: { normal: '#d9d9d9', hover: '#bfbfbf', text: '#595959' },
      danger: { normal: '#ff4d4f', hover: '#ff7875', text: 'white' },
    };

    const colors = colorConfig[variant] || colorConfig.primary;
    const sizes = sizeConfig[size] || sizeConfig.small;

    return (
      <button
        ref={ref}
        type={htmlType}
        onClick={onClick}
        disabled={disabled || loading}
        {...rest}
        style={{
          backgroundColor: disabled ? '#f5f5f5' : colors.normal,
          color: disabled ? '#999' : colors.text,
          border: 'none',
          borderRadius: '4px',
          ...sizes,
          cursor: disabled || loading ? 'not-allowed' : 'pointer',
          transition: 'background-color 0.3s, opacity 0.3s',
          opacity: loading ? 0.7 : 1,
          ...style,
        }}
        onMouseOver={(e) => {
          if (!disabled && !loading) {
            e.currentTarget.style.backgroundColor = colors.hover;
          }
        }}
        onMouseOut={(e) => {
          if (!disabled && !loading) {
            e.currentTarget.style.backgroundColor = colors.normal;
          }
        }}
      >
        {children}
      </button>
    );
  }
);

SmallActionButton.displayName = 'SmallActionButton';

export default SmallActionButton;
