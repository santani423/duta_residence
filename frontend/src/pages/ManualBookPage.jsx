import { Button, Card, Col, Collapse, Empty, Input, Progress, Row, Space, Typography } from 'antd';
import { ArrowLeftOutlined, ArrowRightOutlined, CheckOutlined, PrinterOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import ManualBookSectionView from '../components/help/ManualBookSectionView.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import { api } from '../services/estateApi.js';
import { moduleLabel } from '../utils/helpModules.js';

export default function ManualBookPage() {
  const { module: moduleParam, section: slugParam } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');

  const query = useQuery({ queryKey: ['manual-book-sections'], queryFn: api.manualBook.list });
  const sections = query.data?.data || [];

  const markRead = useMutation({
    mutationFn: (id) => api.manualBook.markRead(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['manual-book-sections'] }),
  });

  const grouped = useMemo(() => {
    const keyword = search.trim().toLowerCase();
    const map = new Map();
    sections
      .filter((section) => !keyword
        || section.title.toLowerCase().includes(keyword)
        || section.summary?.toLowerCase().includes(keyword)
        || section.content?.toLowerCase().includes(keyword))
      .forEach((section) => {
        if (!map.has(section.module)) map.set(section.module, []);
        map.get(section.module).push(section);
      });
    return map;
  }, [sections, search]);

  const flatOrder = useMemo(() => Array.from(grouped.values()).flat(), [grouped]);

  const activeSection = moduleParam && slugParam
    ? sections.find((item) => item.module === moduleParam && item.slug === slugParam)
    : moduleParam
      ? sections.find((item) => item.module === moduleParam)
      : null;

  // Tandai otomatis sebagai sudah dibaca setelah pengguna benar-benar membaca beberapa
  // detik, bukan langsung saat halaman dibuka (supaya indikator progres lebih bermakna).
  useEffect(() => {
    if (!activeSection || activeSection.is_read) return undefined;
    const timer = setTimeout(() => markRead.mutate(activeSection.id), 4000);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeSection?.id]);

  if (query.isLoading) return <LoadingState rows={10} />;
  if (query.isError) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const readCount = sections.filter((item) => item.is_read).length;
  const totalCount = sections.length;
  const progressPercent = totalCount ? Math.round((readCount / totalCount) * 100) : 0;
  const currentIndex = activeSection ? flatOrder.findIndex((item) => item.id === activeSection.id) : -1;
  const prevSection = currentIndex > 0 ? flatOrder[currentIndex - 1] : null;
  const nextSection = currentIndex >= 0 && currentIndex < flatOrder.length - 1 ? flatOrder[currentIndex + 1] : null;

  function goTo(section) {
    navigate(`/manual-book/${section.module}/${section.slug}`);
  }

  const breadcrumbs = [{ label: 'Manual Book', to: '/manual-book' }];
  if (activeSection) {
    breadcrumbs.push({ label: moduleLabel(activeSection.module), to: `/manual-book/${activeSection.module}` });
    breadcrumbs.push({ label: activeSection.title });
  }

  return (
    <section className="manual-book-page">
      <PageHeader
        title="Manual Book"
        subtitle="Panduan penggunaan aplikasi yang otomatis menyesuaikan dengan role dan hak akses Anda."
        breadcrumbs={breadcrumbs}
        helpModule="general"
      />

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8} lg={7}>
          <Card size="small">
            <Input.Search placeholder="Cari panduan..." value={search} onChange={(event) => setSearch(event.target.value)} allowClear />
          </Card>
          <Card size="small" title={`Progres Membaca (${readCount}/${totalCount})`} className="section-row">
            <Progress percent={progressPercent} size="small" />
          </Card>
          <Card size="small" title="Daftar Isi" className="section-row">
            {grouped.size === 0 ? (
              <Empty description="Tidak ditemukan" image={Empty.PRESENTED_IMAGE_SIMPLE} />
            ) : (
              <Collapse
                defaultActiveKey={moduleParam ? [moduleParam] : Array.from(grouped.keys())}
                items={Array.from(grouped.entries()).map(([module, items]) => ({
                  key: module,
                  label: moduleLabel(module),
                  children: (
                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                      {items.map((item) => (
                        <Button
                          key={item.id}
                          type={activeSection?.id === item.id ? 'primary' : 'text'}
                          block
                          style={{ textAlign: 'left', justifyContent: 'flex-start' }}
                          icon={item.is_read ? <CheckOutlined /> : null}
                          onClick={() => goTo(item)}
                        >
                          {item.title}
                        </Button>
                      ))}
                    </Space>
                  ),
                }))}
              />
            )}
          </Card>
        </Col>
        <Col xs={24} md={16} lg={17}>
          <Card>
            {!activeSection ? (
              grouped.size === 0 ? (
                <Empty description="Belum ada panduan yang tersedia untuk role Anda. Hubungi admin melalui Pusat Bantuan jika Anda merasa seharusnya punya akses." />
              ) : (
                <>
                  <Typography.Title level={3} style={{ marginTop: 0 }}>Selamat Datang di Manual Book</Typography.Title>
                  <Typography.Paragraph>
                    Pilih salah satu bab di daftar isi sebelah kiri untuk mulai membaca panduan penggunaan aplikasi.
                  </Typography.Paragraph>
                </>
              )
            ) : (
              <>
                <ManualBookSectionView section={activeSection} />
                <Space className="section-row" style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                  <Button icon={<ArrowLeftOutlined />} disabled={!prevSection} onClick={() => prevSection && goTo(prevSection)}>
                    Sebelumnya
                  </Button>
                  <Space>
                    <Button icon={<PrinterOutlined />} onClick={() => window.print()}>Cetak / PDF</Button>
                    {!activeSection.is_read ? (
                      <Button icon={<CheckOutlined />} onClick={() => markRead.mutate(activeSection.id)}>Tandai Sudah Dibaca</Button>
                    ) : null}
                  </Space>
                  <Button icon={<ArrowRightOutlined />} disabled={!nextSection} onClick={() => nextSection && goTo(nextSection)}>
                    Berikutnya
                  </Button>
                </Space>
              </>
            )}
          </Card>
        </Col>
      </Row>
    </section>
  );
}
