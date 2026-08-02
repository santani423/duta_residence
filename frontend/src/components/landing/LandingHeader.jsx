import { LoginOutlined, MenuOutlined, MoonOutlined, SunOutlined } from '@ant-design/icons';
import { Button, Drawer } from 'antd';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { NAV_LINKS } from '../../constants/landingContent.js';
import { useLandingContent } from '../../state/LandingContentContext.jsx';
import { useThemeMode } from '../../state/ThemeContext.jsx';
import { cmsImageUrl } from '../../utils/cmsMedia.js';

function navItemsFrom(headerNavItems) {
  if (!headerNavItems?.length) return NAV_LINKS;

  return headerNavItems.map((item) => ({
    key: item.url?.startsWith('#') ? item.url.slice(1) : `nav-${item.id}`,
    label: item.label,
    href: item.url,
    openInNewTab: item.open_in_new_tab,
  }));
}

export default function LandingHeader() {
  const [scrolled, setScrolled] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [activeKey, setActiveKey] = useState('');
  const { effectiveMode, setMode } = useThemeMode();
  const isDark = effectiveMode === 'dark';
  const content = useLandingContent();

  const header = content?.header;
  const navLinks = navItemsFrom(header?.nav_items);
  const logo = cmsImageUrl(header?.logo) || '/logo-estate-management.png';
  const siteName = header?.site_name || 'Duta Indah Residences';
  const showLoginButton = header?.show_login_button ?? true;
  const loginButtonText = header?.login_button_text || 'Login';
  const stickyEnabled = header?.sticky_enabled ?? true;

  useEffect(() => {
    function onScroll() {
      setScrolled(window.scrollY > 8);
    }
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  // Smooth-scroll for in-page anchor navigation, scoped to the public pages
  // that mount this header (left off elsewhere to avoid touching the app shell).
  useEffect(() => {
    document.documentElement.classList.add('lp-smooth-scroll');
    return () => document.documentElement.classList.remove('lp-smooth-scroll');
  }, []);

  // Highlights the nav link for the section currently in view.
  useEffect(() => {
    if (typeof IntersectionObserver === 'undefined') return undefined;

    const sections = navLinks.map((link) => document.getElementById(link.key)).filter(Boolean);
    if (!sections.length) return undefined;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setActiveKey(entry.target.id);
          }
        });
      },
      { rootMargin: '-45% 0px -50% 0px', threshold: 0 },
    );

    sections.forEach((section) => observer.observe(section));
    return () => observer.disconnect();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [navLinks.map((link) => link.key).join(',')]);

  function toggleTheme() {
    setMode(isDark ? 'light' : 'dark');
  }

  return (
    <header className={`lp-header ${scrolled ? 'is-scrolled' : ''} ${stickyEnabled ? '' : 'is-static'}`}>
      <div className="lp-header-inner">
        <Link to="/" className="lp-logo" aria-label={`${siteName} - Beranda`}>
          <img src={logo} alt={`Logo ${siteName}`} width="36" height="36" />
        </Link>

        <nav className="lp-nav" aria-label="Navigasi utama">
          {navLinks.map((link) => (
            <a
              key={link.key}
              href={link.href}
              target={link.openInNewTab ? '_blank' : undefined}
              rel={link.openInNewTab ? 'noreferrer' : undefined}
              className={activeKey === link.key ? 'is-active' : ''}
            >
              {link.label}
            </a>
          ))}
        </nav>

        <div className="lp-header-actions">
          <button
            type="button"
            className="lp-theme-toggle"
            onClick={toggleTheme}
            aria-label={isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'}
            title={isDark ? 'Mode Terang' : 'Mode Gelap'}
          >
            {isDark ? <SunOutlined /> : <MoonOutlined />}
          </button>
          {header?.cta_button_enabled && header?.cta_button_text ? (
            <a href={header.cta_button_url || '#kontak'} className="lp-login-desktop">
              <Button>{header.cta_button_text}</Button>
            </a>
          ) : null}
          {showLoginButton ? (
            <Link to="/login" className="lp-login-desktop">
              <Button type="primary" icon={<LoginOutlined />}>
                {loginButtonText}
              </Button>
            </Link>
          ) : null}
          <Button
            className="lp-hamburger"
            type="text"
            icon={<MenuOutlined />}
            aria-label="Buka menu navigasi"
            onClick={() => setMobileOpen(true)}
          />
        </div>
      </div>

      <Drawer
        title="Menu"
        placement="right"
        width={280}
        open={mobileOpen}
        onClose={() => setMobileOpen(false)}
        className="lp-mobile-drawer"
      >
        {navLinks.map((link) => (
          <a
            key={link.key}
            href={link.href}
            target={link.openInNewTab ? '_blank' : undefined}
            rel={link.openInNewTab ? 'noreferrer' : undefined}
            className={activeKey === link.key ? 'is-active' : ''}
            onClick={() => setMobileOpen(false)}
          >
            {link.label}
          </a>
        ))}
        {showLoginButton ? (
          <Link to="/login" onClick={() => setMobileOpen(false)} style={{ marginTop: 12 }}>
            <Button type="primary" icon={<LoginOutlined />} block>
              {loginButtonText}
            </Button>
          </Link>
        ) : null}
      </Drawer>
    </header>
  );
}
