import {
  ArrowUpRight,
  ChevronRight,
  KeyRound,
  Layers,
  LayoutGrid,
  Shield,
  TrendingUp,
  UserCog,
  UserRound,
  Users,
} from 'lucide-react';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const GREEN = '#1B8A4F';
const TITLE_CLASS = 'text-[11px] font-semibold uppercase tracking-[0.14em]';

const fmt = (n) => Number(n ?? 0).toLocaleString();

const KpiCard = ({ icon: Icon, label, value, onClick, sub = 'Configured in system' }) => (
  <button
    type="button"
    onClick={onClick}
    disabled={!onClick}
    className={`flex h-full w-full flex-col rounded-xl border border-slate-200 bg-white p-4 text-left shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition-all duration-200 ${
      onClick ? 'hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#962E32]/25' : ''
    }`}
  >
    <div className="flex items-start justify-between">
      <div className="flex items-center gap-2" style={{ color: BRAND }}>
        <Icon size={16} />
        <span className={TITLE_CLASS}>{label}</span>
      </div>
      {onClick ? <ArrowUpRight size={16} className="text-slate-300" /> : null}
    </div>
    <div className="mt-3 text-[26px] font-bold tracking-tight text-slate-900">{fmt(value)}</div>
    <div className="mt-2 flex items-center gap-1.5 text-xs">
      <TrendingUp size={13} style={{ color: GREEN }} />
      <span className="text-slate-400">{sub}</span>
    </div>
  </button>
);

const OverviewBar = ({ label, value, max, description }) => {
  const pct = max > 0 ? Math.min((value / max) * 100, 100) : 0;
  return (
    <div className="flex items-center gap-3">
      <span className="w-28 shrink-0 text-sm text-slate-700">{label}</span>
      <span className="w-14 shrink-0 text-right text-sm font-semibold text-slate-900">{fmt(value)}</span>
      <div className="flex-1">
        <div
          className="h-2.5 rounded-full"
          style={{
            width: `${pct}%`,
            background: `linear-gradient(90deg, ${BRAND_DARK} 0%, #E07B3C 100%)`,
          }}
        />
      </div>
      <span className="hidden w-32 shrink-0 text-right text-[11px] text-slate-400 sm:inline">{description}</span>
    </div>
  );
};

const QuickActionButton = ({ icon: Icon, label, description, onClick }) => (
  <button
    type="button"
    onClick={onClick}
    className="flex h-full flex-col items-start rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#ead6d7] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#962E32]/25"
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

const ManagementMenuItem = ({ icon: Icon, label, description, onClick, active }) => (
  <button
    type="button"
    onClick={onClick}
    className={`flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition-all duration-200 ${
      active
        ? 'border-[#ead6d7] bg-[#fff5f5] shadow-sm'
        : 'border-slate-200 bg-white hover:border-[#ead6d7] hover:bg-slate-50'
    }`}
  >
    <span
      className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
      style={{ backgroundColor: '#fff5f5', color: BRAND }}
    >
      <Icon size={18} />
    </span>
    <span className="min-w-0 flex-1">
      <span className="block text-sm font-semibold text-slate-900">{label}</span>
      <span className="block text-xs text-slate-500">{description}</span>
    </span>
    <ChevronRight size={16} className="shrink-0 text-slate-400" />
  </button>
);

const AccountManagementDashboard = ({
  stats,
  onManageUsers,
  onManageRoles,
  onManagePermissions,
  onManageModules,
  onGrantRole,
  onRoleModuleMenu,
}) => {
  const roles = stats?.totalRoles ?? 0;
  const permissions = stats?.totalPermissions ?? 0;
  const modules = stats?.totalModules ?? 0;
  const menuAssignments = stats?.totalMenuAssignments ?? 0;
  const overviewMax = Math.max(roles, permissions, modules, menuAssignments, 1);

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard icon={Users} label="System Roles" value={roles} onClick={onManageRoles} sub="Active role definitions" />
        <KpiCard
          icon={KeyRound}
          label="Permissions"
          value={permissions}
          onClick={onManagePermissions}
          sub="Granular access controls"
        />
        <KpiCard
          icon={LayoutGrid}
          label="Modules"
          value={modules}
          onClick={onManageModules}
          sub="Registered application modules"
        />
        <KpiCard
          icon={Layers}
          label="Menu Assignments"
          value={menuAssignments}
          onClick={onRoleModuleMenu}
          sub="Role–module menu links"
        />
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <div className="xl:col-span-5">
          <div className="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2" style={{ color: BRAND }}>
                <Shield size={16} />
                <span className={TITLE_CLASS}>Access Overview</span>
              </div>
              <ArrowUpRight size={16} className="text-slate-300" />
            </div>
            <p className="mt-2 text-sm text-slate-500">
              Relative scale of roles, permissions, modules, and menu assignments in the access layer.
            </p>
            <div className="mt-5 space-y-3">
              <OverviewBar label="Roles" value={roles} max={overviewMax} description="Role catalog" />
              <OverviewBar label="Permissions" value={permissions} max={overviewMax} description="Permission catalog" />
              <OverviewBar label="Modules" value={modules} max={overviewMax} description="Bridge modules" />
              <OverviewBar label="Assignments" value={menuAssignments} max={overviewMax} description="Role menu links" />
            </div>
          </div>
        </div>

        <div className="xl:col-span-7">
          <div className="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2" style={{ color: BRAND }}>
                <UserCog size={16} />
                <span className={TITLE_CLASS}>Quick Actions</span>
              </div>
              <ArrowUpRight size={16} className="text-slate-300" />
            </div>
            <p className="mt-2 text-sm text-slate-500">
              Jump to common admin tasks for roles, permissions, and module access configuration.
            </p>
            <div className="mt-5 grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
              <QuickActionButton
                icon={Users}
                label="Manage Roles"
                description="Create, edit, and activate system roles"
                onClick={onManageRoles}
              />
              <QuickActionButton
                icon={KeyRound}
                label="Manage Permissions"
                description="Define and maintain permission entries"
                onClick={onManagePermissions}
              />
              <QuickActionButton
                icon={LayoutGrid}
                label="Register Module"
                description="Add or update bridge application modules"
                onClick={onManageModules}
              />
              <QuickActionButton
                icon={Shield}
                label="BMS Users"
                description="Manage bridge users and role assignments"
                onClick={onGrantRole}
              />
              <QuickActionButton
                icon={Layers}
                label="Role Module Menu"
                description="Map menus to roles per module"
                onClick={onRoleModuleMenu}
              />
            </div>
          </div>
        </div>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2" style={{ color: BRAND }}>
            <UserRound size={16} />
            <span className={TITLE_CLASS}>Management</span>
          </div>
        </div>
        <p className="mt-2 text-sm text-slate-500">
          Open user and access administration pages from the dashboard.
        </p>
        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <ManagementMenuItem
            icon={UserRound}
            label="Manage Users"
            description="Collection and BMS users, roles, and account status"
            onClick={onManageUsers}
            active
          />
          <ManagementMenuItem
            icon={Users}
            label="Manage Roles"
            description="Auth roles and bridge module access"
            onClick={onManageRoles}
          />
          <ManagementMenuItem
            icon={KeyRound}
            label="Manage Permissions"
            description="Define permission entries"
            onClick={onManagePermissions}
          />
        </div>
      </div>
    </div>
  );
};

export default AccountManagementDashboard;
