import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Alert, Button, Card, Form, Radio, Segmented, Tooltip, Typography, Upload, message } from 'antd';
import { CloudUploadOutlined, MenuFoldOutlined, MenuUnfoldOutlined, PlusOutlined } from '@ant-design/icons';
import PageHeader from '../../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../../components/common/ApiState.jsx';
import Can from '../../components/common/Can.jsx';
import { useAuth } from '../../state/AuthContext.jsx';
import { api } from '../../services/estateApi.js';
import { getApiErrorMessage } from '../../utils/apiError.js';
import ClusterMapCanvas from './ClusterMapCanvas.jsx';
import ClusterMapToolbar from './ClusterMapToolbar.jsx';
import ClusterMapComponentPalette from './ClusterMapComponentPalette.jsx';
import ClusterMapObjectPanel from './ClusterMapObjectPanel.jsx';
import ClusterMapAddUnitModal from './ClusterMapAddUnitModal.jsx';
import ClusterMapLayerPanel from './ClusterMapLayerPanel.jsx';
import ClusterMapLegend from './ClusterMapLegend.jsx';
import ClusterMapVersionDrawer from './ClusterMapVersionDrawer.jsx';
import ClusterMapSearchBar from './ClusterMapSearchBar.jsx';
import ClusterMapLocationPicker from './ClusterMapLocationPicker.jsx';
import { useClusterMapHistory } from './useClusterMapHistory.js';
import { cleanObjectForSave, createUuid, defaultPointsFor, unitSizeFromArea } from './mapConstants.js';

const AUTOSAVE_DELAY_MS = 15000;

function MapSetupCard({ clusterId, onCreated }) {
  const [canvasType, setCanvasType] = useState('blank');
  const [location, setLocation] = useState(null);
  const [form] = Form.useForm();

  const create = useMutation({
    mutationFn: async (values) => {
      const payload = { canvas_type: canvasType };
      if (canvasType === 'geo') {
        payload.latitude = location.lat;
        payload.longitude = location.lng;
        payload.zoom = location.zoom;
      }
      const created = await api.clusterMaps.create(clusterId, payload);
      const mapId = created.data.id;
      if (canvasType === 'image' && values.image?.[0]?.originFileObj) {
        const formData = new FormData();
        formData.append('image', values.image[0].originFileObj);
        await api.clusterMaps.uploadBackground(mapId, formData);
      }
      return mapId;
    },
    onSuccess: () => {
      message.success('Peta cluster berhasil dibuat.');
      onCreated();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  return (
    <Card title="Buat Peta Cluster" style={{ maxWidth: 480 }}>
      <Typography.Paragraph type="secondary">
        Pilih jenis area kerja untuk membuat peta cluster ini.
      </Typography.Paragraph>
      <Radio.Group value={canvasType} onChange={(e) => setCanvasType(e.target.value)} style={{ marginBottom: 16 }}>
        <Radio.Button value="blank">Canvas Kosong</Radio.Button>
        <Radio.Button value="image">Gambar Denah</Radio.Button>
        <Radio.Button value="geo">Peta Lokasi</Radio.Button>
      </Radio.Group>
      {canvasType === 'geo' && (
        <div style={{ marginBottom: 16 }}>
          <Typography.Text strong style={{ display: 'block', marginBottom: 8 }}>Lokasi Cluster</Typography.Text>
          <ClusterMapLocationPicker onChange={setLocation} />
        </div>
      )}
      <Form form={form} layout="vertical" onFinish={(values) => create.mutate(values)}>
        {canvasType === 'image' && (
          <Form.Item
            label="Gambar Denah"
            name="image"
            valuePropName="fileList"
            getValueFromEvent={(event) => event?.fileList}
            rules={[{ required: true, message: 'Gambar denah wajib diunggah' }]}
          >
            <Upload.Dragger beforeUpload={() => false} maxCount={1} accept=".jpg,.jpeg,.png">
              <p className="ant-upload-drag-icon"><CloudUploadOutlined /></p>
              <p>Tarik gambar ke sini atau klik untuk memilih</p>
            </Upload.Dragger>
          </Form.Item>
        )}
        <Button
          type="primary"
          htmlType="submit"
          icon={<PlusOutlined />}
          loading={create.isPending}
          disabled={canvasType === 'geo' && !location}
        >
          Buat Peta
        </Button>
      </Form>
    </Card>
  );
}

export default function ClusterMapPage() {
  const { id: clusterId } = useParams();
  const { can } = useAuth();
  const canEdit = can('cluster-maps.edit');

  const mapQuery = useQuery({
    queryKey: ['cluster-map', clusterId],
    queryFn: () => api.clusterMaps.get(clusterId),
    staleTime: Infinity,
  });
  const componentTypesQuery = useQuery({
    queryKey: ['cluster-map-component-types'],
    queryFn: () => api.clusterMapComponentTypes.list(),
  });

  const [mode, setMode] = useState(canEdit ? 'editor' : 'view');
  const [availableUnits, setAvailableUnits] = useState([]);
  const [selectedIds, setSelectedIds] = useState([]);
  const [hiddenIds, setHiddenIds] = useState([]);
  const [dimmedIds, setDimmedIds] = useState([]);
  const [highlightedId, setHighlightedId] = useState(null);
  const [addUnitOpen, setAddUnitOpen] = useState(false);
  const [versionsOpen, setVersionsOpen] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [clipboard, setClipboard] = useState([]);
  const [layerPanelCollapsed, setLayerPanelCollapsed] = useState(false);

  const canvasRef = useRef(null);
  const containerRef = useRef(null);
  const hydratedMapIdRef = useRef(null);
  const history = useClusterMapHistory([]);

  const map = mapQuery.data?.data?.map;
  const cluster = mapQuery.data?.data?.cluster;
  const componentTypes = componentTypesQuery.data?.data || [];

  useEffect(() => {
    if (map && hydratedMapIdRef.current !== map.id) {
      hydratedMapIdRef.current = map.id;
      history.replace(map.objects || []);
      setAvailableUnits(mapQuery.data.data.available_units || []);
      setDirty(false);
    }
  }, [map, mapQuery.data, history]);

  useEffect(() => {
    function handleFullscreenChange() {
      setIsFullscreen(Boolean(document.fullscreenElement));
    }
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  const commitObjects = useCallback((updater) => {
    history.commit(updater);
    setDirty(true);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const saveMutation = useMutation({
    mutationFn: ({ silent: _silent, ...payload }) => api.clusterMaps.saveObjects(map.id, payload),
    onSuccess: (response, variables) => {
      history.replace(response.data.objects || []);
      setDirty(false);
      if (!variables.silent) message.success('Peta cluster berhasil disimpan.');
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const handleSave = useCallback((silent = false) => {
    if (!map) return;
    saveMutation.mutate({
      objects: history.objects.map(cleanObjectForSave),
      version_label: silent ? 'Autosave' : 'Disimpan manual',
      silent,
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [map, history.objects]);

  useEffect(() => {
    if (!dirty || mode !== 'editor' || !canEdit) return undefined;
    const timer = setTimeout(() => handleSave(true), AUTOSAVE_DELAY_MS);
    return () => clearTimeout(timer);
  }, [dirty, mode, canEdit, handleSave]);

  const restoreMutation = useMutation({
    mutationFn: (versionId) => api.clusterMaps.versions.restore(versionId),
    onSuccess: async () => {
      const refreshed = await mapQuery.refetch();
      history.replace(refreshed.data?.data?.map?.objects || []);
      setAvailableUnits(refreshed.data?.data?.available_units || []);
      setDirty(false);
      setVersionsOpen(false);
      message.success('Versi peta berhasil dipulihkan.');
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const objects = history.objects;
  const selectedObject = selectedIds.length === 1 ? objects.find((o) => o.id === selectedIds[0]) : null;
  const isLockedSelection = selectedIds.length > 0 && selectedIds.every((id) => objects.find((o) => o.id === id)?.is_locked);
  const nextLayerOrder = useMemo(() => (objects.length ? Math.max(...objects.map((o) => o.layer_order || 0)) + 1 : 1), [objects]);

  function handleAddUnit(unit) {
    const size = unitSizeFromArea(unit.land_area, map.scale_meters_per_pixel);
    const newObject = {
      id: createUuid(),
      object_category: 'unit',
      unit_id: unit.id,
      component_type_id: null,
      shape_type: 'rect',
      x: (map.canvas_width - size.width) / 2,
      y: (map.canvas_height - size.height) / 2,
      width: size.width,
      height: size.height,
      rotation: 0,
      points: null,
      fill_color: null,
      stroke_color: null,
      stroke_width: 1,
      opacity: 1,
      layer_order: nextLayerOrder,
      is_locked: false,
      is_color_auto: true,
      label_text: null,
      group_id: null,
      metadata: null,
      unit_detail: {
        id: unit.id, block: unit.block, lot_number: unit.lot_number,
        land_area: unit.land_area, building_area: unit.building_area,
        resident_name: null, occupancy_id: null, status_id: null, has_arrears: false, total_outstanding: 0,
      },
    };
    commitObjects((prev) => [...prev, newObject]);
    setAvailableUnits((prev) => prev.filter((u) => u.id !== unit.id));
    setSelectedIds([newObject.id]);
  }

  function handlePlaceComponent(componentType) {
    const isPolygonLike = ['trapezoid', 'polygon', 'line'].includes(componentType.default_shape_type);
    const width = 100;
    const height = 60;
    const newObject = {
      id: createUuid(),
      object_category: 'component',
      unit_id: null,
      component_type_id: componentType.id,
      shape_type: componentType.default_shape_type,
      x: map.canvas_width / 2 - width / 2,
      y: map.canvas_height / 2 - height / 2,
      width, height,
      rotation: 0,
      points: isPolygonLike ? defaultPointsFor(componentType.default_shape_type, width, height) : null,
      fill_color: componentType.fill_color,
      stroke_color: componentType.stroke_color,
      stroke_width: 1,
      opacity: 1,
      layer_order: nextLayerOrder,
      is_locked: false,
      is_color_auto: false,
      label_text: null,
      group_id: null,
      metadata: null,
      componentType,
    };
    commitObjects((prev) => [...prev, newObject]);
    setSelectedIds([newObject.id]);
  }

  function handleObjectPanelChange(patch) {
    if (!selectedObject) return;
    commitObjects((prev) => prev.map((o) => (o.id === selectedObject.id ? { ...o, ...patch } : o)));
  }

  function handleDeleteSelected() {
    const removed = objects.filter((o) => selectedIds.includes(o.id));
    removed.forEach((object) => {
      if (object.object_category === 'unit' && object.unit_detail) {
        setAvailableUnits((prev) => [...prev, {
          id: object.unit_id, block: object.unit_detail.block, lot_number: object.unit_detail.lot_number,
          land_area: object.unit_detail.land_area, building_area: object.unit_detail.building_area,
        }]);
      }
    });
    commitObjects((prev) => prev.filter((o) => !selectedIds.includes(o.id)));
    setSelectedIds([]);
  }

  function copyableSelection() {
    return objects.filter((o) => selectedIds.includes(o.id) && o.object_category !== 'unit');
  }

  function pasteObjects(sourceObjects) {
    if (!sourceObjects.length) return;
    const clones = sourceObjects.map((object) => ({ ...object, id: createUuid(), x: object.x + 20, y: object.y + 20 }));
    commitObjects((prev) => [...prev, ...clones]);
    setSelectedIds(clones.map((c) => c.id));
  }

  function handleCopy() {
    const copyable = copyableSelection();
    setClipboard(copyable);
    if (!copyable.length) message.info('Objek unit tidak dapat disalin (satu unit hanya bisa ditempatkan sekali).');
  }

  function handlePaste() {
    pasteObjects(clipboard);
  }

  function handleDuplicate() {
    const copyable = copyableSelection();
    setClipboard(copyable);
    pasteObjects(copyable);
  }

  function handleGroup() {
    const groupId = createUuid();
    commitObjects((prev) => prev.map((o) => (selectedIds.includes(o.id) ? { ...o, group_id: groupId } : o)));
  }

  function handleUngroup() {
    commitObjects((prev) => prev.map((o) => (selectedIds.includes(o.id) ? { ...o, group_id: null } : o)));
  }

  function handleLockToggle() {
    const next = !isLockedSelection;
    commitObjects((prev) => prev.map((o) => (selectedIds.includes(o.id) ? { ...o, is_locked: next } : o)));
  }

  function handleBringToFront() {
    const max = objects.length ? Math.max(...objects.map((o) => o.layer_order || 0)) : 0;
    commitObjects((prev) => prev.map((o) => (selectedIds.includes(o.id) ? { ...o, layer_order: max + 1 } : o)));
  }

  function handleSendToBack() {
    const min = objects.length ? Math.min(...objects.map((o) => o.layer_order || 0)) : 0;
    commitObjects((prev) => prev.map((o) => (selectedIds.includes(o.id) ? { ...o, layer_order: min - 1 } : o)));
  }

  function handleMoveLayer(id, direction) {
    const sorted = [...objects].sort((a, b) => (a.layer_order || 0) - (b.layer_order || 0));
    const index = sorted.findIndex((o) => o.id === id);
    const swapIndex = direction === 'up' ? index + 1 : index - 1;
    if (swapIndex < 0 || swapIndex >= sorted.length) return;
    const a = sorted[index];
    const b = sorted[swapIndex];
    commitObjects((prev) => prev.map((o) => {
      if (o.id === a.id) return { ...o, layer_order: b.layer_order };
      if (o.id === b.id) return { ...o, layer_order: a.layer_order };
      return o;
    }));
  }

  function handleToggleHidden(id) {
    setHiddenIds((prev) => (prev.includes(id) ? prev.filter((h) => h !== id) : [...prev, id]));
  }

  function handleFocusUnit(objectId) {
    setHighlightedId(objectId);
    setSelectedIds([objectId]);
    canvasRef.current?.focusObject(objectId);
  }

  function toggleFullscreen() {
    if (!document.fullscreenElement) {
      containerRef.current?.requestFullscreen?.();
    } else {
      document.exitFullscreen?.();
    }
  }

  if (mapQuery.isLoading) return <LoadingState rows={8} />;
  if (mapQuery.isError) return <ErrorState error={mapQuery.error} onRetry={mapQuery.refetch} />;

  return (
    <section>
      <PageHeader
        title={`Peta Cluster ${cluster?.name || ''}`}
        subtitle="Peta interaktif unit dan fasilitas cluster."
        breadcrumbs={[{ label: 'Cluster', to: '/clusters' }, { label: cluster?.name, to: `/clusters/${clusterId}` }, { label: 'Peta' }]}
        extra={canEdit && map ? (
          <Segmented
            value={mode}
            onChange={setMode}
            options={[{ label: 'Lihat', value: 'view' }, { label: 'Edit', value: 'editor' }]}
          />
        ) : null}
      />

      {!map && (
        <Can permission="cluster-maps.edit" fallback={<Alert type="info" showIcon message="Peta cluster ini belum tersedia." />}>
          <MapSetupCard clusterId={clusterId} onCreated={() => mapQuery.refetch()} />
        </Can>
      )}

      {map && (
        <div
          ref={containerRef}
          className="section-row"
          style={{ background: '#fff', border: '1px solid #f0f0f0', borderRadius: 8, overflow: 'hidden' }}
        >
          {mode === 'view' && (
            <ClusterMapSearchBar objects={objects} onFocusUnit={handleFocusUnit} onDimmedChange={setDimmedIds} />
          )}
          {mode === 'editor' && (
            <ClusterMapToolbar
              canUndo={history.canUndo}
              canRedo={history.canRedo}
              onUndo={history.undo}
              onRedo={history.redo}
              hasSelection={selectedIds.length > 0}
              hasMultiSelection={selectedIds.length > 1}
              hasClipboard={clipboard.length > 0}
              isLockedSelection={isLockedSelection}
              onCopy={handleCopy}
              onPaste={handlePaste}
              onDuplicate={handleDuplicate}
              onDelete={handleDeleteSelected}
              onGroup={handleGroup}
              onUngroup={handleUngroup}
              onLockToggle={handleLockToggle}
              onBringToFront={handleBringToFront}
              onSendToBack={handleSendToBack}
              onZoomIn={() => canvasRef.current?.zoomIn()}
              onZoomOut={() => canvasRef.current?.zoomOut()}
              onFit={() => canvasRef.current?.fitToScreen()}
              onResetView={() => canvasRef.current?.resetView()}
              isFullscreen={isFullscreen}
              onToggleFullscreen={toggleFullscreen}
              dirty={dirty}
              saving={saveMutation.isPending}
              onSave={() => handleSave(false)}
              onOpenVersions={() => setVersionsOpen(true)}
            />
          )}

          <div style={{ display: 'flex', height: 640, overflowX: 'auto' }}>
            {mode === 'editor' && (
              <>
                <ClusterMapComponentPalette
                  componentTypes={componentTypes}
                  onPlace={handlePlaceComponent}
                  onComponentTypesChange={() => componentTypesQuery.refetch()}
                  getContainer={() => containerRef.current}
                />
                {layerPanelCollapsed ? (
                  <div style={{ flexShrink: 0, width: 36, borderRight: '1px solid #f0f0f0', display: 'flex', justifyContent: 'center', paddingTop: 12 }}>
                    <Tooltip title="Tampilkan panel layer" placement="right">
                      <Button size="small" type="text" icon={<MenuUnfoldOutlined />} onClick={() => setLayerPanelCollapsed(false)} />
                    </Tooltip>
                  </div>
                ) : (
                  <div style={{ flexShrink: 0, width: 160, borderRight: '1px solid #f0f0f0', overflowY: 'auto' }}>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 8px 0' }}>
                      <Typography.Text strong style={{ fontSize: 12 }}>Layer</Typography.Text>
                      <Tooltip title="Sembunyikan panel">
                        <Button size="small" type="text" icon={<MenuFoldOutlined />} onClick={() => setLayerPanelCollapsed(true)} />
                      </Tooltip>
                    </div>
                    <Button block size="small" icon={<PlusOutlined />} style={{ margin: 8, width: 'calc(100% - 16px)' }} onClick={() => setAddUnitOpen(true)}>
                      Tambah Unit
                    </Button>
                    <ClusterMapLayerPanel
                      objects={objects}
                      selectedIds={selectedIds}
                      hiddenIds={hiddenIds}
                      onSelect={setSelectedIds}
                      onToggleHidden={handleToggleHidden}
                      onToggleLock={(id) => commitObjects((prev) => prev.map((o) => (o.id === id ? { ...o, is_locked: !o.is_locked } : o)))}
                      onMoveLayer={handleMoveLayer}
                      editable
                    />
                  </div>
                )}
              </>
            )}

            <div style={{ flex: 1, minWidth: 200, position: 'relative' }}>
              <ClusterMapCanvas
                ref={canvasRef}
                map={map}
                objects={objects}
                selectedIds={selectedIds}
                editable={mode === 'editor'}
                highlightedId={highlightedId}
                dimmedIds={dimmedIds}
                hiddenIds={hiddenIds}
                onSelectionChange={setSelectedIds}
                onObjectsChange={commitObjects}
              />
              <div style={{ position: 'absolute', bottom: 12, left: 12 }}>
                <ClusterMapLegend />
              </div>
            </div>

            {mode === 'editor' && (
              <ClusterMapObjectPanel
                object={selectedObject}
                editable={mode === 'editor'}
                onChange={handleObjectPanelChange}
                onDelete={handleDeleteSelected}
              />
            )}
          </div>
        </div>
      )}

      <ClusterMapAddUnitModal
        open={addUnitOpen}
        onClose={() => setAddUnitOpen(false)}
        availableUnits={availableUnits}
        onAdd={handleAddUnit}
        getContainer={() => containerRef.current}
      />

      {map && (
        <ClusterMapVersionDrawer
          open={versionsOpen}
          onClose={() => setVersionsOpen(false)}
          mapId={map.id}
          onRestore={(versionId) => restoreMutation.mutate(versionId)}
          restoring={restoreMutation.isPending}
          getContainer={() => containerRef.current}
        />
      )}
    </section>
  );
}
