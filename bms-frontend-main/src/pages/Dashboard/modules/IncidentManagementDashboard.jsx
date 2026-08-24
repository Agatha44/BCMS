import { Button, Card, Col, Empty, Row, Statistic } from 'antd';
import { CheckCircleOutlined, ClockCircleOutlined, ExclamationCircleOutlined, PlusOutlined } from '@ant-design/icons';

const IncidentManagementDashboard = ({ stats, onReportIncident }) => {
  return (
    <>
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-4">
        <div className="min-w-0 flex-1">
          <h1 className="text-xl sm:text-2xl font-bold text-gray-900 truncate">Report Incident</h1>
          <p className="text-sm sm:text-base text-gray-500 mt-1">
            Track, manage and resolve incidents and issues efficiently
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          size="large"
          onClick={onReportIncident}
          className="btn-standard-primary"
          danger
        >
          Report Incident
        </Button>
      </div>

      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic title="Total Incidents" value={stats?.totalIncidents ?? 0} prefix={<ExclamationCircleOutlined />} valueStyle={{ color: '#962E32' }} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic title="Open Incidents" value={stats?.openIncidents ?? 0} prefix={<ClockCircleOutlined />} valueStyle={{ color: '#f59e0b' }} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic title="Pending Incidents" value={stats?.pendingIncidents ?? 0} prefix={<ClockCircleOutlined />} valueStyle={{ color: '#3b82f6' }} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic title="Resolved Incidents" value={stats?.resolvedIncidents ?? 0} prefix={<CheckCircleOutlined />} valueStyle={{ color: '#10b981' }} />
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col xs={24}>
          <Card title="Incident List">
            <Empty
              description={
                <div className="text-center py-8">
                  <ExclamationCircleOutlined style={{ fontSize: '48px', color: '#d9d9d9', marginBottom: '16px' }} />
                  <p className="text-gray-500 mb-2">No incidents found</p>
                  <p className="text-sm text-gray-400">Incident management features will be available soon</p>
                </div>
              }
            />
          </Card>
        </Col>
      </Row>
    </>
  );
};

export default IncidentManagementDashboard;

