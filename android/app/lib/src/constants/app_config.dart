class AppConfig {
  const AppConfig._();

  static const appName = 'Duta Residence';
  static const estateName = 'Grand Duta Residence';
  static const apiBaseUrl = String.fromEnvironment(
    'DUTA_API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );
}
