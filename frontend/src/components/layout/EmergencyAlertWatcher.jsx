import { useEffect, useRef, useState } from 'react';
import { Button, Modal, Space, Tag, Typography, message } from 'antd';
import { WarningFilled } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../services/estateApi.js';
import { useAuth } from '../../state/AuthContext.jsx';
import { formatDateTime } from '../../utils/format.js';
import { getApiErrorMessage } from '../../utils/apiError.js';
import { startEmergencyAlarm, stopEmergencyAlarm, unlockEmergencyAudio } from '../../utils/emergencyAlarm.js';

const POLL_INTERVAL_MS = 12000;

export default function EmergencyAlertWatcher() {
  const { can } = useAuth();
  const enabled = can('emergency-alerts.view');
  const queryClient = useQueryClient();
  const [current, setCurrent] = useState(null);
  const seenIds = useRef(new Set());

  const query = useQuery({
    queryKey: ['emergency-alerts', 'active'],
    queryFn: () => api.emergencyAlerts.list({ status: 'active', per_page: 20 }),
    enabled,
    refetchInterval: POLL_INTERVAL_MS,
  });

  useEffect(() => {
    if (current) return;
    const rows = query.data?.data || [];
    const fresh = rows.find((row) => !seenIds.current.has(row.id));
    if (fresh) setCurrent(fresh);
  }, [query.data, current]);

  // AudioContext playback is blocked until a real user gesture happens -
  // unlock it on the first click/keypress anywhere so the siren can play
  // immediately once an actual emergency alert arrives, without needing the
  // admin to interact with the alert modal itself first.
  useEffect(() => {
    if (!enabled) return undefined;
    function unlock() {
      unlockEmergencyAudio();
      document.removeEventListener('click', unlock);
      document.removeEventListener('keydown', unlock);
    }
    document.addEventListener('click', unlock);
    document.addEventListener('keydown', unlock);
    return () => {
      document.removeEventListener('click', unlock);
      document.removeEventListener('keydown', unlock);
    };
  }, [enabled]);

  useEffect(() => {
    if (!current) return undefined;
    startEmergencyAlarm();
    return () => stopEmergencyAlarm();
  }, [current]);

  const acknowledge = useMutation({
    mutationFn: (id) => api.emergencyAlerts.acknowledge(id),
    onSuccess: (_response, id) => {
      seenIds.current.add(id);
      setCurrent(null);
      queryClient.invalidateQueries({ queryKey: ['emergency-alerts'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  if (!enabled || !current) return null;

  return (
    <Modal
      open
      closable={false}
      maskClosable={false}
      keyboard={false}
      title={(
        <Space>
          <WarningFilled style={{ color: '#cf1322' }} />
          <Typography.Text strong style={{ color: '#cf1322' }}>Sinyal Darurat</Typography.Text>
        </Space>
      )}
      footer={[
        <Button
          key="ack"
          type="primary"
          danger
          loading={acknowledge.isPending}
          onClick={() => acknowledge.mutate(current.id)}
        >
          Tandai Ditangani
        </Button>,
      ]}
    >
      <Space direction="vertical" size="small" style={{ width: '100%' }}>
        <Typography.Text>
          <Tag color="red">{current.unit?.id}</Tag>
          {current.unit?.cluster?.name} {current.unit?.block}/{current.unit?.lot_number}
        </Typography.Text>
        <Typography.Text>Dilaporkan oleh: {current.resident?.name || '-'}</Typography.Text>
        {current.note ? <Typography.Text>Catatan: {current.note}</Typography.Text> : null}
        <Typography.Text type="secondary">{formatDateTime(current.created_at)}</Typography.Text>
      </Space>
    </Modal>
  );
}
