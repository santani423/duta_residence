import { CalendarOutlined, EnvironmentOutlined, LinkOutlined } from '@ant-design/icons';
import { Button, Modal } from 'antd';
import dayjs from 'dayjs';
import 'dayjs/locale/id.js';
import { useState } from 'react';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import { cmsImageUrl } from '../../../utils/cmsMedia.js';
import Reveal from '../Reveal.jsx';

dayjs.locale('id');

function formatEventDate(event) {
  if (!event.starts_at) return null;
  const start = dayjs(event.starts_at);
  const end = event.ends_at ? dayjs(event.ends_at) : null;

  if (end && !end.isSame(start, 'day')) {
    return `${start.format('D MMM YYYY')} — ${end.format('D MMM YYYY')}`;
  }

  return `${start.format('D MMMM YYYY')}, ${start.format('HH.mm')} WIB`;
}

export default function EventsSection() {
  const [active, setActive] = useState(null);
  const content = useLandingContent();
  const events = content?.events || [];

  if (!events.length) return null;

  return (
    <section className="lp-section lp-section-alt">
      <div className="lp-container">
        <Reveal as="div" className="lp-section-head">
          <span className="lp-eyebrow">Agenda Kawasan</span>
          <h2>Acara &amp; Kegiatan Mendatang</h2>
          <p>Ikuti berbagai kegiatan yang diselenggarakan pengelola untuk seluruh penghuni kawasan.</p>
        </Reveal>

        <div className="lp-grid">
          {events.map((event, index) => (
            <Reveal key={event.id} delay={(index % 3) * 80} className="lp-news-card" as="article">
              <div className="lp-news-img">
                <img src={cmsImageUrl(event.banner)} alt={event.title} loading="lazy" />
              </div>
              <div className="lp-news-body">
                <span className="lp-news-date">
                  <CalendarOutlined /> {formatEventDate(event)}
                </span>
                <h3>{event.title}</h3>
                {event.location ? (
                  <p style={{ margin: 0, fontSize: 13 }}>
                    <EnvironmentOutlined /> {event.location}
                  </p>
                ) : null}
                <Button type="link" style={{ padding: 0, alignSelf: 'flex-start' }} onClick={() => setActive(event)}>
                  Lihat Detail
                </Button>
              </div>
            </Reveal>
          ))}
        </div>
      </div>

      <Modal open={!!active} onCancel={() => setActive(null)} footer={null} title={active?.title} destroyOnHidden>
        {active ? (
          <div>
            <img src={cmsImageUrl(active.banner)} alt={active.title} style={{ width: '100%', borderRadius: 12, marginBottom: 16 }} loading="lazy" />
            <p style={{ color: 'var(--lp-text-soft)', fontSize: 12, fontWeight: 700, textTransform: 'uppercase' }}>
              <CalendarOutlined /> {formatEventDate(active)}
            </p>
            {active.location ? (
              <p style={{ color: 'var(--lp-text-soft)', fontSize: 13 }}>
                <EnvironmentOutlined /> {active.location}
              </p>
            ) : null}
            <p>{active.description}</p>
            {active.registration_url ? (
              <Button type="primary" icon={<LinkOutlined />} href={active.registration_url} target="_blank" rel="noreferrer">
                Daftar Sekarang
              </Button>
            ) : null}
          </div>
        ) : null}
      </Modal>
    </section>
  );
}
