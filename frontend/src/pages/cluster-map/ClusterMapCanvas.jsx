import { forwardRef, useEffect, useImperativeHandle, useMemo, useRef, useState } from 'react';
import { Circle, Image as KonvaImage, Layer, Line, Rect, RegularPolygon, Stage, Text, Transformer } from 'react-konva';
import { storageUrl } from '../../services/estateApi.js';
import { objectColor, POLYGON_LIKE_SHAPES } from './mapConstants.js';

const MIN_SCALE = 0.2;
const MAX_SCALE = 5;

function useBackgroundImage(path) {
  const [image, setImage] = useState(null);
  useEffect(() => {
    if (!path) return undefined;
    const img = new window.Image();
    img.crossOrigin = 'anonymous';
    img.src = storageUrl(path);
    img.onload = () => setImage(img);
    return () => { img.onload = null; };
  }, [path]);
  return path ? image : null;
}

function clampToCanvas(shape, map) {
  const width = map.canvas_width;
  const height = map.canvas_height;
  let { x, y } = shape;

  if (shape.shape_type === 'circle') {
    const r = (shape.width || 40) / 2;
    x = Math.min(Math.max(x, r), Math.max(r, width - r));
    y = Math.min(Math.max(y, r), Math.max(r, height - r));
  } else {
    const w = shape.width || 0;
    const h = shape.height || 0;
    x = Math.min(Math.max(x, 0), Math.max(0, width - w));
    y = Math.min(Math.max(y, 0), Math.max(0, height - h));
  }
  return { x, y };
}

function snap(value, gridSize, enabled) {
  if (!enabled) return value;
  return Math.round(value / gridSize) * gridSize;
}

function GridLayer({ map }) {
  const lines = useMemo(() => {
    if (!map.grid_enabled || !map.grid_visible) return [];
    const result = [];
    for (let x = 0; x <= map.canvas_width; x += map.grid_size) {
      result.push({ points: [x, 0, x, map.canvas_height], key: `v-${x}` });
    }
    for (let y = 0; y <= map.canvas_height; y += map.grid_size) {
      result.push({ points: [0, y, map.canvas_width, y], key: `h-${y}` });
    }
    return result;
  }, [map.grid_enabled, map.grid_visible, map.grid_size, map.canvas_width, map.canvas_height]);

  if (!lines.length) return null;

  return (
    <Layer listening={false}>
      <Rect x={0} y={0} width={map.canvas_width} height={map.canvas_height} fill="#ffffff" />
      {lines.map((line) => (
        <Line key={line.key} points={line.points} stroke="#f0f0f0" strokeWidth={1} />
      ))}
    </Layer>
  );
}

function ShapeNode({ shapeRef, object, isSelected, isDimmed, isHighlighted, editable, onSelect, onDragEnd, onTransformEnd, onDblClick }) {
  const colors = objectColor(object);
  const commonProps = {
    ref: shapeRef,
    x: object.x,
    y: object.y,
    rotation: object.rotation || 0,
    fill: colors.fill,
    stroke: isHighlighted ? '#faad14' : colors.stroke,
    strokeWidth: isHighlighted ? (object.stroke_width || 1) + 2 : (object.stroke_width || 1),
    opacity: isDimmed ? 0.25 : (object.opacity ?? 1),
    draggable: editable && !object.is_locked,
    onClick: (e) => { e.cancelBubble = true; onSelect(object.id, e); },
    onTap: (e) => { e.cancelBubble = true; onSelect(object.id, e); },
    onDragEnd: (e) => onDragEnd(object.id, { x: e.target.x(), y: e.target.y() }),
    onTransformEnd: (e) => onTransformEnd(object.id, e.target),
    onDblClick: (e) => onDblClick?.(object.id, e),
    perfectDrawEnabled: false,
  };

  if (object.shape_type === 'circle') {
    return <Circle {...commonProps} radius={(object.width || 40) / 2} />;
  }
  if (object.shape_type === 'triangle') {
    return <RegularPolygon {...commonProps} sides={3} radius={(object.width || 60) / 2} />;
  }
  if (object.shape_type === 'text') {
    return <Text {...commonProps} text={object.label_text || 'Label'} fontSize={16} fill={colors.stroke} stroke={undefined} />;
  }
  if (POLYGON_LIKE_SHAPES.includes(object.shape_type)) {
    return (
      <Line
        {...commonProps}
        points={object.points || [0, 0, 60, 0, 60, 40, 0, 40]}
        closed={object.shape_type !== 'line'}
        fill={object.shape_type === 'line' ? undefined : colors.fill}
        dash={isSelected ? [8, 4] : undefined}
      />
    );
  }
  // rect (default)
  return <Rect {...commonProps} width={object.width || 60} height={object.height || 90} cornerRadius={2} />;
}

const ClusterMapCanvas = forwardRef(function ClusterMapCanvas(
  { map, objects, selectedIds, editable, highlightedId, dimmedIds = [], hiddenIds = [], onSelectionChange, onObjectsChange },
  ref,
) {
  const containerRef = useRef(null);
  const stageRef = useRef(null);
  const transformerRef = useRef(null);
  const shapeRefs = useRef({});
  const [size, setSize] = useState({ width: 800, height: 500 });
  const [viewport, setViewport] = useState({ scale: 1, x: 0, y: 0 });
  const backgroundImage = useBackgroundImage(map.canvas_type === 'image' ? map.background_image_path : null);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return undefined;
    const observer = new ResizeObserver((entries) => {
      const entry = entries[0];
      if (entry) setSize({ width: entry.contentRect.width, height: Math.max(400, entry.contentRect.height) });
    });
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    if (!transformerRef.current) return;
    const nodes = selectedIds
      .map((id) => shapeRefs.current[id])
      .filter((node) => node && !POLYGON_LIKE_SHAPES.includes(objects.find((o) => o.id === node.attrs.__objectId)?.shape_type));
    transformerRef.current.nodes(nodes);
    transformerRef.current.getLayer()?.batchDraw();
  }, [selectedIds, objects]);

  useImperativeHandle(ref, () => ({
    zoomIn: () => setViewport((v) => ({ ...v, scale: Math.min(MAX_SCALE, v.scale * 1.2) })),
    zoomOut: () => setViewport((v) => ({ ...v, scale: Math.max(MIN_SCALE, v.scale / 1.2) })),
    resetView: () => setViewport({ scale: 1, x: 0, y: 0 }),
    fitToScreen: () => {
      const scaleX = size.width / map.canvas_width;
      const scaleY = size.height / map.canvas_height;
      const scale = Math.min(scaleX, scaleY) * 0.95;
      setViewport({ scale, x: (size.width - map.canvas_width * scale) / 2, y: (size.height - map.canvas_height * scale) / 2 });
    },
    focusObject: (id) => {
      const target = objects.find((o) => o.id === id);
      if (!target) return;
      const scale = Math.max(viewport.scale, 1);
      setViewport({
        scale,
        x: size.width / 2 - target.x * scale,
        y: size.height / 2 - target.y * scale,
      });
    },
  }), [objects, size, map.canvas_width, map.canvas_height, viewport.scale]);

  function handleWheel(e) {
    e.evt.preventDefault();
    const stage = stageRef.current;
    const oldScale = viewport.scale;
    const pointer = stage.getPointerPosition();
    const mousePointTo = {
      x: (pointer.x - viewport.x) / oldScale,
      y: (pointer.y - viewport.y) / oldScale,
    };
    const direction = e.evt.deltaY > 0 ? -1 : 1;
    const newScale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, direction > 0 ? oldScale * 1.1 : oldScale / 1.1));
    setViewport({
      scale: newScale,
      x: pointer.x - mousePointTo.x * newScale,
      y: pointer.y - mousePointTo.y * newScale,
    });
  }

  function updateOne(id, patch) {
    onObjectsChange((prev) => prev.map((o) => (o.id === id ? { ...o, ...patch } : o)));
  }

  function handleDragEnd(id, pos) {
    const target = objects.find((o) => o.id === id);
    if (!target) return;
    const snapped = { x: snap(pos.x, map.grid_size, map.snap_enabled), y: snap(pos.y, map.grid_size, map.snap_enabled) };
    const clamped = clampToCanvas({ ...target, ...snapped }, map);
    updateOne(id, clamped);
  }

  function handleTransformEnd(id, node) {
    const scaleX = node.scaleX();
    const scaleY = node.scaleY();
    node.scaleX(1);
    node.scaleY(1);
    const target = objects.find((o) => o.id === id);
    const patch = {
      x: node.x(),
      y: node.y(),
      rotation: node.rotation(),
      width: Math.max(5, (target.width || node.width()) * scaleX),
      height: Math.max(5, (target.height || node.height()) * scaleY),
    };
    updateOne(id, clampToCanvas({ ...target, ...patch }, map));
  }

  function handleAddVertex(objectId, konvaEvent) {
    const target = objects.find((o) => o.id === objectId);
    if (!target || !editable || !POLYGON_LIKE_SHAPES.includes(target.shape_type)) return;
    const stage = stageRef.current;
    const pointer = stage.getPointerPosition();
    const transform = stage.getAbsoluteTransform().copy().invert();
    const stagePoint = transform.point(pointer);
    const points = [...(target.points || []), stagePoint.x - target.x, stagePoint.y - target.y];
    updateOne(objectId, { points });
    konvaEvent.cancelBubble = true;
  }

  function handleVertexDrag(objectId, index, pos) {
    const target = objects.find((o) => o.id === objectId);
    if (!target) return;
    const points = [...(target.points || [])];
    points[index * 2] = pos.x;
    points[index * 2 + 1] = pos.y;
    updateOne(objectId, { points });
  }

  const selectedPolygon = selectedIds.length === 1
    ? objects.find((o) => o.id === selectedIds[0] && POLYGON_LIKE_SHAPES.includes(o.shape_type))
    : null;

  return (
    <div ref={containerRef} style={{ width: '100%', height: '100%', minHeight: 480, background: '#fafafa', overflow: 'hidden' }}>
      <Stage
        ref={stageRef}
        width={size.width}
        height={size.height}
        scaleX={viewport.scale}
        scaleY={viewport.scale}
        x={viewport.x}
        y={viewport.y}
        draggable={editable === false || selectedIds.length === 0}
        onDragEnd={(e) => {
          if (e.target === stageRef.current) setViewport((v) => ({ ...v, x: e.target.x(), y: e.target.y() }));
        }}
        onWheel={handleWheel}
        onClick={(e) => { if (e.target === stageRef.current) onSelectionChange([]); }}
      >
        <GridLayer map={map} />
        {map.canvas_type === 'image' && backgroundImage && (
          <Layer listening={false}>
            <KonvaImage
              image={backgroundImage}
              x={map.background_x || 0}
              y={map.background_y || 0}
              width={(map.background_width || backgroundImage.width) * (map.background_scale || 1)}
              height={(map.background_height || backgroundImage.height) * (map.background_scale || 1)}
              opacity={map.background_opacity ?? 1}
            />
          </Layer>
        )}
        <Layer>
          {[...objects].filter((object) => !hiddenIds.includes(object.id)).sort((a, b) => (a.layer_order || 0) - (b.layer_order || 0)).map((object) => (
            <ShapeNode
              key={object.id}
              object={object}
              editable={editable}
              isSelected={selectedIds.includes(object.id)}
              isDimmed={dimmedIds.includes(object.id)}
              isHighlighted={highlightedId === object.id}
              shapeRef={(node) => {
                if (node) { node.attrs.__objectId = object.id; shapeRefs.current[object.id] = node; } else delete shapeRefs.current[object.id];
              }}
              onSelect={(id, e) => {
                if (e.evt?.shiftKey) {
                  onSelectionChange(selectedIds.includes(id) ? selectedIds.filter((s) => s !== id) : [...selectedIds, id]);
                } else {
                  onSelectionChange([id]);
                }
              }}
              onDragEnd={handleDragEnd}
              onTransformEnd={handleTransformEnd}
              onDblClick={(id, e) => handleAddVertex(id, e)}
            />
          ))}
          {editable && <Transformer ref={transformerRef} rotateEnabled boundBoxFunc={(oldBox, newBox) => (newBox.width < 5 || newBox.height < 5 ? oldBox : newBox)} />}
          {editable && selectedPolygon && (selectedPolygon.points || []).map((_, index) => {
            if (index % 2 !== 0) return null;
            const px = selectedPolygon.x + selectedPolygon.points[index];
            const py = selectedPolygon.y + selectedPolygon.points[index + 1];
            return (
              <Circle
                key={index}
                x={px}
                y={py}
                radius={6}
                fill="#fff"
                stroke="#1677ff"
                strokeWidth={2}
                draggable
                onDragMove={(e) => {
                  e.cancelBubble = true;
                  handleVertexDrag(selectedPolygon.id, index / 2, {
                    x: e.target.x() - selectedPolygon.x,
                    y: e.target.y() - selectedPolygon.y,
                  });
                }}
              />
            );
          })}
        </Layer>
      </Stage>
    </div>
  );
});

export default ClusterMapCanvas;
