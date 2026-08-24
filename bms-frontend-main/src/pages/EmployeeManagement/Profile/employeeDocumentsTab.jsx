import { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { Row, Col, Button, Upload, Modal, Typography, App } from 'antd';
import { FilePdfOutlined, ExpandOutlined } from '@ant-design/icons';

const { Text } = Typography;

/**
 * Canonical employee document slots — match API rows by id / type / name (case-insensitive).
 */
const EMPLOYEE_DOCUMENT_SLOT_DEFS = [
  { id: 'certificate', label: 'Certificate', matchKeys: ['certificate'] },
  { id: 'diploma', label: 'Diploma', matchKeys: ['diploma'] },
  { id: 'degree', label: 'Degree', matchKeys: ['degree'] },
  { id: 'masters', label: 'Masters', matchKeys: ['masters'] },
  { id: 'phd', label: 'PhD', matchKeys: ['phd'] },
  { id: 'tin', label: 'Tax Identification Number', matchKeys: ['tin'] },
  { id: 'medical_certificate', label: 'Medical Certificate', matchKeys: ['medical'] },
  { id: 'other_certificate', label: 'Other Certificate', matchKeys: ['other'] }
];

const getDocumentUrl = (doc) =>
  doc?.url || doc?.file_url || doc?.download_url || doc?.path || null;

const getDocumentLabelText = (doc) =>
  String(doc?.name || doc?.file_name || doc?.title || doc?.document_name || '').toLowerCase();

const getDocumentTypeText = (doc) =>
  String(doc?.type || doc?.document_type || doc?.category || '').toLowerCase();

const findDocumentForSlot = (slot, documents) => {
  if (!Array.isArray(documents) || documents.length === 0) return null;
  const idKey = String(slot.id || '').toLowerCase();
  const keys = (slot.matchKeys || []).map((k) => String(k).toLowerCase()).filter(Boolean);

  return (
    documents.find((doc) => {
      const label = getDocumentLabelText(doc);
      const typ = getDocumentTypeText(doc);
      const normLabel = label.replace(/\s+/g, '_');
      if (idKey && (typ === idKey || typ === slot.id)) return true;
      if (idKey && (normLabel === idKey || label.includes(idKey.replace(/_/g, ' ')))) return true;
      return keys.some(
        (k) =>
          label.includes(k) ||
          typ === k ||
          normLabel === k.replace(/\s+/g, '_')
      );
    }) || null
  );
};

const buildEmployeeDocumentSlots = (documents) => {
  const list = Array.isArray(documents) ? documents : [];
  return EMPLOYEE_DOCUMENT_SLOT_DEFS.map((def) => {
    const matched = findDocumentForSlot(def, list);
    const url = matched ? getDocumentUrl(matched) : null;
    const available = Boolean(url && String(url).trim());
    return {
      ...def,
      matched,
      url: available ? String(url).trim() : null,
      status: available ? 'available' : 'missing'
    };
  });
};

const isSafeHttpUrl = (url) => {
  if (!url || typeof url !== 'string') return false;
  try {
    const u = new URL(url, typeof window !== 'undefined' ? window.location.origin : undefined);
    return u.protocol === 'http:' || u.protocol === 'https:';
  } catch {
    return false;
  }
};

const EmployeeDocumentsTab = ({ employeeData }) => {
  const { message } = App.useApp();
  const [selectedDocumentSlotId, setSelectedDocumentSlotId] = useState(null);
  const [documentLargeViewOpen, setDocumentLargeViewOpen] = useState(false);

  const employeeDocuments = Array.isArray(employeeData?.documents)
    ? employeeData.documents
    : Array.isArray(employeeData?.attachments)
      ? employeeData.attachments
      : [];

  const documentSlots = buildEmployeeDocumentSlots(employeeDocuments);
  const availableDocumentSlots = documentSlots.filter((s) => s.status === 'available');
  const missingDocumentSlots = documentSlots.filter((s) => s.status === 'missing');
  const resolvedDocumentSlotId =
    selectedDocumentSlotId != null && documentSlots.some((s) => s.id === selectedDocumentSlotId)
      ? selectedDocumentSlotId
      : availableDocumentSlots[0]?.id ?? missingDocumentSlots[0]?.id ?? null;
  const selectedDocumentSlot =
    documentSlots.find((s) => s.id === resolvedDocumentSlotId) || null;
  const selectedDocPreviewUrl =
    selectedDocumentSlot?.url && isSafeHttpUrl(selectedDocumentSlot.url)
      ? selectedDocumentSlot.url
      : null;

  useEffect(() => {
    setSelectedDocumentSlotId(null);
    setDocumentLargeViewOpen(false);
  }, [employeeData?.national_id, employeeData?.employee_id, employeeData?.pfno]);

  return (
    <>
      <div className="employee-profile-documents-layout">
        <Row gutter={[16, 16]}>
          <Col xs={24} lg={7} xl={6}>
            <aside className="employee-profile-documents-sidebar">
              <div className="employee-profile-documents-sidebar-section">
                <h4 className="employee-profile-documents-sidebar-heading">Available</h4>
                <div className="employee-profile-documents-sidebar-list">
                  {availableDocumentSlots.length === 0 ? (
                    <Text type="secondary" className="employee-profile-documents-sidebar-empty">
                      No documents uploaded yet.
                    </Text>
                  ) : (
                    availableDocumentSlots.map((slot) => (
                      <button
                        key={slot.id}
                        type="button"
                        className={
                          'employee-profile-doc-pill' +
                          (resolvedDocumentSlotId === slot.id ? ' employee-profile-doc-pill--selected' : '')
                        }
                        onClick={() => setSelectedDocumentSlotId(slot.id)}
                      >
                        <FilePdfOutlined className="employee-profile-doc-pill-icon" aria-hidden />
                        <span className="employee-profile-doc-pill-label">{slot.label}</span>
                      </button>
                    ))
                  )}
                </div>
              </div>
              <div className="employee-profile-documents-sidebar-section employee-profile-documents-sidebar-section--missing">
                <h4 className="employee-profile-documents-sidebar-heading">Missing</h4>
                <div className="employee-profile-documents-sidebar-list">
                  {missingDocumentSlots.map((slot) => (
                    <button
                      key={slot.id}
                      type="button"
                      className={
                        'employee-profile-doc-pill employee-profile-doc-pill--missing' +
                        (resolvedDocumentSlotId === slot.id
                          ? ' employee-profile-doc-pill--missing-selected'
                          : '')
                      }
                      onClick={() => setSelectedDocumentSlotId(slot.id)}
                    >
                      <FilePdfOutlined className="employee-profile-doc-pill-icon" aria-hidden />
                      <span className="employee-profile-doc-pill-label">{slot.label}</span>
                    </button>
                  ))}
                </div>
              </div>
            </aside>
          </Col>
          <Col xs={24} lg={17} xl={18}>
            <div className="employee-profile-documents-preview">
              {selectedDocumentSlot ? (
                <>
                  <div className="employee-profile-documents-preview-header">
                    <h3 className="employee-profile-documents-preview-title">{selectedDocumentSlot.label}</h3>
                    {selectedDocPreviewUrl ? (
                      <Button
                        type="default"
                        icon={<ExpandOutlined />}
                        onClick={() => setDocumentLargeViewOpen(true)}
                        className="employee-profile-documents-large-btn"
                      >
                        Large view
                      </Button>
                    ) : null}
                  </div>
                  {selectedDocPreviewUrl ? (
                    <div className="employee-profile-documents-frame-wrap">
                      <iframe
                        title={selectedDocumentSlot.label}
                        src={selectedDocPreviewUrl}
                        className="employee-profile-documents-iframe"
                      />
                    </div>
                  ) : (
                    <div className="employee-profile-documents-missing-panel">
                      <Text type="secondary" className="employee-profile-documents-missing-text">
                        This document has not been uploaded yet. Upload a file to attach it to this employee
                        record.
                      </Text>
                      <Upload
                        accept=".pdf,.png,.jpg,.jpeg"
                        showUploadList={false}
                        beforeUpload={(file) => {
                          message.info(
                            `Selected "${file.name}". Connect your upload API to save for "${selectedDocumentSlot.label}".`
                          );
                          return false;
                        }}
                      >
                        <Button type="primary" className="employee-profile-documents-upload-btn">
                          Upload document
                        </Button>
                      </Upload>
                    </div>
                  )}
                </>
              ) : (
                <div className="employee-profile-documents-missing-panel">
                  <Text type="secondary">Select a document from the list.</Text>
                </div>
              )}
            </div>
          </Col>
        </Row>
      </div>

      <Modal
        open={documentLargeViewOpen}
        title={selectedDocumentSlot?.label || 'Document'}
        footer={null}
        onCancel={() => setDocumentLargeViewOpen(false)}
        width="min(960px, 94vw)"
        className="employee-profile-documents-large-modal"
        styles={{ body: { padding: 0, height: 'min(80vh, 720px)' } }}
        destroyOnHidden
      >
        {selectedDocPreviewUrl ? (
          <iframe
            title={selectedDocumentSlot?.label || 'Document preview'}
            src={selectedDocPreviewUrl}
            className="employee-profile-documents-iframe employee-profile-documents-iframe--modal"
          />
        ) : null}
      </Modal>
    </>
  );
};

EmployeeDocumentsTab.propTypes = {
  employeeData: PropTypes.object.isRequired
};

export default EmployeeDocumentsTab;
