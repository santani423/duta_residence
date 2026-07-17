import { Card, Space, Typography } from 'antd';
import { STATUS_COLORS } from './mapConstants.js';

export default function ClusterMapLegend() {
  return (
    <Card size="small" title="Legenda" style={{ width: 220 }}>
      <Space direction="vertical" size={6}>
        {Object.entries(STATUS_COLORS).map(([key, value]) => (
          <Space key={key} size={8}>
            <span style={{
              display: 'inline-block', width: 14, height: 14, borderRadius: 3,
              background: value.fill, border: `1px solid ${value.stroke}`,
            }}
            />
            <Typography.Text style={{ fontSize: 12 }}>{value.label}</Typography.Text>
          </Space>
        ))}
      </Space>
    </Card>
  );
}
