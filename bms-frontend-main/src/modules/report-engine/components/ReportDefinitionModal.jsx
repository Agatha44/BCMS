import { useCallback, useEffect, useMemo } from 'react';
import { Button, Col, Form, Input, Modal, Row, Select, Switch, Tag } from 'antd';
import {
  DeleteOutlined,
  DownOutlined,
  PlusCircleOutlined,
  SaveOutlined,
  UpOutlined,
} from '@ant-design/icons';
import BrandModalHeader from '../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import SmallActionButton from './SmallActionButton.jsx';
import {
  REPORT_ENGINE_DEFINITION_MODAL_CSS,
  REPORT_ENGINE_MODAL_FOOTER_STYLE,
} from './reportEngineModal.styles.js';
import { REPORT_CATEGORY_OPTIONS } from '../constants.js';
import {
  extractColumnsFromQuery,
  extractParamsFromQuery,
  humanizeColumnTitle,
  resolveReportOutputColumns,
} from '../utils/reportQueryUtils.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const { Option } = Select;

const STYLES = {
  radius: { sm: 4, md: 6 },
  border: '1px solid #e2e8f0',
  listRow: {
    display: 'flex',
    alignItems: 'center',
    gap: 8,
    padding: '6px 10px',
    minHeight: 36,
    marginBottom: 0,
    borderTop: '1px solid #eee',
  },
  listActions: { flexShrink: 0, display: 'flex', alignItems: 'center', gap: 2 },
  iconBtn: {
    width: 24,
    height: 24,
    padding: 0,
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    color: '#666',
    border: 'none',
    background: 'transparent',
    cursor: 'pointer',
    borderRadius: 4,
  },
  readonlyInput: {
    borderRadius: 6,
    backgroundColor: '#f4f4f5',
    color: '#18181b',
    cursor: 'not-allowed',
    fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
    fontSize: 13,
  },
};

function FormLabel({ children }) {
  return (
    <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {children}
    </span>
  );
}

function SectionHeading({ children }) {
  return (
    <h4
      className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em] dark:border-slate-600"
      style={{ color: BRAND }}
    >
      {children}
    </h4>
  );
}

function slugFromReportName(name) {
  if (!name || typeof name !== 'string') return '';
  return name
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '');
}

export default function ReportDefinitionModal({
  open,
  onClose,
  onSubmit,
  submitting,
  initialValues,
}) {
  const [form] = Form.useForm();
  const isEdit = Boolean(initialValues?.definition_id ?? initialValues?.id);
  const definitionKey = initialValues?.definition_id ?? initialValues?.id ?? 'create';
  const storedHandler = initialValues?.handler ?? initialValues?.script ?? initialValues?.key ?? '';

  const queryRules = useMemo(() => [{ required: true, message: 'Required' }], []);

  useEffect(() => {
    if (!open) return;
    form.resetFields();
    if (initialValues) {
      const scriptKey =
        initialValues.script ?? initialValues.handler ?? initialValues.key ?? '';
      form.setFieldsValue({
        category_key: initialValues.category_key,
        sort_order: initialValues.sort_order ?? 0,
        name: initialValues.name ?? initialValues.label,
        description: initialValues.description ?? '',
        script: scriptKey,
        query: initialValues.query ?? '',
        params: Object.entries(initialValues.params ?? {}).map(([name, param]) => ({
          name,
          type: param?.type ?? 'string',
          required: param?.required !== false,
          description: param?.description ?? '',
          ...(param?.input ? { input: param.input } : {}),
          ...(param?.options ? { options: param.options } : {}),
        })),
        output_columns: resolveReportOutputColumns(
          initialValues.output_columns ?? initialValues.columns,
          initialValues.query
        ),
        paginated: Boolean(initialValues.paginated ?? initialValues.options?.paginated),
        nested: Boolean(initialValues.nested ?? initialValues.options?.nested),
        is_active: initialValues.is_active !== false,
      });
    } else {
      form.setFieldsValue({
        sort_order: 0,
        params: [],
        output_columns: [],
        paginated: false,
        nested: false,
        is_active: true,
      });
    }
  }, [open, initialValues, form]);

  const handleQueryChange = useCallback(
    (e) => {
      const query = e?.target?.value ?? '';
      form.setFieldsValue({
        params: extractParamsFromQuery(query),
        output_columns: extractColumnsFromQuery(query),
      });
    },
    [form]
  );

  const handleFinish = (values) => {
    const cleanedQuery = (values.query || '')
      .replace(/\s+AS\s+col_(\w+)/gi, ' AS $1')
      .replace(/\s+as\s+col_(\w+)/gi, ' AS $1');

    const params = (values.params || []).reduce((acc, param) => {
      if (param?.name) {
        acc[param.name] = {
          type: param.type,
          required: param.required,
          description: param.description || '',
          ...(param.input ? { input: param.input } : {}),
          ...(param.options ? { options: param.options } : {}),
        };
      }
      return acc;
    }, {});

    const output_columns = (values.output_columns || [])
      .filter((col) => col?.name)
      .map((col) => ({
        title: humanizeColumnTitle(col.name),
        key: col.name,
        type: col.type,
      }));

    onSubmit({
      category_key: values.category_key,
      name: values.name,
      description: values.description,
      script: values.script,
      handler: values.script || initialValues?.handler || values.script,
      query: cleanedQuery,
      params,
      output_columns,
      options: {
        paginated: Boolean(values.paginated),
        nested: Boolean(values.nested),
      },
      sort_order: values.sort_order ?? 0,
      is_active: values.is_active !== false,
    });
  };

  const modalTitle = isEdit ? 'Edit Report Definition' : 'Add Report Definition';

  return (
    <>
      <style>{REPORT_ENGINE_DEFINITION_MODAL_CSS}</style>
      <Modal
        key={definitionKey}
        open={open}
        onCancel={onClose}
        footer={null}
        centered
        destroyOnClose
        title={null}
        closable={false}
        width={1300}
        className="brand-modal report-engine-definition-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title={modalTitle} onClose={onClose} />

        <Form form={form} layout="vertical" onFinish={handleFinish}>
          <div className="max-h-[calc(92vh-7.5rem)] overflow-y-auto px-6 py-4">
            <SectionHeading>Report Details</SectionHeading>
            <Row gutter={[16, 0]}>
              <Col xs={24} md={8}>
                <Form.Item
                  name="category_key"
                  label={<FormLabel>Category</FormLabel>}
                  rules={[{ required: true, message: 'Category is required' }]}
                >
                  <Select placeholder="Select category" options={REPORT_CATEGORY_OPTIONS} />
                </Form.Item>
              </Col>
              <Col xs={24} md={4}>
                <Form.Item name="sort_order" label={<FormLabel>Sort Order</FormLabel>}>
                  <Input type="number" min={0} />
                </Form.Item>
              </Col>
              <Col xs={8} md={4}>
                <Form.Item name="paginated" label={<FormLabel>Paginated</FormLabel>} valuePropName="checked">
                  <Switch />
                </Form.Item>
              </Col>
              <Col xs={8} md={4}>
                <Form.Item name="nested" label={<FormLabel>Nested</FormLabel>} valuePropName="checked">
                  <Switch />
                </Form.Item>
              </Col>
              <Col xs={8} md={4}>
                <Form.Item name="is_active" label={<FormLabel>Active</FormLabel>} valuePropName="checked">
                  <Switch />
                </Form.Item>
              </Col>
            </Row>

            <Row gutter={[16, 0]}>
              <Col xs={24} md={8}>
                <Form.Item
                  name="name"
                  label={<FormLabel>Report Name</FormLabel>}
                  rules={[{ required: true, message: 'Report name is required' }]}
                >
                  <Input
                    placeholder="Enter report name"
                    onChange={(e) => {
                      form.setFieldValue('script', slugFromReportName(e.target.value));
                    }}
                  />
                </Form.Item>
              </Col>
              <Col xs={24} md={8}>
                <Form.Item
                  name="script"
                  label={<FormLabel>Script Name</FormLabel>}
                  rules={[{ required: true, message: 'Script name is required' }]}
                >
                  <Input readOnly placeholder="Derived from report name" style={STYLES.readonlyInput} />
                </Form.Item>
                {storedHandler ? (
                  <div className="-mt-2 mb-2">
                    <Tag style={{ fontFamily: 'monospace', fontSize: 12, borderColor: BRAND, color: BRAND }}>
                      Handler: {storedHandler}
                    </Tag>
                  </div>
                ) : null}
              </Col>
              <Col xs={24} md={8}>
                <Form.Item
                  name="description"
                  label={<FormLabel>Description</FormLabel>}
                  rules={[{ required: true, message: 'Description is required' }]}
                >
                  <Input placeholder="Enter description" />
                </Form.Item>
              </Col>
            </Row>

            <SectionHeading>SQL Query</SectionHeading>
            <Form.Item
              name="query"
              rules={queryRules}
              className="mb-4"
              style={{ marginBottom: 16 }}
            >
              <Input.TextArea
                rows={6}
                onChange={handleQueryChange}
                placeholder="Enter your SQL query here... Use :param_name for parameters."
                className="report-engine-sql-editor"
                style={{
                  fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
                  fontSize: 14,
                  background: '#1e1e1e',
                  color: '#d4d4d4',
                  border: '1px solid #333',
                  borderRadius: STYLES.radius.md,
                  padding: 12,
                }}
              />
            </Form.Item>

            <Row gutter={16}>
              <Col xs={24} lg={12}>
                <div className="report-engine-panel mb-4 flex h-full min-h-[220px] flex-col overflow-hidden rounded border border-slate-200 dark:border-slate-600">
                  <div className="report-engine-panel-header flex shrink-0 items-center justify-between border-b border-slate-200 px-3 py-2.5 dark:border-slate-600">
                    <span
                      className="text-xs font-semibold uppercase tracking-[0.12em]"
                      style={{ color: BRAND }}
                    >
                      Parameters
                    </span>
                    <div className="flex gap-1.5">
                      <SmallActionButton
                        variant="default"
                        onClick={() => {
                          const current = form.getFieldValue('params') || [];
                          form.setFieldsValue({ params: current.map((p) => ({ ...p, required: true })) });
                        }}
                      >
                        Set All Required
                      </SmallActionButton>
                      <SmallActionButton
                        variant="primary"
                        onClick={() => {
                          const current = form.getFieldValue('params') || [];
                          form.setFieldsValue({
                            params: [...current, { name: '', type: 'string', required: true }],
                          });
                        }}
                      >
                        <PlusCircleOutlined style={{ marginRight: 6 }} /> Add
                      </SmallActionButton>
                    </div>
                  </div>
                  <div className="min-h-0 flex-1 overflow-auto">
                    <Form.List name="params">
                      {(fields, { remove, move }) => (
                        <div>
                          {fields.map(({ key, name, ...rest }) => (
                            <div
                              key={key}
                              className="report-engine-list-row"
                              style={{
                                ...STYLES.listRow,
                                ...(name === 0 ? { borderTop: 'none' } : {}),
                              }}
                            >
                              <Form.Item
                                {...rest}
                                name={[name, 'name']}
                                rules={[{ required: true, message: 'Required' }]}
                                style={{ flex: '1 1 0', minWidth: 0, marginBottom: 0 }}
                              >
                                <Input placeholder="Name" size="small" />
                              </Form.Item>
                              <Form.Item
                                {...rest}
                                name={[name, 'type']}
                                rules={[{ required: true, message: 'Required' }]}
                                style={{ width: 100, marginBottom: 0 }}
                              >
                                <Select placeholder="Type" size="small">
                                  <Option value="date">Date</Option>
                                  <Option value="integer">Integer</Option>
                                  <Option value="string">String</Option>
                                  <Option value="decimal">Decimal</Option>
                                </Select>
                              </Form.Item>
                              <Form.Item
                                {...rest}
                                name={[name, 'required']}
                                valuePropName="checked"
                                style={{ marginBottom: 0, width: 'auto' }}
                              >
                                <Switch size="small" />
                              </Form.Item>
                              <div style={STYLES.listActions}>
                                {name > 0 && (
                                  <button
                                    type="button"
                                    style={STYLES.iconBtn}
                                    onClick={() => move(name, name - 1)}
                                    aria-label="Move up"
                                  >
                                    <UpOutlined style={{ fontSize: 12 }} />
                                  </button>
                                )}
                                {name < fields.length - 1 && (
                                  <button
                                    type="button"
                                    style={STYLES.iconBtn}
                                    onClick={() => move(name, name + 1)}
                                    aria-label="Move down"
                                  >
                                    <DownOutlined style={{ fontSize: 12 }} />
                                  </button>
                                )}
                                <button
                                  type="button"
                                  style={{ ...STYLES.iconBtn, color: '#ff4d4f' }}
                                  onClick={() => remove(name)}
                                  aria-label="Remove"
                                >
                                  <DeleteOutlined style={{ fontSize: 12 }} />
                                </button>
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </Form.List>
                  </div>
                </div>
              </Col>

              <Col xs={24} lg={12}>
                <div className="report-engine-panel mb-4 flex h-full min-h-[220px] flex-col overflow-hidden rounded border border-slate-200 dark:border-slate-600">
                  <div className="report-engine-panel-header flex shrink-0 items-center justify-between border-b border-slate-200 px-3 py-2.5 dark:border-slate-600">
                    <span
                      className="text-xs font-semibold uppercase tracking-[0.12em]"
                      style={{ color: BRAND }}
                    >
                      Output Columns
                    </span>
                    <div className="flex gap-1.5">
                      <SmallActionButton
                        variant="default"
                        onClick={() => {
                          const current = form.getFieldValue('output_columns') || [];
                          form.setFieldsValue({ output_columns: current.map((c) => ({ ...c, type: 'string' })) });
                        }}
                      >
                        Set All String
                      </SmallActionButton>
                      <SmallActionButton
                        variant="primary"
                        onClick={() => {
                          const current = form.getFieldValue('output_columns') || [];
                          form.setFieldsValue({
                            output_columns: [...current, { name: '', type: 'string' }],
                          });
                        }}
                      >
                        <PlusCircleOutlined style={{ marginRight: 6 }} /> Add
                      </SmallActionButton>
                    </div>
                  </div>
                  <div className="min-h-0 flex-1 overflow-auto">
                    <Form.List name="output_columns">
                      {(fields, { remove, move }) => (
                        <div>
                          {fields.map(({ key, name, ...rest }) => (
                            <div
                              key={key}
                              className="report-engine-list-row"
                              style={{
                                ...STYLES.listRow,
                                ...(name === 0 ? { borderTop: 'none' } : {}),
                              }}
                            >
                              <Form.Item
                                {...rest}
                                name={[name, 'name']}
                                rules={[{ required: true, message: 'Required' }]}
                                style={{ flex: '1 1 0', minWidth: 0, marginBottom: 0 }}
                              >
                                <Input placeholder="Column name" size="small" />
                              </Form.Item>
                              <Form.Item
                                {...rest}
                                name={[name, 'type']}
                                rules={[{ required: true, message: 'Required' }]}
                                style={{ width: 100, marginBottom: 0 }}
                              >
                                <Select placeholder="Type" size="small">
                                  <Option value="string">String</Option>
                                  <Option value="integer">Integer</Option>
                                  <Option value="date">Date</Option>
                                  <Option value="decimal">Decimal</Option>
                                </Select>
                              </Form.Item>
                              <div style={STYLES.listActions}>
                                {name > 0 && (
                                  <button
                                    type="button"
                                    style={STYLES.iconBtn}
                                    onClick={() => move(name, name - 1)}
                                    aria-label="Move up"
                                  >
                                    <UpOutlined style={{ fontSize: 12 }} />
                                  </button>
                                )}
                                {name < fields.length - 1 && (
                                  <button
                                    type="button"
                                    style={STYLES.iconBtn}
                                    onClick={() => move(name, name + 1)}
                                    aria-label="Move down"
                                  >
                                    <DownOutlined style={{ fontSize: 12 }} />
                                  </button>
                                )}
                                <button
                                  type="button"
                                  style={{ ...STYLES.iconBtn, color: '#ff4d4f' }}
                                  onClick={() => remove(name)}
                                  aria-label="Remove"
                                >
                                  <DeleteOutlined style={{ fontSize: 12 }} />
                                </button>
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </Form.List>
                  </div>
                </div>
              </Col>
            </Row>
          </div>

          <div
            className="report-engine-modal-footer flex items-center justify-end gap-2 px-6 py-3"
            style={REPORT_ENGINE_MODAL_FOOTER_STYLE}
          >
            <Button
              type="primary"
              htmlType="submit"
              loading={submitting}
              icon={<SaveOutlined />}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
                e.currentTarget.style.borderColor = BRAND_DARK;
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
                e.currentTarget.style.borderColor = BRAND;
              }}
            >
              {isEdit ? 'Update Report' : 'Create Report'}
            </Button>
            <Button onClick={onClose} disabled={submitting}>
              Close
            </Button>
          </div>
        </Form>
      </Modal>
    </>
  );
}
