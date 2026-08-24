import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ReactApexChart from 'react-apexcharts';
import { useSelector } from 'react-redux';
import { DatePicker } from 'antd';
import dayjs from 'dayjs';
import {
  ArrowUpRight,
  Car,
  CreditCard,
  PieChart,
  TrendingUp,
  Wallet,
  ChevronDown,
  AlertTriangle,
  Layers,
} from 'lucide-react';
import { apiService } from '../../services/api.jsx';
import CollectionLoader from './components/CollectionLoader.jsx';
import {
  mapBodyTypeItems,
  mapFinancialYearsFromApi,
  mapKpisFromApi,
  mapTollTrendEntry,
  mergeBodyTypePeriods,
} from './collectionDashboardTransforms.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const ORANGE = '#E07B3C';
const GOLD = '#F9C002';
const GREEN = '#1B8A4F';
const TITLE_CLASS = 'text-[11px] font-semibold uppercase tracking-[0.14em]';

// Roles allowed to see the money/revenue view of the Toll Passages chart.
// Add or rename role keys as new finance-related roles are introduced.
const REVENUE_VIEW_ROLES = [
  'superadmin',
  'super admin',
  'admin',
  'finance',
  'finance manager',
  'accountant',
  'collection manager',
];

const useCanViewRevenue = () => {
  const selectedRole = useSelector((state) => state.app.selectedRole);
  if (!selectedRole) return false;
  return REVENUE_VIEW_ROLES.includes(String(selectedRole).trim().toLowerCase());
};

const fmt = (n) => Number(n).toLocaleString();

const niceMax = (raw) => {
  if (!raw || raw <= 0) return 10;
  const padded = raw * 1.15;
  const exp = Math.floor(Math.log10(padded));
  const base = Math.pow(10, exp);
  const n = padded / base;
  let nice = 10;
  if (n <= 1) nice = 1;
  else if (n <= 2) nice = 2;
  else if (n <= 2.5) nice = 2.5;
  else if (n <= 5) nice = 5;
  else nice = 10;
  return nice * base;
};

const compactNumber = (n) => {
  const abs = Math.abs(n);
  if (abs >= 1_000_000) return `${(n / 1_000_000).toFixed(abs >= 10_000_000 ? 0 : 1)}M`;
  if (abs >= 1_000) return `${(n / 1_000).toFixed(abs >= 10_000 ? 0 : 1)}K`;
  return `${n}`;
};

const SKEL = 'animate-pulse rounded-md bg-slate-200';

const KpiCardSkeleton = () => (
  <div className="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
    <div className="flex items-start justify-between">
      <div className={`h-4 w-32 ${SKEL}`} />
      <div className={`h-4 w-4 rounded ${SKEL}`} />
    </div>
    <div className={`mt-4 h-8 w-36 ${SKEL}`} />
    <div className={`mt-3 h-3 w-44 ${SKEL}`} />
  </div>
);

const TollPassagesCardSkeleton = () => (
  <div className="flex min-h-[520px] flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div className="flex items-center justify-between">
      <div className={`h-4 w-32 ${SKEL}`} />
      <div className={`h-8 w-48 rounded-lg ${SKEL}`} />
    </div>
    <div className="mt-4 flex items-end justify-between">
      <div>
        <div className={`h-9 w-40 ${SKEL}`} />
        <div className={`mt-2 h-3 w-28 ${SKEL}`} />
      </div>
      <div className={`h-10 w-24 ${SKEL}`} />
    </div>
    <div className={`mt-6 min-h-[260px] flex-1 rounded-xl ${SKEL}`} />
  </div>
);

const KpiCard = ({ icon: Icon, label, value, change, changeColor = GREEN, sub }) => (
  <div className="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
    <div className="flex items-start justify-between">
      <div className="flex items-center gap-2" style={{ color: BRAND }}>
        <Icon size={16} />
        <span className={TITLE_CLASS}>{label}</span>
      </div>
      <ArrowUpRight size={16} className="text-slate-300" />
    </div>
    <div className="mt-3 text-[26px] font-bold tracking-tight text-slate-900">{value}</div>
    <div className="mt-2 flex items-center gap-1.5 text-xs">
      <TrendingUp size={13} style={{ color: changeColor }} />
      <span className="font-semibold" style={{ color: changeColor }}>
        {change}
      </span>
      <span className="text-slate-400">{sub}</span>
    </div>
  </div>
);

const FanGauge = () => {
  const bars = 18;
  const radiusInner = 60;
  const radiusOuter = 110;
  const startAngle = -180;
  const endAngle = 0;

  const getColor = (i) => {
    const colors = [
      '#5A1A1D', '#6E1F22', '#7A2326', '#8C2A2E', '#962E32', '#A23438',
      '#B23F30', '#C24E2A', '#D26124', '#E07B3C', '#E68F36', '#EBA32E',
      '#EFB420', '#F2BD18', '#F5C711', '#F7CE0A', '#F8D204', '#F9C002',
    ];
    return colors[i] || colors[colors.length - 1];
  };

  const cx = 130;
  const cy = 120;

  return (
    <svg viewBox="0 0 260 140" className="mx-auto w-full max-w-[280px]">
      {Array.from({ length: bars }).map((_, i) => {
        const t = i / (bars - 1);
        const angleDeg = startAngle + t * (endAngle - startAngle);
        const angleRad = (angleDeg * Math.PI) / 180;
        const x1 = cx + radiusInner * Math.cos(angleRad);
        const y1 = cy + radiusInner * Math.sin(angleRad);
        const x2 = cx + radiusOuter * Math.cos(angleRad);
        const y2 = cy + radiusOuter * Math.sin(angleRad);
        return (
          <line
            key={i}
            x1={x1}
            y1={y1}
            x2={x2}
            y2={y2}
            stroke={getColor(i)}
            strokeWidth={9}
            strokeLinecap="round"
          />
        );
      })}
    </svg>
  );
};

const TOLL_MONTHS = ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];

const fyFromYear = (year) => `FY ${year}/${year + 1}`;

const yearFromFy = (fy) => {
  const match = String(fy).match(/(\d{4})/);
  return match ? Number(match[1]) : dayjs().year();
};

const TOLL_VIEWS = {
  passages: {
    label: 'Passages',
    seriesName: 'Passages',
    formatY: (v) => compactNumber(v),
    formatTooltip: (v) => fmt(v),
  },
  revenue: {
    label: 'Revenue',
    seriesName: 'Revenue',
    formatY: (v) => `TZS ${compactNumber(v)}`,
    formatTooltip: (v) => `TZS ${fmt(v)}`,
  },
};

const Dropdown = ({ label }) => (
  <button className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
    <span>{label}</span>
    <ChevronDown size={12} className="text-slate-400" />
  </button>
);

const normalizeLaneTransactions = (payload) => {
  const rows = Array.isArray(payload)
    ? payload
    : payload?.lanes || payload?.transactions || payload?.data || [];

  if (!Array.isArray(rows)) return [];

  const byLane = rows.reduce((acc, row) => {
    const code = row.code || row.lane_code || row.lane || row.laneNo || row.lane_number || row.lane_no;
    if (!code) return acc;
    const normalizedCode = String(code).trim().toUpperCase();
    const count = Number(row.count ?? row.total_passages ?? row.passages ?? row.transaction_count ?? 0);
    if (count <= 0) return acc;
    acc[normalizedCode] = (acc[normalizedCode] || 0) + count;
    return acc;
  }, {});

  return Object.entries(byLane)
    .map(([code, count]) => ({ code, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 14);
};

const LanePerformance = ({ lanes, loading, lastUpdated }) => {
  const total = lanes.reduce((sum, l) => sum + l.count, 0);
  const max = Math.max(...lanes.map((l) => l.count), 1);
  const hasData = lanes.length > 0;
  const gridCols = lanes.length <= 7 ? 'grid-cols-1' : 'grid-cols-2';

  return (
    <div className="flex min-h-[520px] flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2" style={{ color: BRAND }}>
          <PieChart size={16} />
          <span className={TITLE_CLASS}>Lane Performance</span>
        </div>
        {loading ? (
          <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
            Updating…
          </span>
        ) : lastUpdated ? (
          <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
            Live
          </span>
        ) : (
          <ArrowUpRight size={16} className="text-slate-300" />
        )}
      </div>

      {loading && !hasData ? (
        <div className="flex flex-1 items-center justify-center py-6">
          <CollectionLoader size={64} compact />
        </div>
      ) : hasData ? (
        <>
          <div className="mt-3 flex items-end justify-center">
            <FanGauge />
          </div>

          <div className="-mt-2 text-center">
            <div className="text-2xl font-bold text-slate-900">{fmt(total)}</div>
            <div className="text-[11px] text-slate-400">
              Today&apos;s passages · {lanes.length} active lane{lanes.length === 1 ? '' : 's'}
              {lastUpdated
                ? ` · ${lastUpdated.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
                : ''}
            </div>
          </div>

          <div className={`mt-5 grid flex-1 auto-rows-min ${gridCols} gap-x-4 gap-y-2`}>
            {lanes.map((lane) => {
              const pct = max ? (lane.count / max) * 100 : 0;
              return (
                <div
                  key={lane.code}
                  className="flex items-center gap-2 rounded-md px-1 transition-colors hover:bg-slate-50"
                >
                  <span
                    className="w-14 shrink-0 truncate text-xs font-semibold text-slate-700"
                    title={lane.code}
                  >
                    {lane.code}
                  </span>
                  <div className="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-slate-100">
                    <div
                      className="h-2 rounded-full transition-[width] duration-300"
                      style={{
                        width: `${Math.max(pct, lane.count > 0 ? 4 : 0)}%`,
                        background: `linear-gradient(90deg, ${BRAND_DARK} 0%, ${ORANGE} 100%)`,
                      }}
                    />
                  </div>
                  <span className="w-12 shrink-0 text-right text-[11px] font-semibold tabular-nums text-slate-900">
                    {fmt(lane.count)}
                  </span>
                </div>
              );
            })}
          </div>
        </>
      ) : (
        <div className="flex flex-1 flex-col items-center justify-center py-16 text-center">
          <p className="text-sm font-medium text-slate-700">No lane activity today</p>
          <p className="mt-1 max-w-[220px] text-xs text-slate-400">
            Passages will appear here as vehicles use each booth.
          </p>
        </div>
      )}
    </div>
  );
};

const BODY_TYPE_PERIODS = [
  { id: 'today', label: 'Today' },
  { id: 'week', label: 'This Week' },
  { id: 'month', label: 'This Month' },
];

const BODY_TYPE_BAR_HEIGHT = 230;
const BODY_TYPE_CHART_MAX = 12;

const getBodyTypeTier = (rank) => {
  if (rank <= 1) return { color: BRAND, label: 'Top performer' };
  if (rank <= 4) return { color: BRAND_DARK, label: 'Rank 3 – 5' };
  if (rank <= 9) return { color: ORANGE, label: 'Rank 6 – 10' };
  return { color: '#CBD5E1', label: 'Rank 11+' };
};

const TIER_LEGEND = [
  { color: BRAND, label: 'Top performer' },
  { color: BRAND_DARK, label: 'Rank 3 – 5' },
  { color: ORANGE, label: 'Rank 6 – 10' },
  { color: '#CBD5E1', label: 'Others' },
];

const BodyTypePerformanceCard = ({
  bodyTypeDataByPeriod,
  bodyTypesLoading,
  onPeriodChange,
}) => {
  const [period, setPeriod] = useState('today');
  const [hoveredIdx, setHoveredIdx] = useState(null);

  const data = bodyTypeDataByPeriod?.[period] ?? [];
  const hasLoaded = bodyTypeDataByPeriod !== null;
  const showInitialLoading = !hasLoaded && bodyTypesLoading;
  const isRefreshing = hasLoaded && bodyTypesLoading;
  const hasData = data.length > 0;
  const total = data.reduce((sum, d) => sum + d.value, 0);
  const periodLabel = BODY_TYPE_PERIODS.find((p) => p.id === period)?.label || '';

  const sorted = useMemo(
    () => [...data].sort((a, b) => b.value - a.value).slice(0, BODY_TYPE_CHART_MAX),
    [data],
  );
  const othersCount = Math.max(data.length - sorted.length, 0);
  const othersTotal = useMemo(() => {
    if (othersCount === 0) return 0;
    const topNames = new Set(sorted.map((d) => d.name));
    return data.filter((d) => !topNames.has(d.name)).reduce((sum, d) => sum + d.value, 0);
  }, [data, sorted, othersCount]);
  const max = useMemo(
    () => niceMax(sorted.reduce((m, b) => Math.max(m, b.value || 0), 0)),
    [sorted],
  );
  const ticks = useMemo(() => [max, max * 0.75, max * 0.5, max * 0.25, 0], [max]);

  const top1 = sorted[0];
  const top1Pct = top1 ? (top1.value / Math.max(total, 1)) * 100 : 0;
  const minVal = sorted[sorted.length - 1]?.value || 0;
  const maxVal = top1?.value || 0;
  const focused = hoveredIdx !== null ? sorted[hoveredIdx] : null;

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2" style={{ color: BRAND }}>
          <Layers size={16} />
          <span className={TITLE_CLASS}>Body Type Performance</span>
        </div>

        <div className="flex items-center gap-2">
          {isRefreshing && (
            <span className="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">
              Updating…
            </span>
          )}
          {hasLoaded && !bodyTypesLoading && hasData && (
            <span
              className="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-white"
              style={{ backgroundColor: BRAND }}
            >
              Live
            </span>
          )}
        <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5">
          {BODY_TYPE_PERIODS.map((p) => {
            const active = period === p.id;
            return (
              <button
                key={p.id}
                type="button"
                onClick={() => {
                  setPeriod(p.id);
                  setHoveredIdx(null);
                  onPeriodChange?.(p.id);
                }}
                className={`rounded-md px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.06em] transition-colors ${
                  active ? 'text-white shadow' : 'text-slate-500 hover:text-slate-700'
                }`}
                style={active ? { backgroundColor: BRAND } : undefined}
              >
                {p.label}
              </button>
            );
          })}
        </div>
        </div>
      </div>

      {showInitialLoading ? (
        <div className="mt-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <div key={i} className="rounded-xl border border-slate-100 p-3">
                <div className={`h-3 w-24 ${SKEL}`} />
                <div className={`mt-3 h-7 w-20 ${SKEL}`} />
                <div className={`mt-2 h-3 w-32 ${SKEL}`} />
              </div>
            ))}
          </div>
          <div className="mt-5 rounded-xl border border-slate-100 bg-slate-50/40 p-4">
            <div className="ml-14 flex items-end gap-2" style={{ height: BODY_TYPE_BAR_HEIGHT }}>
              {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="flex w-10 shrink-0 flex-col items-center justify-end">
                  <div
                    className={`w-full rounded-t-lg ${SKEL}`}
                    style={{ height: `${60 + (i % 4) * 28}px` }}
                  />
                  <div className={`mt-2 h-2 w-full ${SKEL}`} />
                </div>
              ))}
            </div>
          </div>
        </div>
      ) : !hasData ? (
        <div className="mt-8 flex flex-col items-center justify-center py-20 text-center">
          <p className="text-sm font-medium text-slate-700">No passages for {periodLabel.toLowerCase()}</p>
          <p className="mt-1 max-w-sm text-xs text-slate-400">
            Counts include cash, prepayment, and bundle passages by vehicle body type.
          </p>
        </div>
      ) : (
        <>
          <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div className="rounded-xl border border-slate-100 bg-gradient-to-br from-white to-slate-50 p-3">
              <div className="text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-400">
                Total Passages
              </div>
              <div className="mt-1 flex items-baseline gap-2">
                <span className="text-2xl font-bold tracking-tight text-slate-900">{fmt(total)}</span>
              </div>
              <div className="mt-1 text-[11px] text-slate-500">
                {data.length} body type{data.length === 1 ? '' : 's'} · {periodLabel}
              </div>
            </div>

            <div
              className="rounded-xl border p-3"
              style={{
                borderColor: '#F1D7D9',
                background: `linear-gradient(135deg, ${BRAND}10, ${BRAND}05)`,
              }}
            >
              <div
                className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.1em]"
                style={{ color: BRAND }}
              >
                <span
                  className="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full text-[9px] font-bold text-white"
                  style={{ backgroundColor: BRAND }}
                >
                  1
                </span>
                Top Body Type
              </div>
              <div className="mt-1 truncate text-base font-bold" style={{ color: BRAND }}>
                {top1?.name || '—'}
              </div>
              <div className="text-[11px] text-slate-500">
                {fmt(top1?.value || 0)} passages · {top1Pct.toFixed(1)}% of total
              </div>
            </div>

            <div className="rounded-xl border border-slate-100 bg-gradient-to-br from-white to-slate-50 p-3">
              <div className="text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-400">
                {focused ? 'Selected' : 'Range'}
              </div>
              {focused ? (
                <>
                  <div className="mt-1 truncate text-base font-bold text-slate-900">{focused.name}</div>
                  <div className="text-[11px] text-slate-500">
                    {fmt(focused.value)} passages · Rank #{hoveredIdx + 1}
                  </div>
                </>
              ) : (
                <>
                  <div className="mt-1 text-base font-bold text-slate-900">
                    {compactNumber(minVal)} – {compactNumber(maxVal)}
                  </div>
                  <div className="text-[11px] text-slate-500">min – max passages</div>
                </>
              )}
            </div>
          </div>

          <div className="relative mt-5 overflow-x-auto rounded-xl border border-slate-100 bg-slate-50/40 p-4 select-none">
            <div
              className="absolute left-4 right-4 top-4 flex flex-col justify-between"
              style={{ height: `${BODY_TYPE_BAR_HEIGHT}px`, minWidth: `${Math.max(sorted.length * 44, 320)}px` }}
            >
              {ticks.map((t) => (
                <div key={t} className="flex items-center gap-3">
                  <span className="w-12 shrink-0 text-right text-[11px] font-medium text-slate-400">
                    {compactNumber(Math.round(t))}
                  </span>
                  <span className="h-px flex-1 border-t border-dashed border-slate-200" />
                </div>
              ))}
            </div>

            <div
              className="relative ml-14 flex items-end gap-2"
              style={{ height: `${BODY_TYPE_BAR_HEIGHT}px`, minWidth: `${Math.max(sorted.length * 44, 320)}px` }}
            >
              {sorted.map((item, idx) => {
                const barHeight = max ? Math.max((item.value / max) * BODY_TYPE_BAR_HEIGHT, 6) : 0;
                const isActive = hoveredIdx === idx;
                const tier = getBodyTypeTier(idx);
                const showRank = idx < 3;

                return (
                  <div
                    key={item.name}
                    className="group relative flex w-10 shrink-0 flex-col items-center justify-end sm:w-11"
                    onMouseEnter={() => setHoveredIdx(idx)}
                    onMouseLeave={() => setHoveredIdx(null)}
                  >
                    {isActive && (
                      <div className="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 max-w-[200px] -translate-x-1/2 rounded-xl bg-[#1F1B1B] px-3 py-2 text-center text-white shadow-2xl">
                        <div className="text-xs font-bold leading-snug">{item.name}</div>
                        <div className="mt-0.5 text-[11px]">{fmt(item.value)} passages</div>
                        <div className="text-[10px] text-slate-300">
                          Rank #{idx + 1} · {((item.value / Math.max(total, 1)) * 100).toFixed(1)}%
                        </div>
                        <span className="absolute left-1/2 top-full -mt-px h-2 w-2 -translate-x-1/2 rotate-45 bg-[#1F1B1B]" />
                      </div>
                    )}

                    {showRank && !isActive && (
                      <span
                        className="absolute z-10 inline-flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-bold text-white shadow-sm"
                        style={{ backgroundColor: tier.color, bottom: `${barHeight + 4}px` }}
                      >
                        {idx + 1}
                      </span>
                    )}

                    <div
                      className="w-full overflow-hidden rounded-t-lg transition-all duration-200"
                      style={{
                        height: `${barHeight}px`,
                        backgroundColor: tier.color,
                        backgroundImage:
                          'repeating-linear-gradient(45deg, rgba(255,255,255,0.16) 0 3px, transparent 3px 8px)',
                        boxShadow: isActive
                          ? '0 10px 20px rgba(15,23,42,0.22)'
                          : '0 1px 2px rgba(15,23,42,0.06)',
                        transform: isActive ? 'translateY(-3px)' : 'translateY(0)',
                      }}
                    />
                    <span
                      className="mt-2 max-h-8 w-full truncate text-center text-[9px] font-medium leading-tight text-slate-500"
                      title={item.name}
                    >
                      {item.name}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="mt-3 flex flex-wrap items-center justify-between gap-3 text-[11px] text-slate-500">
            <div className="flex flex-wrap items-center gap-3">
              {TIER_LEGEND.map((t) => (
                <span key={t.label} className="flex items-center gap-1.5">
                  <span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: t.color }} />
                  {t.label}
                </span>
              ))}
            </div>
            <span className="text-slate-400">
              Top {sorted.length} shown
              {othersCount > 0 ? ` · +${othersCount} more (${fmt(othersTotal)})` : ''}
              {' · '}
              Hover for details
            </span>
          </div>
        </>
      )}
    </div>
  );
};

const TollPassagesCard = ({
  tollDataByFy,
  financialYears,
  fyStartYearByLabel,
  onFinancialYearChange,
  chartLoading,
}) => {
  const canViewRevenue = useCanViewRevenue();
  const [view, setView] = useState('passages');
  const yearOptions = financialYears ?? [];
  const [financialYear, setFinancialYear] = useState(null);

  useEffect(() => {
    if (!financialYears?.length) return;
    setFinancialYear((prev) => (prev && financialYears.includes(prev) ? prev : financialYears[0]));
  }, [financialYears]);

  const activeView = canViewRevenue ? view : 'passages';
  const viewMeta = TOLL_VIEWS[activeView];
  const yearData = financialYear ? tollDataByFy?.[financialYear] : null;
  const showSkeleton = chartLoading || !yearOptions.length || !yearData;

  const series = useMemo(
    () => (yearData ? [{ name: viewMeta.seriesName, data: yearData[activeView].data }] : []),
    [viewMeta, yearData, activeView],
  );

  const chart = useMemo(
    () => ({
      options: {
        chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit' },
        colors: [BRAND],
        stroke: { curve: 'smooth', width: 3 },
        fill: {
          type: 'gradient',
          gradient: { shadeIntensity: 0.2, opacityFrom: 0.18, opacityTo: 0.02, stops: [0, 90, 100] },
        },
        dataLabels: { enabled: false },
        grid: { borderColor: '#EEF1F5', strokeDashArray: 4, padding: { left: 6, right: 6 } },
        legend: { show: false },
        markers: { size: 0, hover: { size: 5 } },
        xaxis: {
          categories: TOLL_MONTHS,
          axisBorder: { show: false },
          axisTicks: { show: false },
          labels: { style: { colors: '#94A3B8', fontSize: '11px' } },
        },
        yaxis: {
          labels: {
            formatter: viewMeta.formatY,
            style: { colors: '#94A3B8', fontSize: '11px' },
          },
        },
        tooltip: {
          shared: true,
          custom: ({ dataPointIndex }) => {
            const m = TOLL_MONTHS[dataPointIndex];
            const a = series[0].data[dataPointIndex];
            return `
              <div style="background:#0f172a;color:#fff;padding:10px 12px;border-radius:8px;font-size:12px;min-width:200px;">
                <div style="font-weight:600;margin-bottom:6px;">${m} · ${financialYear}</div>
                <div style="display:flex;justify-content:space-between;gap:16px;">
                  <span style="color:#E07B3C;">● ${viewMeta.seriesName}</span>
                  <span>${viewMeta.formatTooltip(a)}</span>
                </div>
              </div>`;
          },
        },
      },
      series,
    }),
    [viewMeta, series, financialYear],
  );

  if (showSkeleton) {
    return <TollPassagesCardSkeleton />;
  }

  return (
    <div className="flex min-h-[520px] flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2" style={{ color: BRAND }}>
          <Car size={16} />
          <span className={TITLE_CLASS}>Toll Passages</span>
        </div>

        <div className="flex items-center gap-2">
          <DatePicker
            picker="year"
            size="small"
            allowClear={false}
            value={dayjs().year(yearFromFy(financialYear))}
            onChange={(d) => {
              if (!d) return;
              const next = fyFromYear(d.year());
              if (tollDataByFy?.[next] || fyStartYearByLabel?.[next]) {
                setFinancialYear(next);
                onFinancialYearChange?.(next, fyStartYearByLabel?.[next] ?? d.year());
              }
            }}
            disabledDate={(d) => {
              const fy = fyFromYear(d.year());
              return !(tollDataByFy?.[fy] || fyStartYearByLabel?.[fy]);
            }}
            format={(value) => `FY ${value.year()}/${value.year() + 1}`}
            className="!rounded-lg"
          />

          {canViewRevenue && (
            <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5">
              {['passages', 'revenue'].map((v) => {
                const active = activeView === v;
                return (
                  <button
                    key={v}
                    type="button"
                    onClick={() => setView(v)}
                    className={`rounded-md px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] transition-colors ${
                      active ? 'text-white shadow' : 'text-slate-500 hover:text-slate-700'
                    }`}
                    style={active ? { backgroundColor: BRAND } : undefined}
                  >
                    {TOLL_VIEWS[v].label}
                  </button>
                );
              })}
            </div>
          )}
        </div>
      </div>

      <div className="mt-3 flex items-end justify-between">
        <div>
          <div className="flex items-end gap-2">
            <span className="text-3xl font-bold tracking-tight text-slate-900">
              {yearData[activeView].headline}
            </span>
            <span className="mb-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
              <TrendingUp size={11} />
              {yearData[activeView].delta}
            </span>
          </div>
          <div className="text-[11px] text-slate-400">{financialYear}</div>
        </div>
        <div className="text-right">
          <div className="text-[11px] text-slate-400">Financial Year</div>
          <div className="flex items-center justify-end gap-1 text-sm font-semibold text-slate-700">
            {financialYear}
            <span className="h-2 w-2 rounded-full bg-[#F9C002]" />
          </div>
        </div>
      </div>
      <div className="mt-3 min-h-[260px] flex-1">
        <ReactApexChart options={chart.options} series={chart.series} type="area" height="100%" />
      </div>
    </div>
  );
};

const BAR_AREA_HEIGHT = 150;

const BarGraph = ({ bars, tooltipTitle = 'Today', valuePrefix = 'TZS ' }) => {
  const [hoveredIdx, setHoveredIdx] = useState(null);

  const max = useMemo(
    () => niceMax(bars.reduce((m, b) => Math.max(m, b.value || 0), 0)),
    [bars],
  );

  const ticks = useMemo(() => [max, max * 0.75, max * 0.5, max * 0.25, 0], [max]);

  return (
    <div className="relative mt-4 select-none">
      <div
        className="absolute left-0 right-0 top-6 flex flex-col justify-between"
        style={{ height: `${BAR_AREA_HEIGHT}px` }}
      >
        {ticks.map((t) => (
          <div key={t} className="flex items-center gap-3">
            <span className="w-12 text-right text-xs font-medium text-slate-400">
              {compactNumber(Math.round(t))}
            </span>
            <span className="h-px flex-1 border-t border-dashed border-slate-200" />
          </div>
        ))}
      </div>

      <div
        className="ml-14 flex items-end justify-around gap-2 pt-6"
        style={{ height: `${BAR_AREA_HEIGHT + 24}px` }}
      >
        {bars.map((bar, idx) => {
          const barHeight = max ? Math.max((bar.value / max) * BAR_AREA_HEIGHT, 22) : 0;
          const isActive = hoveredIdx === idx;

          return (
            <div
              key={bar.label}
              className="group relative flex min-w-0 flex-1 flex-col items-center gap-2"
              onMouseEnter={() => setHoveredIdx(idx)}
              onMouseLeave={() => setHoveredIdx(null)}
            >
              <div className="relative flex w-full max-w-[72px] items-end justify-center">
                {isActive && (bar.revenue !== undefined || bar.target !== undefined) && (
                  <div className="pointer-events-none absolute bottom-full left-1/2 z-30 mb-3 -translate-x-1/2 whitespace-nowrap rounded-xl bg-[#1F1B1B] px-4 py-3 text-white shadow-2xl">
                    <div className="mb-2 text-sm font-bold">{tooltipTitle}</div>
                    {bar.revenue !== undefined && (
                      <div className="flex items-center justify-between gap-8 text-xs">
                        <span className="flex items-center gap-2">
                          <span className="h-2 w-2 rounded-full bg-[#C23A45]" />
                          Revenue
                        </span>
                        <span className="font-semibold">{`${valuePrefix}${fmt(bar.revenue)}`}</span>
                      </div>
                    )}
                    {bar.target !== undefined && (
                      <div className="mt-1.5 flex items-center justify-between gap-8 text-xs">
                        <span className="flex items-center gap-2">
                          <span className="h-2 w-2 rounded-full bg-[#F0A01E]" />
                          Target
                        </span>
                        <span className="font-semibold">{`${valuePrefix}${fmt(bar.target)}`}</span>
                      </div>
                    )}
                    <span className="absolute left-1/2 top-full -mt-px h-2 w-2 -translate-x-1/2 rotate-45 bg-[#1F1B1B]" />
                  </div>
                )}
                <div
                  className="flex w-full items-end justify-center overflow-hidden rounded-md transition-all duration-200"
                  style={{
                    height: `${barHeight}px`,
                    backgroundColor: bar.color || '#E5E7EB',
                    backgroundImage:
                      'repeating-linear-gradient(45deg, rgba(255,255,255,0.14) 0 3px, transparent 3px 8px)',
                    boxShadow: isActive
                      ? '0 8px 16px rgba(15,23,42,0.18)'
                      : '0 1px 2px rgba(15,23,42,0.06)',
                    transform: isActive ? 'translateY(-2px)' : 'translateY(0)',
                  }}
                >
                  <span
                    className="pb-1.5 text-base font-bold leading-none"
                    style={{ color: bar.textColor || '#334155' }}
                  >
                    {fmt(bar.value)}
                  </span>
                </div>
              </div>
              <span className="max-w-[80px] truncate text-center text-xs font-medium text-slate-500">
                {bar.label}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
};

const DailyTollPassagesCard = ({
  bars,
  title = 'Daily Toll Passages',
  filterLabel = 'Today Last 82days',
  tooltipTitle = 'Today',
  valuePrefix = 'TZS ',
  caption = 'Toll activity breakdown',
}) => {
  const total = useMemo(
    () => bars.reduce((sum, b) => sum + (b.value || 0), 0),
    [bars],
  );

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2" style={{ color: BRAND }}>
          <Car size={16} />
          <span className={TITLE_CLASS}>{title}</span>
        </div>
        <Dropdown label={filterLabel} />
      </div>
      <div className="mt-3 flex items-end justify-between">
        <div>
          <span className="text-2xl font-bold tracking-tight text-slate-900">{fmt(total)}</span>
          <span className="ml-2 text-[11px] text-slate-400">today</span>
          <div className="text-[11px] text-slate-400">{caption}</div>
        </div>
      </div>
      <BarGraph bars={bars} tooltipTitle={tooltipTitle} valuePrefix={valuePrefix} />
    </div>
  );
};

const TopBridgeIncidentsCard = () => (
  <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
    <div className="flex items-center justify-between">
      <div className="flex items-center gap-2" style={{ color: BRAND }}>
        <AlertTriangle size={16} />
        <span className={TITLE_CLASS}>Top Bridge Incidents</span>
      </div>
      <Dropdown label="Last 30 days" />
    </div>
    <div className="mt-3 flex items-center justify-between">
      <div>
        <div className="flex items-end gap-2">
          <span className="text-2xl font-bold tracking-tight text-slate-900">156</span>
          <span className="mb-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
            <TrendingUp size={11} />
            +18%
          </span>
        </div>
        <div className="text-[11px] text-slate-400">Reported Cases</div>
      </div>
      <div className="text-right">
        <div className="text-2xl font-bold tracking-tight text-slate-900">16</div>
        <div className="text-[11px] text-slate-400">Active Cases</div>
      </div>
    </div>
    <div className="mt-4 space-y-2.5">
      {[
        { name: 'Bridge A', value: 84, count: '78' },
        { name: 'Bridge B', value: 58, count: '54' },
        { name: 'Bridge C', value: 32, count: '14' },
        { name: 'Bridge D', value: 18, count: '10' },
      ].map((b) => (
        <div key={b.name} className="flex items-center gap-3">
          <span className="w-20 text-sm text-slate-700">{b.name}</span>
          <div className="relative flex-1 rounded-full bg-slate-100">
            <div
              className="h-2 rounded-full"
              style={{ width: `${b.value}%`, backgroundColor: BRAND }}
            />
          </div>
          <span className="inline-flex items-center justify-center rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700">
            {b.count}
          </span>
          <span className="text-[11px] text-slate-400">1m</span>
        </div>
      ))}
    </div>
  </div>
);

const TopBridgesByPassagesCard = () => (
  <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
    <div className="flex items-center justify-between">
      <div className="flex items-center gap-2" style={{ color: BRAND }}>
        <Car size={16} />
        <span className={TITLE_CLASS}>Top Bridges by Passages</span>
      </div>
      <ArrowUpRight size={16} className="text-slate-300" />
    </div>
    <div className="mt-3 space-y-3">
      {[
        { name: 'Bridge A', count: 2785, fill: 92 },
        { name: 'Bridge B', count: 1565, fill: 60 },
        { name: 'Bridge C', count: 1148, fill: 44 },
        { name: 'Bridge D', count: 892, fill: 32 },
      ].map((b) => (
        <div key={b.name} className="flex items-center gap-3">
          <span className="w-20 text-sm text-slate-700">{b.name}</span>
          <span className="w-14 text-right text-sm font-semibold text-slate-900">
            {fmt(b.count)}
          </span>
          <div className="flex-1">
            <div
              className="h-2.5 rounded-full"
              style={{
                width: `${b.fill}%`,
                background: `linear-gradient(90deg, ${BRAND_DARK} 0%, ${ORANGE} 100%)`,
              }}
            />
          </div>
          <span className="text-[11px] text-slate-400">Today</span>
        </div>
      ))}
    </div>
  </div>
);

const EMPTY_KPIS = {
  totalPassages: { value: '0', change: '+0%' },
  bundlePassages: { value: '0', change: '+0%' },
  prepaymentPassages: { value: '0', change: '+0%' },
  cashPassages: { value: '0', change: '+0%' },
  periodLabel: 'last month',
};

const CollectionDashboard = () => {
  const [incidentBars] = useState([]);
  const [vehicleBars] = useState([]);
  const [laneTransactions, setLaneTransactions] = useState([]);
  const [laneLoading, setLaneLoading] = useState(true);
  const [laneLastUpdated, setLaneLastUpdated] = useState(null);
  const [kpis, setKpis] = useState(null);
  const [kpisLoading, setKpisLoading] = useState(true);
  const [bodyTypeDataByPeriod, setBodyTypeDataByPeriod] = useState(null);
  const [bodyTypesLoading, setBodyTypesLoading] = useState(true);
  const [tollDataByFy, setTollDataByFy] = useState(null);
  const [tollChartLoading, setTollChartLoading] = useState(true);
  const [financialYears, setFinancialYears] = useState(null);
  const [fyStartYearByLabel, setFyStartYearByLabel] = useState(null);
  const tollTrendsLoadingRef = useRef(new Set());
  const bodyTypeDataRef = useRef(null);

  void incidentBars;
  void vehicleBars;

  const loadBodyTypes = useCallback(async (periods = ['today', 'week', 'month']) => {
    setBodyTypesLoading(true);
    try {
      const results = await Promise.all(
        periods.map((period) => apiService.getCollectionDashboardBodyTypes(period)),
      );
      const merged = mergeBodyTypePeriods(
        periods[0] ? results[0] : null,
        periods[1] ? results[1] : null,
        periods[2] ? results[2] : null,
      );

      if (periods.length === 3) {
        if (merged !== null) {
          setBodyTypeDataByPeriod({
            today: merged.today ?? [],
            week: merged.week ?? [],
            month: merged.month ?? [],
          });
        } else {
          setBodyTypeDataByPeriod({ today: [], week: [], month: [] });
        }
        return;
      }

      const period = periods[0];
      const mapped = mapBodyTypeItems(results[0]);
      if (mapped !== null) {
        setBodyTypeDataByPeriod((prev) => ({
          ...(prev ?? { today: [], week: [], month: [] }),
          [period]: mapped,
        }));
      }
    } catch (err) {
      console.error('Failed to load body type performance', err);
      setBodyTypeDataByPeriod((prev) => prev ?? { today: [], week: [], month: [] });
    } finally {
      setBodyTypesLoading(false);
    }
  }, []);

  useEffect(() => {
    bodyTypeDataRef.current = bodyTypeDataByPeriod;
  }, [bodyTypeDataByPeriod]);

  const handleBodyTypePeriodChange = useCallback(
    (period) => {
      const prev = bodyTypeDataRef.current;
      if (prev && Object.prototype.hasOwnProperty.call(prev, period)) {
        return;
      }
      loadBodyTypes([period]);
    },
    [loadBodyTypes],
  );

  useEffect(() => {
    let cancelled = false;

    const loadKpis = async () => {
      setKpisLoading(true);
      try {
        const res = await apiService.getCollectionDashboardKpis();
        if (cancelled) return;
        if (res.success) {
          const mapped = mapKpisFromApi(res.data);
          if (mapped) setKpis(mapped);
          else setKpis(EMPTY_KPIS);
        } else {
          setKpis(EMPTY_KPIS);
        }
      } catch (err) {
        console.error('Failed to load collection dashboard KPIs', err);
        if (!cancelled) setKpis(EMPTY_KPIS);
      } finally {
        if (!cancelled) setKpisLoading(false);
      }
    };

    const loadFinancialYears = async () => {
      try {
        const res = await apiService.getCollectionDashboardFinancialYears();
        if (cancelled || !res.success) {
          if (!cancelled) setTollChartLoading(false);
          return;
        }
        const mapped = mapFinancialYearsFromApi(res.data);
        if (mapped) {
          setFinancialYears(mapped.labels);
          setFyStartYearByLabel(mapped.startYearByLabel);
          const firstYear = mapped.startYearByLabel[mapped.labels[0]];
          if (firstYear) await loadTollTrendsForYear(mapped.labels[0], firstYear, cancelled);
        } else if (!cancelled) {
          setTollChartLoading(false);
        }
      } catch (err) {
        console.error('Failed to load financial years', err);
        if (!cancelled) setTollChartLoading(false);
      }
    };

    const loadTollTrendsForYear = async (fyLabel, startYear, isCancelled) => {
      if (!fyLabel || !startYear || tollTrendsLoadingRef.current.has(fyLabel)) return;
      tollTrendsLoadingRef.current.add(fyLabel);
      if (!isCancelled) setTollChartLoading(true);
      try {
        const res = await apiService.getCollectionDashboardTollTrends(startYear);
        if (isCancelled || !res.success) return;
        const entry = mapTollTrendEntry(res.data);
        if (entry) {
          setTollDataByFy((prev) => ({
            ...(prev ?? {}),
            [entry.label]: entry.entry,
          }));
        }
      } catch (err) {
        console.error('Failed to load toll trends', err);
      } finally {
        tollTrendsLoadingRef.current.delete(fyLabel);
        if (!isCancelled) setTollChartLoading(false);
      }
    };

    loadBodyTypes();
    loadKpis();
    loadFinancialYears();

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loadBodyTypes]);

  const handleFinancialYearChange = useCallback(
    async (fyLabel, startYear) => {
      if (!fyLabel || tollDataByFy?.[fyLabel] || tollTrendsLoadingRef.current.has(fyLabel)) {
        return;
      }
      const year = startYear ?? fyStartYearByLabel?.[fyLabel] ?? yearFromFy(fyLabel);
      tollTrendsLoadingRef.current.add(fyLabel);
      setTollChartLoading(true);
      try {
        const res = await apiService.getCollectionDashboardTollTrends(year);
        if (!res.success) return;
        const entry = mapTollTrendEntry(res.data);
        if (entry) {
          setTollDataByFy((prev) => ({
            ...(prev ?? {}),
            [entry.label]: entry.entry,
          }));
        }
      } catch (err) {
        console.error('Failed to load toll trends for selected year', err);
      } finally {
        tollTrendsLoadingRef.current.delete(fyLabel);
        setTollChartLoading(false);
      }
    },
    [fyStartYearByLabel, tollDataByFy],
  );

  useEffect(() => {
    let cancelled = false;
    let refreshTimer;

    const loadLiveLaneTransactions = async (isBackgroundRefresh = false) => {
      if (!isBackgroundRefresh) setLaneLoading(true);
      try {
        const response = await apiService.getCollectionDashboardLanePerformance();
        if (cancelled) return;

        if (response.success) {
          setLaneTransactions(normalizeLaneTransactions(response.data));
          setLaneLastUpdated(new Date());
        }
      } catch (err) {
        console.error('Failed to load live lane transactions', err);
      } finally {
        if (!cancelled) setLaneLoading(false);
      }
    };

    loadLiveLaneTransactions(false);
    refreshTimer = setInterval(() => loadLiveLaneTransactions(true), 30_000);

    return () => {
      cancelled = true;
      if (refreshTimer) clearInterval(refreshTimer);
    };
  }, []);

  const kpiData = kpis ?? EMPTY_KPIS;

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {kpisLoading ? (
          Array.from({ length: 4 }).map((_, i) => <KpiCardSkeleton key={i} />)
        ) : (
          <>
            <KpiCard
              icon={Car}
              label="Total Passages"
              value={kpiData.totalPassages.value}
              change={kpiData.totalPassages.change}
              sub={kpiData.periodLabel}
            />
            <KpiCard
              icon={CreditCard}
              label="Bundle Passages"
              value={kpiData.bundlePassages.value}
              change={kpiData.bundlePassages.change}
              sub={kpiData.periodLabel}
            />
            <KpiCard
              icon={Wallet}
              label="Prepayment Passage"
              value={kpiData.prepaymentPassages.value}
              change={kpiData.prepaymentPassages.change}
              sub={kpiData.periodLabel}
            />
            <KpiCard
              icon={Wallet}
              label="Cash Passage"
              value={kpiData.cashPassages.value}
              change={kpiData.cashPassages.change}
              sub={kpiData.periodLabel}
            />
          </>
        )}
      </div>

      <BodyTypePerformanceCard
        bodyTypeDataByPeriod={bodyTypeDataByPeriod}
        bodyTypesLoading={bodyTypesLoading}
        onPeriodChange={handleBodyTypePeriodChange}
      />

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <div className="xl:col-span-4">
          <LanePerformance
            lanes={laneTransactions}
            loading={laneLoading}
            lastUpdated={laneLastUpdated}
          />
        </div>

        <div className="space-y-4 xl:col-span-8">
          <TollPassagesCard
            tollDataByFy={tollDataByFy}
            financialYears={financialYears}
            fyStartYearByLabel={fyStartYearByLabel}
            onFinancialYearChange={handleFinancialYearChange}
            chartLoading={tollChartLoading}
          />
          {/* Hidden for now - Daily Toll Passages (incidents) + Top Bridge Incidents,
              and Daily Toll Passages (vehicle categories) + Top Bridges by Passages.
          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <DailyTollPassagesCard
              bars={incidentBars}
              title="Daily Toll Passages"
              filterLabel="Today Last 82days"
              tooltipTitle="June 2023"
              caption="Incidents and tickets breakdown"
            />
            <TopBridgeIncidentsCard />
          </div>
          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <DailyTollPassagesCard
              bars={vehicleBars}
              title="Daily Toll Passages"
              filterLabel="Categories"
              tooltipTitle="Today"
              caption="Vehicle category split"
            />
            <TopBridgesByPassagesCard />
          </div>
          */}
        </div>
      </div>
    </div>
  );
};

export default CollectionDashboard;
