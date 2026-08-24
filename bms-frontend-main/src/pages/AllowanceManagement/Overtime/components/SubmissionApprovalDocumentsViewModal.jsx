import { App, Modal } from 'antd';
import { FileTextOutlined } from '@ant-design/icons';
import PropTypes from 'prop-types';
import { useEffect, useMemo, useState } from 'react';
import PayrollDocumentTab from '../../../../components/PayrollManagement/PayrollDocumentTab.jsx';
import { overtimeService } from '../../../../services/overtimeService.js';
import { formatReadinessText, getSubmissionDocumentTypeLabel } from '../submissionApprovalDocumentsUtils.js';

const getDocumentLabel = (record) => {
  if (!record) return 'Document';
  return (
    record.document_name ||
    record.file_name ||
    record.document_type_name ||
    getSubmissionDocumentTypeLabel(record.document_type, record) ||
    'Document'
  );
};

const SubmissionApprovalDocumentsViewModal = ({ open, record, onClose }) => {
  const { message } = App.useApp();
  const [state, setState] = useState({ loading: false, url: null });

  useEffect(() => {
    if (!open || !record?.id) {
      setState({ loading: false, url: null });
      return undefined;
    }

    let cancelled = false;
    let createdUrl = null;

    (async () => {
      setState({ loading: true, url: null });
      try {
        const response = await overtimeService.downloadSubmissionDocument(record.id);
        if (cancelled) return;

        if (!response?.success || !response.blob) {
          setState({ loading: false, url: null });
          message.error(formatReadinessText(response?.message) || 'Could not load document preview.');
          return;
        }

        createdUrl = URL.createObjectURL(response.blob);
        setState({ loading: false, url: createdUrl });
      } catch (error) {
        if (cancelled) return;
        setState({ loading: false, url: null });
        message.error(error?.message || 'Could not load document preview.');
      }
    })();

    return () => {
      cancelled = true;
      if (createdUrl) URL.revokeObjectURL(createdUrl);
    };
  }, [open, record?.id, message]);

  const slots = useMemo(
    () => [
      {
        id: record?.id ?? 'document',
        label: getDocumentLabel(record),
        loading: state.loading,
        url: state.url,
      },
    ],
    [record, state.loading, state.url]
  );

  const modalWidth = typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 1100;

  return (
    <Modal
      title={
        <span>
          <FileTextOutlined style={{ marginRight: 8 }} />
          {getDocumentLabel(record)}
        </span>
      }
      open={open}
      onCancel={onClose}
      footer={null}
      width={modalWidth}
      destroyOnHidden
      style={{ top: 20 }}
    >
      <PayrollDocumentTab slots={slots} />
    </Modal>
  );
};

SubmissionApprovalDocumentsViewModal.propTypes = {
  open: PropTypes.bool.isRequired,
  record: PropTypes.object,
  onClose: PropTypes.func.isRequired,
};

export default SubmissionApprovalDocumentsViewModal;
