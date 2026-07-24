import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/guest/visitor_pass/domain/visitor_pass.dart';

/// Raised when a visitor pass could not be issued, carrying a user-facing message.
class VisitorPassException implements Exception {
  VisitorPassException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the visitor-pass endpoints with the tablet's own device token, which
/// is what scopes every call to this room.
class VisitorPassRepository {
  VisitorPassRepository({Dio? dio})
      : _dio = dio ??
            Dio(BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              // Issuing a pass writes the row first and pushes the gate code
              // afterwards, so this is normally instant — but a loaded server
              // still deserves more headroom than the connect handshake, since
              // giving up here would tell the guest the pass failed when it did not.
              receiveTimeout: const Duration(seconds: 25),
              sendTimeout: const Duration(seconds: 25),
            ));

  final Dio _dio;

  Options _auth(String deviceToken) => Options(headers: {
        'Authorization': 'Bearer $deviceToken',
        'Accept': 'application/json',
      });

  /// This room's visitor history, newest first. Failures fall back to an empty
  /// list — the screen still renders, just without history.
  Future<List<VisitorPass>> list(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('visitor-passes')),
        options: _auth(deviceToken),
      );
      final data = response.data?['data'];
      if (data is! List) return const [];
      return data
          .whereType<Map>()
          .map((e) => VisitorPass.fromJson(e.cast<String, dynamic>()))
          .toList();
    } catch (error) {
      debugPrint('VisitorPassRepository: list failed — $error');
      return const [];
    }
  }

  /// Invite a visitor; the server mints the entry code and returns the new pass.
  Future<VisitorPass> create(
    String deviceToken, {
    required String name,
    String? email,
    String? phone,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('visitor-passes')),
        data: {
          'visitor_name': name,
          if (email != null && email.isNotEmpty) 'visitor_email': email,
          if (phone != null && phone.isNotEmpty) 'visitor_phone': phone,
        },
        options: _auth(deviceToken),
      );
      return VisitorPass.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw VisitorPassException(_messageFrom(error));
    } catch (error) {
      debugPrint('VisitorPassRepository: create failed — $error');
      throw VisitorPassException('Could not create the pass. Please try again.');
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    // Laravel validation: surface the first field error verbatim.
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
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.sendTimeout:
        // The server may well have created the pass — say so rather than
        // inviting the guest to issue a second one for the same visitor.
        return 'The server is taking a while. Check Visitor History before trying again.';
      default:
        return 'Could not create the pass. Please try again.';
    }
  }
}
