import { Button } from 'antd';
import { ReadOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';

export default function ManualBookLink({ module, slug, label = 'Lihat Panduan', type = 'link', size = 'small' }) {
  const navigate = useNavigate();
  const target = slug ? `/manual-book/${module}/${slug}` : module ? `/manual-book/${module}` : '/manual-book';

  return (
    <Button type={type} size={size} icon={<ReadOutlined />} style={{ padding: 0 }} onClick={() => navigate(target)}>
      {label}
    </Button>
  );
}
