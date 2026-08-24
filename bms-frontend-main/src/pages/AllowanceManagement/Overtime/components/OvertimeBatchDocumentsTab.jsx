import { ExpandOutlined, FilePdfOutlined, UploadOutlined } from '@ant-design/icons';
import { App, Button, Empty, Modal, Spin, Typography } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { overtimeService } from '../../../../services/overtimeService.js';
import SubmissionApprovalDocumentsUploadModal from './SubmissionApprovalDocumentsUploadModal.jsx';
import {
  extractSubmissionDocumentTypes,
  formatReadinessText,
  getSubmissionReadinessRequiredDocuments,
  getSubmissionDocumentTypeLabel,
  isRequiredDocumentAvailable,
  isRequiredDocumentMissing,
  resolveSubmissionDocumentId,
  resolveSubmissionDocumentTypeValue,
} from '../submissionApprovalDocumentsUtils.js';

const { Text } = Typography;
const OVERTIME_MEMO_SLOT_ID = 'overtime-memo';

const isSafeHttpUrl = (url) => {
  if (!url || typeof url !== 'string') return false;
  try {
    const u = new URL(url, typeof window !== 'undefined' ? window.location.origin : undefined);
    return u.protocol === 'http:' || u.protocol === 'https:' || u.protocol === 'blob:' || u.protocol === 'data:';
  } catch {
    return false;
  }
};

const getPillClass = (active, missing, loading) => {
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

const createBlobUrlFromBase64Pdf = (pdfBase64) => {
  if (!pdfBase64) return null;
  const byteCharacters = atob(pdfBase64);
  const byteArray = new Uint8Array(byteCharacters.length);
  for (let i = 0; i < byteCharacters.length; i += 1) {
    byteArray[i] = byteCharacters.charCodeAt(i);
  }
  const blob = new Blob([byteArray], { type: 'application/pdf' });
  return URL.createObjectURL(blob);
};

const OvertimeBatchDocumentsTab = ({
  batchNumber,
  submissionReadiness,
  active,
  onDocumentsUpdated,
}) => {
  const { message } = App.useApp();
  const [invoiceState, setInvoiceState] = useState({ loading: false, url: null });
  const [submissionDocStates, setSubmissionDocStates] = useState({});
  const [documentTypes, setDocumentTypes] = useState([]);
  const [uploadModal, setUploadModal] = useState({ open: false, requiredDocument: null });
  const [selectedId, setSelectedId] = useState(null);
  const [largeViewOpen, setLargeViewOpen] = useState(false);

  const requiredDocuments = useMemo(
    () => getSubmissionReadinessRequiredDocuments(submissionReadiness),
    [submissionReadiness]
  );

  const previewableDocuments = useMemo(
    () => requiredDocuments.filter(isRequiredDocumentAvailable),
    [requiredDocuments]
  );

  useEffect(() => {
    if (!active) return undefined;

    let cancelled = false;
    (async () => {
      try {
        const response = await overtimeService.getSubmissionDocumentTypes();
        if (cancelled || !response?.success) return;
        setDocumentTypes(extractSubmissionDocumentTypes(response.data));
      } catch {
        if (!cancelled) setDocumentTypes([]);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [active]);

  useEffect(() => {
    if (!active || !batchNumber) {
      setInvoiceState({ loading: false, url: null });
      return undefined;
    }

    let cancelled = false;
    let createdUrl = null;

    (async () => {
      setInvoiceState({ loading: true, url: null });
      try {
        const response = await overtimeService.getOvertimeInvoiceDocument(batchNumber);
        if (cancelled) return;

        if (!response?.success || !response.data?.pdf_base64) {
          setInvoiceState({ loading: false, url: null });
          if (response?.message) {
            message.error(formatReadinessText(response.message) || 'Failed to fetch overtime memo.');
          }
          return;
        }

        createdUrl = createBlobUrlFromBase64Pdf(response.data.pdf_base64);
        setInvoiceState({ loading: false, url: createdUrl });
      } catch (error) {
        if (cancelled) return;
        setInvoiceState({ loading: false, url: null });
        message.error(error?.message || 'Failed to fetch overtime memo.');
      }
    })();

    return () => {
      cancelled = true;
      if (createdUrl) URL.revokeObjectURL(createdUrl);
    };
  }, [active, batchNumber, message]);

  useEffect(() => {
    if (!active || previewableDocuments.length === 0) {
      setSubmissionDocStates({});
      return undefined;
    }

    let cancelled = false;
    const createdUrls = [];

    (async () => {
      const nextStates = {};
      previewableDocuments.forEach((doc) => {
        const docId = resolveSubmissionDocumentId(doc);
        nextStates[docId] = { loading: true, url: null };
      });
      setSubmissionDocStates(nextStates);

      await Promise.all(
        previewableDocuments.map(async (doc) => {
          const docId = resolveSubmissionDocumentId(doc);
          if (!docId) return;

          try {
            const response = await overtimeService.downloadSubmissionDocument(docId);
            if (cancelled) return;

            if (!response?.success || !response.blob) {
              setSubmissionDocStates((prev) => ({
                ...prev,
                [docId]: { loading: false, url: null },
              }));
              return;
            }

            const url = URL.createObjectURL(response.blob);
            createdUrls.push(url);
            setSubmissionDocStates((prev) => ({
              ...prev,
              [docId]: { loading: false, url },
            }));
          } catch {
            if (cancelled) return;
            setSubmissionDocStates((prev) => ({
              ...prev,
              [docId]: { loading: false, url: null },
            }));
          }
        })
      );
    })();

    return () => {
      cancelled = true;
      createdUrls.forEach((url) => URL.revokeObjectURL(url));
    };
  }, [active, previewableDocuments]);

  const openUploadModal = useCallback((requiredDocument) => {
    setUploadModal({ open: true, requiredDocument });
  }, []);

  const closeUploadModal = useCallback(() => {
    setUploadModal({ open: false, requiredDocument: null });
  }, []);

  const handleUploadSuccess = useCallback(
    async (response) => {
      const successMessage = formatReadinessText(response?.message);
      if (successMessage) message.success(successMessage);
      closeUploadModal();
      await onDocumentsUpdated?.();
    },
    [closeUploadModal, message, onDocumentsUpdated]
  );

  const uploadSeedRecord = useMemo(() => {
    const doc = uploadModal.requiredDocument;
    if (!doc) return null;

    return {
      document_type: resolveSubmissionDocumentTypeValue(doc.document_type ?? doc),
      document_name: doc.document_name || undefined,
      period_start: submissionReadiness?.batch_reference_date || undefined,
    };
  }, [submissionReadiness?.batch_reference_date, uploadModal.requiredDocument]);

  const slots = useMemo(() => {
    const list = [
      {
        id: OVERTIME_MEMO_SLOT_ID,
        label: 'Overtime Memo',
        loading: invoiceState.loading,
        url: invoiceState.url,
        missing: false,
        onUpload: null,
      },
    ];

    requiredDocuments.forEach((doc, index) => {
      const docId = resolveSubmissionDocumentId(doc);
      const slotId = docId ? `submission-${docId}` : `required-${doc.document_type || index}`;
      const docState = docId
        ? submissionDocStates[docId] || { loading: isRequiredDocumentAvailable(doc), url: null }
        : { loading: false, url: null };
      const missing = isRequiredDocumentMissing(doc);

      list.push({
        id: slotId,
        label:
          doc.document_type_name ||
          getSubmissionDocumentTypeLabel(doc.document_type, doc) ||
          doc.document_name ||
          'Document',
        loading: docState.loading,
        url: docState.url,
        missing,
        onUpload: missing ? () => openUploadModal(doc) : null,
      });
    });

    return list;
  }, [
    requiredDocuments,
    invoiceState.loading,
    invoiceState.url,
    submissionDocStates,
    openUploadModal,
  ]);

  const normalizedSlots = useMemo(
    () =>
      slots.map((slot, idx) => {
        const url = slot.url && isSafeHttpUrl(String(slot.url)) ? String(slot.url) : null;
        return {
          ...slot,
          id: slot.id ?? `${idx}`,
          loading: Boolean(slot.loading),
          url,
        };
      }),
    [slots]
  );

  const resolvedSelectedId =
    selectedId != null && normalizedSlots.some((slot) => slot.id === selectedId)
      ? selectedId
      : normalizedSlots[0]?.id ?? null;

  const selectedSlot = normalizedSlots.find((slot) => slot.id === resolvedSelectedId) || null;
  const selectedUrl = selectedSlot?.url || null;

  useEffect(() => {
    setLargeViewOpen(false);
  }, [resolvedSelectedId]);

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
                    normalizedSlots.map((slot) => (
                      <button
                        key={slot.id}
                        type="button"
                        className={getPillClass(
                          resolvedSelectedId === slot.id,
                          slot.missing && !slot.url && !slot.loading,
                          slot.loading
                        )}
                        onClick={() => setSelectedId(slot.id)}
                      >
                        <FilePdfOutlined />
                        <span className="truncate">{slot.label}</span>
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
                    <Spin size="large" />
                  </div>
                ) : selectedUrl ? (
                  <div className="flex-1 border rounded-md overflow-hidden bg-white min-h-[480px]">
                    <iframe title={selectedSlot.label} src={selectedUrl} className="w-full h-full min-h-[480px]" />
                  </div>
                ) : (
                  <div className="flex-1 border rounded-md bg-white flex flex-col items-center justify-center gap-3 min-h-[480px] px-4">
                    <Empty description="This document is missing." />
                    {selectedSlot.onUpload ? (
                      <Button
                        type="primary"
                        icon={<UploadOutlined />}
                        onClick={selectedSlot.onUpload}
                        className="btn-standard-primary"
                      >
                        Upload Document
                      </Button>
                    ) : null}
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
        {selectedUrl ? (
          <iframe
            title={selectedSlot?.label || 'Document preview'}
            src={selectedUrl}
            className="w-full h-full"
          />
        ) : null}
      </Modal>

      <SubmissionApprovalDocumentsUploadModal
        open={uploadModal.open}
        mode="create"
        documentRecord={uploadSeedRecord}
        documentTypes={documentTypes}
        lockDocumentType
        onClose={closeUploadModal}
        onSuccess={handleUploadSuccess}
      />
    </>
  );
};

OvertimeBatchDocumentsTab.propTypes = {
  batchNumber: PropTypes.string,
  submissionReadiness: PropTypes.object,
  active: PropTypes.bool,
  onDocumentsUpdated: PropTypes.func,
};

export default OvertimeBatchDocumentsTab;
