import { Button, Drawer, Empty, Tabs } from 'antd';
import { CompassOutlined, QuestionCircleOutlined } from '@ant-design/icons';
import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useHelpVisible } from '../../hooks/useHelpSettings.js';
import { useManualBookModule } from '../../hooks/useManualBook.js';
import { useHelpCenter } from '../../state/HelpCenterContext.jsx';
import ManualBookSectionView from './ManualBookSectionView.jsx';

/**
 * Ikon bantuan di judul halaman (dipasang otomatis oleh PageHeader). Membuka drawer
 * berisi panduan modul terkait dan tombol mulai tur jika modul ini punya Guided Tour.
 * Kalau panduan belum tersedia untuk modul ini, tampilkan pesan informatif, bukan error.
 */
export default function PageHelpButton({ module }) {
  const location = useLocation();
  const navigate = useNavigate();
  const visible = useHelpVisible({ module, page: location.pathname });
  const { sections } = useManualBookModule(module);
  const helpCenter = useHelpCenter();
  const [open, setOpen] = useState(false);
  const tour = helpCenter?.tourFor(module) || helpCenter?.tourFor('general');

  if (!visible) return null;

  return (
    <>
      <Button
        type="text"
        size="small"
        icon={<QuestionCircleOutlined />}
        onClick={() => setOpen(true)}
        aria-label="Bantuan halaman ini"
        data-tour-id="page-title"
      />
      <Drawer title="Panduan Halaman Ini" open={open} onClose={() => setOpen(false)} width={480} destroyOnHidden>
        {tour ? (
          <Button
            className="section-row"
            icon={<CompassOutlined />}
            onClick={() => { setOpen(false); helpCenter.startTour(tour.module); }}
          >
            Mulai Tur Panduan
          </Button>
        ) : null}
        {sections.length === 0 ? (
          <Empty description="Panduan untuk halaman ini belum tersedia." />
        ) : sections.length === 1 ? (
          <ManualBookSectionView section={sections[0]} />
        ) : (
          <Tabs
            items={sections.map((section) => ({
              key: section.slug,
              label: section.title,
              children: <ManualBookSectionView section={section} />,
            }))}
          />
        )}
        <Button type="link" style={{ padding: 0 }} onClick={() => { setOpen(false); navigate(`/manual-book/${module}`); }}>
          Buka Manual Book lengkap
        </Button>
      </Drawer>
    </>
  );
}
