import { useEffect, useMemo, useState } from 'react';
import { Button, Modal, Segmented, Tag } from 'antd';
import { EditOutlined, FileSearchOutlined, InfoCircleOutlined } from '@ant-design/icons';
import BrandModalHeader from '../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import DataTable from '../../../common/data/DataTable.jsx';
import CollectionLoader from '../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import CollectionReportRunner from '../../../pages/CollectionManagement/collection-reports/CollectionReportRunner.jsx';
import { REPORT_CATEGORY_OPTIONS } from '../constants.js';
import { normalizeReportDefinition } from '../utils/normalizeReportDefinition.js';
import { resolveReportOutputColumns } from '../utils/reportQueryUtils.js';
import {
  REPORT_ENGINE_MANAGE_MODAL_CSS,
  REPORT_ENGINE_MODAL_FOOTER_STYLE,
} from './reportEngineModal.styles.js';

const BRAND = '#962E32';
const EMPTY = 'N/A';

function SectionHeading({ children }) {
  return (
    <h4
      className="mb-3 border-b border-slate-100 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
      style={{ color: BRAND }}
    >
      {children}
    </h4>
  );
}

function ReadOnlyField({ label, value, mono = false }) {
  return (
    <div className="min-w-0 py-1.5">
      <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
        {label}
      </span>
      <div className={`mt-0.5 text-sm font-medium text-slate-900 ${mono ? 'font-mono' : ''}`}>
        {value != null && value !== '' ? (
          value
        ) : (
          <span className="font-normal text-slate-400">{EMPTY}</span>
        )}
      </div>
    </div>
  );
}

function categoryLabel(key) {
  const map = Object.fromEntries(REPORT_CATEGORY_OPTIONS.map((o) => [o.value, o.label]));
  return map[key] ?? key ?? EMPTY;
}

function DefinitionDetails({ definition }) {
  const params = Object.entries(definition?.params ?? {}).map(([name, param]) => ({
    key: name,
    name,
    type: param?.type ?? 'string',
    required: param?.required ? 'Yes' : 'No',
    description: param?.description ?? '',
  }));

  const columns = resolveReportOutputColumns(
    definition?.output_columns ?? definition?.columns,
    definition?.query
  ).map((col) => ({
    key: col.name,
    name: col.name,
    type: col.type ?? 'string',
  }));

  const statusTag = definition?.deleted_at ? (
    <Tag color="default">Deleted</Tag>
  ) : definition?.is_active ? (
    <Tag color="success">Active</Tag>
  ) : (
    <Tag color="warning">Inactive</Tag>
  );

  return (
    <>
      <SectionHeading>Report Information</SectionHeading>
      <div className="mb-4 grid grid-cols-1 gap-x-6 gap-y-1 md:grid-cols-2 lg:grid-cols-3">
        <ReadOnlyField label="Report Name" value={definition?.name ?? definition?.label} />
        <ReadOnlyField label="Script Name" value={definition?.script} mono />
        <ReadOnlyField label="Handler" value={definition?.handler} mono />
        <ReadOnlyField label="Category" value={categoryLabel(definition?.category_key)} />
        <ReadOnlyField label="Sort Order" value={definition?.sort_order ?? 0} />
        <div className="min-w-0 py-1.5">
          <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
            Status
          </span>
          <div className="mt-1">{statusTag}</div>
        </div>
        <ReadOnlyField
          label="Paginated"
          value={definition?.paginated ?? definition?.options?.paginated ? 'Yes' : 'No'}
        />
        <ReadOnlyField
          label="Nested"
          value={definition?.nested ?? definition?.options?.nested ? 'Yes' : 'No'}
        />
      </div>
      <ReadOnlyField label="Description" value={definition?.description} />

      <div className="mt-5">
        <SectionHeading>SQL Query</SectionHeading>
        <pre
          className="max-h-64 overflow-auto rounded-md border border-slate-200 bg-slate-50 p-3 text-sm leading-relaxed text-slate-800"
          style={{ fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace' }}
        >
          {definition?.query?.trim() ? definition.query.trim() : EMPTY}
        </pre>
      </div>

      <div className="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
          <SectionHeading>Parameters</SectionHeading>
          <DataTable
            columns={[
              {
                title: 'Parameter',
                dataIndex: 'name',
                key: 'name',
                render: (v) => <span className="font-mono text-sm">{v}</span>,
              },
              { title: 'Type', dataIndex: 'type', key: 'type', width: 90 },
              { title: 'Required', dataIndex: 'required', key: 'required', width: 90 },
              { title: 'Description', dataIndex: 'description', key: 'description', ellipsis: true },
            ]}
            data={params}
            showSearch={false}
            showRefresh={false}
            rowKey="key"
            pagination={false}
          />
        </div>
        <div>
          <SectionHeading>Output Columns</SectionHeading>
          <DataTable
            columns={[
              {
                title: 'Column',
                dataIndex: 'name',
                key: 'name',
                render: (v) => <span className="font-mono text-sm">{v}</span>,
              },
              { title: 'Type', dataIndex: 'type', key: 'type', width: 100 },
            ]}
            data={columns}
            showSearch={false}
            showRefresh={false}
            rowKey="key"
            pagination={false}
          />
        </div>
      </div>
    </>
  );
}

export default function ReportDefinitionViewModal({
  open,
  onClose,
  definition,
  loading = false,
  onEdit,
  moduleSlug = 'collection-management',
}) {
  const [activeTab, setActiveTab] = useState('generate');

  useEffect(() => {
    if (open) setActiveTab('generate');
  }, [open, definition?.definition_id]);

  const runnerReport = useMemo(() => {
    if (!definition) return null;
    return normalizeReportDefinition(definition);
  }, [definition]);

  const reports = useMemo(() => (runnerReport ? [runnerReport] : []), [runnerReport]);

  const modalTitle = definition?.name
    ? `Run Report — ${definition.name}`
    : 'Run Report';

  return (
    <>
      <style>{REPORT_ENGINE_MANAGE_MODAL_CSS}</style>
      <Modal
        open={open}
        onCancel={onClose}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        width={1300}
        style={{ maxWidth: '96vw', top: 24 }}
        className="brand-modal report-engine-run-report-modal"
        styles={{
          body: { padding: 0, backgroundColor: '#ffffff' },
          content: { padding: 0, overflow: 'hidden', backgroundColor: '#ffffff' },
        }}
      >
        <BrandModalHeader title={modalTitle} onClose={onClose} />

        <div className="report-engine-run-report-tabs px-6 py-3">
          <Segmented
            value={activeTab}
            onChange={setActiveTab}
            options={[
              {
                label: (
                  <span className="inline-flex items-center gap-1.5 text-slate-700">
                    <FileSearchOutlined /> Generate
                  </span>
                ),
                value: 'generate',
              },
              {
                label: (
                  <span className="inline-flex items-center gap-1.5 text-slate-700">
                    <InfoCircleOutlined /> Definition
                  </span>
                ),
                value: 'definition',
              },
            ]}
          />
        </div>

        <div
          className={`report-engine-run-report-body bg-white ${
            activeTab === 'generate'
              ? 'flex max-h-[calc(92vh-10rem)] min-h-[420px] flex-col overflow-hidden'
              : 'max-h-[calc(92vh-10rem)] overflow-y-auto px-6 py-4'
          }`}
        >
        {loading ? (
          <CollectionLoader compact />
        ) : activeTab === 'generate' ? (
          runnerReport ? (
            <CollectionReportRunner
              reports={reports}
              selectedReportId={runnerReport.id}
              moduleSlug={moduleSlug}
              useReportEngine
              hideReportList
              embedded
            />
          ) : (
            <CollectionLoader compact />
          )
        ) : definition ? (
          <DefinitionDetails definition={definition} />
        ) : (
          <CollectionLoader compact />
        )}
      </div>

      <div
        className="report-engine-run-report-footer report-engine-modal-footer flex items-center justify-end gap-2 px-6 py-3"
        style={REPORT_ENGINE_MODAL_FOOTER_STYLE}
      >
        {onEdit && definition ? (
          <Button type="default" icon={<EditOutlined />} onClick={() => onEdit(definition)}>
            Edit Definition
          </Button>
        ) : null}
        <Button onClick={onClose}>Close</Button>
      </div>
    </Modal>
    </>
  );
}
