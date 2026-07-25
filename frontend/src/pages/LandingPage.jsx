import { useEffect } from 'react';
import LandingFooter from '../components/landing/LandingFooter.jsx';
import LandingHeader from '../components/landing/LandingHeader.jsx';
import AboutSection from '../components/landing/sections/AboutSection.jsx';
import AdvantagesSection from '../components/landing/sections/AdvantagesSection.jsx';
import ContactSection from '../components/landing/sections/ContactSection.jsx';
import CtaSection from '../components/landing/sections/CtaSection.jsx';
import EmergencySection from '../components/landing/sections/EmergencySection.jsx';
import EventsSection from '../components/landing/sections/EventsSection.jsx';
import FacilitiesSection from '../components/landing/sections/FacilitiesSection.jsx';
import FaqSection from '../components/landing/sections/FaqSection.jsx';
import GallerySection from '../components/landing/sections/GallerySection.jsx';
import HeroSection from '../components/landing/sections/HeroSection.jsx';
import NewsSection from '../components/landing/sections/NewsSection.jsx';
import PartnersSection from '../components/landing/sections/PartnersSection.jsx';
import ServicesSection from '../components/landing/sections/ServicesSection.jsx';
import TestimonialsSection from '../components/landing/sections/TestimonialsSection.jsx';
import { useLandingContentQuery } from '../hooks/useLandingContent.js';
import { LandingContentProvider } from '../state/LandingContentContext.jsx';
import '../styles/landing.css';

function upsertMeta(attr, key, value) {
  if (!value) return null;
  let el = document.head.querySelector(`meta[${attr}="${key}"]`);
  if (!el) {
    el = document.createElement('meta');
    el.setAttribute(attr, key);
    document.head.appendChild(el);
  }
  el.setAttribute('content', value);
  return el;
}

function injectTag(tagName, content, attrs = {}) {
  if (!content) return null;
  const el = document.createElement(tagName);
  Object.entries(attrs).forEach(([key, value]) => el.setAttribute(key, value));
  el.textContent = content;
  document.head.appendChild(el);
  return el;
}

// Applies CMS-managed SEO/meta/JSON-LD and custom CSS/JS/analytics only while
// the landing page is mounted, cleaning everything up on unmount so other
// routes are never affected. This is client-side (CSR SPA, no SSR) - correct
// for the browser tab, in-app dynamic SEO, and JS-executing crawlers, but not
// visible to non-JS social link-preview scrapers; fixing that needs SSR,
// which is out of scope.
function useLandingHeadEffects(content) {
  useEffect(() => {
    if (!content) return undefined;

    const created = [];
    const { seo, site } = content;
    const originalTitle = document.title;

    if (seo?.meta_title || site?.site_name) {
      document.title = seo?.meta_title || site?.site_name;
    }

    created.push(upsertMeta('name', 'description', seo?.meta_description));
    created.push(upsertMeta('name', 'keywords', seo?.meta_keywords));
    created.push(upsertMeta('property', 'og:title', seo?.og_title || seo?.meta_title));
    created.push(upsertMeta('property', 'og:description', seo?.og_description || seo?.meta_description));
    created.push(upsertMeta('name', 'twitter:card', seo?.twitter_card_type));

    if (seo?.structured_data) {
      try {
        const parsed = JSON.parse(seo.structured_data);
        const script = document.createElement('script');
        script.type = 'application/ld+json';
        script.textContent = JSON.stringify(parsed);
        document.head.appendChild(script);
        created.push(script);
      } catch {
        // Invalid JSON is already rejected by the CMS form validation - ignore silently on render.
      }
    }

    created.push(injectTag('style', site?.custom_css, { 'data-landing-injected': 'custom-css' }));
    created.push(injectTag('script', site?.analytics_script, { 'data-landing-injected': 'analytics' }));
    created.push(injectTag('script', site?.pixel_script, { 'data-landing-injected': 'pixel' }));
    created.push(injectTag('script', site?.custom_js, { 'data-landing-injected': 'custom-js' }));

    return () => {
      document.title = originalTitle;
      created.filter(Boolean).forEach((el) => el.remove());
    };
  }, [content]);
}

function MaintenanceScreen({ message }) {
  return (
    <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 24, textAlign: 'center', background: '#0f172a', color: '#f1f5f9' }}>
      <div style={{ maxWidth: 480 }}>
        <h1 style={{ fontSize: 28, marginBottom: 12 }}>Sedang Dalam Pemeliharaan</h1>
        <p style={{ color: '#94a3b8', marginBottom: 24 }}>
          {message || 'Landing page sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.'}
        </p>
        <a href="/login" style={{ color: '#2dd4bf', fontWeight: 700 }}>
          Login ke Aplikasi
        </a>
      </div>
    </div>
  );
}

export default function LandingPage() {
  const query = useLandingContentQuery();
  const content = query.data?.data;
  useLandingHeadEffects(content);

  if (content?.site?.maintenance_mode) {
    return <MaintenanceScreen message={content.site.maintenance_message} />;
  }

  return (
    <LandingContentProvider value={content}>
      <div className="landing-page">
        <LandingHeader />
        <main>
          <HeroSection />
          <AboutSection />
          <ServicesSection />
          <AdvantagesSection />
          <FacilitiesSection />
          <NewsSection />
          <EventsSection />
          <EmergencySection />
          <GallerySection />
          <PartnersSection />
          <FaqSection />
          <TestimonialsSection />
          <CtaSection />
          <ContactSection />
        </main>
        <LandingFooter />
      </div>
    </LandingContentProvider>
  );
}
