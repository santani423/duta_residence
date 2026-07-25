import { StarFilled } from '@ant-design/icons';
import { Carousel } from 'antd';
import { TESTIMONIALS } from '../../../constants/landingContent.js';
import { useLandingContent } from '../../../state/LandingContentContext.jsx';
import { cmsImageUrl } from '../../../utils/cmsMedia.js';
import Reveal from '../Reveal.jsx';

function initials(name) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();
}

export default function TestimonialsSection() {
  const content = useLandingContent();
  const testimonials = content?.testimonials?.length ? content.testimonials : TESTIMONIALS;

  return (
    <section className="lp-section">
      <div className="lp-container">
        <Reveal as="div" className="lp-section-head">
          <span className="lp-eyebrow">Testimoni</span>
          <h2>Apa Kata Penghuni Kami</h2>
          <p>Pengalaman nyata penghuni yang telah menggunakan aplikasi Estate Management.</p>
        </Reveal>

        <Reveal as="div" style={{ maxWidth: 720, margin: '0 auto' }}>
          <Carousel autoplay dots adaptiveHeight autoplaySpeed={5000} pauseOnHover effect="fade">
            {testimonials.map((testimonial) => {
              const photo = cmsImageUrl(testimonial.photo);

              return (
                <div key={testimonial.name}>
                  <div className="lp-testimonial-card">
                    <div aria-hidden="true">
                      {Array.from({ length: testimonial.rating }).map((_, index) => (
                        <StarFilled key={index} style={{ color: '#f59e0b', marginRight: 4 }} />
                      ))}
                    </div>
                    <p className="lp-testimonial-quote">&ldquo;{testimonial.content}&rdquo;</p>
                    <div className="lp-testimonial-person">
                      {photo ? (
                        <img src={photo} alt={testimonial.name} className="lp-testimonial-avatar" style={{ objectFit: 'cover' }} />
                      ) : (
                        <span className="lp-testimonial-avatar" aria-hidden="true">
                          {initials(testimonial.name)}
                        </span>
                      )}
                      <div>
                        <strong>{testimonial.name}</strong>
                        <span>{testimonial.position}</span>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </Carousel>
        </Reveal>
      </div>
    </section>
  );
}
