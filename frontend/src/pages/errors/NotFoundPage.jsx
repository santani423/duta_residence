import { Button } from 'antd';
import { Link } from 'react-router-dom';
import { HttpErrorPage } from '../../components/common/ApiState.jsx';

export default function NotFoundPage() {
  return (
    <HttpErrorPage
      status="404"
      title="Halaman tidak ditemukan"
      subTitle="Alamat yang dibuka tidak tersedia di aplikasi Estate Management."
      action={<Button type="primary"><Link to="/">Dashboard</Link></Button>}
    />
  );
}
