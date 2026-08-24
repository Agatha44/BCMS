import { useCallback, useEffect, useState } from 'react';
import { apiService } from '../../../services/api.jsx';
import { REPORT_CATEGORIES } from '../../../pages/CollectionManagement/collection-reports/reportCatalog.js';
import { normalizeCatalogResponse } from '../utils/normalizeReportDefinition.js';

/**
 * Loads report categories for a module from the report engine API, with static fallback.
 */
export default function useReportEngineCatalog(moduleSlug, { fallback = REPORT_CATEGORIES } = {}) {
  const [categories, setCategories] = useState(fallback);
  const [registration, setRegistration] = useState(null);
  const [loading, setLoading] = useState(true);
  const [usingFallback, setUsingFallback] = useState(false);
  const [error, setError] = useState(null);

  const fetchCatalog = useCallback(async () => {
    if (!moduleSlug) {
      setCategories(fallback);
      setUsingFallback(true);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const response = await apiService.getReportEngineCatalog(moduleSlug);
      if (response?.success && response.data?.categories?.length) {
        setRegistration(response.data.registration ?? null);
        setCategories(normalizeCatalogResponse(response.data));
        setUsingFallback(false);
      } else {
        setCategories(fallback);
        setUsingFallback(true);
        setError(response?.message || 'Report catalog unavailable — using built-in definitions.');
      }
    } catch (err) {
      setCategories(fallback);
      setUsingFallback(true);
      setError(err?.message || 'Failed to load report catalog');
    } finally {
      setLoading(false);
    }
  }, [moduleSlug, fallback]);

  useEffect(() => {
    fetchCatalog();
  }, [fetchCatalog]);

  return {
    categories,
    registration,
    loading,
    usingFallback,
    error,
    refetch: fetchCatalog,
  };
}
