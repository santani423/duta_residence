import { LoginOutlined } from '@ant-design/icons';
import { Button } from 'antd';
import { Link } from 'react-router-dom';
import { EMERGENCY_CONTACTS, EMERGENCY_STEPS } from '../../../constants/landingContent.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import Reveal from '../Reveal.jsx';

export default function EmergencySection() {
  const content = useLandingContent();
  const siteName = content?.site?.site_name || 'Duta Indah Estate Management';

  return (
    <section className="lp-section">
      <div className="lp-container">
        <Reveal as="div" className="lp-emergency" direction="scale">
          <span className="lp-eyebrow">Informasi Darurat</span>
          <h2>Bantuan Darurat Hanya Sekali Tekan</h2>
          <p>
            Fitur emergency / panic button tersedia setelah Anda login sebagai penghuni. Berikut alur bantuan
            darurat pada aplikasi {siteName}.
          </p>

          <div className="lp-emergency-grid">
            <div className="lp-steps">
              {EMERGENCY_STEPS.map((step, index) => (
                <div className="lp-step" key={step.title}>
                  <span className="lp-step-num">{index + 1}</span>
                  <div>
                    <h4>{step.title}</h4>
                    <p>{step.description}</p>
                  </div>
                </div>
              ))}
            </div>

            <div className="lp-emergency-panel">
              <h4>Nomor Penting</h4>
              {EMERGENCY_CONTACTS.map((contact) => (
                <div className="lp-contact-row" key={contact.label}>
                  <span>{contact.label}</span>
                  <span>{contact.value}</span>
                </div>
              ))}
              <Link to="/login" style={{ display: 'block', marginTop: 20 }}>
                <Button type="default" icon={<LoginOutlined />} block>
                  Login untuk Akses Fitur Darurat
                </Button>
              </Link>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
