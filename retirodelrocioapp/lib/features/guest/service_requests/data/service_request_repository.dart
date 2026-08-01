import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/domain/guest_service_request.dart';

/// Raised when a service request could not be submitted, carrying a
/// user-facing message.
class ServiceRequestException implements Exception {
  ServiceRequestException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the guest tablet's Service Request endpoints with the tablet's
/// own device token, which is what scopes every call to this room.
class ServiceRequestRepository {
  ServiceRequestRepository({Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 15),
              sendTimeout: const Duration(seconds: 15),
            ),
          );

  final Dio _dio;

  Options _auth(String deviceToken) => Options(
    headers: {'Authorization': 'Bearer $deviceToken', 'Accept': 'application/json'},
  );

  /// This stay's request history, newest first. Failures fall back to an
  /// empty list — the screen still renders, just without history.
  Future<List<GuestServiceRequest>> list(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('service-requests')),
        options: _auth(deviceToken),
      );
      final data = response.data?['data'];
      if (data is! List) return const [];
      return data
          .whereType<Map>()
          .map((e) => GuestServiceRequest.fromJson(e.cast<String, dynamic>()))
          .toList();
    } catch (error) {
      debugPrint('ServiceRequestRepository: list failed — $error');
      return const [];
    }
  }

  /// Raise a housekeeping ask against this room.
  Future<GuestServiceRequest> createHousekeeping(
    String deviceToken, {
    required String type,
    String? notes,
  }) => _create(deviceToken, {
    'category': 'housekeeping',
    'type': type,
    if (notes != null && notes.isNotEmpty) 'notes': notes,
  });

  /// Report a maintenance fault against this room.
  Future<GuestServiceRequest> createMaintenance(
    String deviceToken, {
    required String title,
    String? description,
    String priority = 'medium',
  }) => _create(deviceToken, {
    'category': 'maintenance',
    'title': title,
    if (description != null && description.isNotEmpty) 'description': description,
    'priority': priority,
  });

  Future<GuestServiceRequest> _create(String deviceToken, Map<String, dynamic> data) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('service-requests')),
        data: data,
        options: _auth(deviceToken),
      );
      return GuestServiceRequest.fromJson((response.data!['data'] as Map).cast<String, dynamic>());
    } on DioException catch (error) {
      throw ServiceRequestException(_messageFrom(error));
    } catch (error) {
      debugPrint('ServiceRequestRepository: create failed — $error');
      throw ServiceRequestException('Could not send this request. Please try again.');
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map && data['errors'] is Map) {
      final errors = (data['errors'] as Map).values;
      if (errors.isNotEmpty && errors.first is List && (errors.first as List).isNotEmpty) {
        return (errors.first as List).first.toString();
      }
    }
    if (data is Map && data['message'] is String && (data['message'] as String).isNotEmpty) {
      return data['message'] as String;
    }
    switch (error.type) {
      case DioExceptionType.connectionError:
      case DioExceptionType.connectionTimeout:
        return 'No connection. Please try again.';
      default:
        return 'Could not send this request. Please try again.';
    }
  }
}
