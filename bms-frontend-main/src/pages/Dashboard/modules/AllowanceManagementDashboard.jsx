import DashboardInfoCard from '../components/DashboardInfoCard.jsx';

const AllowanceManagementDashboard = ({ stats, onViewOvertime }) => {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <DashboardInfoCard
        label="My pending overtime requests"
        actionLabel="View all →"
        onClick={onViewOvertime}
      >
        {stats?.pendingOvertimeRequests ?? 0}
      </DashboardInfoCard>

      <DashboardInfoCard label="My Total overtime records">
        {stats?.totalOvertimeRecords ?? 0}
      </DashboardInfoCard>

      <DashboardInfoCard label="My Overtime approved this month">
        {stats?.approvedOvertimeThisMonth ?? 0}
      </DashboardInfoCard>
    </div>
  );
};

export default AllowanceManagementDashboard;
