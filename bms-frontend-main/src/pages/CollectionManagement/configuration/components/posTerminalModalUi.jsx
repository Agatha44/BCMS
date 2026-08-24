import React from 'react';

export const BRAND = '#962E32';
export const BRAND_DARK = '#7A2326';
export const EMPTY_VALUE = 'N/A';
export const INPUT_CLASS =
  'w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20';

export const FormLabel = ({ children, required = false }) => (
  <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
    {children}
    {required && <span className="text-red-500"> *</span>}
  </label>
);

export const ReadOnlyField = ({ label, value, mono = false }) => (
  <div className="min-w-0 py-1.5">
    <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <div className={`mt-0.5 text-sm font-medium text-black ${mono ? 'font-mono' : ''}`}>
      {value != null && value !== '' ? value : <span className="font-normal text-slate-400">{EMPTY_VALUE}</span>}
    </div>
  </div>
);

export const SectionTitle = ({ children }) => (
  <h4
    className="mb-4 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
    style={{ color: BRAND }}
  >
    {children}
  </h4>
);

export const brandPrimaryButtonProps = {
  style: { backgroundColor: BRAND, borderColor: BRAND },
  onMouseEnter: (e) => {
    e.currentTarget.style.backgroundColor = BRAND_DARK;
    e.currentTarget.style.borderColor = BRAND_DARK;
  },
  onMouseLeave: (e) => {
    e.currentTarget.style.backgroundColor = BRAND;
    e.currentTarget.style.borderColor = BRAND;
  },
};
