import { Button, Drawer, Empty, List, Popconfirm, Typography } from 'antd';
import { HistoryOutlined } from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../services/estateApi.js';
import { formatDateTime } from '../../utils/format.js';

export default function ClusterMapVersionDrawer({ open, onClose, mapId, onRestore, restoring, getContainer }) {
  const versions = useQuery({
    queryKey: ['cluster-map-versions', mapId],
    queryFn: () => api.clusterMaps.versions.list(mapId),
    enabled: open && !!mapId,
  });

  const items = versions.data?.data || [];

  return (
    <Drawer title="Riwayat Versi Peta" open={open} onClose={onClose} width={380} getContainer={getContainer} zIndex={2000}>
      {items.length === 0
        ? <Empty description="Belum ada riwayat perubahan" />
        : (
          <List
            loading={versions.isLoading}
            dataSource={items}
            renderItem={(version) => (
              <List.Item
                actions={[
                  <Popconfirm
                    key="restore"
                    title="Pulihkan versi ini?"
                    description="Perubahan yang belum disimpan akan tergantikan oleh versi ini."
                    onConfirm={() => onRestore(version.id)}
                  >
                    <Button size="small" icon={<HistoryOutlined />} loading={restoring}>Pulihkan</Button>
                  </Popconfirm>,
                ]}
              >
                <List.Item.Meta
                  title={version.label || 'Perubahan disimpan'}
                  description={(
                    <>
                      <Typography.Text type="secondary">{formatDateTime(version.created_at)}</Typography.Text>
                      <br />
                      <Typography.Text type="secondary">oleh {version.creator?.name || '-'}</Typography.Text>
                    </>
                  )}
                />
              </List.Item>
            )}
          />
        )}
    </Drawer>
  );
}
