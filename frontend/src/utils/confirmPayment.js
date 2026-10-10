import { Modal } from 'antd';

// Konfirmasi sebelum pembayaran diproses. `onOk` boleh mengembalikan promise agar tombol OK menampilkan loading;
// error sudah ditangani onError mutation sehingga ditelan di sini supaya modal tetap tertutup.
export function confirmPayment({ title = 'Proses pembayaran?', content, okText = 'Ya, Proses', onOk }) {
  Modal.confirm({
    title,
    content: content ?? 'Pastikan tagihan dan nominal sudah benar sebelum melanjutkan.',
    okText,
    cancelText: 'Periksa Lagi',
    onOk: () => Promise.resolve(onOk?.()).catch(() => undefined),
  });
}
