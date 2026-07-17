import { useEffect, useRef, useState } from 'react';
import { Input, Typography, message } from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import L from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';

const DEFAULT_CENTER = [-6.2088, 106.8456]; // Jakarta, Indonesia
const DEFAULT_ZOOM = 13;

const markerIcon = L.icon({
  iconRetinaUrl, iconUrl, shadowUrl,
  iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41],
});

export default function ClusterMapLocationPicker({ onChange }) {
  const containerRef = useRef(null);
  const mapRef = useRef(null);
  const markerRef = useRef(null);
  const [searching, setSearching] = useState(false);
  const [query, setQuery] = useState('');

  useEffect(() => {
    if (!containerRef.current || mapRef.current) return undefined;

    const map = L.map(containerRef.current).setView(DEFAULT_CENTER, DEFAULT_ZOOM);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(map);

    const marker = L.marker(DEFAULT_CENTER, { icon: markerIcon, draggable: true }).addTo(map);

    function emitChange() {
      const { lat, lng } = marker.getLatLng();
      onChange({ lat, lng, zoom: map.getZoom() });
    }

    marker.on('dragend', emitChange);
    map.on('click', (event) => {
      marker.setLatLng(event.latlng);
      emitChange();
    });
    map.on('zoomend', emitChange);

    mapRef.current = map;
    markerRef.current = marker;
    emitChange();

    return () => {
      map.remove();
      mapRef.current = null;
      markerRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleSearch(value) {
    const trimmed = value.trim();
    if (!trimmed) return;
    setSearching(true);
    try {
      const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(trimmed)}`);
      const results = await response.json();
      if (!results.length) {
        message.warning('Lokasi tidak ditemukan. Coba kata kunci lain atau klik langsung pada peta.');
        return;
      }
      const { lat, lon } = results[0];
      const latLng = [parseFloat(lat), parseFloat(lon)];
      mapRef.current?.setView(latLng, 17);
      markerRef.current?.setLatLng(latLng);
      onChange({ lat: latLng[0], lng: latLng[1], zoom: 17 });
    } catch {
      message.error('Gagal mencari lokasi. Periksa koneksi internet.');
    } finally {
      setSearching(false);
    }
  }

  return (
    <div>
      <Input.Search
        placeholder="Cari alamat, misal: Jl. Merdeka No. 1, Bandung"
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        onSearch={handleSearch}
        enterButton={<SearchOutlined />}
        loading={searching}
        style={{ marginBottom: 8 }}
        allowClear
      />
      <div ref={containerRef} style={{ height: 320, borderRadius: 8, overflow: 'hidden' }} />
      <Typography.Text type="secondary" style={{ fontSize: 12, display: 'block', marginTop: 6 }}>
        Klik pada peta atau geser penanda untuk menentukan lokasi asli cluster. Citra peta pada lokasi ini akan dijadikan gambar latar.
      </Typography.Text>
    </div>
  );
}
