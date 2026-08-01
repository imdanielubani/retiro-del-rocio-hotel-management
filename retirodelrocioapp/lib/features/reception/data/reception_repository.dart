import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_bill.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_booking_row.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_checkin.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_departure_readiness.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_guest.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_overview.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_pickup.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_room_status.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_visitor.dart';
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

  /// The read-only Room Status board: every room's occupancy, housekeeping
  /// cleanliness, and open maintenance faults. Failures fall back to an
  /// empty board so the screen still renders.
  Future<ReceptionRoomStatusBoard> roomStatus(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/room-status')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null
          ? ReceptionRoomStatusBoard.fromJson(data)
          : ReceptionRoomStatusBoard.empty;
    } catch (error) {
      debugPrint('ReceptionRepository: roomStatus failed — $error');
      return ReceptionRoomStatusBoard.empty;
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

  /// Every checked-in guest's outstanding room-charge balance. Failures fall
  /// back to the empty overview so the module still renders.
  Future<ReceptionBillsOverview> bills(String token, {String? search}) async {
    try {
      final uri = Uri.parse(ApiConfig.endpoint('reception/bills')).replace(
        queryParameters: (search != null && search.isNotEmpty)
            ? {'search': search}
            : null,
      );
      final response = await _dio.getUri<Map<String, dynamic>>(
        uri,
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null
          ? ReceptionBillsOverview.fromJson(data)
          : ReceptionBillsOverview.empty;
    } catch (error) {
      debugPrint('ReceptionRepository: bills failed — $error');
      return ReceptionBillsOverview.empty;
    }
  }

  /// Every visitor pass, invited or arrived — not scoped to today, unlike
  /// security's gate queue, so the desk can see who is coming later too.
  /// Optional `status` narrows by pass status.
  Future<ReceptionVisitorsOverview> visitors(
    String token, {
    String? search,
    String? status,
  }) async {
    try {
      final query = {
        if (search != null && search.isNotEmpty) 'search': search,
        if (status != null && status.isNotEmpty) 'status': status,
      };
      final uri = Uri.parse(ApiConfig.endpoint('reception/visitors')).replace(
        queryParameters: query.isEmpty ? null : query,
      );
      final response = await _dio.getUri<Map<String, dynamic>>(
        uri,
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null
          ? ReceptionVisitorsOverview.fromJson(data)
          : ReceptionVisitorsOverview.empty;
    } catch (error) {
      debugPrint('ReceptionRepository: visitors failed — $error');
      return ReceptionVisitorsOverview.empty;
    }
  }

  /// One guest's itemised folio — the same real numbers their own tablet's My
  /// Bills screen shows them.
  Future<ReceptionBillDetail> bookingBill(String token, int bookingId) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/bookings/$bookingId/bill')),
        options: _auth(token),
      );
      return ReceptionBillDetail.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: bookingBill failed — $error');
      throw ReceptionException('Could not load this bill.');
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

  /// Everything the desk should see before checking a guest out — the
  /// outstanding balance, the guest's open housekeeping requests and
  /// maintenance faults, and any visitor passes still active against the
  /// stay. Throws [ReceptionException] on failure so the checkout dialog can
  /// offer a retry rather than opening on stale or missing data.
  Future<ReceptionDepartureReadiness> departureReadiness(
    String token,
    int bookingId,
  ) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('reception/bookings/$bookingId/departure-readiness'),
        ),
        options: _auth(token),
      );
      return ReceptionDepartureReadiness.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: departureReadiness failed — $error');
      throw ReceptionException('Could not load checkout details.');
    }
  }

  /// The desk records that a guest has already paid their outstanding
  /// room-charge balance in person, by [method] — picked by reception before
  /// this fires — so a balance settled outside the app doesn't block
  /// checkout. Idempotent — settling an already-clear balance is a no-op.
  /// Returns the refreshed readiness so the checkout dialog can re-render
  /// immediately.
  Future<ReceptionDepartureReadiness> settleBill(
    String token,
    int bookingId,
    ReceptionDeskPaymentMethod method,
  ) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/bookings/$bookingId/settle-bill')),
        data: {'payment_method': method.apiValue},
        options: _auth(token),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: settleBill failed — $error');
      throw ReceptionException('Could not mark this bill as paid.');
    }
    return departureReadiness(token, bookingId);
  }

  /// Ask housekeeping to inspect the room before this guest is allowed to
  /// check out — raises a request on their Requests queue and notifies
  /// them, the same way any other housekeeping ask does. Idempotent: a
  /// second call while one is already open returns the existing one rather
  /// than raising a duplicate. Returns the refreshed readiness so the
  /// checkout dialog can re-render immediately.
  Future<ReceptionDepartureReadiness> requestInspection(
    String token,
    int bookingId,
  ) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/bookings/$bookingId/request-inspection')),
        options: _auth(token),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: requestInspection failed — $error');
      throw ReceptionException('Could not request the inspection.');
    }
    return departureReadiness(token, bookingId);
  }

  /// Close out a departing guest — the room is freed and the departure
  /// recorded. The server rejects this until the balance is settled and
  /// housekeeping has completed the requested inspection.
  Future<void> checkOut(String token, int bookingId) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/bookings/$bookingId/check-out')),
        options: _auth(token),
      );
    } on DioException catch (error) {
      throw ReceptionException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionRepository: check-out failed — $error');
      throw ReceptionException('Could not check the guest out.');
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
