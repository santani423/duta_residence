import { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Button, Card, Space, Typography } from 'antd';
import { api } from '../../services/estateApi.js';

const CARD_WIDTH = 320;

function computeCardStyle(rect, placement) {
  if (!rect) {
    return { position: 'fixed', top: '50%', left: '50%', transform: 'translate(-50%, -50%)', width: CARD_WIDTH };
  }

  const left = Math.min(Math.max(rect.left, 16), window.innerWidth - CARD_WIDTH - 16);
  const style = { position: 'fixed', left, width: CARD_WIDTH };

  if (placement === 'top') {
    style.bottom = Math.max(window.innerHeight - rect.top + 12, 16);
  } else {
    style.top = Math.min(rect.bottom + 12, window.innerHeight - 200);
  }

  return style;
}

/**
 * Mesin Guided Tour custom (tanpa dependency baru): menyorot elemen lewat atribut
 * data-tour-id, menampilkan kartu penjelasan mengambang, dan menyimpan progres per
 * tur/user lewat api.guidedTours.progress setelah selesai atau dilewati.
 */
export default function GuidedTour({ tour, onClose }) {
  const [index, setIndex] = useState(0);
  const [rect, setRect] = useState(null);
  const steps = tour?.steps || [];
  const step = steps[index];

  const measure = useCallback(() => {
    if (!step) return null;
    const element = document.querySelector(`[data-tour-id="${step.target}"]`);
    if (!element) {
      setRect(null);
      return null;
    }
    element.classList.add('gd-tour-highlight');
    setRect(element.getBoundingClientRect());
    return element;
  }, [step]);

  // Mengukur posisi elemen target adalah sinkronisasi dengan DOM (sistem eksternal), tapi
  // pemanggilan setState-nya sengaja ditunda lewat requestAnimationFrame supaya tidak
  // memicu setState sinkron langsung di badan effect.
  useEffect(() => {
    let current = null;
    const frame = requestAnimationFrame(() => {
      current = measure();
      current?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    });
    window.addEventListener('resize', measure);
    window.addEventListener('scroll', measure, true);
    return () => {
      cancelAnimationFrame(frame);
      window.removeEventListener('resize', measure);
      window.removeEventListener('scroll', measure, true);
      current?.classList.remove('gd-tour-highlight');
    };
  }, [measure]);

  async function finish(status) {
    try {
      await api.guidedTours.progress(tour.id, { status, last_step: index });
    } finally {
      onClose();
    }
  }

  if (!step) return null;

  return createPortal(
    <>
      <div className="gd-tour-overlay" onClick={() => finish('skipped')} />
      <Card size="small" className="gd-tour-card" style={computeCardStyle(rect, step.placement)} title={step.title}>
        <Typography.Paragraph>{step.content}</Typography.Paragraph>
        <Space style={{ width: '100%', justifyContent: 'space-between' }}>
          <Typography.Text type="secondary">{index + 1} / {steps.length}</Typography.Text>
          <Space>
            <Button size="small" onClick={() => finish('skipped')}>Lewati</Button>
            {index > 0 ? <Button size="small" onClick={() => setIndex((current) => current - 1)}>Sebelumnya</Button> : null}
            {index < steps.length - 1 ? (
              <Button size="small" type="primary" onClick={() => setIndex((current) => current + 1)}>Berikutnya</Button>
            ) : (
              <Button size="small" type="primary" onClick={() => finish('completed')}>Selesai</Button>
            )}
          </Space>
        </Space>
      </Card>
    </>,
    document.body,
  );
}
