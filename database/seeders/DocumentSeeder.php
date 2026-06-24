<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\Customer;
use App\Models\ManagedFile;
use App\Models\MaintenanceRequest;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $uploader = User::where('username', 'admin.estate')->first() ?: User::where('username', 'root')->first();

        Customer::where('status_id', 'AK')->limit(90)->get()->each(fn (Customer $customer) => $this->file($customer, $uploader, 'Perjanjian-'.$customer->id.'.pdf', 'agreements/'.$customer->id.'.pdf'));
        Billing::limit(160)->get()->each(fn (Billing $billing) => $this->file($billing, $uploader, 'Invoice-BIL-'.$billing->id.'.pdf', 'invoices/BIL-'.$billing->id.'.pdf'));
        Receipt::limit(120)->get()->each(fn (Receipt $receipt) => $this->file($receipt, $uploader, 'Receipt-'.$receipt->number.'.pdf', 'receipts/'.$receipt->number.'.pdf'));
        PaymentTransaction::whereNotNull('manual_proof_path')->limit(80)->get()->each(fn (PaymentTransaction $payment) => $this->file($payment, $uploader, 'Bukti-'.$payment->transaction_number.'.jpg', $payment->manual_proof_path));
        MaintenanceRequest::limit(80)->get()->each(fn (MaintenanceRequest $maintenance) => $this->file($maintenance, $uploader, 'Maintenance-'.$maintenance->id.'.pdf', 'maintenance/report-'.$maintenance->id.'.pdf'));
    }

    private function file(Model $entity, ?User $uploader, string $original, string $path): void
    {
        $extension = pathinfo($original, PATHINFO_EXTENSION) ?: 'pdf';
        ManagedFile::updateOrCreate(
            ['entity_type' => $entity::class, 'entity_id' => $entity->getKey(), 'path' => $path],
            [
                'original_filename' => $original,
                'stored_filename' => basename($path),
                'disk' => 'public',
                'mime_type' => $extension === 'pdf' ? 'application/pdf' : 'image/'.$extension,
                'extension' => $extension,
                'size' => rand(80_000, 1_800_000),
                'uploaded_by' => $uploader?->id,
            ]
        );
    }
}
