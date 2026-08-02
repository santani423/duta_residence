import { ClockCircleOutlined, EnvironmentOutlined, MailOutlined, PhoneOutlined, SendOutlined, WhatsAppOutlined } from '@ant-design/icons';
import { Button, Form, Input, message } from 'antd';
import { useState } from 'react';
import { CONTACT_INFO } from '../../../constants/landingContent.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import Reveal from '../Reveal.jsx';

const { TextArea } = Input;

// Simulated submission until a public contact endpoint is available on the backend.
function submitContactMessage(values) {
  return new Promise((resolve, reject) => {
    setTimeout(() => {
      if (values.email && values.message) {
        resolve({ ok: true });
      } else {
        reject(new Error('Pengiriman gagal, silakan coba lagi.'));
      }
    }, 800);
  });
}

export default function ContactSection() {
  const [form] = Form.useForm();
  const [submitting, setSubmitting] = useState(false);
  const content = useLandingContent();
  const contact = content?.contact?.address ? content.contact : CONTACT_INFO;
  const businessHours = contact.business_hours?.length ? contact.business_hours : CONTACT_INFO.business_hours;
  const siteName = content?.site?.site_name || 'Duta Indah Residences';

  async function handleFinish(values) {
    setSubmitting(true);
    try {
      await submitContactMessage(values);
      message.success('Pesan Anda berhasil dikirim. Tim kami akan segera menghubungi Anda.');
      form.resetFields();
    } catch (err) {
      message.error(err.message || 'Terjadi kesalahan saat mengirim pesan.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section id="kontak" className="lp-section lp-section-alt">
      <div className="lp-container">
        <Reveal as="div" className="lp-section-head">
          <span className="lp-eyebrow">Kontak</span>
          <h2>Hubungi Kami</h2>
          <p>Ada pertanyaan seputar kawasan atau layanan? Tim kami siap membantu Anda.</p>
        </Reveal>

        <div className="lp-contact-grid">
          <Reveal as="div" direction="left">
            <div className="lp-contact-info">
              <div className="lp-contact-info-item">
                <span className="lp-icon-badge">
                  <EnvironmentOutlined />
                </span>
                <div>
                  <h4>Alamat Kantor</h4>
                  <p>{contact.address}</p>
                </div>
              </div>
              <div className="lp-contact-info-item">
                <span className="lp-icon-badge">
                  <PhoneOutlined />
                </span>
                <div>
                  <h4>Telepon</h4>
                  <p>{contact.phone}</p>
                </div>
              </div>
              <div className="lp-contact-info-item">
                <span className="lp-icon-badge">
                  <WhatsAppOutlined />
                </span>
                <div>
                  <h4>WhatsApp</h4>
                  <p>{contact.whatsapp}</p>
                </div>
              </div>
              <div className="lp-contact-info-item">
                <span className="lp-icon-badge">
                  <MailOutlined />
                </span>
                <div>
                  <h4>Email</h4>
                  <p>{contact.email}</p>
                </div>
              </div>
              <div className="lp-contact-info-item">
                <span className="lp-icon-badge">
                  <ClockCircleOutlined />
                </span>
                <div>
                  <h4>Jam Operasional</h4>
                  {businessHours.map((entry) => (
                    <p key={entry.label} style={{ margin: 0 }}>
                      {entry.label}: {entry.value}
                    </p>
                  ))}
                </div>
              </div>
            </div>

            <div className="lp-map">
              <iframe
                src={contact.maps_embed_url}
                title={`Peta lokasi kantor pengelola ${siteName}`}
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
              />
            </div>
          </Reveal>

          <Reveal delay={120} direction="right" as="div" className="lp-contact-form-card">
            <Form layout="vertical" form={form} onFinish={handleFinish} requiredMark="optional">
              <Form.Item
                label="Nama Lengkap"
                name="name"
                rules={[{ required: true, message: 'Nama wajib diisi' }]}
              >
                <Input placeholder="Nama Anda" autoComplete="name" />
              </Form.Item>
              <Form.Item
                label="Email"
                name="email"
                rules={[
                  { required: true, message: 'Email wajib diisi' },
                  { type: 'email', message: 'Format email tidak valid' },
                ]}
              >
                <Input placeholder="nama@email.com" autoComplete="email" />
              </Form.Item>
              <Form.Item
                label="Nomor Telepon"
                name="phone"
                rules={[{ required: true, message: 'Nomor telepon wajib diisi' }]}
              >
                <Input placeholder="08xx-xxxx-xxxx" autoComplete="tel" />
              </Form.Item>
              <Form.Item
                label="Subjek"
                name="subject"
                rules={[{ required: true, message: 'Subjek wajib diisi' }]}
              >
                <Input placeholder="Topik pesan Anda" />
              </Form.Item>
              <Form.Item
                label="Pesan"
                name="message"
                rules={[{ required: true, message: 'Pesan wajib diisi' }]}
              >
                <TextArea rows={4} placeholder="Tuliskan pesan Anda di sini" />
              </Form.Item>
              <Button type="primary" htmlType="submit" icon={<SendOutlined />} loading={submitting} block>
                Kirim Pesan
              </Button>
            </Form>
          </Reveal>
        </div>
      </div>
    </section>
  );
}
