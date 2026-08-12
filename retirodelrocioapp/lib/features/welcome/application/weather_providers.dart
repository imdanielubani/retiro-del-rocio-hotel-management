import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/welcome/data/weather_service.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// The weather service instance.
final weatherServiceProvider = Provider<WeatherService>(
  (ref) => WeatherService(),
);

/// Current weather for the hotel, refreshed every 15 minutes.
final weatherProvider = FutureProvider<Weather>((ref) async {
  final service = ref.watch(weatherServiceProvider);

  // Keep it warm and periodically refreshed while the welcome screen is open.
  final timer = Timer(const Duration(minutes: 15), () => ref.invalidateSelf());
  ref.onDispose(timer.cancel);

  return service.current();
});
