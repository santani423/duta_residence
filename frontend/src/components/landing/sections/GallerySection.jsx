import { useQuery } from '@tanstack/react-query';
import { Modal, Skeleton } from 'antd';
import { useState } from 'react';
import { api } from '../../../services/estateApi.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import { cmsImageUrl } from '../../../utils/cmsMedia.js';
import Reveal from '../Reveal.jsx';

function youTubeEmbedUrl(url) {
  const match = url.match(/(?:youtu\.be\/|youtube\.com\/watch\?v=|youtube\.com\/embed\/)([\w-]+)/);
  return match ? `https://www.youtube.com/embed/${match[1]}` : null;
}

function GalleryItem({ item }) {
  if (item.type === 'video' && item.video_url) {
    const embed = youTubeEmbedUrl(item.video_url);
    return embed ? (
      <iframe src={embed} title={item.caption || 'Video galeri'} style={{ width: '100%', aspectRatio: '16/9', border: 0, borderRadius: 12 }} allowFullScreen />
    ) : (
      <video src={item.video_url} controls style={{ width: '100%', borderRadius: 12 }} />
    );
  }

  return <img src={cmsImageUrl(item.media)} alt={item.caption || ''} style={{ width: '100%', borderRadius: 12 }} loading="lazy" />;
}

function GalleryAlbumBody({ slug }) {
  const query = useQuery({
    queryKey: ['landing-gallery-album', slug],
    queryFn: () => api.landing.galleryAlbum(slug),
    enabled: Boolean(slug),
  });

  if (query.isLoading) {
    return <Skeleton active paragraph={{ rows: 4 }} />;
  }

  const items = query.data?.data?.items || [];

  if (!items.length) {
    return <p>Belum ada item pada album ini.</p>;
  }

  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 12 }}>
      {items.map((item) => (
        <div key={item.id}>
          <GalleryItem item={item} />
          {item.caption ? <p style={{ fontSize: 13, marginTop: 6 }}>{item.caption}</p> : null}
        </div>
      ))}
    </div>
  );
}

export default function GallerySection() {
  const content = useLandingContent();
  const [activeSlug, setActiveSlug] = useState(null);
  const albums = content?.gallery_albums || [];

  if (!albums.length) return null;

  return (
    <section id="galeri" className="lp-section">
      <div className="lp-container">
        <Reveal as="div" className="lp-section-head">
          <span className="lp-eyebrow">Galeri</span>
          <h2>Momen &amp; Fasilitas Kawasan</h2>
          <p>Lihat lebih dekat suasana dan fasilitas kawasan melalui dokumentasi foto dan video kami.</p>
        </Reveal>

        <div className="lp-grid">
          {albums.map((album, index) => (
            <Reveal
              key={album.id}
              delay={(index % 3) * 80}
              className="lp-facility"
              as="button"
              type="button"
              style={{ border: 0, cursor: 'pointer', padding: 0, textAlign: 'left' }}
              onClick={() => setActiveSlug(album.slug)}
            >
              <img src={cmsImageUrl(album.cover)} alt={album.title} loading="lazy" />
              <div className="lp-facility-label">
                {album.title} · {album.items_count || 0} item
              </div>
            </Reveal>
          ))}
        </div>
      </div>

      <Modal
        open={!!activeSlug}
        onCancel={() => setActiveSlug(null)}
        footer={null}
        title={albums.find((album) => album.slug === activeSlug)?.title}
        width={720}
        destroyOnHidden
      >
        <GalleryAlbumBody slug={activeSlug} />
      </Modal>
    </section>
  );
}
