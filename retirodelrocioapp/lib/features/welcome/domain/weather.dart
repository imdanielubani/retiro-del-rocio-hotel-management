import 'package:flutter/foundation.dart';

/// Current weather for the hotel's location.
@immutable
class Weather {
  const Weather({
    required this.temperatureC,
    required this.condition,
    required this.emoji,
    required this.city,
  });

  final int temperatureC;
  final String condition;
  final String emoji;
  final String city;

  String get temperatureLabel => '$temperatureC°C';
  String get subtitle => '$condition • $city';
}
