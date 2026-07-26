import { Card, Empty, List, Tag, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useRef } from 'react';
import L from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import dayjs from 'dayjs';
import PageHeader from '../../components/common/PageHeader.jsx';
import { api } from '../../services/estateApi.js';

const DEFAULT_CENTER = [-6.2088, 106.8456];
const collectorIcon = L.icon({
  iconRetinaUrl, iconUrl, shadowUrl,
  iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41],
});
const emergencyIcon = L.icon({
  iconRetinaUrl, iconUrl, shadowUrl,
  iconSize: [30, 49], iconAnchor: [15, 49], popupAnchor: [1, -34], shadowSize: [49, 49],
  className: 'supervisor-map-emergency-marker',
});

export default function SupervisorMapPage() {
  const containerRef = useRef(null);
  const mapRef = useRef(null);
  const markersRef = useRef([]);

  const map = useQuery({ queryKey: ['supervisor-map'], queryFn: () => api.supervisorMonitoring.map(), refetchInterval: 60000 });
  const locations = useMemo(() => map.data?.data?.collector_locations || [], [map.data]);
  const emergencies = useMemo(() => map.data?.data?.active_emergencies || [], [map.data]);

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

    const collectorMarkers = locations.map((location) => {
      const marker = L.marker([location.latitude, location.longitude], { icon: collectorIcon }).addTo(mapRef.current);
      marker.bindPopup(`<strong>${location.collector?.name || 'Kolektor'}</strong><br/>${dayjs(location.recorded_at).format('DD MMM YYYY HH:mm')}`);
      return marker;
    });

    const emergencyMarkers = emergencies
      .filter((emergency) => emergency.latitude && emergency.longitude)
      .map((emergency) => {
        const marker = L.marker([emergency.latitude, emergency.longitude], { icon: emergencyIcon }).addTo(mapRef.current);
        marker.bindPopup(`<strong>Darurat — Unit ${emergency.unit_id}</strong><br/>${emergency.note || ''}`);
        return marker;
      });

    markersRef.current = [...collectorMarkers, ...emergencyMarkers];

    const bounds = [
      ...locations.map((location) => [location.latitude, location.longitude]),
      ...emergencies.filter((e) => e.latitude && e.longitude).map((e) => [e.latitude, e.longitude]),
    ];
    if (bounds.length) {
      mapRef.current.fitBounds(bounds, { maxZoom: 15, padding: [30, 30] });
    }
  }, [locations, emergencies]);

  return (
    <section>
      <PageHeader
        title="Peta Pemantauan Wilayah"
        subtitle="Lokasi terakhir kolektor dan kondisi darurat aktif di wilayah yang Anda awasi."
        breadcrumbs={[{ label: 'Supervisor' }, { label: 'Peta Pemantauan' }]}
        onRefresh={map.refetch}
        loading={map.isFetching}
      />
      <Card style={{ marginBottom: 16 }}>
        <div ref={containerRef} style={{ height: 420, borderRadius: 8, overflow: 'hidden' }} />
      </Card>
      <Card title="Kolektor Aktif">
        {locations.length ? (
          <List
            dataSource={locations}
            renderItem={(location) => (
              <List.Item>
                <List.Item.Meta
                  title={location.collector?.name || 'Kolektor'}
                  description={`Terakhir dilaporkan ${dayjs(location.recorded_at).format('DD MMM YYYY HH:mm')}`}
                />
                <Tag>{Number(location.accuracy_meters || 0).toFixed(0)} m akurasi</Tag>
              </List.Item>
            )}
          />
        ) : (
          <Empty description="Belum ada laporan lokasi kolektor." />
        )}
      </Card>
      {emergencies.length ? (
        <Card title="Kondisi Darurat Aktif" style={{ marginTop: 16 }}>
          <List
            dataSource={emergencies}
            renderItem={(emergency) => (
              <List.Item>
                <List.Item.Meta title={`Unit ${emergency.unit_id}`} description={emergency.note || 'Tidak ada catatan.'} />
                <Tag color="red">Aktif</Tag>
              </List.Item>
            )}
          />
        </Card>
      ) : null}
      <Typography.Paragraph type="secondary" style={{ marginTop: 12 }}>
        Lokasi dikirim hanya selama aplikasi kolektor terbuka di perangkat mereka. Supervisor tidak mengirim lokasi sendiri.
      </Typography.Paragraph>
    </section>
  );
}
