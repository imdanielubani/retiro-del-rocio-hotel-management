import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/domain/lost_found_item.dart';

/// Raised when a Lost & Found action could not be completed, carrying a
/// user-facing [message].
class LostFoundException implements MessagedException {
  LostFoundException(this.message);
  @override
  final String message;
  @override
  String toString() => message;
}

/// Talks to the housekeeping tablet's Lost & Found endpoints with the
/// signed-in housekeeper's staff JWT — the role is enforced server-side on
/// every call.
class LostFoundRepository {
  LostFoundRepository({Dio? dio})
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

  /// Every logged item, unclaimed first then newest. Optional [status]
  /// narrows to unclaimed, returned, or disposed.
  Future<List<LostFoundItem>> items(String token, {String? status}) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('housekeeping/lost-found')).replace(
          queryParameters: (status != null && status.isNotEmpty) ? {'status': status} : null,
        ),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => LostFoundItem.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('LostFoundRepository: items failed — $error');
      return const [];
    }
  }

  /// Log an item found while turning over a room (or a common area, when
  /// [roomUnitId] is left null).
  Future<LostFoundItem> createItem(
    String token, {
    int? roomUnitId,
    required String itemDescription,
    String? notes,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('housekeeping/lost-found')),
        data: {
          if (roomUnitId != null) 'room_unit_id': roomUnitId,
          'item_description': itemDescription,
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
        options: _auth(token),
      );
      return LostFoundItem.fromJson((response.data!['data'] as Map).cast<String, dynamic>());
    } on DioException catch (error) {
      throw LostFoundException(_messageFrom(error));
    } catch (error) {
      debugPrint('LostFoundRepository: createItem failed — $error');
      throw LostFoundException('Could not log this item.');
    }
  }

  /// Hand the item back to its owner.
  Future<LostFoundItem> markReturned(
    String token,
    int id, {
    String? claimantName,
    String? claimantContact,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('housekeeping/lost-found/$id/status')),
        data: {
          'action': 'returned',
          if (claimantName != null && claimantName.isNotEmpty) 'claimant_name': claimantName,
          if (claimantContact != null && claimantContact.isNotEmpty) 'claimant_contact': claimantContact,
        },
        options: _auth(token),
      );
      return LostFoundItem.fromJson((response.data!['data'] as Map).cast<String, dynamic>());
    } on DioException catch (error) {
      throw LostFoundException(_messageFrom(error));
    } catch (error) {
      debugPrint('LostFoundRepository: markReturned failed — $error');
      throw LostFoundException('Could not update this item.');
    }
  }

  /// Never claimed, discarded per policy.
  Future<LostFoundItem> markDisposed(String token, int id) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('housekeeping/lost-found/$id/status')),
        data: {'action': 'disposed'},
        options: _auth(token),
      );
      return LostFoundItem.fromJson((response.data!['data'] as Map).cast<String, dynamic>());
    } on DioException catch (error) {
      throw LostFoundException(_messageFrom(error));
    } catch (error) {
      debugPrint('LostFoundRepository: markDisposed failed — $error');
      throw LostFoundException('Could not update this item.');
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
