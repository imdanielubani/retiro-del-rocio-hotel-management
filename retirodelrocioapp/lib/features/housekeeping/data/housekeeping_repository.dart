import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_guest_request.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_overview.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_room.dart';

/// Raised when a housekeeping action could not be completed, carrying a
/// user-facing [message].
class HousekeepingException implements MessagedException {
  HousekeepingException(this.message);
  @override
  final String message;
  @override
  String toString() => message;
}

/// Talks to the housekeeping tablet endpoints with the signed-in
/// housekeeper's staff JWT — the role is enforced server-side on every call.
class HousekeepingRepository {
  HousekeepingRepository({Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 8),
            ),
          );

  final Dio _dio;

  Options _auth(String token) => Options(
    headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
  );

  /// The whole dashboard in one call. Falls back to the empty shape on
  /// failure so the screen still renders while the tablet stays usable.
  Future<HousekeepingOverview> overview(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('housekeeping/overview')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null ? HousekeepingOverview.fromJson(data) : HousekeepingOverview.empty;
    } catch (error) {
      debugPrint('HousekeepingRepository: overview failed — $error');
      return HousekeepingOverview.empty;
    }
  }

  /// Every room, optionally narrowed by [status] (clean/dirty/inspected/
  /// out_of_order) or [search] (room number).
  Future<List<HousekeepingRoom>> rooms(String token, {String? status, String? search}) async {
    try {
      final query = {
        if (status != null && status.isNotEmpty) 'status': status,
        if (search != null && search.isNotEmpty) 'search': search,
      };
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('housekeeping/rooms'))
            .replace(queryParameters: query.isEmpty ? null : query),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => HousekeepingRoom.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('HousekeepingRepository: rooms failed — $error');
      return const [];
    }
  }

  /// Mark a room clean, inspected, dirty again, or out of order.
  Future<HousekeepingRoom> updateRoomStatus(String token, int unitId, String status) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('housekeeping/rooms/$unitId/status')),
        data: {'status': status},
        options: _auth(token),
      );
      return HousekeepingRoom.fromJson((response.data!['data'] as Map).cast<String, dynamic>());
    } on DioException catch (error) {
      throw HousekeepingException(_messageFrom(error));
    } catch (error) {
      debugPrint('HousekeepingRepository: updateRoomStatus failed — $error');
      throw HousekeepingException('Could not update this room.');
    }
  }

  /// Guest requests, newest pending first. Optional [status] narrows to
  /// pending or completed.
  Future<List<HousekeepingGuestRequest>> requests(String token, {String? status}) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('housekeeping/requests')).replace(
          queryParameters: (status != null && status.isNotEmpty) ? {'status': status} : null,
        ),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => HousekeepingGuestRequest.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('HousekeepingRepository: requests failed — $error');
      return const [];
    }
  }

  /// Log a guest ask against a room.
  Future<HousekeepingGuestRequest> createRequest(
    String token, {
    required int roomUnitId,
    required String type,
    String? notes,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('housekeeping/requests')),
        data: {
          'room_unit_id': roomUnitId,
          'type': type,
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
        options: _auth(token),
      );
      return HousekeepingGuestRequest.fromJson((response.data!['data'] as Map).cast<String, dynamic>());
    } on DioException catch (error) {
      throw HousekeepingException(_messageFrom(error));
    } catch (error) {
      debugPrint('HousekeepingRepository: createRequest failed — $error');
      throw HousekeepingException('Could not log this request.');
    }
  }

  /// Clear a guest ask.
  Future<HousekeepingGuestRequest> completeRequest(String token, int id) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('housekeeping/requests/$id/complete')),
        options: _auth(token),
      );
      return HousekeepingGuestRequest.fromJson((response.data!['data'] as Map).cast<String, dynamic>());
    } on DioException catch (error) {
      throw HousekeepingException(_messageFrom(error));
    } catch (error) {
      debugPrint('HousekeepingRepository: completeRequest failed — $error');
      throw HousekeepingException('Could not update this request.');
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map && data['message'] is String && (data['message'] as String).isNotEmpty) {
      return data['message'] as String;
    }
    if (error.type == DioExceptionType.connectionError || error.type == DioExceptionType.connectionTimeout) {
      return 'No connection. Check the station network.';
    }
    return 'Something went wrong. Please try again.';
  }
}
