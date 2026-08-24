import { useEffect, useMemo, useState } from 'react';
import { UploadOutlined, FileTextOutlined, EditOutlined } from '@ant-design/icons';
import { Button, DatePicker, Form, Input, Modal, Select, Space, Upload } from 'antd';
import dayjs from 'dayjs';
import { overtimeService } from '../../../../services/overtimeService.js';
import {
  BRAND,
  MAX_PDF_SIZE_BYTES,
  computePeriodEnd,
  formatReadinessText,
  toSubmissionDocumentTypeOptions,
} from '../submissionApprovalDocumentsUtils.js';

const SubmissionApprovalDocumentsUploadModal = ({
  open,
  mode = 'create',
  documentRecord = null,
  documentTypes = [],
  lockDocumentType = false,
  onClose,
  onSuccess,
}) => {
  const [form] = Form.useForm();
  const [submitting, setSubmitting] = useState(false);
  const [fileList, setFileList] = useState([]);
  const [periodMonths, setPeriodMonths] = useState(null);
  const [errorMessage, setErrorMessage] = useState('');

  const isEdit = mode === 'edit';
  const isReupload = mode === 'reupload';
  const title = isEdit ? 'Edit Submission Document' : isReupload ? 'Re-upload Submission Document' : 'Upload Submission Document';
  const TitleIcon = isEdit ? EditOutlined : isReupload ? UploadOutlined : FileTextOutlined;

  const modalWidth = typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 640;

  const typeOptions = useMemo(
    () => toSubmissionDocumentTypeOptions(documentTypes),
    [documentTypes]
  );

  const selectedDocumentType = Form.useWatch('document_type', form);
  const selectedTypeOption = typeOptions.find((option) => option.value === selectedDocumentType);
  const periodMonthOptions = (selectedTypeOption?.periodMonthOptions || [])
    .map((months) => {
      if (typeof months === 'object' && months !== null) {
        const value = Number(months.value ?? months.months ?? months.code);
        return {
          value,
          label: months.label ?? months.name ?? String(value),
        };
      }
      const value = Number(months);
      return { value, label: String(value) };
    })
    .filter((option) => Number.isFinite(option.value) && option.value > 0);

  useEffect(() => {
    if (!open) return;

    setFileList([]);
    setPeriodMonths(null);
    setErrorMessage('');

    if (isEdit && documentRecord) {
      form.setFieldsValue({
        document_type: documentRecord.document_type,
        document_name: documentRecord.document_name || undefined,
        period_start: documentRecord.period_start ? dayjs(documentRecord.period_start) : undefined,
        period_end: documentRecord.period_end ? dayjs(documentRecord.period_end) : undefined,
      });
      return;
    }

    if ((isReupload || mode === 'create') && documentRecord) {
      form.setFieldsValue({
        document_type: documentRecord.document_type,
        document_name: documentRecord.document_name || undefined,
        period_start: documentRecord.period_start ? dayjs(documentRecord.period_start) : undefined,
        period_end: documentRecord.period_end ? dayjs(documentRecord.period_end) : undefined,
      });
      return;
    }

    form.resetFields();
  }, [open, mode, documentRecord, form, isEdit, isReupload]);

  const handleClose = () => {
    if (submitting) return;
    form.resetFields();
    setFileList([]);
    setPeriodMonths(null);
    setErrorMessage('');
    onClose?.();
  };

  const handleTypeChange = (value) => {
    const selected = typeOptions.find((t) => t.value === value);
    if (selected?.defaultMonths) {
      setPeriodMonths(selected.defaultMonths);
      const start = form.getFieldValue('period_start');
      if (start) {
        form.setFieldValue('period_end', computePeriodEnd(start, selected.defaultMonths));
      }
    }
  };

  const handlePeriodStartChange = (date) => {
    if (periodMonths && date) {
      form.setFieldValue('period_end', computePeriodEnd(date, periodMonths));
    }
  };

  const handleApplyMonths = () => {
    const start = form.getFieldValue('period_start');
    if (!start || !periodMonths) return;
    form.setFieldValue('period_end', computePeriodEnd(start, periodMonths));
  };

  const validatePdf = (file) => {
    if (!file) return 'PDF file is required';
    if (file.type && file.type !== 'application/pdf') {
      return 'Only PDF files are allowed';
    }
    if (file.size > MAX_PDF_SIZE_BYTES) {
      return 'File size must not exceed 10MB';
    }
    return null;
  };

  const handleSubmit = async () => {
    setErrorMessage('');
    try {
      const values = await form.validateFields();
      const file = fileList[0]?.originFileObj;

      if (!isEdit) {
        const pdfError = validatePdf(file);
        if (pdfError) {
          setErrorMessage(pdfError);
          return;
        }
      } else if (file) {
        const pdfError = validatePdf(file);
        if (pdfError) {
          setErrorMessage(pdfError);
          return;
        }
      }

      const payload = {};
      if (values.document_name?.trim()) {
        payload.document_name = values.document_name.trim();
      }
      if (values.period_start) {
        payload.period_start = dayjs(values.period_start).format('YYYY-MM-DD');
      }
      if (values.period_end) {
        payload.period_end = dayjs(values.period_end).format('YYYY-MM-DD');
      }

      if (!isEdit) {
        payload.document_type = values.document_type;
      } else if (!Object.keys(payload).length && !file) {
        setErrorMessage('Change at least one field or upload a new PDF.');
        return;
      }

      setSubmitting(true);
      let response;
      if (isEdit) {
        response = await overtimeService.updateSubmissionDocument(documentRecord.id, payload, file || null);
      } else {
        response = await overtimeService.createSubmissionDocument(payload, file);
      }

      if (!response?.success) {
        setErrorMessage(formatReadinessText(response?.message));
        return;
      }

      onSuccess?.(response);
      handleClose();
    } catch (err) {
      if (err?.errorFields) return;
      setErrorMessage(formatReadinessText(err?.message));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal
      title={
        <span>
          <TitleIcon style={{ marginRight: 8 }} />
          {title}
        </span>
      }
      open={open}
      onCancel={handleClose}
      footer={null}
      width={modalWidth}
      destroyOnHidden
      style={{ top: 20 }}
      maskClosable={!submitting}
      keyboard={!submitting}
    >
      <Form form={form} layout="vertical" requiredMark={false}>
        {errorMessage ? (
          <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {errorMessage}
          </div>
        ) : null}

        <Form.Item
          name="document_type"
          label={<span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>Document Type</span>}
          rules={[{ required: !isEdit, message: 'Please select a document type' }]}
        >
          <Select
            placeholder="Select document type"
            options={typeOptions}
            disabled={isEdit || lockDocumentType || submitting}
            onChange={handleTypeChange}
            className="!rounded-lg"
          />
        </Form.Item>

        <Form.Item
          name="document_name"
          label={<span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>Document Name</span>}
        >
          <Input placeholder="Optional display name" disabled={submitting} className="!rounded-lg" />
        </Form.Item>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Form.Item
            name="period_start"
            label={<span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>Period Start</span>}
            rules={[{ required: !isEdit, message: 'Period start is required' }]}
          >
            <DatePicker
              className="!w-full !rounded-lg"
              format="DD-MMM-YYYY"
              disabled={submitting}
              onChange={handlePeriodStartChange}
            />
          </Form.Item>

          <Form.Item
            name="period_end"
            label={<span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>Period End</span>}
            dependencies={['period_start']}
            rules={[
              { required: !isEdit, message: 'Period end is required' },
              ({ getFieldValue }) => ({
                validator(_, value) {
                  const start = getFieldValue('period_start');
                  if (!value || !start) return Promise.resolve();
                  if (dayjs(value).isBefore(dayjs(start), 'day')) {
                    return Promise.reject(new Error('Period end must be on or after period start'));
                  }
                  return Promise.resolve();
                },
              }),
            ]}
          >
            <DatePicker className="!w-full !rounded-lg" format="DD-MMM-YYYY" disabled={submitting} />
          </Form.Item>
        </div>

        {!isEdit && periodMonthOptions.length > 0 ? (
          <div className="mb-4 flex flex-wrap items-center gap-2">
            <Select
              placeholder={selectedTypeOption?.raw?.period_months_label ?? 'Months'}
              value={periodMonths}
              onChange={setPeriodMonths}
              options={periodMonthOptions}
              className="min-w-[120px]"
              disabled={submitting}
              allowClear
            />
            <Button size="small" onClick={handleApplyMonths} disabled={!periodMonths || submitting}>
              {selectedTypeOption?.raw?.apply_period_label ?? 'Apply'}
            </Button>
          </div>
        ) : null}

        <div>
          <span className="mb-2 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
            PDF Document
          </span>
          <p className="mb-2 text-xs text-slate-500">
            {isEdit
              ? 'Upload a new PDF to replace the existing file (optional). Max 10MB.'
              : 'Upload a PDF document. Max 10MB.'}
          </p>
          <Upload
            accept=".pdf,application/pdf"
            maxCount={1}
            fileList={fileList}
            beforeUpload={() => false}
            onChange={({ fileList: nextList }) => setFileList(nextList.slice(-1))}
            onRemove={() => setFileList([])}
            disabled={submitting}
          >
            <Button icon={<UploadOutlined />} disabled={submitting}>
              Select PDF
            </Button>
          </Upload>
        </div>
      </Form>

      <div
        style={{
          display: 'flex',
          justifyContent: 'flex-end',
          gap: 8,
          marginTop: 16,
          paddingTop: 12,
          borderTop: '1px solid #f0f0f0',
        }}
      >
        <Space>
          <Button onClick={handleClose} disabled={submitting}>
            Close
          </Button>
          <Button
            type="primary"
            icon={<UploadOutlined />}
            loading={submitting}
            onClick={handleSubmit}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
          >
            {isEdit ? 'Update' : 'Upload'}
          </Button>
        </Space>
      </div>
    </Modal>
  );
};

export default SubmissionApprovalDocumentsUploadModal;
