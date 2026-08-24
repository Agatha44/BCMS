import { useEffect, useMemo } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import CollectionReportRunner from './CollectionReportRunner.jsx';
import { REPORT_CATEGORIES } from './reportCatalog.js';

/**
 * Report workspace for one category tab. Syncs selected report with `?tab=` and `?report=`.
 */
export default function CategoryReportTabContent({
  categoryKey,
  category: categoryProp,
  useReportEngine = true,
}) {
  const location = useLocation();
  const navigate = useNavigate();

  const category = useMemo(() => {
    if (categoryProp) return categoryProp;
    return REPORT_CATEGORIES.find((c) => c.key === categoryKey) ?? REPORT_CATEGORIES[0];
  }, [categoryKey, categoryProp]);

  const params = useMemo(() => new URLSearchParams(location.search), [location.search]);

  const activeTab = params.get('tab');

  const selectedReportId = useMemo(() => {
    if (activeTab !== categoryKey) {
      return category.reports[0]?.id ?? '';
    }
    let id = params.get('report');
    if (id === 'shift-summary-audit') {
      id = 'shift-summary';
    }
    if (id && category.reports.some((r) => r.id === id)) return id;
    return category.reports[0]?.id ?? '';
  }, [params, category, categoryKey, activeTab]);

  const setReport = (reportId) => {
    const next = new URLSearchParams(location.search);
    next.set('tab', categoryKey);
    next.set('report', reportId);
    navigate({ pathname: location.pathname, search: `?${next.toString()}` }, { replace: true });
  };

  useEffect(() => {
    if (activeTab !== categoryKey) return;
    if (!params.get('report') && category.reports[0]) {
      const next = new URLSearchParams(location.search);
      next.set('tab', categoryKey);
      next.set('report', category.reports[0].id);
      navigate({ pathname: location.pathname, search: `?${next.toString()}` }, { replace: true });
    }
  }, [activeTab, categoryKey, category.reports, location.pathname, location.search, navigate, params]);

  if (!category.reports?.length) {
    return (
      <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
        No reports configured for this category.
      </div>
    );
  }

  return (
    <CollectionReportRunner
      key={`${categoryKey}-${selectedReportId}`}
      reports={category.reports}
      selectedReportId={selectedReportId}
      onSelectReport={setReport}
      useReportEngine={useReportEngine}
    />
  );
}
