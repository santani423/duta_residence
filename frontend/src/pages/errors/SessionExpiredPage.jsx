import { Button } from 'antd';
import { Link } from 'react-router-dom';
import { HttpErrorPage } from '../../components/common/ApiState.jsx';

export default function SessionExpiredPage() {
  return (
    <HttpErrorPage
      status="warning"
      title="Sesi berakhir"
      subTitle="Token login sudah tidak valid atau sesi Anda berakhir. Login kembali untuk melanjutkan."
      action={<Button type="primary"><Link to="/login">Login ulang</Link></Button>}
    />
  );
}
