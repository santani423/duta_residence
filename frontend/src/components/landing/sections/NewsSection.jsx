import { ArrowRightOutlined, CalendarOutlined } from '@ant-design/icons';
import { Button, Modal } from 'antd';
import dayjs from 'dayjs';
import 'dayjs/locale/id.js';
import { useState } from 'react';
import RichText from '../../help/RichText.jsx';
import { NEWS } from '../../../constants/landingContent.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import { cmsImageUrl } from '../../../utils/cmsMedia.js';
import Reveal from '../Reveal.jsx';

dayjs.locale('id');

export default function NewsSection() {
  const [active, setActive] = useState(null);
  const content = useLandingContent();

  const cmsArticles = content?.articles?.map((article) => ({
    id: article.id,
    title: article.title,
    published_at: article.published_at,
    img: cmsImageUrl(article.featured_image),
    excerpt: article.excerpt,
    content: article.content,
  }));
  const items = cmsArticles?.length ? cmsArticles : NEWS;

  return (
    <section id="berita" className="lp-section">
      <div className="lp-container">
        <Reveal as="div" className="lp-section-head">
          <span className="lp-eyebrow">Berita &amp; Event</span>
          <h2>Informasi Terbaru Seputar Kawasan</h2>
          <p>Ikuti pengumuman, kegiatan, dan berita terbaru dari pengelola kawasan.</p>
        </Reveal>

        <div className="lp-grid">
          {items.map((item, index) => (
            <Reveal key={item.id} delay={(index % 3) * 80} className="lp-news-card" as="article">
              <div className="lp-news-img">
                <img src={item.img} alt={item.title} loading="lazy" />
              </div>
              <div className="lp-news-body">
                <span className="lp-news-date">
                  <CalendarOutlined /> {dayjs(item.published_at).format('D MMMM YYYY')}
                </span>
                <h3>{item.title}</h3>
                <p>{item.excerpt}</p>
                <Button type="link" style={{ padding: 0, alignSelf: 'flex-start' }} onClick={() => setActive(item)}>
                  Baca Selengkapnya <ArrowRightOutlined />
                </Button>
              </div>
            </Reveal>
          ))}
        </div>
      </div>

      <Modal open={!!active} onCancel={() => setActive(null)} footer={null} title={active?.title} destroyOnHidden>
        {active ? (
          <div>
            <img
              src={active.img}
              alt={active.title}
              style={{ width: '100%', borderRadius: 12, marginBottom: 16 }}
              loading="lazy"
            />
            <p style={{ color: 'var(--lp-text-soft)', fontSize: 12, fontWeight: 700, textTransform: 'uppercase' }}>
              {dayjs(active.published_at).format('D MMMM YYYY')}
            </p>
            <RichText text={active.content} />
          </div>
        ) : null}
      </Modal>
    </section>
  );
}
