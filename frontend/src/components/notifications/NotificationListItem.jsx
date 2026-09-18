import { Space, Tag, Typography } from 'antd';
import { formatDateTime } from '../../utils/format.js';

// One notification row, shared by the header bell, the full lists and the resident portal.
// The whole row is the click target (mouse, touch, Enter/Space) and its accessible name
// spells out the unread state, so the blue dot is never the only signal.
export default function NotificationListItem({ item, onOpen, actions, compact = false }) {
  const unread = item.read_status === 'unread';
  const title = item.title || item.type;

  function onKeyDown(event) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      onOpen(item);
    }
  }

  return (
    <div
      className={`notification-item${unread ? ' notification-item--unread' : ''}${compact ? ' notification-item--compact' : ''}`}
      role="link"
      tabIndex={0}
      aria-label={`${unread ? 'Belum dibaca. ' : ''}${title}. Buka detail notifikasi.`}
      onClick={() => onOpen(item)}
      onKeyDown={onKeyDown}
    >
      <span className="notification-dot" aria-hidden="true" />
      <div className="notification-item-body">
        <Space size={6} wrap>
          <Typography.Text strong={unread}>{title}</Typography.Text>
          {item.category_label ? <Tag variant="filled">{item.category_label}</Tag> : null}
        </Space>
        <div className="notification-item-message">{item.message}</div>
        <Typography.Text type="secondary" className="notification-item-time">{formatDateTime(item.created_at)}</Typography.Text>
      </div>
      {actions ? (
        // Row actions must not also trigger the row's own navigation.
        <div className="notification-item-actions" onClick={(event) => event.stopPropagation()} onKeyDown={(event) => event.stopPropagation()} role="presentation">
          {actions}
        </div>
      ) : null}
    </div>
  );
}
