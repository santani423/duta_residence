# GRANDDUTA — Technical Spec Part 4
## Testing, Deployment, CI/CD, Backup (Sections 19–21)

---

## 17. Integrasi Pihak Ketiga

### 17.1 WhatsApp Notification (Fonnte / WA Gateway)

**Trigger Notifikasi:**

| Event | Penerima | Isi Pesan |
|-------|----------|-----------|
| Tagihan bulan baru diterbitkan | Pelanggan (nohp) | "Tagihan bulan [bulan] sebesar Rp[nominal] telah diterbitkan." |
| H-5 jatuh tempo (tanggal 15) | Pelanggan | "Reminder: Tagihan [bulan] Rp[nominal] belum dibayar. Denda 3% berlaku setelah tgl 20." |
| Pembayaran berhasil | Pelanggan | "Pembayaran Rp[total] diterima. No. Kuitansi: GD.YYYY.MM.XXXXXX. Terima kasih." |
| Reversal approved | Loket yang mengajukan | "Pembatalan kuitansi [no] telah disetujui." |
| Reversal rejected | Loket yang mengajukan | "Pembatalan kuitansi [no] ditolak. Alasan: [notes]" |

**Implementasi:**
```php
// NotificationService.php
class NotificationService
{
    public function sendWhatsApp(string $phone, string $message): bool
    {
        $response = Http::withToken(config('grandduta.fonnte_token'))
            ->post('https://api.fonnte.com/send', [
                'target'  => $phone,
                'message' => $message,
                'schedule'=> 0,
            ]);

        return $response->successful();
    }
}
```

**Konfigurasi:**
```php
// config/grandduta.php
return [
    'fonnte_token'      => env('FONNTE_TOKEN'),
    'notification' => [
        'enabled'       => env('NOTIFICATION_ENABLED', false),
        'billing_day'   => 1,       // Generate tagihan hari ke-1
        'reminder_day'  => 15,      // Reminder H-5
        'penalty_day'   => 20,      // Batas tanpa denda
    ],
];
```

### 17.2 PDF Generation (barryvdh/laravel-dompdf)

```bash
composer require barryvdh/laravel-dompdf
```

```php
// config/dompdf.php — konfigurasi utama
'options' => [
    'font_dir'        => storage_path('fonts/'),
    'font_cache'      => storage_path('fonts/'),
    'temp_dir'        => sys_get_temp_dir(),
    'chroot'          => realpath(base_path()),
    'default_font'    => 'DejaVu Sans',
    'dpi'             => 96,
    'enable_php'      => false,
    'enable_remote'   => false,
    'default_paper_size' => 'a4',
],
```

---

## 19. Pengujian Aplikasi

### 19.1 Strategi Testing

Menggunakan **Pest PHP** (wrapper di atas PHPUnit) dengan coverage minimal **80%** untuk Service dan Controller layer.

```bash
composer require pestphp/pest pestphp/pest-plugin-laravel --dev
```

### 19.2 Unit Tests

#### `PenaltyServiceTest`

```php
<?php

use App\Services\PenaltyService;
use Carbon\Carbon;

it('returns zero penalty before day 20', function () {
    Carbon::setTestNow(Carbon::create(2025, 6, 15));  // Tanggal 15

    $service = new PenaltyService();
    expect($service->calculate(350000, true))->toBe(0.0);
});

it('returns 3% penalty after day 20 for eligible billing', function () {
    Carbon::setTestNow(Carbon::create(2025, 6, 21));  // Tanggal 21

    $service = new PenaltyService();
    expect($service->calculate(350000, true))->toBe(10500.0);
});

it('returns zero penalty even after day 20 for non-eligible billing', function () {
    Carbon::setTestNow(Carbon::create(2025, 6, 21));

    $service = new PenaltyService();
    expect($service->calculate(350000, false))->toBe(0.0);
});

it('returns zero for zero amount', function () {
    Carbon::setTestNow(Carbon::create(2025, 6, 25));

    $service = new PenaltyService();
    expect($service->calculate(0, true))->toBe(0.0);
});
```

#### `ReceiptServiceTest`

```php
<?php

use App\Services\ReceiptService;

it('generates receipt number in correct format', function () {
    Carbon::setTestNow(Carbon::create(2025, 6, 21));

    $service = new ReceiptService();
    $number = $service->generateReceiptNumber();

    expect($number)->toMatch('/^GD\.\d{4}\.\d{2}\.\d{6}$/');
    expect($number)->toStartWith('GD.2025.06.');
});

it('increments counter sequentially', function () {
    // Create first receipt
    Receipt::factory()->create([
        'number'           => 'GD.2025.06.000005',
        'transaction_date' => Carbon::create(2025, 6, 21),
    ]);

    Carbon::setTestNow(Carbon::create(2025, 6, 21));
    $service = new ReceiptService();
    $number = $service->generateReceiptNumber();

    expect($number)->toBe('GD.2025.06.000006');
});
```

### 19.3 Feature Tests

#### `AuthTest`

```php
<?php

use App\Models\User;

it('allows valid user to login', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'password' => bcrypt('secret123'),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'testuser',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success', 'data' => ['token', 'user' => ['id', 'username', 'role']]
        ])
        ->assertJson(['success' => true]);
});

it('rejects invalid credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'wronguser',
        'password' => 'wrongpass',
    ]);

    $response->assertStatus(401)
        ->assertJson(['success' => false]);
});

it('rejects inactive user', function () {
    User::factory()->create([
        'username'  => 'inactiveuser',
        'password'  => bcrypt('secret123'),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'inactiveuser',
        'password' => 'secret123',
    ]);

    $response->assertStatus(403);
});
```

#### `CustomerTest`

```php
<?php

use App\Models\User;
use App\Models\Customer;

beforeEach(function () {
    $this->backOfficeUser = User::factory()->withRole('back_office')->create();
    $this->csUser = User::factory()->withRole('cs')->create();
});

it('allows back_office to create customer', function () {
    $response = $this->actingAs($this->backOfficeUser)
        ->postJson('/api/v1/customers', [
            'id'               => 'DO099',
            'name'             => 'Test Customer',
            'cluster_id'       => 'DO',
            'block'            => 'Z',
            'lot_number'       => '99',
            'property_type_id' => 'B',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.id', 'DO099');

    $this->assertDatabaseHas('customers', ['id' => 'DO099']);
});

it('prevents cs from creating customer', function () {
    $response = $this->actingAs($this->csUser)
        ->postJson('/api/v1/customers', [
            'id' => 'DO099',
        ]);

    $response->assertStatus(403);
});

it('rejects duplicate customer id', function () {
    Customer::factory()->create(['id' => 'DO001']);

    $response = $this->actingAs($this->backOfficeUser)
        ->postJson('/api/v1/customers', [
            'id'               => 'DO001',
            'name'             => 'Duplicate',
            'cluster_id'       => 'DO',
            'block'            => 'A',
            'lot_number'       => '01',
            'property_type_id' => 'B',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['id']);
});
```

#### `PaymentTest`

```php
<?php

use App\Models\Billing;
use App\Models\Customer;

it('processes payment and creates receipt', function () {
    Carbon::setTestNow(Carbon::create(2025, 6, 15));  // Sebelum tgl 20

    $loket = User::factory()->withRole('loket')->create();
    $customer = Customer::factory()->active()->create(['id' => 'DO001']);

    $billing = Billing::factory()->create([
        'customer_id'   => 'DO001',
        'year'          => 2025,
        'month'         => 5,
        'amount'        => 350000,
        'status_id'     => '01',
        'approved_by'   => 1,
        'approved_at'   => now(),
    ]);

    $response = $this->actingAs($loket)
        ->postJson('/api/v1/payments/process', [
            'customer_id'       => 'DO001',
            'billing_ids'       => [$billing->id],
            'payment_method_id' => 'C',
            'cashier_name'      => 'Rini',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['receipt_number', 'grand_total']
        ]);

    // Penalty harus 0 karena tgl 15
    $response->assertJsonPath('data.total_penalty', 0);

    // Billing harus lunas
    $this->assertDatabaseHas('billings', [
        'id'        => $billing->id,
        'status_id' => '02',
    ]);
});

it('applies 3% penalty after day 20', function () {
    Carbon::setTestNow(Carbon::create(2025, 6, 21));  // Setelah tgl 20

    $loket = User::factory()->withRole('loket')->create();
    $billing = Billing::factory()->approvedUnpaid()->create([
        'customer_id'           => 'DO001',
        'amount'                => 350000,
        'is_penalty_eligible'   => true,
    ]);

    $response = $this->actingAs($loket)
        ->postJson('/api/v1/payments/process', [
            'customer_id'       => 'DO001',
            'billing_ids'       => [$billing->id],
            'payment_method_id' => 'C',
            'cashier_name'      => 'Rini',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.total_penalty', 10500.0);
});

it('rejects payment of already paid billing', function () {
    $loket = User::factory()->withRole('loket')->create();
    $billing = Billing::factory()->paid()->create(['customer_id' => 'DO001']);

    $response = $this->actingAs($loket)
        ->postJson('/api/v1/payments/process', [
            'customer_id'       => 'DO001',
            'billing_ids'       => [$billing->id],
            'payment_method_id' => 'C',
            'cashier_name'      => 'Rini',
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('error_code', 'ERR_BILLING_PAID');
});
```

#### `ReversalTest`

```php
<?php

it('completes full reversal flow', function () {
    $loket = User::factory()->withRole('loket')->create();
    $backOffice = User::factory()->withRole('back_office')->create();

    // 1. Buat kuitansi
    $receipt = Receipt::factory()->create(['number' => 'GD.2025.06.000001']);
    $billing = Billing::factory()->paid()->create([
        'receipt_number' => 'GD.2025.06.000001',
    ]);

    // 2. Ajukan pembatalan
    $submitResponse = $this->actingAs($loket)
        ->postJson('/api/v1/reversals', [
            'receipt_number' => 'GD.2025.06.000001',
            'reason'         => 'Salah input kasir, tagihan ganda',
        ]);

    $submitResponse->assertStatus(201);
    $reversalId = $submitResponse->json('data.id');

    // 3. Back Office approve
    $approveResponse = $this->actingAs($backOffice)
        ->postJson("/api/v1/reversals/{$reversalId}/approve", [
            'review_notes' => 'Dikonfirmasi, pembayaran ganda',
        ]);

    $approveResponse->assertStatus(200);

    // 4. Verifikasi billing kembali unpaid
    $this->assertDatabaseHas('billings', [
        'id'             => $billing->id,
        'status_id'      => '01',
        'receipt_number' => null,
    ]);
});
```

### 19.4 Factories

```php
// CustomerFactory.php
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id'                    => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{3}'),
            'name'                  => $this->faker->name(),
            'cluster_id'            => 'DO',
            'block'                 => $this->faker->randomLetter(),
            'lot_number'            => $this->faker->numerify('##'),
            'property_type_id'      => 'B',
            'phone'                 => '08' . $this->faker->numerify('##########'),
            'status_id'             => 'AK',
            'is_penalty_eligible'   => true,
            'is_discount_eligible'  => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['status_id' => 'AK']);
    }

    public function inactive(): static
    {
        return $this->state(['status_id' => 'TA']);
    }
}

// BillingFactory.php
class BillingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'           => 'DO001',
            'year'                  => 2025,
            'month'                 => 6,
            'amount'                => 350000,
            'penalty'               => 0,
            'status_id'             => '01',
            'billing_type'          => 'regular',
            'is_penalty_eligible'   => true,
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status_id'     => '02',
            'paid_at'       => now(),
            'receipt_number'=> 'GD.2025.06.000001',
        ]);
    }

    public function approvedUnpaid(): static
    {
        return $this->state([
            'status_id'  => '01',
            'approved_by'=> 1,
            'approved_at'=> now(),
        ]);
    }
}
```

### 19.5 Perintah Testing

```bash
# Jalankan semua test
php artisan test

# Dengan coverage report
php artisan test --coverage --min=80

# Filter test tertentu
php artisan test --filter PaymentTest

# Jalankan test paralel
php artisan test --parallel
```

---

## 20. Deployment & CI/CD

### 20.1 Environment Configuration

#### `.env.example`

```bash
APP_NAME="GRANDDUTA API"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.grandduta.com
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=grandduta_api
DB_USERNAME=grandduta_user
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=
REDIS_PORT=6379

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Sanctum
SANCTUM_STATEFUL_DOMAINS=
SESSION_DOMAIN=

# WhatsApp
FONNTE_TOKEN=
NOTIFICATION_ENABLED=false

# Mail (untuk notifikasi sistem)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="system@grandduta.com"
MAIL_FROM_NAME="GRANDDUTA System"

# Storage
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=info
```

### 20.2 Server Requirements

| Komponen | Minimum | Rekomendasi |
|----------|---------|-------------|
| PHP | 8.2 | 8.3 |
| MySQL | 8.0 | 8.0+ |
| Redis | 6.x | 7.x |
| Nginx | 1.18 | 1.24 |
| RAM | 2 GB | 4 GB |
| Storage | 20 GB | 50 GB |
| CPU | 2 core | 4 core |

### 20.3 Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name api.grandduta.com;
    root /var/www/grandduta-api/public;

    ssl_certificate     /etc/letsencrypt/live/api.grandduta.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.grandduta.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=300r/m;
    location /api/ {
        limit_req zone=api burst=50 nodelay;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name api.grandduta.com;
    return 301 https://$host$request_uri;
}
```

### 20.4 Deployment Script (Manual)

```bash
#!/bin/bash
# deploy.sh — Script deployment manual

set -e

echo "=== GRANDDUTA API Deployment ==="
echo "Timestamp: $(date)"

# 1. Pull kode terbaru
git pull origin main

# 2. Install/update dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Cache config, routes, views
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 4. Jalankan migrasi
php artisan migrate --force

# 5. Clear stale caches
php artisan cache:clear
php artisan queue:restart

# 6. Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "=== Deployment selesai ==="
```

### 20.5 CI/CD Pipeline (GitHub Actions)

```yaml
# .github/workflows/deploy.yml

name: Test & Deploy

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: grandduta_test
          MYSQL_USER: test_user
          MYSQL_PASSWORD: test_pass
          MYSQL_ROOT_PASSWORD: root
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s

      redis:
        image: redis:7
        ports:
          - 6379:6379

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo_mysql, redis
          coverage: xdebug

      - name: Cache Composer packages
        uses: actions/cache@v3
        with:
          path: vendor
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}

      - name: Install Dependencies
        run: composer install --no-interaction --no-progress

      - name: Copy .env
        run: |
          cp .env.example .env
          php artisan key:generate

      - name: Configure test database
        run: |
          sed -i 's/DB_DATABASE=.*/DB_DATABASE=grandduta_test/' .env
          sed -i 's/DB_USERNAME=.*/DB_USERNAME=test_user/' .env
          sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=test_pass/' .env

      - name: Run Migrations
        run: php artisan migrate --force

      - name: Run Tests with Coverage
        run: php artisan test --coverage --min=80

      - name: Upload Coverage
        uses: codecov/codecov-action@v3

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'

    steps:
      - name: Deploy to Production
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/grandduta-api
            bash deploy.sh
```

### 20.6 Laravel Scheduler

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // Generate tagihan bulanan — tanggal 1 setiap bulan jam 06:00
    $schedule->command('grandduta:billing:generate {year} {month}')
        ->monthlyOn(1, '06:00')
        ->withoutOverlapping()
        ->onOneServer();

    // Snapshot piutang — akhir bulan jam 23:00
    $schedule->command('grandduta:receivables:snapshot')
        ->lastDayOfMonth('23:00')
        ->withoutOverlapping();

    // Kirim reminder tagihan — tanggal 15 jam 09:00
    $schedule->command('grandduta:billing:remind')
        ->monthlyOn(15, '09:00');

    // Cleanup log file lama (> 90 hari)
    $schedule->command('log:prune', ['--days' => 90])
        ->weekly();

    // Monitor queue worker health
    $schedule->command('queue:monitor', ['redis:default'])
        ->everyFiveMinutes();
}
```

### 20.7 Queue Worker (Supervisor)

```ini
; /etc/supervisor/conf.d/grandduta-worker.conf

[program:grandduta-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/grandduta-api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/grandduta-worker.log
stopwaitsecs=3600
```

---

## 21. Strategi Backup & Recovery

### 21.1 Backup Database

#### Artisan Command

```php
// app/Console/Commands/BackupDatabase.php

class BackupDatabase extends Command
{
    protected $signature = 'grandduta:backup:db {--compress}';
    protected $description = 'Backup database MySQL ke storage';

    public function handle(): int
    {
        $filename = 'grandduta-db-' . now()->format('Y-m-d-His') . '.sql';

        if ($this->option('compress')) {
            $filename .= '.gz';
            $command = sprintf(
                'mysqldump -u%s -p%s %s | gzip > %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                storage_path("backups/{$filename}")
            );
        } else {
            $command = sprintf(
                'mysqldump -u%s -p%s %s > %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                storage_path("backups/{$filename}")
            );
        }

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('Backup gagal!');
            Log::error('Database backup failed', ['output' => $output]);
            return Command::FAILURE;
        }

        // Hapus backup lama (> 30 file)
        $this->cleanOldBackups(30);

        $this->info("Backup berhasil: {$filename}");
        return Command::SUCCESS;
    }

    private function cleanOldBackups(int $keep): void
    {
        $backups = collect(glob(storage_path('backups/grandduta-db-*.sql*')))
            ->sortDesc();

        $backups->slice($keep)->each(fn($file) => unlink($file));
    }
}
```

#### Jadwal Backup Otomatis

```php
// Kernel.php
$schedule->command('grandduta:backup:db --compress')
    ->dailyAt('23:00')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::channel('audit')->info('Daily backup completed successfully');
    })
    ->onFailure(function () {
        // Kirim notifikasi email/Slack jika backup gagal
        Mail::to(config('grandduta.admin_email'))
            ->send(new BackupFailedMail());
    });
```

### 21.2 Retensi Backup

| Jenis Backup | Frekuensi | Retensi | Storage |
|-------------|-----------|---------|---------|
| Database harian | Setiap hari 23:00 | 30 hari | Lokal + S3 |
| Database mingguan | Setiap Minggu | 12 minggu | S3 |
| Database bulanan | Akhir bulan | 12 bulan | S3 |
| Log files | — | 90 hari | Lokal |
| PDF documents | — | Permanen | S3 |

### 21.3 Recovery Procedure

**Prosedur Recovery Database:**

```bash
# 1. Identifikasi backup yang akan digunakan
ls -la storage/backups/

# 2. Stop queue workers sementara
php artisan queue:pause

# 3. Aktifkan maintenance mode
php artisan down --message="System sedang dalam pemeliharaan" --retry=60

# 4. Drop dan recreate database
mysql -u root -p -e "DROP DATABASE grandduta_api; CREATE DATABASE grandduta_api;"

# 5. Restore dari backup
gunzip -c storage/backups/grandduta-db-YYYY-MM-DD-HHmmss.sql.gz | \
    mysql -u grandduta_user -p grandduta_api

# 6. Verifikasi data
php artisan tinker --execute="echo 'Customers: ' . App\Models\Customer::count();"

# 7. Disable maintenance mode
php artisan up

# 8. Resume queue workers
php artisan queue:resume
```

---

## 22. Konfigurasi Aplikasi Kustom

### 22.1 `config/grandduta.php`

```php
<?php

return [

    /*
     | Konfigurasi bisnis utama aplikasi GRANDDUTA
     */

    'company' => [
        'name'    => env('COMPANY_NAME', 'Grand Duta'),
        'address' => env('COMPANY_ADDRESS', 'Bandar Lampung'),
        'phone'   => env('COMPANY_PHONE', ''),
        'email'   => env('COMPANY_EMAIL', ''),
    ],

    'billing' => [
        'prefix'            => 'GD',                  // Prefix nomor kuitansi
        'penalty_rate'      => 0.03,                   // 3% denda
        'penalty_day'       => 20,                     // Denda jika bayar setelah tgl ini
        'generation_day'    => 1,                      // Tanggal generate tagihan bulanan
        'reminder_day'      => 15,                     // Tanggal kirim reminder
    ],

    'receipt' => [
        'number_format'     => 'GD.%04d.%02d.%06d',   // GD.YYYY.MM.NNNNNN
    ],

    'pagination' => [
        'default_per_page'  => 20,
        'max_per_page'      => 100,
    ],

    'notification' => [
        'enabled'           => env('NOTIFICATION_ENABLED', false),
        'channel'           => env('NOTIFICATION_CHANNEL', 'whatsapp'),
        'fonnte_token'      => env('FONNTE_TOKEN'),
    ],

    'admin_email'   => env('ADMIN_EMAIL', 'admin@grandduta.com'),

];
```

---

## 23. Ringkasan Checklist Implementasi

### Phase 1 — Foundation (Minggu 1-2)

- [ ] Setup project Laravel 12
- [ ] Konfigurasi database, Redis, Queue
- [ ] Install packages: Sanctum, Spatie Permission, DomPDF
- [ ] Buat semua migrations dan seeders
- [ ] Buat Models dengan relasi
- [ ] Implementasi Auth (login, logout, change password)
- [ ] Implementasi RBAC (roles, permissions)
- [ ] Buat global exception handler
- [ ] Buat ApiResponse trait
- [ ] Setup ForceJsonResponse middleware

### Phase 2 — Master Data (Minggu 2-3)

- [ ] CRUD Cluster (GET, PUT)
- [ ] CRUD Customer (GET, POST, PUT, DELETE)
- [ ] Konversi properti Customer
- [ ] Lookup endpoints (regencies, districts, property types)
- [ ] Unit & Feature tests untuk Customer
- [ ] Cache implementation untuk Cluster

### Phase 3 — Billing Engine (Minggu 3-4)

- [ ] PenaltyService + unit tests
- [ ] BillingService (monthly, special, back)
- [ ] BillingController (prepare, list, detail)
- [ ] BillingApprovalController (approve single & batch)
- [ ] Artisan command untuk generate billing
- [ ] Feature tests untuk billing flow

### Phase 4 — Payment (Minggu 4-5)

- [ ] ReceiptService (generate number dengan DB lock)
- [ ] PaymentService (proses bayar + kalkulasi denda)
- [ ] PaymentController (search, preview, process, list receipts)
- [ ] InstallmentController
- [ ] BackPaymentController
- [ ] Event: PaymentProcessed
- [ ] Feature tests untuk payment flow

### Phase 5 — Reversal & Receivable (Minggu 5-6)

- [ ] ReversalService (submit, approve, reject)
- [ ] ReversalController
- [ ] ReceivableController (aging analysis)
- [ ] Artisan command untuk snapshot piutang
- [ ] Feature tests untuk reversal flow

### Phase 6 — Report & Document (Minggu 6-7)

- [ ] ReportService (dashboard, monthly, LPP, RPP, collector)
- [ ] ReportController
- [ ] DocumentService (SPT, SPK, rekap)
- [ ] Blade templates untuk PDF
- [ ] DocumentController

### Phase 7 — Supporting Features (Minggu 7-8)

- [ ] UserController (CRUD, reset password)
- [ ] AuditService + AuditLogger middleware
- [ ] AuditController
- [ ] NotificationService (WhatsApp integration)
- [ ] SendWhatsAppNotificationJob
- [ ] Laravel Scheduler setup

### Phase 8 — Quality & Deployment (Minggu 8-10)

- [ ] Complete test coverage minimal 80%
- [ ] API Documentation (Swagger/Scribe)
- [ ] Performance testing & optimization
- [ ] Setup CI/CD pipeline (GitHub Actions)
- [ ] Setup Supervisor untuk queue worker
- [ ] Setup backup automation
- [ ] Security audit (OWASP checklist)
- [ ] Nginx configuration & SSL
- [ ] Go-live

---

## 24. Dependency Lengkap (composer.json)

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^12.0",
        "laravel/sanctum": "^4.0",
        "spatie/laravel-permission": "^6.0",
        "barryvdh/laravel-dompdf": "^3.0",
        "guzzlehttp/guzzle": "^7.0"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pint": "^1.13",
        "laravel/telescope": "^5.0",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.1",
        "pestphp/pest": "^2.30",
        "pestphp/pest-plugin-laravel": "^2.3"
    }
}
```

---

*Dokumen ini merupakan bagian dari seri TECHNICAL_SPEC_PART1.md hingga TECHNICAL_SPEC_PART4.md*  
*Untuk penggunaan optimal, baca secara berurutan.*
