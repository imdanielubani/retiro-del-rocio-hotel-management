import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_booking_row.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_checkin.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_guest.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_overview.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_pickup.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';

/// Raised when a reception action could not be completed, carrying a
/// user-facing [message].
class ReceptionException implements MessagedException {
  ReceptionException(this.message);
  @override
  final String message;
  @override
  String toString() => message;
}

/// Talks to the reception dashboard endpoints with the signed-in receptionist's
/// staff JWT — the role is enforced server-side on every call.
class ReceptionRepository {
  ReceptionRepository({Dio? dio})
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

  /// The whole dashboard in one call. Throws [ReceptionException] on failure so
  /// the screen can offer a retry rather than a silently blank desk.
  Future<ReceptionOverview> overview(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/overview')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null
          ? ReceptionOverview.fromJson(data)
          : ReceptionOverview.empty;
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: overview failed — $error');
      throw ReceptionException('Could not load the dashboard.');
    }
  }

  /// The hotel's guests, aggregated from their bookings. Failures fall back to
  /// an empty list so the list still renders.
  Future<List<ReceptionGuestSummary>> guests(
    String token, {
    String? search,
  }) async {
    try {
      final uri = Uri.parse(ApiConfig.endpoint('reception/guests')).replace(
        queryParameters: (search != null && search.isNotEmpty)
            ? {'search': search}
            : null,
      );
      final response = await _dio.getUri<Map<String, dynamic>>(
        uri,
        options: _auth(token),
      );
      final list = (response.data?['data'] as List?) ?? const [];
      return list
          .whereType<Map>()
          .map((e) => ReceptionGuestSummary.fromJson(e.cast<String, dynamic>()))
          .toList();
    } catch (error) {
      debugPrint('ReceptionRepository: guests failed — $error');
      return const [];
    }
  }

  /// One guest's full record. Throws [ReceptionException] on failure so the
  /// profile screen can show a retry.
  Future<ReceptionGuestProfile> guestProfile(String token, String key) async {
    try {
      final uri = Uri.parse(
        ApiConfig.endpoint('reception/guests/profile'),
      ).replace(queryParameters: {'key': key});
      final response = await _dio.getUri<Map<String, dynamic>>(
        uri,
        options: _auth(token),
      );
      return ReceptionGuestProfile.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: guestProfile failed — $error');
      throw ReceptionException('Could not load the guest.');
    }
  }

  /// Every room booking, newest first. Failures fall back to an empty list.
  Future<List<ReceptionBookingRow>> bookings(
    String token, {
    String? status,
    String? search,
  }) async {
    try {
      final params = <String, String>{
        if (status != null && status.isNotEmpty) 'status': status,
        if (search != null && search.isNotEmpty) 'search': search,
      };
      final uri = Uri.parse(
        ApiConfig.endpoint('reception/bookings'),
      ).replace(queryParameters: params.isEmpty ? null : params);
      final response = await _dio.getUri<Map<String, dynamic>>(
        uri,
        options: _auth(token),
      );
      final list = (response.data?['data'] as List?) ?? const [];
      return list
          .whereType<Map>()
          .map((e) => ReceptionBookingRow.fromJson(e.cast<String, dynamic>()))
          .toList();
    } catch (error) {
      debugPrint('ReceptionRepository: bookings failed — $error');
      return const [];
    }
  }

  /// The room-assignment options for the check-in flow — the assigned unit plus
  /// same-type alternatives the desk can move the guest to.
  Future<RoomOptions> roomOptions(String token, int bookingId) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/bookings/$bookingId/rooms')),
        options: _auth(token),
      );
      return RoomOptions.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: roomOptions failed — $error');
      throw ReceptionException('Could not load the rooms.');
    }
  }

  /// Admit an arriving guest with the identity captured on the tablet: the
  /// document type and number, an optional scan (uploaded to Cloudinary by the
  /// backend), and the chosen room. Returns the confirmation for the completion
  /// screen.
  Future<CheckinConfirmation> checkIn(
    String token,
    int bookingId, {
    String? documentType,
    String? documentNumber,
    String? documentPath,
    String? documentName,
    int? roomUnitId,
  }) async {
    try {
      final fields = <String, dynamic>{};
      if (documentType != null) fields['document_type'] = documentType;
      if ((documentNumber ?? '').isNotEmpty)
        fields['document_number'] = documentNumber;
      if (roomUnitId != null) fields['room_unit_id'] = roomUnitId;
      if (documentPath != null) {
        fields['document'] = await MultipartFile.fromFile(
          documentPath,
          filename: documentName,
        );
      }
      final form = FormData.fromMap(fields);

      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/bookings/$bookingId/check-in')),
        data: form,
        options: _auth(token),
      );
      return CheckinConfirmation.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: checkIn failed — $error');
      throw ReceptionException('Could not complete the check-in.');
    }
  }

  /// Every guest vehicle pickup, unassigned first. Failures fall back to an empty
  /// list so the module still renders.
  Future<List<PickupBooking>> pickups(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/pickups')),
        options: _auth(token),
      );
      final list = (response.data?['data'] as List?) ?? const [];
      return list
          .whereType<Map>()
          .map((e) => PickupBooking.fromJson(e.cast<String, dynamic>()))
          .toList();
    } catch (error) {
      debugPrint('ReceptionRepository: pickups failed — $error');
      return const [];
    }
  }

  /// The assignable driver roster (available drivers only).
  Future<List<PickupDriver>> drivers(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/drivers')),
        options: _auth(token),
      );
      final list = (response.data?['data'] as List?) ?? const [];
      return list
          .whereType<Map>()
          .map((e) => PickupDriver.fromJson(e.cast<String, dynamic>()))
          .toList();
    } catch (error) {
      debugPrint('ReceptionRepository: drivers failed — $error');
      return const [];
    }
  }

  /// Assign (or, with a null [driverId], clear) the driver for a pickup. Returns
  /// the updated pickup.
  Future<PickupBooking> assignDriver(
    String token,
    int bookingId,
    int? driverId,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('reception/bookings/$bookingId/assign-driver'),
        ),
        data: {'driver_id': driverId},
        options: _auth(token),
      );
      return PickupBooking.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: assignDriver failed — $error');
      throw ReceptionException('Could not assign the driver.');
    }
  }

  /// Mark a guest as collected once the driver has picked them up.
  Future<PickupBooking> completePickup(String token, int bookingId) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('reception/bookings/$bookingId/pickup-complete'),
        ),
        options: _auth(token),
      );
      return PickupBooking.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: completePickup failed — $error');
      throw ReceptionException('Could not complete the pickup.');
    }
  }

  /// The SOS Alert Logs — every incident, newest first. [status] optionally
  /// narrows the list server-side (active | acknowledged | resolved | cancelled
  /// | open). Failures fall back to an empty list so the log still renders.
  Future<List<SecurityIncident>> incidents(
    String token, {
    String? status,
  }) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/incidents')).replace(
          queryParameters: (status != null && status.isNotEmpty)
              ? {'status': status}
              : null,
        ),
        options: _auth(token),
      );
      final list = (response.data?['data'] as List?) ?? const [];
      return list
          .whereType<Map>()
          .map((e) => SecurityIncident.fromJson(e.cast<String, dynamic>()))
          .toList();
    } catch (error) {
      debugPrint('ReceptionRepository: incidents failed — $error');
      return const [];
    }
  }

  /// Acknowledge an incident — the guest is told help is on the way.
  Future<SecurityIncident> respondIncident(String token, int incidentId) =>
      _incidentAction(token, incidentId, 'respond');

  /// Resolve an incident — it is dealt with and closes.
  Future<SecurityIncident> resolveIncident(String token, int incidentId) =>
      _incidentAction(token, incidentId, 'resolve');

  Future<SecurityIncident> _incidentAction(
    String token,
    int incidentId,
    String action,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('reception/incidents/$incidentId/$action'),
        ),
        options: _auth(token),
      );
      return SecurityIncident.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: $action incident failed — $error');
      throw ReceptionException('Could not update the incident.');
    }
  }

  /// Close out a departing guest — the room is freed and the departure recorded.
  Future<void> checkOut(String token, int bookingId) => _bookingAction(
    token,
    bookingId,
    'check-out',
    'Could not check the guest out.',
  );

  Future<void> _bookingAction(
    String token,
    int bookingId,
    String action,
    String fallback,
  ) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/bookings/$bookingId/$action')),
        options: _auth(token),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: $action failed — $error');
      throw ReceptionException(fallback);
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
      return 'Cannot reach the server. Check the connection.';
    }
    return 'Something went wrong. Please try again.';
  }
}
