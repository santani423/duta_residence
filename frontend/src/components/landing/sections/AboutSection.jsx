import { ABOUT_PILLARS } from '../../../constants/landingContent.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import { cmsImageUrl } from '../../../utils/cmsMedia.js';
import Reveal from '../Reveal.jsx';
import { LandingIcon } from '../../../constants/landingIcons.jsx';

const FALLBACK_IMAGE = 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=900&q=70';

export default function AboutSection() {
  const content = useLandingContent();
  const about = content?.about;
  const siteName = content?.site?.site_name || 'Duta Indah Estate Management';

  const title = about?.title || 'Pengelola Kawasan yang Mengutamakan Kenyamanan Anda';
  const description = about?.description
    || `${siteName} adalah sistem pengelolaan kawasan yang membantu pengelola properti — perumahan, apartemen, maupun cluster — dalam menjalankan operasional sehari-hari secara lebih rapi, cepat, dan transparan. Kami berkomitmen menghadirkan pengalaman tinggal yang aman dan nyaman bagi seluruh penghuni.`;
  const image = cmsImageUrl(about?.image) || FALLBACK_IMAGE;
  const pillars = about?.pillars?.length ? about.pillars : ABOUT_PILLARS;

  return (
    <section id="tentang" className="lp-section">
      <div className="lp-container lp-about-grid">
        <Reveal className="lp-about-photo" as="div" direction="left">
          <img src={image} alt={`Tim pengelola kawasan ${siteName}`} loading="lazy" />
        </Reveal>

        <Reveal delay={120} direction="right">
          <div className="lp-about-copy">
            <span className="lp-eyebrow">Tentang Kami</span>
            <h2>{title}</h2>
            <p>{description}</p>
            <div className="lp-pillars">
              {pillars.map((pillar) => (
                <div className="lp-pillar" key={pillar.title}>
                  <span className="lp-icon-badge">
                    <LandingIcon name={pillar.icon} />
                  </span>
                  <div>
                    <h4>{pillar.title}</h4>
                    <p>{pillar.description}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
