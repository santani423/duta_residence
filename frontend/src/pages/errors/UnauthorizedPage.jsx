import { Button } from 'antd';
import { Link } from 'react-router-dom';
import { HttpErrorPage } from '../../components/common/ApiState.jsx';

export default function UnauthorizedPage() {
  return (
    <HttpErrorPage
      status="401"
      title="Belum login"
      subTitle="Silakan login kembali untuk mengakses aplikasi."
      action={<Button type="primary"><Link to="/login">Login</Link></Button>}
    />
  );
}
