class AppConfig {
  const AppConfig._();

  static const appName = 'Duta Residence';
  static const estateName = 'Grand Duta Residence';
  static const apiBaseUrl = String.fromEnvironment(
    'DUTA_API_BASE_URL',
    defaultValue: 'https://beestate.santani.dev/api/v1',
  );
}
