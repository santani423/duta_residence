import { LoginOutlined, PhoneOutlined } from '@ant-design/icons';
import { Button } from 'antd';
import { Link } from 'react-router-dom';
import Reveal from '../Reveal.jsx';

export default function CtaSection() {
  return (
    <section className="lp-section">
      <div className="lp-container">
        <Reveal as="div" className="lp-cta" direction="scale">
          <h2>Siap Mengelola Kawasan Lebih Mudah?</h2>
          <p>
            Masuk ke aplikasi untuk mengakses seluruh layanan, atau hubungi tim kami untuk mendaftarkan kawasan
            Anda ke dalam sistem Estate Management.
          </p>
          <div className="lp-cta-actions">
            <Link to="/login">
              <Button type="primary" size="large" ghost icon={<LoginOutlined />}>
                Login ke Aplikasi
              </Button>
            </Link>
            <a href="#kontak">
              <Button
                size="large"
                icon={<PhoneOutlined />}
                style={{ background: '#fff', color: '#0f766e', borderColor: '#fff' }}
              >
                Hubungi Pengelola
              </Button>
            </a>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
