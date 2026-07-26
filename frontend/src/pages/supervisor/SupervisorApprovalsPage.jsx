import { Button, Card, Descriptions, Drawer, Form, Input, InputNumber, Modal, Select, Space, Tag, message } from 'antd';
import { CheckOutlined, CloseOutlined, EyeOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import PageHeader from '../../components/common/PageHeader.jsx';
import FilterBar from '../../components/common/FilterBar.jsx';
import Can from '../../components/common/Can.jsx';
import ResponsiveTable from '../../components/tables/ResponsiveTable.jsx';
import { useTableState } from '../../hooks/useTableState.js';
import { api } from '../../services/estateApi.js';
import { getApiErrorMessage, mapValidationErrors } from '../../utils/apiError.js';
import { formatCurrency, formatDateTime } from '../../utils/format.js';

const TYPE_LABELS = {
  reversal: 'Pembatalan Transaksi/Pembayaran',
  penalty_waiver: 'Penghapusan Denda',
  installment_plan: 'Cicilan Tunggakan',
  billing_adjustment: 'Penyesuaian Tagihan',
};

const STATUS_COLORS = {
  draft: 'default', submitted: 'gold', under_review: 'blue', approved: 'green',
  rejected: 'red', cancelled: 'default', expired: 'volcano',
};

export default function SupervisorApprovalsPage() {
  const table = useTableState();
  const [detail, setDetail] = useState(null);
  const [decisionModal, setDecisionModal] = useState(null); // { record, action: 'approve'|'reject' }
  const [newRequestModal, setNewRequestModal] = useState(null); // 'installment_plan' | 'billing_adjustment'
  const [decisionForm] = Form.useForm();
  const [installmentForm] = Form.useForm();
  const [adjustmentForm] = Form.useForm();
  const queryClient = useQueryClient();

  const list = useQuery({ queryKey: ['approvals', table.params], queryFn: () => api.approvals.list(table.params) });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['approvals'] });
  }

  const decide = useMutation({
    mutationFn: ({ id, action, values }) => (action === 'approve' ? api.approvals.approve(id, values) : api.approvals.reject(id, values)),
    onSuccess: () => {
      message.success('Keputusan berhasil disimpan.');
      setDecisionModal(null);
      invalidate();
    },
    onError: (error) => {
      decisionForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const submitInstallmentPlan = useMutation({
    mutationFn: (values) => api.approvals.submitInstallmentPlan(values),
    onSuccess: () => {
      message.success('Pengajuan rencana cicilan berhasil dikirim.');
      setNewRequestModal(null);
      installmentForm.resetFields();
      invalidate();
    },
    onError: (error) => {
      installmentForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const submitBillingAdjustment = useMutation({
    mutationFn: (values) => api.approvals.submitBillingAdjustment(values),
    onSuccess: () => {
      message.success('Pengajuan penyesuaian tagihan berhasil dikirim.');
      setNewRequestModal(null);
      adjustmentForm.resetFields();
      invalidate();
    },
    onError: (error) => {
      adjustmentForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <section>
      <PageHeader
        title="Pusat Persetujuan"
        subtitle="Tinjau dan putuskan pengajuan cicilan tunggakan, penyesuaian tagihan, keringanan denda, dan pembatalan transaksi/pembayaran."
        breadcrumbs={[{ label: 'Supervisor' }, { label: 'Pusat Persetujuan' }]}
        onRefresh={list.refetch}
        extra={
          <Space>
            <Can permission="installment-plans.submit">
              <Button icon={<PlusOutlined />} onClick={() => setNewRequestModal('installment_plan')}>Ajukan Cicilan</Button>
            </Can>
            <Can permission="billing-adjustments.submit">
              <Button icon={<PlusOutlined />} onClick={() => setNewRequestModal('billing_adjustment')}>Ajukan Penyesuaian Tagihan</Button>
            </Can>
          </Space>
        }
      />

      <FilterBar>
        <Select
          allowClear
          placeholder="Jenis Pengajuan"
          options={Object.entries(TYPE_LABELS).map(([value, label]) => ({ value, label }))}
          value={table.filters.type}
          onChange={(value) => table.setFilters({ ...table.filters, type: value })}
          className="filter-input"
        />
        <Select
          allowClear
          placeholder="Status"
          options={['draft', 'submitted', 'under_review', 'approved', 'rejected', 'cancelled', 'expired'].map((s) => ({ value: s, label: s }))}
          value={table.filters.status}
          onChange={(value) => table.setFilters({ ...table.filters, status: value })}
          className="filter-input"
        />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={list}
          onChange={table.handleTableChange}
          scrollX={1100}
          columns={[
            { title: 'No. Pengajuan', dataIndex: 'request_number', width: 140 },
            { title: 'Jenis', render: (_, r) => TYPE_LABELS[r.type] || r.type, width: 200 },
            { title: 'Pemohon', dataIndex: ['requester', 'name'], width: 150 },
            { title: 'Nominal', dataIndex: 'amount', render: (v) => (v ? formatCurrency(v) : '-'), width: 140 },
            { title: 'Status', dataIndex: 'status', render: (v) => <Tag color={STATUS_COLORS[v] || 'default'}>{v}</Tag>, width: 120 },
            { title: 'Diajukan', dataIndex: 'submitted_at', render: formatDateTime, width: 160 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 200,
              render: (_, record) => (
                <Space>
                  <Button size="small" icon={<EyeOutlined />} onClick={() => setDetail(record)}>Detail</Button>
                  {['submitted', 'under_review'].includes(record.status) && (
                    <Can permission="approvals.approve">
                      <Button size="small" type="primary" icon={<CheckOutlined />} onClick={() => { decisionForm.resetFields(); setDecisionModal({ record, action: 'approve' }); }} />
                    </Can>
                  )}
                  {['submitted', 'under_review'].includes(record.status) && (
                    <Can permission="approvals.reject">
                      <Button size="small" danger icon={<CloseOutlined />} onClick={() => { decisionForm.resetFields(); setDecisionModal({ record, action: 'reject' }); }} />
                    </Can>
                  )}
                </Space>
              ),
            },
          ]}
        />
      </Card>

      <Drawer title={`Detail Pengajuan ${detail?.request_number || ''}`} open={Boolean(detail)} onClose={() => setDetail(null)} width={480}>
        {detail ? (
          <Descriptions column={1} bordered size="small">
            <Descriptions.Item label="Jenis">{TYPE_LABELS[detail.type] || detail.type}</Descriptions.Item>
            <Descriptions.Item label="Status"><Tag color={STATUS_COLORS[detail.status]}>{detail.status}</Tag></Descriptions.Item>
            <Descriptions.Item label="Pemohon">{detail.requester?.name || '-'}</Descriptions.Item>
            <Descriptions.Item label="Kolektor Terkait">{detail.related_collector?.name || '-'}</Descriptions.Item>
            <Descriptions.Item label="Unit Terkait">{detail.related_unit_id || '-'}</Descriptions.Item>
            <Descriptions.Item label="Nominal">{detail.amount ? formatCurrency(detail.amount) : '-'}</Descriptions.Item>
            <Descriptions.Item label="Alasan">{detail.reason || '-'}</Descriptions.Item>
            <Descriptions.Item label="Catatan Supervisor">{detail.supervisor_notes || '-'}</Descriptions.Item>
            <Descriptions.Item label="Diputuskan Oleh">{detail.decider?.name || '-'}</Descriptions.Item>
            <Descriptions.Item label="Waktu Keputusan">{detail.decided_at ? formatDateTime(detail.decided_at) : '-'}</Descriptions.Item>
          </Descriptions>
        ) : null}
      </Drawer>

      <Modal
        title={decisionModal?.action === 'approve' ? 'Setujui Pengajuan' : 'Tolak Pengajuan'}
        open={Boolean(decisionModal)}
        onCancel={() => setDecisionModal(null)}
        onOk={() => decisionForm.submit()}
        confirmLoading={decide.isPending}
        okButtonProps={{ danger: decisionModal?.action === 'reject' }}
        destroyOnHidden
      >
        {decisionModal ? <p>Pengajuan <strong>{decisionModal.record.request_number}</strong> ({TYPE_LABELS[decisionModal.record.type]}).</p> : null}
        <Form
          form={decisionForm}
          layout="vertical"
          onFinish={(values) => decide.mutate({ id: decisionModal.record.id, action: decisionModal.action, values })}
        >
          <Form.Item
            label="Catatan"
            name="notes"
            rules={decisionModal?.action === 'reject' ? [{ required: true, message: 'Alasan penolakan wajib diisi' }] : []}
          >
            <Input.TextArea rows={3} placeholder={decisionModal?.action === 'reject' ? 'Jelaskan alasan penolakan' : 'Catatan (opsional)'} />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Ajukan Rencana Cicilan Tunggakan"
        open={newRequestModal === 'installment_plan'}
        onCancel={() => setNewRequestModal(null)}
        onOk={() => installmentForm.submit()}
        confirmLoading={submitInstallmentPlan.isPending}
        destroyOnHidden
      >
        <Form form={installmentForm} layout="vertical" onFinish={submitInstallmentPlan.mutate}>
          <Form.Item label="ID Unit" name="unit_id" rules={[{ required: true, message: 'ID unit wajib diisi' }]}>
            <Input />
          </Form.Item>
          <Form.Item label="Total Tunggakan (Rp)" name="total_outstanding" rules={[{ required: true, message: 'Wajib diisi' }]}>
            <InputNumber min={1} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Jumlah Cicilan" name="number_of_installments" rules={[{ required: true, message: 'Wajib diisi' }]}>
            <InputNumber min={2} max={24} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Tanggal Mulai" name="start_date" rules={[{ required: true, message: 'Wajib diisi' }]}>
            <Input type="date" />
          </Form.Item>
          <Form.Item label="Alasan" name="reason" rules={[{ required: true, message: 'Alasan wajib diisi' }]}>
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Ajukan Penyesuaian Tagihan"
        open={newRequestModal === 'billing_adjustment'}
        onCancel={() => setNewRequestModal(null)}
        onOk={() => adjustmentForm.submit()}
        confirmLoading={submitBillingAdjustment.isPending}
        destroyOnHidden
      >
        <Form form={adjustmentForm} layout="vertical" onFinish={submitBillingAdjustment.mutate}>
          <Form.Item label="ID Tagihan" name="billing_id" rules={[{ required: true, message: 'ID tagihan wajib diisi' }]}>
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Jenis Penyesuaian" name="adjustment_type" rules={[{ required: true, message: 'Pilih jenis' }]}>
            <Select options={[
              { value: 'discount', label: 'Diskon' },
              { value: 'penalty', label: 'Denda' },
              { value: 'principal_correction', label: 'Koreksi Pokok Tagihan' },
            ]}
            />
          </Form.Item>
          <Form.Item label="Nilai Baru (Rp)" name="new_value" rules={[{ required: true, message: 'Wajib diisi' }]}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Alasan" name="reason" rules={[{ required: true, message: 'Alasan wajib diisi' }]}>
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
