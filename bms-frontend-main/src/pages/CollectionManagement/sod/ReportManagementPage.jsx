import { BarChart3, Clock, CreditCard, ShieldCheck } from 'lucide-react';
import SoDTabbedPage from './components/SoDTabbedPage.jsx';
import CategoryReportTabContent from '../collection-reports/CategoryReportTabContent.jsx';
import CollectionLoader from '../components/CollectionLoader.jsx';
import useReportEngineCatalog from '../../../modules/report-engine/hooks/useReportEngineCatalog.js';

const CATEGORY_ICONS = {
  collection: <BarChart3 size={16} />,
  shift: <Clock size={16} />,
  payment: <CreditCard size={16} />,
  audit: <ShieldCheck size={16} />,
};

const MODULE_SLUG = 'collection-management';

const ReportManagementPage = () => {
  const { categories, loading, usingFallback, error } = useReportEngineCatalog(MODULE_SLUG);

  if (loading) {
    return <CollectionLoader tall />;
  }

  const tabs = categories.map((category) => ({
    key: category.key,
    label: `${category.label} Reports`,
    icon: CATEGORY_ICONS[category.key] ?? <BarChart3 size={16} />,
    description: category.description,
    content: (
      <CategoryReportTabContent
        categoryKey={category.key}
        category={category}
        useReportEngine={!usingFallback}
      />
    ),
  }));

  return (
    <>
      {usingFallback && error ? (
        <div className="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
          {error}
        </div>
      ) : null}
      <SoDTabbedPage tabs={tabs} />
    </>
  );
};

export default ReportManagementPage;
