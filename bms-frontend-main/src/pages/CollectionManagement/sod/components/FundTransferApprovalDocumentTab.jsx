import { App } from 'antd';
import PropTypes from 'prop-types';
import { useEffect, useMemo, useState } from 'react';

import PayrollDocumentTab from '../../../../components/PayrollManagement/PayrollDocumentTab.jsx';
import { apiService } from '../../../../services/api.jsx';

const APPROVAL_DOC_SLOT_ID = 'approval-document';

export default function FundTransferApprovalDocumentTab({ transferId, transfer, active }) {
  const { message } = App.useApp();
  const [docState, setDocState] = useState({ loading: false, url: null });

  const hasDocument = Boolean(transfer?.has_approval_document);
  const label = transfer?.approval_document_name || 'Supporting Document';

  useEffect(() => {
    if (!active || !hasDocument || transferId == null) {
      setDocState({ loading: false, url: null });
      return undefined;
    }

    let cancelled = false;
    let createdUrl = null;

    (async () => {
      setDocState({ loading: true, url: null });
      try {
        const res = await apiService.getFundTransferApprovalDocument(transferId);
        if (cancelled) return;

        if (!res?.success || !res.blob) {
          setDocState({ loading: false, url: null });
          if (res?.message) message.error(res.message);
          return;
        }

        createdUrl = URL.createObjectURL(res.blob);
        setDocState({ loading: false, url: createdUrl });
      } catch (error) {
        if (cancelled) return;
        setDocState({ loading: false, url: null });
        message.error(error?.message || 'Failed to load supporting document.');
      }
    })();

    return () => {
      cancelled = true;
      if (createdUrl) URL.revokeObjectURL(createdUrl);
    };
  }, [active, hasDocument, transferId, message]);

  const slots = useMemo(() => {
    if (!hasDocument) return [];
    return [
      {
        id: APPROVAL_DOC_SLOT_ID,
        label,
        loading: docState.loading,
        url: docState.url,
      },
    ];
  }, [hasDocument, label, docState.loading, docState.url]);

  if (!hasDocument) {
    return (
      
      <p className="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
         No supporting document attached.
      </p>
      
      
    );
  }

  return <PayrollDocumentTab slots={slots} />;
}

FundTransferApprovalDocumentTab.propTypes = {
  transferId: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
  transfer: PropTypes.object,
  active: PropTypes.bool,
};
