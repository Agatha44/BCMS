import { useNavigate } from 'react-router-dom';
import { BarChart3, ChevronRight, Settings, SlidersHorizontal } from 'lucide-react';

const BRAND = '#962E32';

const CONFIG_ITEMS = [
  {
    key: 'report-engine',
    title: 'Report Engine',
    description: 'Register modules and manage report definitions, filters, and output columns.',
    icon: BarChart3,
    path: '/administration-management/report-engine',
    available: true,
  },
  {
    key: 'system-settings',
    title: 'System Settings',
    description: 'Global application settings and operational parameters.',
    icon: SlidersHorizontal,
    path: null,
    available: false,
  },
  {
    key: 'reference-data',
    title: 'Reference Data',
    description: 'Shared lookup tables and master data used across modules.',
    icon: Settings,
    path: null,
    available: false,
  },
];

export default function ConfigurationPage() {
  const navigate = useNavigate();

  return (
    <div className="space-y-5">
      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex items-center gap-2" style={{ color: BRAND }}>
          <Settings size={18} />
          <h2 className="text-[11px] font-semibold uppercase tracking-[0.14em]">Configuration</h2>
        </div>
        <p className="mt-2 max-w-3xl text-sm text-slate-600">
          Central place for system-wide configuration. Report Engine is available now; additional
          configuration areas will be added here over time.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {CONFIG_ITEMS.map((item) => {
          const Icon = item.icon;
          const clickable = item.available && item.path;

          return (
            <button
              key={item.key}
              type="button"
              disabled={!clickable}
              onClick={() => clickable && navigate(item.path)}
              className={`flex h-full flex-col rounded-2xl border p-5 text-left shadow-sm transition-all duration-200 ${
                clickable
                  ? 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-[#ead6d7] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#962E32]/25'
                  : 'cursor-not-allowed border-dashed border-slate-200 bg-slate-50 opacity-80'
              }`}
            >
              <div className="flex items-start justify-between gap-3">
                <span
                  className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                  style={{ backgroundColor: '#fff5f5', color: BRAND }}
                >
                  <Icon size={20} />
                </span>
                {clickable ? (
                  <ChevronRight size={18} className="shrink-0 text-slate-300" />
                ) : (
                  <span className="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                    Coming soon
                  </span>
                )}
              </div>
              <h3 className="mt-4 text-base font-semibold text-slate-900">{item.title}</h3>
              <p className="mt-1.5 flex-1 text-sm text-slate-500">{item.description}</p>
            </button>
          );
        })}
      </div>
    </div>
  );
}
