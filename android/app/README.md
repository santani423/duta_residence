# Duta Residence

Dokumentasi singkat untuk menjalankan aplikasi Flutter dari repository sampai bisa dibuka di emulator Android.

## Prasyarat

Pastikan perangkat Anda sudah menyiapkan hal berikut:

- Flutter SDK terinstall dan sudah tersedia di PATH
- Android Studio terinstall
- Emulator Android sudah berjalan atau device Android terhubung
- ADB tersedia

Cek instalasi dengan perintah berikut:

```bash
flutter --version
flutter doctor
adb devices
```

## 1. Clone repository

```bash
git clone <URL_REPOSITORY>
cd duta_residence/android/app
```

## 2. Install dependency

```bash
flutter pub get
```

## 3. Pastikan emulator terhubung

Jika emulator sudah berjalan, cek koneksinya:

```bash
adb devices
adb connect emulator-5554
adb devices
```

Jika emulator belum muncul, jalankan emulator dari Android Studio atau cek daftar emulator:

```bash
emulator -list-avds
```

## 4. Jalankan aplikasi di emulator

Cek device yang tersedia:

```bash
flutter devices
```

Lalu jalankan aplikasi ke emulator:

```bash
flutter run -d emulator-5554
```

Jika device ID yang muncul berbeda, gunakan ID tersebut pada perintah `flutter run -d`.

## 5. Jika mengalami masalah

Jika aplikasi gagal dijalankan, coba langkah berikut:

```bash
flutter clean
flutter pub get
flutter run -d emulator-5554
```

## Catatan

Untuk pengembangan rutin, Anda bisa menggunakan hot reload dengan menekan `r` saat aplikasi sedang berjalan di terminal.
