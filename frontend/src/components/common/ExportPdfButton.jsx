import { Button, message } from 'antd';
import { FilePdfOutlined } from '@ant-design/icons';
import { useState } from 'react';
import Can from './Can.jsx';
import { api } from '../../services/estateApi.js';
import { printPdf } from '../../utils/download.js';
import { getApiErrorMessage } from '../../utils/apiError.js';

/**
 * "Export PDF" for a payment/billing table. Pass `dataset` + the same filters the table is using
 * (the server rebuilds the whole filtered table, not just the visible page), or a `request`
 * function for a document that already has its own endpoint. Only shown to users who may
 * generate documents and, when `permission` is given, may also view the table's module.
 */
export default function ExportPdfButton({ dataset, params, request, filename, permission, label = 'Export PDF', size, type = 'default', disabled = false }) {
  const [loading, setLoading] = useState(false);

  async function run() {
    setLoading(true);
    try {
      await printPdf(request || (() => api.documents.tablePdf(dataset, params)), filename || `${dataset}.pdf`);
    } catch (error) {
      message.error(getApiErrorMessage(error, 'Gagal membuat PDF'));
    } finally {
      setLoading(false);
    }
  }

  return (
    <Can all={['documents.generate', ...(permission ? [permission] : [])]}>
      <Button icon={<FilePdfOutlined />} size={size} type={type} loading={loading} disabled={disabled || loading} onClick={run}>{label}</Button>
    </Can>
  );
}
