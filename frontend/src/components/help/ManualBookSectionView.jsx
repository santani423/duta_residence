import { Alert, Collapse, Image, Space, Tag, Typography } from 'antd';
import { BulbOutlined, WarningOutlined } from '@ant-design/icons';
import RichText from './RichText.jsx';
import { storageUrl } from '../../services/estateApi.js';
import { formatDateTime } from '../../utils/format.js';

export default function ManualBookSectionView({ section }) {
  if (!section) return null;

  const steps = section.steps || [];
  const tips = section.tips || [];
  const warnings = section.warnings || [];
  const faqs = section.faqs || [];
  const images = section.images || [];

  return (
    <div className="manual-book-section">
      <Typography.Title level={3} style={{ marginTop: 0 }}>{section.title}</Typography.Title>
      {section.summary ? <Typography.Paragraph type="secondary">{section.summary}</Typography.Paragraph> : null}
      <Space size="small" wrap style={{ marginBottom: 16 }}>
        <Tag>Versi {section.version}</Tag>
        {section.updated_at ? <Tag>Diperbarui {formatDateTime(section.updated_at)}</Tag> : null}
      </Space>

      {section.content ? <RichText text={section.content} /> : null}

      {images.length > 0 ? (
        <Space wrap style={{ marginBottom: 16 }}>
          {images.map((image) => (
            <Image key={image.id} src={storageUrl(image.path)} alt={image.original_filename} width={160} />
          ))}
        </Space>
      ) : null}

      {steps.length > 0 ? (
        <div className="section-row">
          <Typography.Title level={5}>Langkah-langkah</Typography.Title>
          <ol style={{ paddingLeft: 20 }}>
            {steps.map((step, index) => (
              <li key={index} style={{ marginBottom: 8 }}>
                <Typography.Text strong>{step.title}</Typography.Text>
                {step.description ? <div><RichText text={step.description} /></div> : null}
              </li>
            ))}
          </ol>
        </div>
      ) : null}

      {tips.length > 0 ? (
        <Alert
          className="section-row"
          type="info"
          showIcon
          icon={<BulbOutlined />}
          message="Tips"
          description={(
            <ul style={{ marginBottom: 0, paddingLeft: 18 }}>
              {tips.map((tip, index) => <li key={index}>{tip}</li>)}
            </ul>
          )}
        />
      ) : null}

      {warnings.length > 0 ? (
        <Alert
          className="section-row"
          type="warning"
          showIcon
          icon={<WarningOutlined />}
          message="Perhatian"
          description={(
            <ul style={{ marginBottom: 0, paddingLeft: 18 }}>
              {warnings.map((warning, index) => <li key={index}>{warning}</li>)}
            </ul>
          )}
        />
      ) : null}

      {faqs.length > 0 ? (
        <div className="section-row">
          <Typography.Title level={5}>Pertanyaan Umum</Typography.Title>
          <Collapse
            items={faqs.map((faq, index) => ({
              key: index,
              label: faq.question,
              children: <RichText text={faq.answer} />,
            }))}
          />
        </div>
      ) : null}
    </div>
  );
}
