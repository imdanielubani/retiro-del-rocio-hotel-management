import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';
import 'package:retirodelrocioapp/features/security/domain/security_overview.dart';
import 'package:retirodelrocioapp/features/security/domain/visitor_pass_record.dart';

/// Raised when a security action could not be completed, carrying a
/// user-facing [message].
class SecurityException implements MessagedException {
  SecurityException(this.message);
  @override
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

  /// Today's visitor passes hotel-wide, newest first. Failures fall back to an
  /// empty list so the list still renders while the keypad stays usable.
  Future<List<VisitorPassRecord>> visitors(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('security/visitors')),
        options: _auth(token),
      );
      final list = (response.data?['data'] as List?) ?? const [];
      return list
          .whereType<Map>()
          .map((e) => VisitorPassRecord.fromJson(e.cast<String, dynamic>()))
          .toList();
    } catch (error) {
      debugPrint('SecurityRepository: visitors failed — $error');
      return const [];
    }
  }

  /// Look up a visitor by the 6-digit code they quote. Throws
  /// [SecurityException] carrying the server's message ("Code not recognised." /
  /// "This code has already been used.") when there is no live match.
  Future<VisitorPassRecord> verifyCode(String token, String code) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('security/visitors/verify')),
        data: {'code': code},
        options: _auth(token),
      );
      return VisitorPassRecord.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw SecurityException(_messageFrom(error));
    } catch (error) {
      debugPrint('SecurityRepository: verifyCode failed — $error');
      throw SecurityException('Could not verify the code.');
    }
  }

  /// Admit the visitor — the pass moves to verified and the entry is logged.
  Future<VisitorPassRecord> grantVisitor(String token, int passId) =>
      _visitorAction(token, passId, 'grant', 'Could not grant access.');

  /// Turn the visitor away — the pass is denied and its codes stop working.
  Future<VisitorPassRecord> denyVisitor(String token, int passId) =>
      _visitorAction(token, passId, 'deny', 'Could not deny access.');

  /// Mark a verified visitor as having left the property.
  Future<VisitorPassRecord> exitVisitor(String token, int passId) =>
      _visitorAction(token, passId, 'exit', 'Could not check out this visitor.');

  Future<VisitorPassRecord> _visitorAction(
    String token,
    int passId,
    String action,
    String fallback,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('security/visitors/$passId/$action')),
        options: _auth(token),
      );
      return VisitorPassRecord.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw SecurityException(_messageFrom(error));
    } catch (error) {
      debugPrint('SecurityRepository: $action visitor failed — $error');
      throw SecurityException(fallback);
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
