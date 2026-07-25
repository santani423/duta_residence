import { ArrowRightOutlined } from '@ant-design/icons';
import { Link } from 'react-router-dom';
import { SERVICES } from '../../../constants/landingContent.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import Reveal from '../Reveal.jsx';
import { LandingIcon } from '../../../constants/landingIcons.jsx';

export default function ServicesSection() {
  const content = useLandingContent();
  // The CMS "Layanan & Fasilitas" module is one shared collection - items
  // categorized "fasilitas" render in FacilitiesSection instead, everything
  // else (or uncategorized) renders here.
  const cmsServices = content?.services?.filter((service) => service.category?.slug !== 'fasilitas');
  const services = cmsServices?.length ? cmsServices : SERVICES;

  return (
    <section id="layanan" className="lp-section lp-section-alt">
      <div className="lp-container">
        <Reveal as="div" className="lp-section-head">
          <span className="lp-eyebrow">Layanan Utama</span>
          <h2>Semua Kebutuhan Kawasan dalam Satu Aplikasi</h2>
          <p>
            Dari pembayaran hingga keamanan, seluruh layanan pengelolaan kawasan dapat diakses penghuni dan
            pengelola secara digital.
          </p>
        </Reveal>

        <div className="lp-grid">
          {services.map((service, index) => (
            <Reveal key={service.title} delay={(index % 3) * 80} className="lp-card">
              <span className="lp-icon-badge">
                <LandingIcon name={service.icon} />
              </span>
              <h3>{service.title}</h3>
              <p>{service.description}</p>
              <Link to="/login" className="lp-card-link">
                Lihat Detail <ArrowRightOutlined />
              </Link>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}
