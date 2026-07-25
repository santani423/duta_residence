import { Typography } from 'antd';
import LandingFooter from '../components/landing/LandingFooter.jsx';
import LandingHeader from '../components/landing/LandingHeader.jsx';
import '../styles/landing.css';

export default function PrivacyPolicyPage() {
  return (
    <div className="landing-page">
      <LandingHeader />
      <main className="lp-section">
        <div className="lp-container" style={{ maxWidth: 800 }}>
          <Typography.Title level={2}>Kebijakan Privasi</Typography.Title>
          <Typography.Paragraph type="secondary">Terakhir diperbarui: 25 Juli 2026</Typography.Paragraph>

          <Typography.Title level={4}>1. Data yang Kami Kumpulkan</Typography.Title>
          <Typography.Paragraph>
            Kami mengumpulkan data pribadi penghuni seperti nama, unit, nomor telepon, dan email untuk keperluan
            pengelolaan kawasan, termasuk penagihan, keamanan, dan komunikasi terkait layanan.
          </Typography.Paragraph>

          <Typography.Title level={4}>2. Penggunaan Data</Typography.Title>
          <Typography.Paragraph>
            Data yang dikumpulkan digunakan semata-mata untuk operasional pengelolaan kawasan, seperti proses
            pembayaran, penanganan pengaduan, layanan darurat, dan penyampaian informasi kawasan.
          </Typography.Paragraph>

          <Typography.Title level={4}>3. Keamanan Data</Typography.Title>
          <Typography.Paragraph>
            Kami menerapkan langkah-langkah keamanan yang wajar untuk melindungi data pribadi Anda dari akses,
            perubahan, atau pengungkapan yang tidak sah.
          </Typography.Paragraph>

          <Typography.Title level={4}>4. Berbagi Data</Typography.Title>
          <Typography.Paragraph>
            Data pribadi tidak akan dibagikan kepada pihak ketiga tanpa persetujuan Anda, kecuali diwajibkan oleh
            hukum yang berlaku.
          </Typography.Paragraph>

          <Typography.Title level={4}>5. Kontak</Typography.Title>
          <Typography.Paragraph>
            Untuk pertanyaan seputar kebijakan privasi ini, silakan hubungi kami melalui halaman{' '}
            <a href="/#kontak">Kontak</a>.
          </Typography.Paragraph>
        </div>
      </main>
      <LandingFooter />
    </div>
  );
}
