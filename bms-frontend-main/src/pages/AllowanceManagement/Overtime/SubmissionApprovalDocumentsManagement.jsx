import { useCallback, useEffect, useState } from 'react';
import { App, Button, Card, Space, Switch, Tag } from 'antd';
import { EditOutlined, EyeOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { DataTable } from '../../../common/data/index.jsx';
import { extractArrayFromResponse, updatePaginationFromResponse } from '../../../common/utils/employeeUtils.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import { overtimeService } from '../../../services/overtimeService.js';
import SubmissionApprovalDocumentsUploadModal from './components/SubmissionApprovalDocumentsUploadModal.jsx';
import SubmissionApprovalDocumentsViewModal from './components/SubmissionApprovalDocumentsViewModal.jsx';
import {
  formatDocDate,
  formatReadinessText,
  getStatusTagProps,
  getSubmissionDocumentTypeLabel,
  extractSubmissionDocumentTypes,
  isActiveDocument,
  isExpiredDocument,
} from './submissionApprovalDocumentsUtils.js';
import '../../../styles/common.css';

const SubmissionApprovalDocumentsManagement = () => {
  const { message, modal } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [togglingId, setTogglingId] = useState(null);
  const [documents, setDocuments] = useState([]);
  const [documentTypes, setDocumentTypes] = useState([]);
  const [searchText, setSearchText] = useState('');
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 15,
    total: 0,
    showSizeChanger: true,
    showQuickJumper: false,
    showTotal: () => null,
    pageSizeOptions: ['10', '15', '20', '50', '100'],
  });
  const [uploadModal, setUploadModal] = useState({
    open: false,
    mode: 'create',
    record: null,
  });
  const [viewModal, setViewModal] = useState({
    open: false,
    record: null,
  });

  const fetchDocumentTypes = useCallback(async () => {
    try {
      const response = await overtimeService.getSubmissionDocumentTypes();
      if (response?.success) {
        setDocumentTypes(extractSubmissionDocumentTypes(response.data));
      } else {
        setDocumentTypes([]);
      }
    } catch {
      setDocumentTypes([]);
    }
  }, []);

  const fetchDocuments = useCallback(
    async (page = 1, pageSize = 15, search = '') => {
      setLoading(true);
      try {
        const params = {
          page,
          per_page: pageSize,
        };
        if (search?.trim()) params.document_name = search.trim();

        const response = await overtimeService.listSubmissionDocuments(params);
        if (response?.success && response.data) {
          const { items, pagination: paginationData } = extractArrayFromResponse(response.data);
          const totalCount = response.data.count || response.data.total || paginationData?.total || items.length;
          setDocuments(items);
          setPagination((prev) => ({
            ...prev,
            ...updatePaginationFromResponse(paginationData, page, pageSize, totalCount),
          }));
        } else {
          message.error(response?.message || 'Failed to fetch submission documents');
          setDocuments([]);
          setPagination((prev) => ({ ...prev, total: 0 }));
        }
      } catch (error) {
        message.error(error?.message || 'Failed to fetch submission documents');
        setDocuments([]);
        setPagination((prev) => ({ ...prev, total: 0 }));
      } finally {
        setLoading(false);
      }
    },
    [message]
  );

  useEffect(() => {
    fetchDocumentTypes();
  }, [fetchDocumentTypes]);

  useEffect(() => {
    fetchDocuments(1, pagination.pageSize);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleSearchChange = (value) => {
    setSearchText(value);
    setPagination((prev) => ({ ...prev, current: 1 }));
    fetchDocuments(1, pagination.pageSize, value);
  };

  const openViewModal = (record) => {
    setViewModal({ open: true, record });
  };

  const closeViewModal = () => {
    setViewModal({ open: false, record: null });
  };

  const openUploadModal = (mode, record = null) => {
    setUploadModal({ open: true, mode, record });
  };

  const closeUploadModal = () => {
    setUploadModal({ open: false, mode: 'create', record: null });
  };

  const getTypeLabel = (value, record) =>
    record?.document_type_name || getSubmissionDocumentTypeLabel(value, record) || value || '';

  const handleUploadSuccess = (response) => {
    const successMessage = formatReadinessText(response?.message);
    if (successMessage) message.success(successMessage);
    fetchDocuments(pagination.current, pagination.pageSize, searchText);
  };

  const handleToggleStatus = (record) => {
    if (isExpiredDocument(record)) {
      message.warning(formatReadinessText(record?.status_message) || 'Expired documents cannot be toggled.');
      return;
    }

    const isActive = isActiveDocument(record);
    const action = isActive ? 'deactivate' : 'activate';
    const documentLabel =
      record.document_name ||
      record.file_name ||
      getTypeLabel(record.document_type, record) ||
      'this document';

    modal.confirm({
      title: `Are you sure you want to ${action} this document?`,
      content: `This will ${action} "${documentLabel}".`,
      okText: `Yes, ${action.charAt(0).toUpperCase()}${action.slice(1)}`,
      cancelText: 'Close',
      onOk: async () => {
        setTogglingId(record.id);
        try {
          const response = await overtimeService.toggleSubmissionDocumentStatus(record.id);
          if (!response?.success) {
            message.error(formatReadinessText(response?.message) || 'Failed to update document status');
            return;
          }
          const successMessage = formatReadinessText(response?.message);
          if (successMessage) message.success(successMessage);
          fetchDocuments(pagination.current, pagination.pageSize, searchText);
        } catch (error) {
          message.error(error?.message || 'Failed to update document status');
        } finally {
          setTogglingId(null);
        }
      },
    });
  };

  const columns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 70,
      align: 'center',
      render: (_, __, index) => {
        const current = pagination.current || 1;
        const pageSize = pagination.pageSize || 15;
        return (current - 1) * pageSize + index + 1;
      },
    },
    {
      title: 'Document Type',
      dataIndex: 'document_type',
      key: 'document_type',
      searchable: true,
      width: 180,
      render: (value, record) => getTypeLabel(record.document_type, record) || <span className="text-slate-400">N/A</span>,
    },
    {
      title: 'Document Name',
      dataIndex: 'document_name',
      key: 'document_name',
      searchable: true,
      width: 200,
      render: (value, record) => value || record.file_name || <span className="text-slate-400">N/A</span>,
    },
    {
      title: 'Period',
      key: 'period',
      width: 220,
      render: (_, record) => {
        const start = formatDocDate(record.period_start);
        const end = formatDocDate(record.period_end);
        if (!start && !end) return <span className="text-slate-400">N/A</span>;
        return `${start || 'N/A'} – ${end || 'N/A'}`;
      },
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 130,
      align: 'center',
      render: (_, record) => {
        if (isExpiredDocument(record)) {
          const { color, label } = getStatusTagProps(record);
          return <Tag color={color}>{label}</Tag>;
        }

        return (
          <Switch
            checked={isActiveDocument(record)}
            loading={togglingId === record.id}
            disabled={togglingId === record.id}
            onChange={() => handleToggleStatus(record)}
            checkedChildren="Active"
            unCheckedChildren="Inactive"
            size="small"
          />
        );
      },
    },
    {
      title: 'Uploaded',
      key: 'uploaded',
      width: 180,
      render: (_, record) => {
        const at = record.uploaded_at || record.created_at;
        return at ? dayjs(at).format('DD-MMM-YYYY HH:mm') : <span className="text-slate-400">N/A</span>;
      },
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 160,
      fixed: 'right',
      align: 'center',
      render: (_, record) => {
        const canEdit = isActiveDocument(record);

        return (
          <Space size="small">
            <Button
              type="primary"
              size="small"
              icon={<EyeOutlined />}
              onClick={() => openViewModal(record)}
              className="btn-standard-primary"
            >
              View
            </Button>
            {canEdit ? (
              <Button
                type="primary"
                size="small"
                icon={<EditOutlined />}
                onClick={() => openUploadModal('edit', record)}
                className="btn-standard-primary"
              >
                Edit
              </Button>
            ) : null}
          </Space>
        );
      },
    },
  ];

  return (
    <div className="submission-documents-management">
      <Card>
        <div className="relative">
          {loading ? (
            <div
              className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
              style={{ top: '4.75rem' }}
            >
              <CollectionLoader />
            </div>
          ) : null}
          <DataTable
            columns={columns}
            data={documents}
            loading={false}
            pagination={{
              ...pagination,
              onChange: (page, pageSize) => {
                setPagination((prev) => ({ ...prev, current: page, pageSize }));
                fetchDocuments(page, pageSize, searchText);
              },
            }}
            pageSize={pagination.pageSize}
            showSearch
            showRefresh={false}
            onSearchChange={handleSearchChange}
            searchPlaceholder="Search submission documents..."
            rowKey={(record) => record.id}
            scroll={{ x: 1100 }}
            bordered
            rightAction={
              <Button
                type="primary"
                icon={<PlusOutlined />}
                onClick={() => openUploadModal('create')}
                size="large"
                className="btn-standard-primary"
              >
                Upload Document
              </Button>
            }
          />
        </div>
      </Card>

      <SubmissionApprovalDocumentsUploadModal
        open={uploadModal.open}
        mode={uploadModal.mode}
        documentRecord={uploadModal.record}
        documentTypes={documentTypes}
        onClose={closeUploadModal}
        onSuccess={handleUploadSuccess}
      />

      <SubmissionApprovalDocumentsViewModal
        open={viewModal.open}
        record={viewModal.record}
        onClose={closeViewModal}
      />
    </div>
  );
};

export default SubmissionApprovalDocumentsManagement;
