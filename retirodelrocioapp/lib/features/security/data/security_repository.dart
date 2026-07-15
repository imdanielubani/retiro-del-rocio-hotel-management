import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';
import 'package:retirodelrocioapp/features/security/domain/security_overview.dart';

/// Raised when a security action could not be completed, carrying a
/// user-facing [message].
class SecurityException implements Exception {
  SecurityException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the security dashboard endpoints with the signed-in officer's staff
/// JWT — the same token drives the session's expiry, and the role is enforced
/// server-side on every call.
class SecurityRepository {
  SecurityRepository({Dio? dio})
      : _dio = dio ??
            Dio(BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 8),
            ));

  final Dio _dio;

  Options _auth(String token) => Options(headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      });

  /// The whole dashboard in one call. Throws [SecurityException] on failure so
  /// the screen can show a retry rather than a blank slate during an emergency.
  Future<SecurityOverview> overview(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('security/overview')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null
          ? SecurityOverview.fromJson(data)
          : SecurityOverview.empty;
    } on DioException catch (error) {
      throw SecurityException(_messageFrom(error));
    } catch (error) {
      debugPrint('SecurityRepository: overview failed — $error');
      throw SecurityException('Could not load the dashboard.');
    }
  }

  /// The SOS Alert Logs — every incident, newest first. [status] optionally
  /// narrows the list server-side (active | acknowledged | resolved | cancelled
  /// | open).
  Future<List<SecurityIncident>> incidents(String token, {String? status}) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('security/incidents')).replace(
          queryParameters: status != null ? {'status': status} : null,
        ),
        options: _auth(token),
      );
      final list = (response.data?['data'] as List?) ?? const [];
      return list
          .whereType<Map>()
          .map((e) => SecurityIncident.fromJson(e.cast<String, dynamic>()))
          .toList();
    } on DioException catch (error) {
      throw SecurityException(_messageFrom(error));
    } catch (error) {
      debugPrint('SecurityRepository: incidents failed — $error');
      throw SecurityException('Could not load the alert logs.');
    }
  }

  /// Acknowledge an incident — the guest is told help is on the way.
  Future<SecurityIncident> respond(String token, int incidentId) =>
      _act(token, incidentId, 'respond');

  /// Resolve an incident — it is dealt with and closes.
  Future<SecurityIncident> resolve(String token, int incidentId) =>
      _act(token, incidentId, 'resolve');

  Future<SecurityIncident> _act(String token, int incidentId, String action) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('security/incidents/$incidentId/$action')),
        options: _auth(token),
      );
      return SecurityIncident.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw SecurityException(_messageFrom(error));
    } catch (error) {
      debugPrint('SecurityRepository: $action failed — $error');
      throw SecurityException('Could not update the incident.');
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map &&
        data['message'] is String &&
        (data['message'] as String).isNotEmpty) {
      return data['message'] as String;
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout) {
      return 'No connection. Check the station network.';
    }
    return 'Something went wrong. Please try again.';
  }
}
