const STORAGE_KEY = 'bms.supportRequests';

export const listSupportRequests = () => {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
};

const persistSupportRequests = (requests) => {
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(requests));
};

export const nextSupportRequestId = () => {
  const ids = listSupportRequests().map((request) => Number(request.id) || 0);
  return (ids.length ? Math.max(...ids) : 0) + 1;
};

export const addSupportRequest = (request) => {
  const next = [request, ...listSupportRequests()];
  try {
    persistSupportRequests(next);
    return next;
  } catch {
    const withoutDocument = next.map((item) => ({
      ...item,
      attachmentUrl: undefined,
    }));
    persistSupportRequests(withoutDocument);
    return withoutDocument;
  }
};

export const updateSupportRequest = (id, patch) => {
  const next = listSupportRequests().map((request) =>
    String(request.id) === String(id) ? { ...request, ...patch } : request
  );
  persistSupportRequests(next);
  return next;
};
