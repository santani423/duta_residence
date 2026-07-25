import { ShopOutlined } from '@ant-design/icons';
import { PARTNERS } from '../../../constants/landingContent.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import { cmsImageUrl } from '../../../utils/cmsMedia.js';
import Reveal from '../Reveal.jsx';

export default function PartnersSection() {
  const content = useLandingContent();
  const partners = content?.partners?.length ? content.partners : PARTNERS;

  return (
    <section id="mitra" className="lp-section lp-section-alt">
      <div className="lp-container">
        <Reveal as="div" className="lp-section-head">
          <span className="lp-eyebrow">Mitra &amp; Kerja Sama</span>
          <h2>Didukung oleh Mitra Terpercaya</h2>
          <p>Kami bekerja sama dengan berbagai vendor dan mitra untuk memastikan kualitas layanan kawasan.</p>
        </Reveal>

        <div className="lp-partners-grid">
          {partners.map((partner, index) => {
            const logo = cmsImageUrl(partner.logo);
            const linkProps = partner.website_url ? { href: partner.website_url, target: '_blank', rel: 'noreferrer' } : {};

            return (
              <Reveal
                key={partner.name}
                delay={(index % 3) * 60}
                as={partner.website_url ? 'a' : 'div'}
                className="lp-partner"
                {...linkProps}
              >
                {logo ? (
                  <img src={logo} alt={partner.name} style={{ height: 28, objectFit: 'contain' }} loading="lazy" />
                ) : (
                  <span className="lp-partner-mark">
                    <ShopOutlined />
                  </span>
                )}
                {partner.name}
              </Reveal>
            );
          })}
        </div>
      </div>
    </section>
  );
}
