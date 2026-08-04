/// Backend environment the app talks to.
enum AppEnvironment { dev, staging, prod }

/// Backend API configuration for the three tiers (dev / staging / prod).
///
/// Select the tier at build/run time:
///   flutter run                                   → dev  (localhost, default)
///   flutter run --dart-define=ENV=staging         → staging
///   flutter run --dart-define=ENV=prod            → prod
///
/// Override the host directly (e.g. testing on a physical device over LAN):
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api
///
/// [baseUrl] is the API root that already includes the `/api` parent locally
/// and the bare host on the sub-domain tiers; endpoints are then `/v1/...`:
///  • dev     `http://10.0.2.2:8000/api` → `http://10.0.2.2:8000/api/v1/...`
///  • staging `https://api.staging.retirodelrocio.com` → `.../v1/...`
///  • prod    `https://api.retirodelrocio.com` → `.../v1/...`
abstract final class ApiConfig {
  const ApiConfig._();

  static const String _envName = String.fromEnvironment(
    'ENV',
    defaultValue: 'dev',
  );
  static const String _override = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );

  static AppEnvironment get environment => switch (_envName) {
    'prod' || 'production' => AppEnvironment.prod,
    'staging' || 'stage' => AppEnvironment.staging,
    _ => AppEnvironment.dev,
  };

  /// Base URL for the active environment.
  ///
  /// Dev points at the machine's **LAN IP**, not `localhost`. The tablet has to
  /// reach the same host for three things — the API, the room photos (built from
  /// the backend's `APP_URL`) and the Reverb socket — and only a real address
  /// works for all three over Wi-Fi. Serve accordingly:
  ///
  ///   `php artisan serve --host=0.0.0.0`
  ///   `php artisan reverb:start`
  ///
  /// When the dev machine's IP changes, override it rather than editing this:
  /// `--dart-define=API_BASE_URL=http://192.168.x.x:8000/api`.
  /// Android emulators use `http://10.0.2.2:8000/api`.
  static String get baseUrl {
    if (_override.isNotEmpty) return _override;
    return switch (environment) {
      AppEnvironment.dev => 'http://192.168.68.80:8000/api',
      AppEnvironment.staging => 'https://api.staging.retirodelrocio.com',
      AppEnvironment.prod => 'https://api.retirodelrocio.com',
    };
  }

  /// Full URL for a versioned endpoint, e.g. `endpoint('app/config')`.
  static String endpoint(String path) => '$baseUrl/v1/$path';
}
