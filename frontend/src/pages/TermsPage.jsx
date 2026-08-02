import { Typography } from 'antd';
import LandingFooter from '../components/landing/LandingFooter.jsx';
import LandingHeader from '../components/landing/LandingHeader.jsx';
import { useLandingContentQuery } from '../hooks/useLandingContent.js';
import { LandingContentProvider } from '../state/LandingContentContext.jsx';
import '../styles/landing.css';

export default function TermsPage() {
  const query = useLandingContentQuery();
  const content = query.data?.data;
  const siteName = content?.site?.site_name || 'Duta Indah Residences';

  return (
    <LandingContentProvider value={content}>
      <div className="landing-page">
        <LandingHeader />
        <main className="lp-section">
          <div className="lp-container" style={{ maxWidth: 800 }}>
            <Typography.Title level={2}>Syarat dan Ketentuan</Typography.Title>
            <Typography.Paragraph type="secondary">Terakhir diperbarui: 25 Juli 2026</Typography.Paragraph>

            <Typography.Title level={4}>1. Penerimaan Ketentuan</Typography.Title>
            <Typography.Paragraph>
              Dengan menggunakan aplikasi {siteName}, Anda dianggap telah membaca, memahami, dan
              menyetujui seluruh syarat dan ketentuan yang berlaku.
            </Typography.Paragraph>

            <Typography.Title level={4}>2. Akun Pengguna</Typography.Title>
            <Typography.Paragraph>
              Setiap pengguna bertanggung jawab menjaga kerahasiaan kredensial akunnya. Segala aktivitas yang
              dilakukan melalui akun pengguna menjadi tanggung jawab pemilik akun.
            </Typography.Paragraph>

            <Typography.Title level={4}>3. Penggunaan Layanan</Typography.Title>
            <Typography.Paragraph>
              Layanan pembayaran, pengaduan, booking fasilitas, dan fitur darurat hanya diperuntukkan bagi penghuni
              dan pihak yang berwenang, dan wajib digunakan sesuai tujuannya.
            </Typography.Paragraph>

            <Typography.Title level={4}>4. Batasan Tanggung Jawab</Typography.Title>
            <Typography.Paragraph>
              Pengelola berupaya menjaga ketersediaan dan keakuratan sistem, namun tidak bertanggung jawab atas
              gangguan yang berada di luar kendali wajar pengelola.
            </Typography.Paragraph>

            <Typography.Title level={4}>5. Perubahan Ketentuan</Typography.Title>
            <Typography.Paragraph>
              Pengelola dapat memperbarui syarat dan ketentuan ini dari waktu ke waktu. Perubahan akan diinformasikan
              melalui aplikasi atau halaman ini.
            </Typography.Paragraph>
          </div>
        </main>
        <LandingFooter />
      </div>
    </LandingContentProvider>
  );
}
