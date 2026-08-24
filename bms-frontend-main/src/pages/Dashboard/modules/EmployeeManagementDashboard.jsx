import { Tag } from 'antd';
import DashboardInfoCard from '../components/DashboardInfoCard.jsx';

const EmployeeManagementDashboard = ({ stats, todayAttendance, onViewProfile, onViewAttendance }) => {
  const timeInLabel = todayAttendance?.timeIn || '—';
  const timeOutLabel = todayAttendance?.timeOut || '—';

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <DashboardInfoCard
        label="My profile"
        actionLabel="View profile →"
        onClick={onViewProfile}
      >
        {stats?.totalEmployees ?? 0}
      </DashboardInfoCard>

      <DashboardInfoCard
        label="Today's attendance"
        actionLabel="View all →"
        onClick={onViewAttendance}
      >
        <div className="flex flex-wrap gap-2">
          <Tag color="green" className="!m-0">
            Time in: {timeInLabel}
          </Tag>
          <Tag color="red" className="!m-0">
            Time out: {timeOutLabel}
          </Tag>
        </div>
      </DashboardInfoCard>
    </div>
  );
};

export default EmployeeManagementDashboard;
