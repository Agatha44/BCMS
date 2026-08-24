/**
 * Normalize list payloads from BMS API responses into a plain array.
 */
export function extractApiList(payload) {
  if (payload == null) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  if (Array.isArray(payload.lanes)) return payload.lanes;
  if (Array.isArray(payload.items)) return payload.items;
  if (Array.isArray(payload.records)) return payload.records;
  if (Array.isArray(payload.shifts)) return payload.shifts;
  if (payload.data && typeof payload.data === 'object') {
    return extractApiList(payload.data);
  }
  return [];
}

/**
 * Normalize shift lookup rows from API responses (handles array or keyed object payloads).
 */
export function extractShiftList(payload) {
  const list = extractApiList(payload);
  if (list.length > 0) {
    return list.filter((row) => row && row.id != null);
  }

  const source = payload?.data ?? payload;
  if (source && typeof source === 'object' && !Array.isArray(source)) {
    return Object.values(source).filter((row) => row && typeof row === 'object' && row.id != null);
  }

  return [];
}
