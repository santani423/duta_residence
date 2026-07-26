import { Button, Card, Empty, List, Space, Tag, Typography } from 'antd';
import { EnvironmentOutlined } from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';
import L from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import PageHeader from '../../components/common/PageHeader.jsx';
import { api } from '../../services/estateApi.js';

const DEFAULT_CENTER = [-6.2088, 106.8456];
const markerIcon = L.icon({
  iconRetinaUrl, iconUrl, shadowUrl,
  iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41],
});

function openInMaps(unit) {
  const url = unit.latitude && unit.longitude
    ? `https://www.google.com/maps/search/?api=1&query=${unit.latitude},${unit.longitude}`
    : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${unit.cluster?.name || ''} Blok ${unit.block} No ${unit.lot_number}`)}`;
  window.open(url, '_blank', 'noopener');
}

export default function CollectorRouteMapPage() {
  const containerRef = useRef(null);
  const mapRef = useRef(null);
  const markersRef = useRef([]);

  const units = useQuery({ queryKey: ['units', 'mine', 'route'], queryFn: () => api.units.list({ per_page: 200 }) });
  const items = units.data?.data || [];
  const withCoords = items.filter((unit) => unit.latitude && unit.longitude);
  const withoutCoords = items.filter((unit) => !(unit.latitude && unit.longitude));

  useEffect(() => {
    if (!containerRef.current || mapRef.current) return;
    mapRef.current = L.map(containerRef.current).setView(DEFAULT_CENTER, 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(mapRef.current);
  }, []);

  useEffect(() => {
    if (!mapRef.current) return;
    markersRef.current.forEach((marker) => marker.remove());
    markersRef.current = withCoords.map((unit) => {
      const marker = L.marker([unit.latitude, unit.longitude], { icon: markerIcon }).addTo(mapRef.current);
      marker.bindPopup(`<strong>${unit.id}</strong><br/>${unit.resident?.name || ''}`);
      return marker;
    });
    if (withCoords.length) {
      mapRef.current.fitBounds(withCoords.map((unit) => [unit.latitude, unit.longitude]), { maxZoom: 15, padding: [30, 30] });
    }
  }, [withCoords]);

  return (
    <section>
      <PageHeader
        title="Rute Kunjungan"
        subtitle="Lokasi unit yang menjadi wilayah tugas Anda."
        breadcrumbs={[{ label: 'Rute Kunjungan' }]}
        onRefresh={units.refetch}
        loading={units.isFetching}
      />
      <Card style={{ marginBottom: 16 }}>
        <div ref={containerRef} style={{ height: 360, borderRadius: 8, overflow: 'hidden' }} />
      </Card>
      {withoutCoords.length ? (
        <Card title="Unit Tanpa Titik Koordinat">
          <Typography.Paragraph type="secondary">
            Unit berikut belum memiliki koordinat tersimpan. Gunakan tombol di bawah untuk membuka alamatnya di Google Maps.
          </Typography.Paragraph>
          <List
            dataSource={withoutCoords}
            renderItem={(unit) => (
              <List.Item
                actions={[<Button key="open" size="small" icon={<EnvironmentOutlined />} onClick={() => openInMaps(unit)}>Buka di Peta</Button>]}
              >
                <List.Item.Meta
                  title={<Space>{unit.id}<Tag>{unit.cluster?.name}</Tag></Space>}
                  description={`Blok ${unit.block} No ${unit.lot_number} — ${unit.resident?.name || ''}`}
                />
              </List.Item>
            )}
          />
        </Card>
      ) : null}
      {!items.length ? <Empty description="Belum ada unit yang ditugaskan kepada Anda." /> : null}
    </section>
  );
}
