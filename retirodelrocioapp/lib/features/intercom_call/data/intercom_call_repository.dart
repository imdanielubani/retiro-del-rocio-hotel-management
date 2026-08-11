import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/intercom_call/domain/intercom_call.dart';

/// Raised when a call action could not be completed, carrying a user-facing
/// message.
class IntercomCallException implements Exception {
  IntercomCallException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// This device's Agora credentials for one call's voice channel, returned by
/// `GET .../{callId}/token`. [token] is null when the Agora project is still
/// in Testing Mode (App Certificate disabled) — App ID alone is enough to
/// join the channel then.
class AgoraCallCredentials {
  const AgoraCallCredentials({
    required this.appId,
    required this.channel,
    required this.uid,
    required this.token,
  });

  factory AgoraCallCredentials.fromJson(Map<String, dynamic> json) =>
      AgoraCallCredentials(
        appId: json['app_id'] as String,
        channel: json['channel'] as String,
        uid: json['uid'] as int,
        token: json['token'] as String?,
      );

  final String appId;
  final String channel;
  final int uid;
  final String? token;
}

/// The result of looking up a room number on the guest tablet's Room to Room
/// dial pad (`GET intercom/rooms/{number}`) — whether that room is currently
/// occupied by a checked-in guest, and if so, who and where. [isOwnRoom] is
/// true when the number typed is this same device's own room — still
/// "found" (so the dial pad can show it), just not callable.
class RoomLookupResult {
  const RoomLookupResult({
    required this.found,
    this.number,
    this.suiteLabel,
    this.roomTypeLabel,
    this.guestName,
    this.isOwnRoom = false,
  });

  factory RoomLookupResult.fromJson(Map<String, dynamic> json) =>
      RoomLookupResult(
        found: json['found'] as bool? ?? false,
        number: json['number'] as String?,
        suiteLabel: json['suite_label'] as String?,
        roomTypeLabel: json['room_type_label'] as String?,
        guestName: json['guest_name'] as String?,
        isOwnRoom: json['is_own_room'] as bool? ?? false,
      );

  final bool found;
  final String? number;
  final String? suiteLabel;
  final String? roomTypeLabel;
  final String? guestName;
  final bool isOwnRoom;
}

/// Talks to the Intercom call endpoints — shared by the guest tablet
/// (`intercom/calls/*`) and Reception (`reception/intercom/calls/*`), which
/// are otherwise identical in shape. [basePath] picks which audience this
/// instance speaks for.
class IntercomCallRepository {
  IntercomCallRepository({required this.basePath, Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 15),
              sendTimeout: const Duration(seconds: 15),
            ),
          );

  final String basePath;
  final Dio _dio;

  Options _auth(String token) => Options(
    headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
  );

  /// GET .../current — this identity's active call, either side, or null.
  Future<IntercomCall?> current(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('$basePath/current')),
        options: _auth(token),
      );
      final data = response.data?['data'];
      return data is Map ? IntercomCall.fromJson(data.cast()) : null;
    } catch (error) {
      debugPrint('IntercomCallRepository: current failed — $error');
      return null;
    }
  }

  /// POST .../ — place a call. [body] carries whatever the audience needs
  /// to identify the target (nothing for the guest tablet, which can only
  /// call Reception; `{'booking_id': ...}` for Reception calling a room).
  Future<IntercomCall> place(String token, [Map<String, dynamic>? body]) =>
      _action(token, '', body ?? const {});

  Future<IntercomCall> answer(String token, int callId) =>
      _action(token, '/$callId/answer', const {});

  Future<IntercomCall> decline(String token, int callId) =>
      _action(token, '/$callId/decline', const {});

  Future<IntercomCall> end(String token, int callId) =>
      _action(token, '/$callId/end', const {});

  /// GET intercom/rooms/{number} — Room to Room's live dial-pad lookup.
  /// Guest-only, so it isn't relative to [basePath] the way every other
  /// method here is (Reception/Staff never call it).
  Future<RoomLookupResult> lookupRoom(String token, String number) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('intercom/rooms/$number')),
        options: _auth(token),
      );
      final data = response.data?['data'];
      return data is Map
          ? RoomLookupResult.fromJson(data.cast())
          : const RoomLookupResult(found: false);
    } catch (error) {
      debugPrint('IntercomCallRepository: lookupRoom failed — $error');
      return const RoomLookupResult(found: false);
    }
  }

  /// POST intercom/calls/room — Room to Room: call another checked-in
  /// guest's room directly by room number, no staff involved. Guest-only,
  /// same reasoning as [lookupRoom].
  Future<IntercomCall> callRoom(String token, String roomNumber) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('intercom/calls/room')),
        data: {'room_number': roomNumber},
        options: _auth(token),
      );
      return IntercomCall.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw IntercomCallException(_messageFrom(error));
    } catch (error) {
      debugPrint('IntercomCallRepository: callRoom failed — $error');
      throw IntercomCallException('Something went wrong. Please try again.');
    }
  }

  /// GET .../{callId}/token — this identity's Agora credentials for the
  /// call's voice channel. Called once the call is accepted.
  Future<AgoraCallCredentials?> fetchToken(String token, int callId) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('$basePath/$callId/token')),
        options: _auth(token),
      );
      final data = response.data?['data'];
      return data is Map ? AgoraCallCredentials.fromJson(data.cast()) : null;
    } catch (error) {
      debugPrint('IntercomCallRepository: fetchToken failed — $error');
      return null;
    }
  }

  Future<IntercomCall> _action(
    String token,
    String suffix,
    Map<String, dynamic> body,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('$basePath$suffix')),
        data: body,
        options: _auth(token),
      );
      return IntercomCall.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw IntercomCallException(_messageFrom(error));
    } catch (error) {
      debugPrint('IntercomCallRepository: action failed — $error');
      throw IntercomCallException('Something went wrong. Please try again.');
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map &&
        data['message'] is String &&
        (data['message'] as String).isNotEmpty) {
      return data['message'] as String;
    }
    switch (error.type) {
      case DioExceptionType.connectionError:
      case DioExceptionType.connectionTimeout:
        return 'No connection. Please try again.';
      default:
        return 'Something went wrong. Please try again.';
    }
  }
}
