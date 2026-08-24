import React, { useMemo } from 'react';
import logo from '../../assets/images/logo.png';
import './LogoLoader.css';

/**
 * LogoLoader Component
 * A premium loader component that displays the NSSF logo with elegant animations
 * 
 * @param {number} size - Size of the logo in pixels (default: 150)
 * @param {boolean} fullScreen - Whether to display as fullscreen overlay (default: false)
 * @param {string} text - Loading text to display (default: 'Loading...')
 * @param {boolean} showText - Whether to show the loading text (default: true)
 * @param {string} vibrationIntensity - Animation intensity: 'subtle', 'normal', or 'intense' (default: 'normal')
 */
const LogoLoader = ({ 
  size = 150, 
  fullScreen = false, 
  text = 'Loading...',
  showText = true,
  vibrationIntensity = 'normal' // 'subtle', 'normal', 'intense'
}) => {
  const containerClass = fullScreen 
    ? 'logo-loader-fullscreen' 
    : 'logo-loader-container';

  const wrapperClass = `logo-loader-image-wrapper ${
    vibrationIntensity === 'subtle' ? 'vibrate-subtle' : 
    vibrationIntensity === 'intense' ? 'vibrate-intense' : 
    ''
  }`.trim();

  const ringSize = size + 60;
  
  // Generate unique IDs for gradients to avoid conflicts
  const gradientId1 = useMemo(() => `gradient1-${Math.random().toString(36).substr(2, 9)}`, []);
  const gradientId2 = useMemo(() => `gradient2-${Math.random().toString(36).substr(2, 9)}`, []);

  return (
    <div className={containerClass}>
      <div className="logo-loader-content">
        <div className="logo-loader-rings-container">
          {/* Outer rotating ring */}
          <div 
            className="logo-loader-ring logo-loader-ring-outer"
            style={{ width: `${ringSize + 40}px`, height: `${ringSize + 40}px` }}
          >
            <svg className="logo-loader-svg" viewBox="0 0 100 100">
              <defs>
                <linearGradient id={gradientId1} x1="0%" y1="0%" x2="100%" y2="100%">
                  <stop offset="0%" stopColor="#f9c000" stopOpacity="0.8" />
                  <stop offset="50%" stopColor="#962E32" stopOpacity="0.6" />
                  <stop offset="100%" stopColor="#059669" stopOpacity="0.8" />
                </linearGradient>
              </defs>
              <circle
                className="logo-loader-circle logo-loader-circle-outer"
                cx="50"
                cy="50"
                r="45"
                fill="none"
                stroke={`url(#${gradientId1})`}
                strokeWidth="2"
              />
            </svg>
          </div>

          {/* Middle rotating ring */}
          <div 
            className="logo-loader-ring logo-loader-ring-middle"
            style={{ width: `${ringSize}px`, height: `${ringSize}px` }}
          >
            <svg className="logo-loader-svg" viewBox="0 0 100 100">
              <defs>
                <linearGradient id={gradientId2} x1="0%" y1="0%" x2="100%" y2="100%">
                  <stop offset="0%" stopColor="#059669" stopOpacity="0.6" />
                  <stop offset="50%" stopColor="#f9c000" stopOpacity="0.7" />
                  <stop offset="100%" stopColor="#962E32" stopOpacity="0.6" />
                </linearGradient>
              </defs>
              <circle
                className="logo-loader-circle logo-loader-circle-middle"
                cx="50"
                cy="50"
                r="45"
                fill="none"
                stroke={`url(#${gradientId2})`}
                strokeWidth="1.5"
              />
            </svg>
          </div>

          {/* Logo with pulse animation */}
          <div 
            className={wrapperClass}
            style={{ width: `${size}px`, height: `${size}px` }}
          >
            <div className="logo-loader-glow"></div>
            <img 
              src={logo} 
              alt="Logo" 
              className="logo-loader-image"
            />
          </div>

          {/* Inner pulsing ring */}
          <div 
            className="logo-loader-ring logo-loader-ring-inner"
            style={{ width: `${size + 20}px`, height: `${size + 20}px` }}
          >
            <svg className="logo-loader-svg" viewBox="0 0 100 100">
              <circle
                className="logo-loader-circle logo-loader-circle-inner"
                cx="50"
                cy="50"
                r="45"
                fill="none"
                stroke="#f9c000"
                strokeWidth="1"
                strokeOpacity="0.4"
              />
            </svg>
          </div>
        </div>

        {/* Loading dots */}
        {showText && (
          <div className="logo-loader-text-container">
            <div className="logo-loader-dots">
              <span className="logo-loader-dot"></span>
              <span className="logo-loader-dot"></span>
              <span className="logo-loader-dot"></span>
            </div>
            {text && (
              <p className="logo-loader-text">{text}</p>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default LogoLoader;

