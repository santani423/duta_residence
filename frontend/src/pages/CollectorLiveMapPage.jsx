import { Card, Empty, List, Tag, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useRef } from 'react';
import L from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import { api } from '../services/estateApi.js';

const DEFAULT_CENTER = [-6.2088, 106.8456];
const markerIcon = L.icon({
  iconRetinaUrl, iconUrl, shadowUrl,
  iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41],
});

export default function CollectorLiveMapPage() {
  const containerRef = useRef(null);
  const mapRef = useRef(null);
  const markersRef = useRef([]);

  const locations = useQuery({
    queryKey: ['collector-locations', 'latest'],
    queryFn: () => api.collectorLocations.latest(),
    refetchInterval: 60000,
  });
  const items = useMemo(() => locations.data?.data || [], [locations.data]);

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
    markersRef.current = items.map((location) => {
      const marker = L.marker([location.latitude, location.longitude], { icon: markerIcon }).addTo(mapRef.current);
      marker.bindPopup(`<strong>${location.collector?.name || 'Kolektor'}</strong><br/>${dayjs(location.recorded_at).format('DD MMM YYYY HH:mm')}`);
      return marker;
    });
    if (items.length) {
      mapRef.current.fitBounds(items.map((location) => [location.latitude, location.longitude]), { maxZoom: 15, padding: [30, 30] });
    }
  }, [items]);

  return (
    <section>
      <PageHeader
        title="Lokasi Real-Time Kolektor"
        subtitle="Pantau posisi terakhir setiap kolektor yang sedang aktif di lapangan (diperbarui saat aplikasi kolektor terbuka)."
        breadcrumbs={[{ label: 'Manajemen Kolektor' }, { label: 'Lokasi Real-Time' }]}
        onRefresh={locations.refetch}
        loading={locations.isFetching}
      />
      <Card style={{ marginBottom: 16 }}>
        <div ref={containerRef} style={{ height: 420, borderRadius: 8, overflow: 'hidden' }} />
      </Card>
      <Card title="Kolektor Aktif">
        {items.length ? (
          <List
            dataSource={items}
            renderItem={(location) => (
              <List.Item>
                <List.Item.Meta
                  title={location.collector?.name || 'Kolektor'}
                  description={`Terakhir dilaporkan ${dayjs(location.recorded_at).fromNow?.() || dayjs(location.recorded_at).format('DD MMM YYYY HH:mm')}`}
                />
                <Tag>{Number(location.accuracy_meters || 0).toFixed(0)} m akurasi</Tag>
              </List.Item>
            )}
          />
        ) : (
          <Empty description="Belum ada laporan lokasi kolektor." />
        )}
      </Card>
      <Typography.Paragraph type="secondary" style={{ marginTop: 12 }}>
        Lokasi dikirim hanya selama aplikasi kolektor terbuka di perangkat mereka (bukan pelacakan latar belakang berkelanjutan).
      </Typography.Paragraph>
    </section>
  );
}
