import { Button } from 'antd';
import { Link } from 'react-router-dom';
import { FACILITIES } from '../../../constants/landingContent.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import { cmsImageUrl } from '../../../utils/cmsMedia.js';
import Reveal from '../Reveal.jsx';

export default function FacilitiesSection() {
  const content = useLandingContent();
  const cmsFacilities = content?.services
    ?.filter((service) => service.category?.slug === 'fasilitas')
    ?.map((service) => ({ title: service.title, img: cmsImageUrl(service.image) }));
  const facilities = cmsFacilities?.length ? cmsFacilities : FACILITIES;

  return (
    <section id="fasilitas" className="lp-section lp-section-alt">
      <div className="lp-container">
        <Reveal as="div" className="lp-section-head">
          <span className="lp-eyebrow">Fasilitas Kawasan</span>
          <h2>Fasilitas Lengkap untuk Kenyamanan Bersama</h2>
          <p>Nikmati berbagai fasilitas kawasan yang terawat dan dapat dipesan langsung melalui aplikasi.</p>
        </Reveal>

        <div className="lp-grid">
          {facilities.map((facility, index) => (
            <Reveal key={facility.title} delay={(index % 3) * 80} className="lp-facility">
              <img src={facility.img} alt={`Fasilitas ${facility.title}`} loading="lazy" />
              <div className="lp-facility-label">{facility.title}</div>
            </Reveal>
          ))}
        </div>

        <div style={{ textAlign: 'center', marginTop: 36 }}>
          <p style={{ marginBottom: 14 }}>Ingin memesan fasilitas kawasan? Login terlebih dahulu untuk melihat jadwal dan booking.</p>
          <Link to="/login">
            <Button type="primary" size="large">
              Booking Fasilitas
            </Button>
          </Link>
        </div>
      </div>
    </section>
  );
}
