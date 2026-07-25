import { Collapse } from 'antd';
import RichText from '../../help/RichText.jsx';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import Reveal from '../Reveal.jsx';

export default function FaqSection() {
  const content = useLandingContent();
  const faqs = content?.faqs || [];

  if (!faqs.length) return null;

  return (
    <section id="faq" className="lp-section lp-section-alt">
      <div className="lp-container" style={{ maxWidth: 820 }}>
        <Reveal as="div" className="lp-section-head">
          <span className="lp-eyebrow">FAQ</span>
          <h2>Pertanyaan yang Sering Diajukan</h2>
          <p>Temukan jawaban cepat seputar layanan dan fitur kawasan.</p>
        </Reveal>

        <Reveal as="div">
          <Collapse
            accordion
            bordered={false}
            className="lp-faq-collapse"
            items={faqs.map((faq) => ({
              key: faq.id,
              label: faq.question,
              children: <RichText text={faq.answer} />,
            }))}
          />
        </Reveal>
      </div>
    </section>
  );
}
