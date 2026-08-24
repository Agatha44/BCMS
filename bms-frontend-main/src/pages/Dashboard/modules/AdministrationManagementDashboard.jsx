import { BarChart3, ChevronRight, Settings, SlidersHorizontal } from 'lucide-react';

const BRAND = '#962E32';
const TITLE_CLASS = 'text-[11px] font-semibold uppercase tracking-[0.14em]';

const QuickActionButton = ({ icon: Icon, label, description, onClick, disabled }) => (
  <button
    type="button"
    onClick={onClick}
    disabled={disabled}
    className={`flex h-full flex-col items-start rounded-xl border p-4 text-left shadow-sm transition-all duration-200 ${
      disabled
        ? 'cursor-not-allowed border-dashed border-slate-200 bg-slate-50 opacity-75'
        : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-[#ead6d7] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#962E32]/25'
    }`}
  >
    <span
      className="flex h-10 w-10 items-center justify-center rounded-xl"
      style={{ backgroundColor: '#fff5f5', color: BRAND }}
    >
      <Icon size={18} />
    </span>
    <span className="mt-3 text-sm font-semibold text-slate-900">{label}</span>
    <span className="mt-1 text-xs text-slate-500">{description}</span>
  </button>
);

const AdministrationManagementDashboard = ({
  onOpenConfiguration,
  onOpenReportEngine,
}) => (
  <div className="space-y-4">
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-center gap-2" style={{ color: BRAND }}>
        <Settings size={16} />
        <span className={TITLE_CLASS}>Administration Management</span>
      </div>
      <p className="mt-2 text-sm text-slate-600">
        Configure system-wide settings, report definitions, and other administration tools from this
        module.
      </p>
    </div>

    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2" style={{ color: BRAND }}>
          <SlidersHorizontal size={16} />
          <span className={TITLE_CLASS}>Configuration Areas</span>
        </div>
        <button
          type="button"
          onClick={onOpenConfiguration}
          className="inline-flex items-center gap-1 text-xs font-semibold text-[#962E32] hover:underline"
        >
          View all
          <ChevronRight size={14} />
        </button>
      </div>
      <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <QuickActionButton
          icon={BarChart3}
          label="Report Engine"
          description="Manage report modules and definitions"
          onClick={onOpenReportEngine}
        />
        <QuickActionButton
          icon={Settings}
          label="Configuration Hub"
          description="Browse all configuration sections"
          onClick={onOpenConfiguration}
        />
        <QuickActionButton
          icon={SlidersHorizontal}
          label="More Settings"
          description="Additional configuration will be added here"
          disabled
        />
      </div>
    </div>
  </div>
);

export default AdministrationManagementDashboard;
