import { DatePicker } from 'antd';
import dayjs from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import { Calendar } from 'lucide-react';
import PropTypes from 'prop-types';
import clsx from 'clsx';

import './BmsDatePicker.css';

dayjs.extend(customParseFormat);

export const BMS_DATE_FORMAT = 'YYYY-MM-DD';
export const BMS_DATETIME_FORMAT = 'YYYY-MM-DD HH:mm:ss';
const DISPLAY_DATE = 'DD MMM YYYY';
const DISPLAY_DATETIME = 'DD MMM YYYY, HH:mm';

/**
 * Parse API / form string into dayjs for the picker.
 */
export function parseBmsDateValue(value, showTime = false) {
  if (value === null || value === undefined || value === '') return null;
  const formats = showTime
    ? [BMS_DATETIME_FORMAT, 'YYYY-MM-DDTHH:mm', 'YYYY-MM-DD HH:mm', BMS_DATE_FORMAT]
    : [BMS_DATE_FORMAT, 'DD-MM-YYYY', 'DD/MM/YYYY'];
  const parsed = dayjs(value, formats, true);
  return parsed.isValid() ? parsed : null;
}

/**
 * Brand-styled date (or date-time) picker. Value is always emitted as
 * `YYYY-MM-DD` or `YYYY-MM-DD HH:mm:ss` for API compatibility.
 */
export default function BmsDatePicker({
  value,
  onChange,
  showTime = false,
  disabled = false,
  allowClear = true,
  placeholder,
  className,
  size = 'middle',
  picker,
  disabledDate,
  minDate,
  maxDate,
  id,
}) {
  const pickerValue = parseBmsDateValue(value, showTime);
  const displayFormat = picker === 'month'
    ? 'MMMM YYYY'
    : showTime
      ? DISPLAY_DATETIME
      : DISPLAY_DATE;

  const mergedDisabledDate = (current) => {
    if (!current) return false;
    if (minDate && current.isBefore(dayjs(minDate).startOf('day'))) return true;
    if (maxDate && current.isAfter(dayjs(maxDate).endOf('day'))) return true;
    if (typeof disabledDate === 'function') return disabledDate(current);
    return false;
  };

  return (
    <DatePicker
      id={id}
      picker={picker}
      showTime={
        showTime
          ? {
              format: 'HH:mm',
              minuteStep: 1,
            }
          : false
      }
      format={displayFormat}
      value={pickerValue}
      onChange={(date) => {
        if (!onChange) return;
        if (!date) {
          onChange('');
          return;
        }
        onChange(date.format(showTime ? BMS_DATETIME_FORMAT : BMS_DATE_FORMAT));
      }}
      disabled={disabled}
      allowClear={allowClear}
      placeholder={placeholder ?? (picker === 'month' ? 'Select month' : showTime ? 'Select date & time' : 'Select date')}
      size={size}
      className={clsx('bms-date-picker', className)}
      classNames={{ popup: { root: 'bms-date-picker-popup' } }}
      suffixIcon={<Calendar className="pointer-events-none h-4 w-4 text-slate-400" strokeWidth={2} />}
      disabledDate={mergedDisabledDate}
      needConfirm={showTime}
    />
  );
}

BmsDatePicker.propTypes = {
  value: PropTypes.string,
  onChange: PropTypes.func,
  showTime: PropTypes.bool,
  disabled: PropTypes.bool,
  allowClear: PropTypes.bool,
  placeholder: PropTypes.string,
  className: PropTypes.string,
  size: PropTypes.oneOf(['small', 'middle', 'large']),
  picker: PropTypes.string,
  disabledDate: PropTypes.func,
  minDate: PropTypes.string,
  maxDate: PropTypes.string,
  id: PropTypes.string,
};

/**
 * Burgundy-styled range picker for filter bars. Emits { from, to } as YYYY-MM-DD.
 */
export function BmsDateRangePicker({
  valueFrom = '',
  valueTo = '',
  onChange,
  disabled = false,
  allowClear = true,
  className,
  size = 'middle',
}) {
  const rangeValue = [
    parseBmsDateValue(valueFrom),
    parseBmsDateValue(valueTo),
  ];

  return (
    <DatePicker.RangePicker
      format={DISPLAY_DATE}
      value={rangeValue[0] && rangeValue[1] ? rangeValue : null}
      onChange={(dates) => {
        if (!onChange) return;
        if (!dates || !dates[0] || !dates[1]) {
          onChange({ from: '', to: '' });
          return;
        }
        onChange({
          from: dates[0].format(BMS_DATE_FORMAT),
          to: dates[1].format(BMS_DATE_FORMAT),
        });
      }}
      disabled={disabled}
      allowClear={allowClear}
      size={size}
      className={clsx('bms-date-picker', className)}
      classNames={{ popup: { root: 'bms-date-picker-popup' } }}
      placeholder={['Start date', 'End date']}
      suffixIcon={<Calendar className="pointer-events-none h-4 w-4 text-slate-400" strokeWidth={2} />}
    />
  );
}

BmsDateRangePicker.propTypes = {
  valueFrom: PropTypes.string,
  valueTo: PropTypes.string,
  onChange: PropTypes.func,
  disabled: PropTypes.bool,
  allowClear: PropTypes.bool,
  className: PropTypes.string,
  size: PropTypes.oneOf(['small', 'middle', 'large']),
};

/**
 * Label + picker field matching BMS modal / report filter styling.
 */
export function BmsDatePickerField({
  label,
  labelClassName,
  required,
  children,
  className,
}) {
  return (
    <div className={className}>
      {label ? (
        <label
          className={labelClassName ?? 'mb-1.5 block text-xs font-semibold tracking-[0.01em]'}
          style={!labelClassName ? { color: '#962E32' } : undefined}
        >
          {label}
          {required ? <span className="text-red-500"> *</span> : null}
        </label>
      ) : null}
      {children}
    </div>
  );
}

BmsDatePickerField.propTypes = {
  label: PropTypes.node,
  labelClassName: PropTypes.string,
  required: PropTypes.bool,
  children: PropTypes.node,
  className: PropTypes.string,
};
