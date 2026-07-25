import { FacebookOutlined, InstagramOutlined, LinkedinOutlined, MailOutlined, PhoneOutlined, SendOutlined, TikTokOutlined, TwitterOutlined, YoutubeOutlined } from '@ant-design/icons';
import { Link } from 'react-router-dom';
import { CONTACT_INFO, NAV_LINKS, SERVICES } from '../../constants/landingContent.js';
import { useLandingContent } from '../../state/LandingContentContext.jsx';
import { cmsImageUrl } from '../../utils/cmsMedia.js';

const SOCIAL_ICONS = {
  facebook: FacebookOutlined,
  instagram: InstagramOutlined,
  tiktok: TikTokOutlined,
  youtube: YoutubeOutlined,
  linkedin: LinkedinOutlined,
  twitter_x: TwitterOutlined,
  telegram: SendOutlined,
  custom: SendOutlined,
};

export default function LandingFooter() {
  const year = new Date().getFullYear();
  const content = useLandingContent();

  const footer = content?.footer;
  const header = content?.header;
  const contact = content?.contact?.address ? content.contact : CONTACT_INFO;
  const showSocialLinks = footer?.show_social_links ?? true;
  const socialLinks = content?.social_links?.length ? content.social_links : null;
  const quickLinks = footer?.quick_links?.length ? footer.quick_links.map((item) => ({ key: item.id, label: item.label, href: item.url })) : NAV_LINKS;
  const showQuickLinks = footer?.show_quick_links ?? true;
  const services = content?.services?.length ? content.services : SERVICES;
  const logo = cmsImageUrl(footer?.logo) || cmsImageUrl(header?.logo) || '/logo-estate-management.png';
  const siteName = header?.site_name || 'Grand Duta';
  const description = footer?.description
    || 'Sistem pengelolaan kawasan modern untuk perumahan, apartemen, dan cluster — menghadirkan layanan yang aman, transparan, dan mudah diakses.';
  const copyrightText = footer?.copyright_text || 'Grand Duta Estate Management. Seluruh hak cipta dilindungi.';

  return (
    <footer className="lp-footer">
      <div className="lp-container">
        <div className="lp-footer-grid">
          <div className="lp-footer-brand">
            <Link to="/" className="lp-logo">
              <img src={logo} alt={`Logo ${siteName}`} width="34" height="34" />
              <span className="lp-logo-text">
                {siteName}
                <small>Estate Management</small>
              </span>
            </Link>
            <p>{description}</p>
            {showSocialLinks ? (
              <div className="lp-social-row">
                {socialLinks ? (
                  socialLinks.map((link) => {
                    const Icon = SOCIAL_ICONS[link.platform] || SendOutlined;
                    return (
                      <a key={link.id} href={link.url} target="_blank" rel="noreferrer" aria-label={`${link.platform} ${siteName}`}>
                        <Icon />
                      </a>
                    );
                  })
                ) : (
                  <>
                    <a href="#" aria-label={`Facebook ${siteName}`}>
                      <FacebookOutlined />
                    </a>
                    <a href="#" aria-label={`Instagram ${siteName}`}>
                      <InstagramOutlined />
                    </a>
                    <a href="#" aria-label={`Twitter / X ${siteName}`}>
                      <TwitterOutlined />
                    </a>
                  </>
                )}
              </div>
            ) : null}
          </div>

          {showQuickLinks ? (
            <div>
              <h5>Navigasi</h5>
              <ul>
                {quickLinks.map((link) => (
                  <li key={link.key}>
                    <a href={link.href}>{link.label}</a>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          <div>
            <h5>Layanan</h5>
            <ul>
              {services.slice(0, 5).map((service) => (
                <li key={service.title}>
                  <a href="#layanan">{service.title}</a>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h5>Kontak</h5>
            <ul>
              <li>
                <a href={`tel:${contact.phone.replace(/[^\d+]/g, '')}`}>
                  <PhoneOutlined /> {contact.phone}
                </a>
              </li>
              <li>
                <a href={`mailto:${contact.email}`}>
                  <MailOutlined /> {contact.email}
                </a>
              </li>
              <li>{contact.address}</li>
            </ul>
          </div>
        </div>

        <div className="lp-footer-bottom">
          <span>&copy; {year} {copyrightText}</span>
          <div className="lp-footer-bottom-links">
            <Link to="/privacy-policy">Kebijakan Privasi</Link>
            <Link to="/terms">Syarat dan Ketentuan</Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
