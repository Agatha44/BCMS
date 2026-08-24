export const REPORT_ENGINE_BRAND = '#962E32';

export const REPORT_ENGINE_MANAGE_MODAL_WIDTH = 1400;

export const REPORT_ENGINE_MODAL_FOOTER_STYLE = {
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'flex-end',
  gap: 8,
  width: '100%',
  backgroundColor: '#ffffff',
  borderTop: '1px solid #e2e8f0',
};

export const REPORT_ENGINE_MANAGE_MODAL_CSS = `
  .report-engine-manage-modal .ant-modal-content {
    max-width: 96vw;
  }
  .report-engine-manage-modal .ant-modal-body {
    max-height: calc(94vh - 180px);
    overflow-y: auto;
  }
  .report-engine-manage-modal .ant-modal-footer {
    background: #ffffff !important;
    border-top: 1px solid #e2e8f0 !important;
    margin: 0 !important;
    padding: 12px 24px !important;
  }
  html.light .report-engine-manage-modal .ant-modal-footer,
  html.light .report-engine-manage-modal .report-engine-modal-footer {
    background-color: #ffffff !important;
    border-top-color: #e2e8f0 !important;
  }
  html.dark .report-engine-manage-modal .ant-modal-footer {
    background-color: var(--bms-bg-elevated) !important;
    border-top-color: var(--bms-border) !important;
  }
  .report-engine-definition-modal .report-engine-modal-footer {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
  }
  html.light .report-engine-definition-modal .report-engine-modal-footer {
    background-color: #ffffff !important;
    border-top-color: #e2e8f0 !important;
  }
  html.dark .report-engine-definition-modal .report-engine-modal-footer {
    background-color: var(--bms-bg-elevated) !important;
    border-top-color: var(--bms-border) !important;
  }
  .report-engine-run-report-modal .ant-modal-body {
    background: #ffffff !important;
  }
  html.light .report-engine-run-report-modal .ant-modal-content,
  html.light .report-engine-run-report-modal .ant-modal-body,
  html.light .report-engine-run-report-modal .report-engine-run-report-body {
    background-color: #ffffff !important;
  }
  .report-engine-run-report-modal .report-engine-run-report-tabs {
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
  }
  html.light .report-engine-run-report-modal .ant-segmented {
    background: #f8fafc !important;
  }
  html.light .report-engine-run-report-modal .ant-segmented-item-selected {
    background: #ffffff !important;
    color: #0f172a !important;
  }
  .report-engine-run-report-modal .report-engine-run-report-footer {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
  }
  html.light .report-engine-run-report-modal .report-engine-run-report-footer {
    background-color: #ffffff !important;
    border-top-color: #e2e8f0 !important;
  }
  html.dark .report-engine-run-report-modal .report-engine-run-report-footer {
    background-color: var(--bms-bg-elevated) !important;
    border-top-color: var(--bms-border) !important;
  }
  .report-engine-run-report-modal .collection-report-runner-embedded {
    background: #ffffff;
  }
  .report-engine-run-report-modal .collection-report-runner-embedded .report-runner-toolbar {
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
  }
  .report-engine-run-report-modal .collection-report-runner-embedded .report-runner-footer {
    border-top: 1px solid #e2e8f0;
    background: #ffffff;
  }
  .report-engine-run-report-modal .collection-report-runner-embedded .report-runner-filters {
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
  }
`;

export const REPORT_ENGINE_DEFINITION_MODAL_CSS = `
  .report-engine-definition-modal .report-engine-panel {
    background: #ffffff;
  }
  .report-engine-definition-modal .report-engine-sql-editor {
    resize: vertical;
  }
  html.dark .report-engine-definition-modal .report-engine-panel {
    background: #0f172a;
  }
  html.dark .report-engine-definition-modal .report-engine-list-row {
    border-top-color: #334155 !important;
  }
  html.dark .report-engine-definition-modal .report-engine-sql-editor {
    background: #1e1e1e !important;
    color: #d4d4d4 !important;
    border-color: #333 !important;
  }
`;

export const REPORT_ENGINE_PRIMARY_BTN = {
  background: REPORT_ENGINE_BRAND,
  borderColor: REPORT_ENGINE_BRAND,
  borderRadius: 8,
  fontWeight: 500,
};
