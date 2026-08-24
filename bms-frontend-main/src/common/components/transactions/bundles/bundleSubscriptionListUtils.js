/** DataTables / Laravel paginator shapes for toll bundle subscriptions. */
export const defaultBundleSubscriptionPagination = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0,
};

const extractRows = (payload) => {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  if (Array.isArray(payload.data?.data)) return payload.data.data;

  const legacy =
    payload.subscriptions ||
    payload.passages ||
    payload.records ||
    payload.items ||
    payload.rows;

  return Array.isArray(legacy) ? legacy : [];
};

export const extractServerPagination = (payload, requestedPage, requestedPerPage) => {
  const apiPagination = payload?.pagination;
  const perPage =
    Number(apiPagination?.per_page) ||
    Number(payload?.length) ||
    Number(payload?.per_page) ||
    Number(requestedPerPage) ||
    15;

  const total =
    Number(apiPagination?.total) ||
    Number(payload?.recordsFiltered) ||
    Number(payload?.recordsTotal) ||
    Number(payload?.total) ||
    0;

  const start = Number(payload?.start) || 0;
  const currentPage =
    Number(apiPagination?.current_page) ||
    Number(payload?.page) ||
    Number(payload?.current_page) ||
    (start >= 0 ? Math.floor(start / Math.max(perPage, 1)) + 1 : 0) ||
    Number(requestedPage) ||
    1;

  const lastPage =
    Number(apiPagination?.last_page) ||
    Math.max(1, Math.ceil(total / Math.max(perPage, 1)));

  const from =
    Number(apiPagination?.from) ||
    (total === 0 ? 0 : (currentPage - 1) * perPage + 1);

  const to =
    Number(apiPagination?.to) ||
    (total === 0 ? 0 : Math.min(currentPage * perPage, total));

  return {
    current_page: currentPage,
    last_page: lastPage,
    per_page: perPage,
    total,
    from,
    to,
  };
};

/**
 * Normalize list API responses such as:
 * - { draw, recordsTotal, recordsFiltered, data: [], pagination: {} }
 * - { success: true, data: { data: [], pagination: {}, recordsFiltered } }
 */
export const resolveSubscriptionListPayload = (response) => {
  if (!response || typeof response !== 'object') {
    return { rows: [], meta: {}, failed: true, message: 'Invalid response from server' };
  }

  if (response.success === false) {
    return {
      rows: [],
      meta: {},
      failed: true,
      message: response.message || 'Failed to load bundle subscriptions',
    };
  }

  // Top-level DataTables payload: data is the row array.
  if (Array.isArray(response.data) && (response.pagination || response.recordsFiltered != null)) {
    return { rows: response.data, meta: response, failed: false };
  }

  // Wrapped object: { success, data: { data: [], pagination, ... } }
  if (response.data != null && typeof response.data === 'object' && !Array.isArray(response.data)) {
    const inner = response.data;
    const rows = Array.isArray(inner.data) ? inner.data : extractRows(inner);
    return {
      rows: Array.isArray(rows) ? rows : [],
      meta: {
        ...response,
        ...inner,
        recordsFiltered: inner.recordsFiltered ?? response.recordsFiltered,
        recordsTotal: inner.recordsTotal ?? response.recordsTotal,
        pagination: inner.pagination ?? response.pagination,
      },
      failed: false,
    };
  }

  // Wrapped array: { success, data: [...], pagination }
  if (Array.isArray(response.data)) {
    return {
      rows: response.data,
      meta: response,
      failed: false,
    };
  }

  const rows = extractRows(response);
  if (!rows.length && response.success === false) {
    return {
      rows: [],
      meta: response,
      failed: true,
      message: response.message || 'Failed to load bundle subscriptions',
    };
  }

  return {
    rows: Array.isArray(rows) ? rows : [],
    meta: response,
    failed: false,
  };
};
