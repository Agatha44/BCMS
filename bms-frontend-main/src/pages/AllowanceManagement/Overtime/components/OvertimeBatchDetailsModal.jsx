import { Button, Modal, Tabs, Tag } from 'antd';
import { SendOutlined } from '@ant-design/icons';
import PropTypes from 'prop-types';
import dayjs from 'dayjs';
import DataTable from '../../../../common/data/DataTable.jsx';
import { ermsOverallTag } from '../../../../components/PayrollManagement/payrollErmsUtils.js';
import OvertimeBatchDocumentsTab from './OvertimeBatchDocumentsTab.jsx';

const formatNumber = (amount) =>
  new Intl.NumberFormat('en-TZ', { minimumFractionDigits: 0 }).format(amount || 0);

const formatBatchDate = (date) => {
  if (!date) return 'N/A';
  return dayjs(date).format('DD MMM YYYY, HH:mm');
};

const batchStatusTag = (status) => {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'approved' || normalized === 'submitted') {
    return { color: 'green', text: status ? String(status).charAt(0).toUpperCase() + String(status).slice(1) : 'Pending' };
  }
  if (normalized === 'pending') {
    return { color: 'gold', text: 'Pending' };
  }
  return { color: 'blue', text: status ? String(status).toUpperCase() : 'PENDING' };
};

const InfoCard = ({ label, children }) => (
  <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
    <span className="block text-xs font-medium text-slate-500">{label}</span>
    <div className="mt-1.5 text-sm font-semibold text-black">{children}</div>
  </div>
);

const OvertimeBatchDetailsModal = ({
  open,
  onClose,
  batchData,
  activeTab,
  onTabChange,
  loading,
  postingErms,
  repostingErms,
  showErmsDetails,
  canShowPostButton,
  canShowRepostButton,
  canSubmitToEoffice,
  submissionReadiness,
  onSubmit,
  onPostErms,
  onRepostErms,
  onDocumentsUpdated,
}) => {
  const batch = batchData?.batch;
  const statusTag = batchStatusTag(batch?.status);
  const ermsTag = ermsOverallTag(batch?.erms_status);
  const showSubmitButton =
    batchData &&
    batch?.status !== 'submitted' &&
    batch?.status !== 'approved';

  const recordColumns = [
    {
      title: '#',
      key: 'serialNumber',
      width: 60,
      align: 'center',
      render: (_, __, index) => index + 1,
    },
    {
      title: 'Employee name',
      dataIndex: 'employeeName',
      key: 'employeeName',
      width: 220,
      render: (text) => text || 'N/A',
    },
    {
      title: 'PF number',
      dataIndex: 'pfNumber',
      key: 'pfNumber',
      width: 130,
      render: (text) => text || 'N/A',
    },
    {
      title: 'Date',
      dataIndex: 'date',
      key: 'date',
      width: 160,
      render: (text) => text || 'N/A',
    },
    {
      title: 'OT hours',
      dataIndex: 'hours',
      key: 'hours',
      width: 110,
      align: 'center',
      render: (hours) => `${Number(hours || 0).toFixed(2)} hrs`,
    },
    {
      title: 'Amount',
      dataIndex: 'amount',
      key: 'amount',
      width: 150,
      align: 'right',
      render: (amount) => `TSh ${formatNumber(amount)}`,
    },
  ];

  const detailsTab = batchData ? (
    <div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <InfoCard label="Batch number">{batchData.batchNumber}</InfoCard>
        <InfoCard label="Status">
          <Tag color={statusTag.color} className="!m-0">
            {statusTag.text}
          </Tag>
        </InfoCard>
        {showErmsDetails ? (
          <InfoCard label="ERMS status">
            <Tag color={ermsTag.color} className="!m-0">
              {ermsTag.text}
            </Tag>
          </InfoCard>
        ) : null}
        <InfoCard label="Created at">{formatBatchDate(batch?.created_at)}</InfoCard>
        <InfoCard label="Total records">
          {batch?.total_requests != null ? batch.total_requests : '—'}
        </InfoCard>
        {showErmsDetails ? (
          <InfoCard label="Payment reference">
            {batch?.payment_reference ? (
              batch.payment_reference
            ) : (
              <span className="font-normal text-slate-400">N/A</span>
            )}
          </InfoCard>
        ) : null}
      </div>

      <div className="mt-6">
        <h4 className="mb-3 border-b border-slate-200 pb-1.5 text-sm font-semibold text-slate-800">
          Overtime records in batch
        </h4>
        <DataTable
          columns={recordColumns}
          data={batchData.processedRecords || []}
          loading={loading}
          pagination={false}
          showSearch={false}
          showRefresh={false}
          bordered
          rowKey={(record) =>
            record?.id ?? `${record?.pfNumber ?? 'pf'}-${record?.date ?? 'date'}-${record?.hours ?? '0'}`
          }
          locale={{ emptyText: 'No records yet' }}
        />
        <div className="mt-4 text-right">
          <span className="text-sm font-semibold text-black">
            Total batch amount:{' '}
            <span className="text-[#1890ff]">
              TSh {formatNumber(batchData.batchTotalAmount)}
            </span>
          </span>
        </div>
      </div>
    </div>
  ) : null;

  const tabItems = [
    {
      key: 'details',
      label: 'Overtime details',
      children: detailsTab,
    },
    {
      key: 'documents',
      label: 'Overtime documents',
      children: batchData ? (
        <OvertimeBatchDocumentsTab
          batchNumber={batchData.batchNumber}
          submissionReadiness={submissionReadiness}
          active={activeTab === 'documents'}
          onDocumentsUpdated={onDocumentsUpdated}
        />
      ) : null,
    },
  ];

  return (
    <Modal
      title={`Batch Details - ${batchData?.batchNumber || ''}`}
      open={open}
      onCancel={onClose}
      width={1200}
      centered
      destroyOnHidden
      footer={[
        showSubmitButton ? (
          <Button
            key="submit"
            type="primary"
            icon={<SendOutlined />}
            onClick={onSubmit}
            loading={loading}
            disabled={!canSubmitToEoffice}
          >
            Submit
          </Button>
        ) : null,
        showErmsDetails && canShowPostButton ? (
          <Button
            key="post-erms"
            type="primary"
            icon={<SendOutlined />}
            onClick={onPostErms}
            loading={postingErms}
          >
            Post to ERMS
          </Button>
        ) : null,
        showErmsDetails && canShowRepostButton ? (
          <Button key="repost-erms" type="primary" onClick={onRepostErms} loading={repostingErms}>
            Repost to ERMS
          </Button>
        ) : null,
        <Button key="close" onClick={onClose}>
          Close
        </Button>,
      ].filter(Boolean)}
    >
      {batchData ? (
        <Tabs activeKey={activeTab} onChange={onTabChange} items={tabItems} />
      ) : null}
    </Modal>
  );
};

OvertimeBatchDetailsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  batchData: PropTypes.shape({
    batchNumber: PropTypes.string,
    batchTotalAmount: PropTypes.number,
    processedRecords: PropTypes.array,
    batch: PropTypes.object,
  }),
  activeTab: PropTypes.string.isRequired,
  onTabChange: PropTypes.func.isRequired,
  loading: PropTypes.bool,
  postingErms: PropTypes.bool,
  repostingErms: PropTypes.bool,
  showErmsDetails: PropTypes.bool,
  canShowPostButton: PropTypes.bool,
  canShowRepostButton: PropTypes.bool,
  canSubmitToEoffice: PropTypes.bool,
  submissionReadiness: PropTypes.object,
  onSubmit: PropTypes.func.isRequired,
  onPostErms: PropTypes.func.isRequired,
  onRepostErms: PropTypes.func.isRequired,
  onDocumentsUpdated: PropTypes.func,
};

export default OvertimeBatchDetailsModal;
