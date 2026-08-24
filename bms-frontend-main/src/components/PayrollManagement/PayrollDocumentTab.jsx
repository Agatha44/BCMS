import { Button, Empty, Modal, Typography } from 'antd';
import PropTypes from 'prop-types';
import { useEffect, useMemo, useState } from 'react';
import { ExpandOutlined, FilePdfOutlined } from '@ant-design/icons';
import CollectionLoader from '../../pages/CollectionManagement/components/CollectionLoader.jsx';

const { Text } = Typography;

const isSafeHttpUrl = (url) => {
  if (!url || typeof url !== 'string') return false;
  try {
    const u = new URL(url, typeof window !== 'undefined' ? window.location.origin : undefined);
    return u.protocol === 'http:' || u.protocol === 'https:' || u.protocol === 'blob:' || u.protocol === 'data:';
  } catch {
    return false;
  }
};

/**
 * PayrollDocumentTab
 * - Left panel: Available + Missing document slots
 * - Right panel: Preview (iframe) + Large view modal
 *
 * Provide `slots` as the canonical list of required documents, each optionally having `url`.
 */
const PayrollDocumentTab = ({ slots = [] }) => {
  const [selectedId, setSelectedId] = useState(null);
  const [largeViewOpen, setLargeViewOpen] = useState(false);

  const normalizedSlots = useMemo(() => {
    const list = Array.isArray(slots) ? slots : [];
    return list.map((s, idx) => {
      const id = s?.id ?? `${idx}`;
      const label = s?.label ?? s?.name ?? 'Document';
      const url = s?.url ?? s?.file_url ?? s?.download_url ?? null;
      return {
        ...s,
        id,
        label,
        loading: Boolean(s?.loading),
        url: url && isSafeHttpUrl(String(url)) ? String(url) : null,
      };
    });
  }, [slots]);

  const resolvedSelectedId =
    selectedId != null && normalizedSlots.some((s) => s.id === selectedId)
      ? selectedId
      : normalizedSlots[0]?.id ?? null;

  const selectedSlot = normalizedSlots.find((s) => s.id === resolvedSelectedId) || null;
  const selectedUrl = selectedSlot?.url || null;

  useEffect(() => {
    setLargeViewOpen(false);
  }, [resolvedSelectedId]);

  const pillClass = (active, missing, loading) => {
    const base =
      'w-full flex items-center gap-2 px-3 py-2 rounded-md border text-left transition-colors ' +
      'focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-[#962E32]/30';
    if (loading) {
      return (
        base +
        (active
          ? ' border-[#962E32] bg-[#fff5f5] text-[#962E32]'
          : ' border-gray-200 bg-white text-gray-700 hover:bg-gray-50')
      );
    }
    if (missing) {
      return (
        base +
        (active
          ? ' border-[#962E32] bg-[#fff5f5] text-[#962E32]'
          : ' border-dashed border-gray-300 bg-white text-gray-700 hover:bg-gray-50')
      );
    }
    return (
      base +
      (active
        ? ' border-[#962E32] bg-[#fff5f5] text-[#962E32]'
        : ' border-gray-200 bg-white text-gray-800 hover:bg-gray-50')
    );
  };

  return (
    <>
      <div className="p-3">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 min-h-[520px]">
          <aside className="lg:col-span-3 xl:col-span-3 border-r border-gray-200 pr-0 lg:pr-3">
            <div className="space-y-5">
              <div>
                <div className="text-xs font-bold tracking-wider text-[#8b2323] uppercase mb-2">Documents</div>
                <div className="space-y-2">
                  {normalizedSlots.length === 0 ? (
                    <Text type="secondary">No documents available.</Text>
                  ) : (
                    normalizedSlots.map((s) => (
                      <button
                        key={s.id}
                        type="button"
                        className={pillClass(resolvedSelectedId === s.id, !s.url && !s.loading, s.loading)}
                        onClick={() => setSelectedId(s.id)}
                      >
                        <FilePdfOutlined />
                        <span className="truncate">{s.label}</span>
                      </button>
                    ))
                  )}
                </div>
              </div>
            </div>
          </aside>

          <section className="lg:col-span-9 xl:col-span-9 min-w-0">
            {selectedSlot ? (
              <div className="h-full flex flex-col min-h-[520px]">
                <div className="flex items-center justify-between gap-3 mb-2">
                  <div className="font-medium text-gray-900 truncate">{selectedSlot.label}</div>
                  {selectedUrl ? (
                    <Button icon={<ExpandOutlined />} onClick={() => setLargeViewOpen(true)}>
                      Large view
                    </Button>
                  ) : null}
                </div>

                {selectedSlot.loading ? (
                  <div className="flex-1 border rounded-md bg-white flex items-center justify-center min-h-[480px]">
                    <CollectionLoader size={64} compact />
                  </div>
                ) : selectedUrl ? (
                  <div className="flex-1 border rounded-md overflow-hidden bg-white">
                    <iframe title={selectedSlot.label} src={selectedUrl} className="w-full h-full" />
                  </div>
                ) : (
                  <div className="flex-1 border rounded-md bg-white flex items-center justify-center min-h-[480px]">
                    <Empty description="This document is missing." />
                  </div>
                )}
              </div>
            ) : (
              <div className="h-full border rounded-md bg-white flex items-center justify-center min-h-[520px]">
                <Empty description="Select a document from the list." />
              </div>
            )}
          </section>
        </div>
      </div>

      <Modal
        open={largeViewOpen}
        title={selectedSlot?.label || 'Document'}
        footer={null}
        onCancel={() => setLargeViewOpen(false)}
        width="min(1100px, 96vw)"
        styles={{ body: { padding: 0, height: 'min(82vh, 820px)' } }}
        destroyOnHidden
      >
        {selectedUrl ? <iframe title={selectedSlot?.label || 'Document preview'} src={selectedUrl} className="w-full h-full" /> : null}
      </Modal>
    </>
  );
};

PayrollDocumentTab.propTypes = {
  slots: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
      label: PropTypes.string,
      url: PropTypes.string,
      file_url: PropTypes.string,
      download_url: PropTypes.string,
      loading: PropTypes.bool,
    })
  ),
};

export default PayrollDocumentTab;

