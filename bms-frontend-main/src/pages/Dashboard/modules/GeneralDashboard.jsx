import { Button, Card, Col, Row, Statistic, Tag } from 'antd';
import { CalendarOutlined, CheckCircleOutlined, ClockCircleOutlined, FileTextOutlined, TeamOutlined, UserOutlined } from '@ant-design/icons';

const GeneralDashboard = ({ stats, todayAttendance, recentActivities, onViewProfile, onViewAttendance, onViewOvertime, onManageRoles }) => {
  const timeInLabel = todayAttendance?.timeIn || '—';
  const timeOutLabel = todayAttendance?.timeOut || '—';

  const renderTodayTimeRow = () => (
    <div className="mt-2 flex flex-wrap gap-2">
      <Tag color="green">Time In: {timeInLabel}</Tag>
      <Tag color="red">Time Out: {timeOutLabel}</Tag>
    </div>
  );

  return (
    <>
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic title="My Profile" value={stats?.totalEmployees ?? 0} prefix={<UserOutlined />} valueStyle={{ color: '#962E32' }} />
            <Button type="link" className="p-0 mt-2" onClick={onViewProfile}>
              View Profile →
            </Button>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <div className="flex items-center gap-2 text-gray-500">
              <CalendarOutlined />
              <span className="text-sm">Today's Attendance</span>
            </div>
            {renderTodayTimeRow()}
            <Button type="link" className="p-0 mt-2" onClick={onViewAttendance}>
              View All →
            </Button>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="My Pending Overtime Requests"
              value={stats?.pendingOvertimeRequests ?? 0}
              prefix={<ClockCircleOutlined />}
              valueStyle={{ color: '#f59e0b' }}
            />
            <Button type="link" className="p-0 mt-2" onClick={onViewOvertime}>
              View All →
            </Button>
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic title="Overtime Records" value={stats?.totalOvertimeRecords ?? 0} prefix={<FileTextOutlined />} valueStyle={{ color: '#8b5cf6' }} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic title="My Approved This Month" value={stats?.approvedOvertimeThisMonth ?? 0} prefix={<CheckCircleOutlined />} valueStyle={{ color: '#10b981' }} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic title="Total Permissions" value={stats?.totalPermissions ?? 0} prefix={<CheckCircleOutlined />} valueStyle={{ color: '#ec4899' }} />
            <Button type="link" className="p-0 mt-2" onClick={onManageRoles}>
              View All →
            </Button>
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col xs={24} lg={12}>
          <Card title="Quick Actions" className="h-full">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
              <Button type="default" block size="large" icon={<UserOutlined />} onClick={onViewProfile} className="h-auto py-4">
                Add Employee
              </Button>
              <Button type="default" block size="large" icon={<ClockCircleOutlined />} onClick={onViewOvertime} className="h-auto py-4">
                New Overtime
              </Button>
              <Button type="default" block size="large" icon={<CalendarOutlined />} onClick={onViewAttendance} className="h-auto py-4">
                View Attendance
              </Button>
              <Button type="default" block size="large" icon={<TeamOutlined />} onClick={onManageRoles} className="h-auto py-4">
                Manage Roles
              </Button>
            </div>
          </Card>
        </Col>
        <Col xs={24} lg={12}>
          <Card title="Recent Activities" className="h-full">
            <div className="space-y-3">
              {Array.isArray(recentActivities) && recentActivities.length > 0 ? (
                recentActivities.map((activity, index) => (
                  <div
                    key={activity.id || `activity-${index}-${activity.created_at || activity.date || index}`}
                    className="flex items-start justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                  >
                    <div className="flex-1">
                      <p className="font-medium text-gray-900">{activity.employee_name || activity.full_name || 'Employee'}</p>
                      <p className="text-sm text-gray-500">Overtime Request - {activity.status || 'Pending'}</p>
                      <p className="text-xs text-gray-400 mt-1">
                        {activity.created_at ? new Date(activity.created_at).toLocaleString() : 'Recently'}
                      </p>
                    </div>
                    <div className="ml-4">
                      {activity.status?.toLowerCase().includes('approved') ? (
                        <CheckCircleOutlined className="text-green-500 text-lg" />
                      ) : (
                        <ClockCircleOutlined className="text-yellow-500 text-lg" />
                      )}
                    </div>
                  </div>
                ))
              ) : (
                <p className="text-gray-500 text-center py-4">No recent activities</p>
              )}
            </div>
          </Card>
        </Col>
      </Row>
    </>
  );
};

export default GeneralDashboard;

