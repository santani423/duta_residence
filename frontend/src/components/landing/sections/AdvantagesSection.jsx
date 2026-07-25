import { ADVANTAGES } from '../../../constants/landingContent.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import Reveal from '../Reveal.jsx';
import { LandingIcon } from '../../../constants/landingIcons.jsx';

export default function AdvantagesSection() {
  const content = useLandingContent();
  const features = content?.features?.length ? content.features : ADVANTAGES;

  return (
    <section className="lp-section">
      <div className="lp-container">
        <Reveal as="div" className="lp-section-head">
          <span className="lp-eyebrow">Keunggulan Sistem</span>
          <h2>Mengapa Memilih Estate Management</h2>
          <p>Dirancang untuk memudahkan pengelolaan kawasan dari sisi pengelola maupun penghuni.</p>
        </Reveal>

        <div className="lp-advantages-grid">
          {features.map((item, index) => (
            <Reveal key={item.title} delay={(index % 4) * 70} className="lp-advantage">
              <span className="lp-icon-badge">
                <LandingIcon name={item.icon} />
              </span>
              <div>
                <h4>{item.title}</h4>
                <p>{item.description}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}
