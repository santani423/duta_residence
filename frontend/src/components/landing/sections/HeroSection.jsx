import { ArrowRightOutlined, PhoneOutlined, SafetyCertificateOutlined } from '@ant-design/icons';
import { Button, Carousel } from 'antd';
import { HERO_STATS } from '../../../constants/landingContent.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import { cmsImageUrl } from '../../../utils/cmsMedia.js';
import Reveal from '../Reveal.jsx';

const FALLBACK_SLIDE = {
  title: 'Kelola Kawasan Anda Lebih Modern, Aman, dan Terintegrasi',
  subtitle: 'Estate Management System',
  description:
    'Satu platform untuk pengelolaan pembayaran, keamanan, pengaduan, fasilitas, hingga informasi penghuni — memudahkan pengelola dan penghuni kawasan dalam satu genggaman.',
  cta_text: 'Pelajari Lebih Lanjut',
  cta_url: '#tentang',
  cta_target: '_self',
  img: 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=1000&q=70',
};

function formatStatValue(value) {
  return (Number(value) || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function HeroCopy({ slide, stats }) {
  return (
    <div className="lp-hero-copy">
      <span className="lp-eyebrow">{slide.subtitle || 'Estate Management System'}</span>
      <h1>{slide.title}</h1>
      {slide.description ? <p>{slide.description}</p> : null}
      <div className="lp-hero-actions">
        {slide.cta_text ? (
          <a href={slide.cta_url || '#tentang'} target={slide.cta_target === '_blank' ? '_blank' : undefined} rel={slide.cta_target === '_blank' ? 'noreferrer' : undefined}>
            <Button type="primary" size="large" icon={<ArrowRightOutlined />}>
              {slide.cta_text}
            </Button>
          </a>
        ) : null}
        <a href="#layanan">
          <Button size="large">Lihat Layanan</Button>
        </a>
        <a href="#kontak">
          <Button size="large" type="text" icon={<PhoneOutlined />}>
            Hubungi Kami
          </Button>
        </a>
      </div>
      <div className="lp-hero-stats">
        {stats.map((stat) => (
          <div className="lp-hero-stat" key={stat.label}>
            <strong>{stat.value}</strong>
            <span>{stat.label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function HeroVisual({ slide }) {
  return (
    <div className="lp-hero-visual">
      <div className="lp-hero-photo">
        <img src={slide.img} alt={slide.title} loading="lazy" />
      </div>
      <div className="lp-hero-card">
        <span className="lp-icon-badge">
          <SafetyCertificateOutlined />
        </span>
        <div>
          <strong>Keamanan Terpantau</strong>
          <span>Respons darurat real-time 24/7</span>
        </div>
      </div>
    </div>
  );
}

export default function HeroSection() {
  const content = useLandingContent();

  const slides = content?.hero_slides?.length
    ? content.hero_slides.map((slide) => ({
      title: slide.title,
      subtitle: slide.subtitle,
      description: slide.description,
      cta_text: slide.cta_text,
      cta_url: slide.cta_url,
      cta_target: slide.cta_target,
      img: cmsImageUrl(slide.background_media) || FALLBACK_SLIDE.img,
    }))
    : [FALLBACK_SLIDE];

  const stats = content?.statistics?.length
    ? content.statistics.map((stat) => ({ label: stat.label, value: `${formatStatValue(stat.resolved_value)}${stat.suffix || ''}` }))
    : HERO_STATS;

  if (slides.length <= 1) {
    const slide = slides[0];
    return (
      <section id="beranda" className="lp-hero">
        <div className="lp-container lp-hero-grid">
          <Reveal direction="left">
            <HeroCopy slide={slide} stats={stats} />
          </Reveal>
          <Reveal delay={150} direction="right">
            <HeroVisual slide={slide} />
          </Reveal>
        </div>
      </section>
    );
  }

  return (
    <section id="beranda" className="lp-hero">
      <div className="lp-container">
        <Carousel autoplay autoplaySpeed={7000} dots effect="fade" adaptiveHeight>
          {slides.map((slide) => (
            <div key={slide.title}>
              <div className="lp-hero-grid">
                <HeroCopy slide={slide} stats={stats} />
                <HeroVisual slide={slide} />
              </div>
            </div>
          ))}
        </Carousel>
      </div>
    </section>
  );
}
