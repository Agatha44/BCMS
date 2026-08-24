/**
 * Maps a report-engine API definition to the CollectionReportRunner shape.
 */
import { sortReportFilters } from '../../../pages/CollectionManagement/collection-reports/reportFilterUtils.js';

export function normalizeReportDefinition(def = {}) {
  const columns = (def.columns ?? def.output_columns ?? []).map((col) => {
    if (typeof col === 'string') {
      return { title: col, key: col };
    }
    return {
      title: col.title ?? col.name ?? col.key,
      key: col.key ?? col.name,
    };
  });

  return {
    id: def.id ?? def.key,
    label: def.label ?? def.name,
    description: def.description ?? '',
    apiSlug: def.apiSlug ?? def.handler ?? def.script,
    filters: sortReportFilters(def.filters ?? (def.params ? Object.keys(def.params) : [])),
    params: def.params ?? {},
    columns,
    paginated: Boolean(def.paginated ?? def.options?.paginated),
    nested: Boolean(def.nested ?? def.options?.nested),
  };
}

export function normalizeCatalogCategory(category = {}) {
  return {
    key: category.key,
    label: category.label,
    shortLabel: category.shortLabel ?? category.label,
    description: category.description ?? '',
    reportCount: category.reportCount ?? category.reports?.length ?? 0,
    reports: (category.reports ?? []).map(normalizeReportDefinition),
  };
}

export function normalizeCatalogResponse(data) {
  if (!data?.categories) return [];
  return data.categories.map(normalizeCatalogCategory);
}
