import { Alert, Button, Col, Descriptions, Image, Modal, Row, Skeleton, Space, Typography, message, theme } from 'antd';
import { EnvironmentOutlined, FileOutlined } from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { cloneElement, useCallback, useEffect, useRef, useState } from 'react';
import StatusBadge, { VisitSignatureBadge, visitSignatureState } from '../common/StatusBadge.jsx';
import { EmptyData, ErrorState, LoadingState } from '../common/ApiState.jsx';
import { api } from '../../services/estateApi.js';
import { formatDateTime } from '../../utils/format.js';
import { getApiErrorMessage } from '../../utils/apiError.js';
import { printPdf } from '../../utils/download.js';

// Tombol/kolom "Bukti" hanya untuk user yang boleh melihat bukti kunjungan. Modal memuat
// GET /visits/{id}/evidence yang hanya menerima collector-evidence.view, jadi izin unduh saja tidak cukup.
// eslint-disable-next-line react-refresh/only-export-components
export const VISIT_EVIDENCE_PERMISSIONS = ['collector-evidence.view'];

const IMAGE_HEIGHT = 160;

// Tanda tangan berupa PNG transparan bertinta hitam: tanpa latar putih tidak terbaca di dark mode
// maupun di atas mask pratinjau yang gelap.
const PAPER_BACKGROUND = '#fff';
const paperPreview = {
  imageRender: (node) => cloneElement(node, { style: { ...node.props.style, background: PAPER_BACKGROUND } }),
};

function unitText(visit) {
  const unit = visit.unit || {};
  const location = [unit.cluster?.name, unit.block && unit.lot_number ? `${unit.block}/${unit.lot_number}` : null].filter(Boolean).join(' ');
  return [unit.id || visit.unit_id, location].filter(Boolean).join(' - ') || '-';
}

function fileName(item) {
  return item.file_path ? item.file_path.split('/').pop() : `bukti-kunjungan-${item.id}`;
}

/**
 * Berkas bukti butuh Bearer token, jadi <img src> langsung ke API akan 401. Berkas diambil sebagai
 * Blob lewat axios lalu dijadikan object URL yang di-revoke saat unmount / file_url berganti.
 */
function useEvidenceFile(fileUrl) {
  const [result, setResult] = useState({ fileUrl: null, url: null, error: null });

  useEffect(() => {
    if (!fileUrl) return undefined;
    let cancelled = false;
    let objectUrl = null;
    api.visitEvidence.file(fileUrl)
      .then((blob) => {
        if (cancelled) return;
        objectUrl = URL.createObjectURL(blob);
        setResult({ fileUrl, url: objectUrl, error: null });
      })
      .catch((error) => {
        if (!cancelled) setResult({ fileUrl, url: null, error });
      });

    return () => {
      cancelled = true;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [fileUrl]);

  // Hasil milik file_url sebelumnya diabaikan sampai berkas yang baru selesai dimuat.
  return result.fileUrl === fileUrl ? result : { url: null, error: null };
}

function EvidenceImage({ item, alt, paper = false, onUrlChange }) {
  const { token } = theme.useToken();
  const { url, error } = useEvidenceFile(item.file_url);
  const { id } = item;
  const frame = {
    borderRadius: token.borderRadius,
    border: `1px solid ${token.colorBorderSecondary}`,
    background: paper ? PAPER_BACKGROUND : token.colorFillQuaternary,
  };

  // Object URL yang sudah termuat dilaporkan ke PhotoGallery untuk daftar pratinjaunya.
  useEffect(() => {
    if (!url || !onUrlChange) return undefined;
    onUrlChange(id, url);
    return () => onUrlChange(id, null);
  }, [onUrlChange, id, url]);

  if (url) {
    return (
      <Image
        src={url}
        alt={alt}
        width="100%"
        height={IMAGE_HEIGHT}
        style={{ ...frame, objectFit: 'contain' }}
        preview={paper ? paperPreview : true}
      />
    );
  }

  if (item.file_url && !error) {
    return <Skeleton.Image active styles={{ root: { display: 'block', width: '100%' } }} style={{ width: '100%', height: IMAGE_HEIGHT }} />;
  }

  return (
    <div style={{ ...frame, height: IMAGE_HEIGHT, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 12, textAlign: 'center' }}>
      <Typography.Text type="secondary">
        {error ? getApiErrorMessage(error, 'Berkas bukti gagal dimuat.') : 'Berkas bukti tidak tersedia.'}
      </Typography.Text>
    </div>
  );
}

function EvidenceMeta({ item }) {
  const { token } = theme.useToken();
  return (
    <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>
      {formatDateTime(item.captured_at)} · oleh {item.uploader?.name || '-'}
    </Typography.Text>
  );
}

function EvidenceSection({ title, count, children }) {
  return (
    <section>
      <Typography.Title level={5} style={{ marginTop: 0 }}>{title} ({count})</Typography.Title>
      {children}
    </section>
  );
}

function ImageGrid({ items, alt, paper = false, onUrlChange }) {
  return (
    <Row gutter={[12, 12]}>
      {items.map((item, index) => (
        <Col key={item.id} xs={24} sm={12} md={paper ? 12 : 8}>
          <Space orientation="vertical" size={4} style={{ width: '100%' }}>
            <EvidenceImage item={item} alt={`${alt} ${index + 1}`} paper={paper} onUrlChange={onUrlChange} />
            <EvidenceMeta item={item} />
          </Space>
        </Col>
      ))}
    </Row>
  );
}

/**
 * Foto bukti dalam satu pratinjau. Daftar pratinjau disusun sendiri menurut urutan grid: tanpa `items`,
 * PreviewGroup mengurutkan foto menurut kapan <Image>-nya dipasang, padahal <Image> baru dipasang
 * setelah berkasnya termuat, sehingga Prev/Next mengikuti foto mana yang lebih dulu selesai diunduh.
 * Selama pratinjau terbuka daftarnya dibekukan agar foto yang baru termuat tidak menggeser foto yang
 * sedang dilihat.
 */
function PhotoGallery({ items, alt }) {
  const [urls, setUrls] = useState({});
  const [openItems, setOpenItems] = useState(null);
  const onUrlChange = useCallback((id, url) => {
    setUrls((current) => {
      const next = { ...current };
      if (url) next[id] = url;
      else delete next[id];
      return next;
    });
  }, []);
  const previewItems = items.map((item) => urls[item.id]).filter(Boolean);

  return (
    <Image.PreviewGroup
      items={openItems || previewItems}
      preview={{ onOpenChange: (open) => setOpenItems(open ? previewItems : null) }}
    >
      <ImageGrid items={items} alt={alt} onUrlChange={onUrlChange} />
    </Image.PreviewGroup>
  );
}

function GpsList({ items }) {
  return (
    <Space orientation="vertical" size={8} style={{ width: '100%' }}>
      {items.map((item) => {
        const latitude = Number(item.latitude);
        const longitude = Number(item.longitude);
        const hasPoint = item.latitude !== null && item.longitude !== null && Number.isFinite(latitude) && Number.isFinite(longitude);
        return (
          <Space key={item.id} orientation="vertical" size={0}>
            {hasPoint ? (
              <a href={`https://www.google.com/maps?q=${latitude},${longitude}`} target="_blank" rel="noreferrer">
                <EnvironmentOutlined /> {latitude}, {longitude}
              </a>
            ) : <Typography.Text type="secondary">Koordinat tidak tersedia</Typography.Text>}
            <EvidenceMeta item={item} />
          </Space>
        );
      })}
    </Space>
  );
}

function DocumentList({ items }) {
  // Dokumen (mis. PDF) dibuka di tab baru lewat axios agar Bearer token ikut terkirim.
  async function openDocument(item) {
    try {
      await printPdf(() => api.visitEvidence.file(item.file_url), fileName(item));
    } catch (error) {
      message.error(getApiErrorMessage(error, 'Berkas bukti gagal dibuka.'));
    }
  }

  return (
    <Space orientation="vertical" size={8} style={{ width: '100%' }}>
      {items.map((item) => (
        <Space key={item.id} wrap align="center">
          <FileOutlined />
          <Space orientation="vertical" size={0}>
            <Typography.Text>{fileName(item)}</Typography.Text>
            <EvidenceMeta item={item} />
          </Space>
          {item.file_url ? <Button size="small" onClick={() => openDocument(item)}>Buka</Button> : null}
        </Space>
      ))}
    </Space>
  );
}

// Badge tanda tangan dari bukti yang baru dimuat. Daftar bukti tidak membawa lifecycle kunjungan,
// jadi status "menunggu" tetap diambil dari baris tabel.
function freshSignatureState(rowState, signatureCount) {
  if (signatureCount) return 'signed';
  return rowState === 'awaiting' ? 'awaiting' : null;
}

function VisitEvidenceContent({ visit, onStale }) {
  const query = useQuery({
    queryKey: ['visit-evidence', visit.id],
    queryFn: () => api.visitEvidence.list(visit.id),
    // Tanda tangan bisa masuk kapan saja dari HP collector, jadi selalu muat ulang saat dibuka.
    staleTime: 0,
  });
  const staleReportedRef = useRef(false);
  const items = query.data?.data || [];
  const byType = (type) => items.filter((item) => item.type === type);
  const signatures = byType('signature');
  const photos = byType('photo');
  const gps = byType('gps');
  const documents = byType('document');
  // Setelah dimuat ulang saat modal dibuka, bukti bisa lebih baru dari baris tabel (penghuni baru saja
  // tanda tangan, atau tanda tangannya dihapus) -> badge mengikuti bukti yang benar-benar ada.
  const rowState = visitSignatureState(visit);
  const fresh = query.isSuccess && query.isFetchedAfterMount;
  const signatureState = fresh ? freshSignatureState(rowState, signatures.length) : rowState;
  // evidence_count hanya dikirim oleh daftar kunjungan penghuni (GET /residents/{id}/visits).
  const hasRowCount = visit.evidence_count !== undefined && visit.evidence_count !== null;
  const rowOutdated = fresh && (signatureState !== rowState || (hasRowCount && items.length !== Number(visit.evidence_count)));

  // Baris tabel tertinggal -> minta halaman induk memuat ulang daftarnya, cukup sekali per modal dibuka.
  useEffect(() => {
    if (!rowOutdated || staleReportedRef.current) return;
    staleReportedRef.current = true;
    onStale?.();
  }, [rowOutdated, onStale]);

  let body;
  if (query.isLoading) {
    body = <LoadingState rows={4} />;
  } else if (query.isError) {
    body = <ErrorState error={query.error} onRetry={query.refetch} />;
  } else if (!items.length) {
    body = <EmptyData description="Belum ada bukti kunjungan." />;
  } else {
    body = (
      <>
        {signatures.length ? (
          <EvidenceSection title="Tanda Tangan Penghuni" count={signatures.length}>
            <ImageGrid items={signatures} alt="Tanda tangan penghuni" paper />
          </EvidenceSection>
        ) : null}
        {photos.length ? (
          <EvidenceSection title="Foto Bukti" count={photos.length}>
            <PhotoGallery items={photos} alt="Foto bukti kunjungan" />
          </EvidenceSection>
        ) : null}
        {gps.length ? (
          <EvidenceSection title="Lokasi GPS" count={gps.length}>
            <GpsList items={gps} />
          </EvidenceSection>
        ) : null}
        {documents.length ? (
          <EvidenceSection title="Dokumen" count={documents.length}>
            <DocumentList items={documents} />
          </EvidenceSection>
        ) : null}
      </>
    );
  }

  return (
    <div className="stack">
      <Descriptions
        size="small"
        bordered
        column={{ xs: 1, sm: 2 }}
        items={[
          { key: 'visit_date', label: 'Tanggal', children: formatDateTime(visit.visit_date) },
          { key: 'unit', label: 'Unit', children: unitText(visit) },
          { key: 'purpose', label: 'Tujuan', children: visit.purpose || '-' },
          { key: 'collector', label: 'Kolektor', children: visit.collector?.name || '-' },
          { key: 'status', label: 'Status', children: <StatusBadge type="visitStatus" value={visit.status} /> },
          { key: 'signature', label: 'Tanda Tangan', children: <VisitSignatureBadge state={signatureState} /> },
        ]}
      />
      {signatureState === 'awaiting' ? (
        <Alert type="warning" showIcon title="Menunggu tanda tangan penghuni — kunjungan belum dihitung selesai." />
      ) : null}
      {body}
    </div>
  );
}

/**
 * Modal bukti kunjungan collector: tanda tangan penghuni, foto, titik GPS, dan dokumen dari
 * GET /visits/{id}/evidence.
 *
 * Props:
 * - visit   : baris kunjungan ({ id, unit|unit_id, purpose, visit_date, collector, status,
 *             has_signature, awaiting_signature, lifecycle, evidence_count? }). null -> modal tertutup.
 * - onClose : dipanggil saat modal ditutup.
 * - onStale : opsional; dipanggil sekali bila bukti yang baru dimuat tidak cocok dengan baris
 *             (tanda tangan/jumlah bukti) agar halaman induk memuat ulang tabelnya.
 */
export default function VisitEvidenceModal({ visit, onClose, onStale }) {
  return (
    <Modal
      title="Bukti Kunjungan"
      open={Boolean(visit)}
      onCancel={onClose}
      footer={<Button onClick={onClose}>Tutup</Button>}
      width={760}
      destroyOnHidden
    >
      {visit ? <VisitEvidenceContent key={visit.id} visit={visit} onStale={onStale} /> : null}
    </Modal>
  );
}
