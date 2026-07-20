import { Button } from 'antd';
import { Link } from 'react-router-dom';
import { HttpErrorPage } from '../../components/common/ApiState.jsx';
import { useAuth } from '../../state/AuthContext.jsx';

export default function ForbiddenPage() {
  const { hasRole } = useAuth();
  const fallbackTo = hasRole('customer') ? '/resident/dashboard' : '/profile';

  return (
    <HttpErrorPage
      status="403"
      title="Akses ditolak"
      subTitle="Akun Anda tidak memiliki permission untuk membuka halaman atau aksi ini."
      action={<Button type="primary"><Link to={fallbackTo}>Kembali</Link></Button>}
    />
  );
}
