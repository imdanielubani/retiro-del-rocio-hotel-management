import 'package:dio/dio.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// Fetches current weather from Open-Meteo (free, no API key required).
///
/// Coordinates default to the hotel's location; override to move it.
class WeatherService {
  WeatherService({
    Dio? dio,
    this.latitude = 9.8965, // Jos, Nigeria
    this.longitude = 8.8583,
    this.city = 'Jos',
  }) : _dio =
           dio ??
           Dio(
             BaseOptions(
               connectTimeout: const Duration(seconds: 8),
               receiveTimeout: const Duration(seconds: 8),
             ),
           );

  final Dio _dio;
  final double latitude;
  final double longitude;
  final String city;

  Future<Weather> current() async {
    final response = await _dio.getUri<Map<String, dynamic>>(
      Uri.parse(
        'https://api.open-meteo.com/v1/forecast'
        '?latitude=$latitude&longitude=$longitude'
        '&current=temperature_2m,weather_code&timezone=auto',
      ),
    );
    final current = (response.data?['current'] as Map).cast<String, dynamic>();
    final temp = (current['temperature_2m'] as num).round();
    final code = (current['weather_code'] as num).toInt();
    final (condition, emoji) = _describe(code);

    return Weather(
      temperatureC: temp,
      condition: condition,
      emoji: emoji,
      city: city,
    );
  }

  /// Maps a WMO weather code to a human label + emoji.
  (String, String) _describe(int code) {
    return switch (code) {
      0 => ('Sunny', '☀️'),
      1 || 2 => ('Partly Cloudy', '🌤️'),
      3 => ('Cloudy', '☁️'),
      45 || 48 => ('Foggy', '🌫️'),
      51 || 53 || 55 || 56 || 57 => ('Drizzle', '🌦️'),
      61 || 63 || 65 || 66 || 67 => ('Rainy', '🌧️'),
      71 || 73 || 75 || 77 => ('Snow', '🌨️'),
      80 || 81 || 82 => ('Showers', '🌦️'),
      85 || 86 => ('Snow Showers', '🌨️'),
      95 || 96 || 99 => ('Thunderstorm', '⛈️'),
      _ => ('Clear', '🌡️'),
    };
  }
}
