import { Card, Empty, Tag } from 'antd';
import PropTypes from 'prop-types';

const DEFAULT_TAG_CLASS = '!m-0 px-2.5 py-0.5 text-xs font-medium capitalize';

const MinutesPanel = ({
  title = 'Minutes',
  items = [],
  emptyText = 'No minutes available',
  footer = null,
  tagClassName = DEFAULT_TAG_CLASS,
}) => {
  return (
    <div className="flex h-full min-w-0 flex-col overflow-hidden rounded">
      <div className="border-b bg-gray-50 px-3 py-2 font-medium">{title}</div>

      <div className="min-h-0 flex-1 space-y-2 overflow-auto p-3">
        {items.length === 0 ? (
          <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={emptyText} />
        ) : (
          items.map((it, idx) => {
            const when = it?.occurredAt ? new Date(it.occurredAt).toLocaleString() : '—';
            const statusLabel = it?.status || (it?.actor || it?.occurredAt ? 'Actioned' : 'Pending');
            const statusColor = it?.statusColor || (it?.actor || it?.occurredAt ? 'green' : 'default');

            return (
              <Card key={it?.key ?? `${idx}`} size="small" className="border" styles={{ body: { padding: 10 } }}>
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="text-sm font-medium text-gray-900">
                      {it?.step != null ? <span className="mr-2 text-xs text-gray-500">{it.step}</span> : null}
                      {it?.title || '—'}
                    </div>
                    <div className="truncate text-xs text-gray-600">{it?.actor || '—'}</div>
                    {it?.meta ? <div className="truncate text-xs text-gray-500">{it.meta}</div> : null}
                    <div className="text-xs text-gray-500">{when}</div>
                  </div>
                  <Tag color={statusColor} className={tagClassName}>
                    {statusLabel}
                  </Tag>
                </div>
              </Card>
            );
          })
        )}
      </div>

      {footer ? <div className="shrink-0 border-t bg-white p-3">{footer}</div> : null}
    </div>
  );
};

MinutesPanel.propTypes = {
  title: PropTypes.string,
  emptyText: PropTypes.string,
  footer: PropTypes.node,
  tagClassName: PropTypes.string,
  items: PropTypes.arrayOf(
    PropTypes.shape({
      key: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
      step: PropTypes.string,
      title: PropTypes.string,
      actor: PropTypes.string,
      meta: PropTypes.string,
      occurredAt: PropTypes.oneOfType([PropTypes.string, PropTypes.instanceOf(Date)]),
      status: PropTypes.string,
      statusColor: PropTypes.string,
    })
  ),
};

export default MinutesPanel;

