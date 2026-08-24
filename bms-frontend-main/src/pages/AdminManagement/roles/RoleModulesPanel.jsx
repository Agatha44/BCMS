import { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert, App, Button, Checkbox } from 'antd';
import { Layers, Save } from 'lucide-react';

import { apiService } from '../../../services/api.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import { apiMessage, BRAND, BRAND_DARK, normalizeModuleList } from './roleUtils.js';

const ModuleRow = ({ module, checked, onToggle, disabled }) => (
  <label
    className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition ${
      checked ? 'border-[#ead6d7] bg-[#fff5f5]' : 'border-slate-200 bg-white hover:border-slate-300'
    } ${disabled ? 'cursor-not-allowed opacity-60' : ''}`}
  >
    <Checkbox checked={checked} onChange={() => onToggle(module.id)} disabled={disabled} className="mt-0.5" />
    <div className="flex min-w-0 flex-1 gap-3">
      <div
        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-sm font-semibold"
        style={{ backgroundColor: '#fff5f5', color: BRAND }}
      >
        {module.module_icon ? (
          <span className="truncate px-1 text-[10px]" title={module.module_icon}>
            {String(module.module_icon).slice(0, 3)}
          </span>
        ) : (
          <Layers size={18} />
        )}
      </div>
      <div className="min-w-0">
        <p className="m-0 text-sm font-semibold text-slate-900">{module.module_name || 'Unnamed module'}</p>
        <p className="m-0 font-mono text-xs text-slate-500">{module.module_identifier || '—'}</p>
        {module.module_description ? (
          <p className="m-0 mt-1 line-clamp-2 text-xs text-slate-500">{module.module_description}</p>
        ) : null}
      </div>
    </div>
  </label>
);

const RoleModulesPanel = ({ roleId, roleName, embedded = false }) => {
  const { message } = App.useApp();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [allModules, setAllModules] = useState([]);
  const [selectedIds, setSelectedIds] = useState(() => new Set());

  const load = useCallback(async () => {
    if (!roleId) return;
    setLoading(true);
    setError(null);
    try {
      const [activeRes, roleModRes] = await Promise.all([
        apiService.getActiveBmsModules(),
        apiService.getAuthRoleModules(roleId),
      ]);

      const errors = [];
      if (!activeRes?.success) {
        errors.push(apiMessage(activeRes, 'Failed to load active modules'));
        setAllModules([]);
      } else {
        setAllModules(normalizeModuleList(activeRes.data));
      }

      if (roleModRes?.success) {
        const assigned = normalizeModuleList(roleModRes.data);
        setSelectedIds(new Set(assigned.map((m) => Number(m.id)).filter(Number.isFinite)));
      } else {
        errors.push(apiMessage(roleModRes, 'Failed to load role modules'));
      }
      if (errors.length) setError(errors.join(' · '));
    } catch (err) {
      console.error('Error loading role modules:', err);
      setError('An error occurred while loading modules');
    } finally {
      setLoading(false);
    }
  }, [roleId]);

  useEffect(() => {
    load();
  }, [load]);

  const assignedModules = useMemo(
    () => allModules.filter((m) => selectedIds.has(Number(m.id))),
    [allModules, selectedIds]
  );

  const availableModules = useMemo(
    () => allModules.filter((m) => !selectedIds.has(Number(m.id))),
    [allModules, selectedIds]
  );

  const toggleModule = (moduleId) => {
    const id = Number(moduleId);
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const response = await apiService.saveAuthRoleModules(roleId, Array.from(selectedIds));
      if (response.success) {
        const assigned = normalizeModuleList(response.data);
        if (assigned.length) {
          setSelectedIds(new Set(assigned.map((m) => Number(m.id)).filter(Number.isFinite)));
        }
        message.success(
          apiMessage(response, `Saved ${response.data?.assigned_count ?? selectedIds.size} module assignment(s)`)
        );
      } else {
        message.error(apiMessage(response, 'Failed to save module assignments'));
      }
    } catch (err) {
      console.error('Error saving role modules:', err);
      message.error('An error occurred while saving module assignments');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-10">
        <CollectionLoader size={64} compact />
      </div>
    );
  }

  const wrapperClass = embedded
    ? 'mt-4 border-t border-slate-200 pt-4'
    : 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm';

  return (
    <div className={wrapperClass}>
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="m-0 flex items-center gap-2 text-sm font-semibold text-slate-900">
            <Layers size={16} style={{ color: BRAND }} />
            Bridge Modules
          </h3>
          <p className="m-0 mt-1 text-sm text-slate-500">
            Users with role <span className="font-semibold text-slate-800">{roleName}</span> see these modules after
            login.
          </p>
        </div>
        <Button
          type="primary"
          icon={<Save size={14} />}
          loading={saving}
          onClick={handleSave}
          style={{ backgroundColor: BRAND, borderColor: BRAND }}
          onMouseEnter={(e) => {
            e.currentTarget.style.backgroundColor = BRAND_DARK;
            e.currentTarget.style.borderColor = BRAND_DARK;
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.backgroundColor = BRAND;
            e.currentTarget.style.borderColor = BRAND;
          }}
        >
          Save Modules
        </Button>
      </div>

      {error ? (
        <Alert className="mt-4" type="error" showIcon message={error} closable onClose={() => setError(null)} />
      ) : null}

      <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
          <p className="mb-2 text-xs font-semibold uppercase tracking-[0.1em]" style={{ color: BRAND }}>
            Available ({availableModules.length})
          </p>
          <div className="max-h-[360px] space-y-2 overflow-y-auto pr-1">
            {availableModules.length ? (
              availableModules.map((module) => (
                <ModuleRow
                  key={module.id}
                  module={module}
                  checked={false}
                  onToggle={toggleModule}
                  disabled={saving}
                />
              ))
            ) : (
              <p className="text-sm text-slate-400">All active modules are assigned.</p>
            )}
          </div>
        </div>
        <div>
          <p className="mb-2 text-xs font-semibold uppercase tracking-[0.1em]" style={{ color: BRAND }}>
            Assigned ({assignedModules.length})
          </p>
          <div className="max-h-[360px] space-y-2 overflow-y-auto pr-1">
            {assignedModules.length ? (
              assignedModules.map((module) => (
                <ModuleRow
                  key={module.id}
                  module={module}
                  checked
                  onToggle={toggleModule}
                  disabled={saving}
                />
              ))
            ) : (
              <p className="text-sm text-slate-400">No modules assigned yet.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default RoleModulesPanel;
