import { useEffect, useRef, useState } from 'react';

const DIRECTION_CLASS = {
  up: 'lp-reveal-up',
  left: 'lp-reveal-left',
  right: 'lp-reveal-right',
  scale: 'lp-reveal-scale',
  none: 'lp-reveal-plain',
};

export default function Reveal({ as: Tag = 'div', delay = 0, direction = 'up', className = '', children, ...rest }) {
  const ref = useRef(null);
  const [inView, setInView] = useState(() => typeof IntersectionObserver === 'undefined');

  useEffect(() => {
    const node = ref.current;
    if (!node || typeof IntersectionObserver === 'undefined') return undefined;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setInView(true);
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: '0px 0px -10% 0px' },
    );

    observer.observe(node);
    return () => observer.disconnect();
  }, []);

  const directionClass = DIRECTION_CLASS[direction] || DIRECTION_CLASS.up;

  return (
    <Tag
      ref={ref}
      className={`lp-reveal ${directionClass} ${inView ? 'lp-in-view' : ''} ${className}`}
      style={{ transitionDelay: `${delay}ms` }}
      {...rest}
    >
      {children}
    </Tag>
  );
}
