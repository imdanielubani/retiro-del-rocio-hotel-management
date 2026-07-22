import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/realtime/realtime_config.dart';

/// The hotel's profile and front-desk policy (Settings → Hotel Info).
@immutable
class HotelInfo {
  const HotelInfo({
    required this.name,
    this.tagline,
    this.address,
    this.city,
    this.country,
    this.phone,
    this.email,
    this.description,
    this.checkInLabel,
    this.checkOutLabel,
  });

  final String name;
  final String? tagline;
  final String? address;
  final String? city;
  final String? country;
  final String? phone;
  final String? email;
  final String? description;

  /// e.g. "3:00 PM" — the hotel's standard arrival/departure times.
  final String? checkInLabel;
  final String? checkOutLabel;

  factory HotelInfo.fromJson(Map<String, dynamic> json) => HotelInfo(
        name: (json['name'] as String?) ?? 'Retiro Del Rocio',
        tagline: json['tagline'] as String?,
        address: json['address'] as String?,
        city: json['city'] as String?,
        country: json['country'] as String?,
        phone: json['phone'] as String?,
        email: json['email'] as String?,
        description: json['description'] as String?,
        checkInLabel: json['check_in_label'] as String?,
        checkOutLabel: json['check_out_label'] as String?,
      );
}

/// Everything the tablet needs once, at launch, rather than on every poll.
@immutable
class AppConfig {
  const AppConfig({this.hotel, this.realtime});

  final HotelInfo? hotel;
  final RealtimeConfig? realtime;
}

/// `GET /v1/app/config` — the slow-moving values: hotel profile, front-desk
/// policy and where to listen for live room updates. Kept alive for the session;
/// a failure resolves to an empty config rather than blocking the UI.
final appConfigProvider = FutureProvider<AppConfig>((ref) async {
  final dio = Dio(BaseOptions(
    connectTimeout: const Duration(seconds: 8),
    receiveTimeout: const Duration(seconds: 8),
  ));

  try {
    final response = await dio.getUri<Map<String, dynamic>>(
      Uri.parse(ApiConfig.endpoint('app/config')),
    );
    final data = response.data?['data'] as Map<String, dynamic>?;

    final config = AppConfig(
      hotel: data?['hotel'] is Map
          ? HotelInfo.fromJson((data!['hotel'] as Map).cast<String, dynamic>())
          : null,
      realtime: RealtimeConfig.fromJson(
        (data?['broadcasting'] as Map?)?.cast<String, dynamic>(),
      ),
    );

    ref.keepAlive();
    return config;
  } catch (error) {
    debugPrint('appConfigProvider: fetch failed — $error');
    return const AppConfig();
  }
});
