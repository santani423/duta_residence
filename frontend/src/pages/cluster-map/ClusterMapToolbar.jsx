import { Badge, Button, Divider, Space, Tooltip } from 'antd';
import {
  CopyOutlined, DeleteOutlined, ExpandOutlined, FullscreenExitOutlined, FullscreenOutlined,
  GroupOutlined, HistoryOutlined, LockOutlined, RedoOutlined, ReloadOutlined, SaveOutlined,
  SnippetsOutlined, UndoOutlined, UngroupOutlined, UnlockOutlined, VerticalAlignBottomOutlined,
  VerticalAlignTopOutlined, ZoomInOutlined, ZoomOutOutlined,
} from '@ant-design/icons';

export default function ClusterMapToolbar({
  canUndo, canRedo, onUndo, onRedo,
  hasSelection, hasClipboard, hasMultiSelection, isLockedSelection,
  onCopy, onPaste, onDuplicate, onDelete, onGroup, onUngroup, onLockToggle,
  onBringToFront, onSendToBack,
  onZoomIn, onZoomOut, onFit, onResetView,
  isFullscreen, onToggleFullscreen,
  dirty, saving, onSave, onOpenVersions,
}) {
  return (
    <Space wrap size={4} style={{ padding: '8px 12px', borderBottom: '1px solid #f0f0f0', background: '#fff' }}>
      <Tooltip title="Urungkan (Undo)"><Button size="small" icon={<UndoOutlined />} disabled={!canUndo} onClick={onUndo} /></Tooltip>
      <Tooltip title="Ulangi (Redo)"><Button size="small" icon={<RedoOutlined />} disabled={!canRedo} onClick={onRedo} /></Tooltip>
      <Divider type="vertical" />
      <Tooltip title="Salin"><Button size="small" icon={<CopyOutlined />} disabled={!hasSelection} onClick={onCopy} /></Tooltip>
      <Tooltip title="Tempel"><Button size="small" icon={<SnippetsOutlined />} disabled={!hasClipboard} onClick={onPaste} /></Tooltip>
      <Tooltip title="Duplikat"><Button size="small" icon={<CopyOutlined />} disabled={!hasSelection} onClick={onDuplicate}>Duplikat</Button></Tooltip>
      <Tooltip title="Hapus"><Button size="small" danger icon={<DeleteOutlined />} disabled={!hasSelection} onClick={onDelete} /></Tooltip>
      <Divider type="vertical" />
      <Tooltip title="Kelompokkan"><Button size="small" icon={<GroupOutlined />} disabled={!hasMultiSelection} onClick={onGroup} /></Tooltip>
      <Tooltip title="Pisahkan Kelompok"><Button size="small" icon={<UngroupOutlined />} disabled={!hasSelection} onClick={onUngroup} /></Tooltip>
      <Tooltip title={isLockedSelection ? 'Buka Kunci' : 'Kunci'}>
        <Button size="small" icon={isLockedSelection ? <UnlockOutlined /> : <LockOutlined />} disabled={!hasSelection} onClick={onLockToggle} />
      </Tooltip>
      <Divider type="vertical" />
      <Tooltip title="Bawa ke Depan"><Button size="small" icon={<VerticalAlignTopOutlined />} disabled={!hasSelection} onClick={onBringToFront} /></Tooltip>
      <Tooltip title="Bawa ke Belakang"><Button size="small" icon={<VerticalAlignBottomOutlined />} disabled={!hasSelection} onClick={onSendToBack} /></Tooltip>
      <Divider type="vertical" />
      <Tooltip title="Perbesar"><Button size="small" icon={<ZoomInOutlined />} onClick={onZoomIn} /></Tooltip>
      <Tooltip title="Perkecil"><Button size="small" icon={<ZoomOutOutlined />} onClick={onZoomOut} /></Tooltip>
      <Tooltip title="Sesuaikan ke Layar"><Button size="small" icon={<ExpandOutlined />} onClick={onFit} /></Tooltip>
      <Tooltip title="Reset Tampilan"><Button size="small" icon={<ReloadOutlined />} onClick={onResetView} /></Tooltip>
      <Tooltip title={isFullscreen ? 'Keluar Layar Penuh' : 'Layar Penuh'}>
        <Button size="small" icon={isFullscreen ? <FullscreenExitOutlined /> : <FullscreenOutlined />} onClick={onToggleFullscreen} />
      </Tooltip>
      <Divider type="vertical" />
      <Tooltip title="Riwayat Versi"><Button size="small" icon={<HistoryOutlined />} onClick={onOpenVersions} /></Tooltip>
      <Badge dot={dirty}>
        <Button size="small" type="primary" icon={<SaveOutlined />} loading={saving} onClick={onSave}>Simpan</Button>
      </Badge>
    </Space>
  );
}
