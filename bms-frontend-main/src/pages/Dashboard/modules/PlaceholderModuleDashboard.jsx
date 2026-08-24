import { Card } from 'antd';

const PlaceholderModuleDashboard = ({ icon, title, description }) => {
  return (
    <Card>
      <div className="text-center py-12">
        {icon}
        <h2 className="text-xl font-semibold text-gray-900 mb-2">{title}</h2>
        <p className="text-gray-500">{description}</p>
      </div>
    </Card>
  );
};

export default PlaceholderModuleDashboard;

