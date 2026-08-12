import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/asset.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/maintenance_overview.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/parts_request.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/technician.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';

/// Raised when a maintenance action could not be completed, carrying a
/// user-facing [message].
class MaintenanceException implements MessagedException {
  MaintenanceException(this.message);
  @override
  final String message;
  @override
  String toString() => message;
}

/// Talks to the maintenance tablet endpoints with the signed-in technician's
/// staff JWT — the role is enforced server-side on every call.
class MaintenanceRepository {
  MaintenanceRepository({Dio? dio})
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
  Future<MaintenanceOverview> overview(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/overview')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null
          ? MaintenanceOverview.fromJson(data)
          : MaintenanceOverview.empty;
    } catch (error) {
      debugPrint('MaintenanceRepository: overview failed — $error');
      return MaintenanceOverview.empty;
    }
  }

  /// Every work order, optionally narrowed by [status] or [priority].
  Future<List<WorkOrder>> workOrders(
    String token, {
    String? status,
    String? priority,
  }) async {
    try {
      final query = {
        if (status != null && status.isNotEmpty) 'status': status,
        if (priority != null && priority.isNotEmpty) 'priority': priority,
      };
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('maintenance/work-orders'),
        ).replace(queryParameters: query.isEmpty ? null : query),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => WorkOrder.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('MaintenanceRepository: workOrders failed — $error');
      return const [];
    }
  }

  /// Report a fault, optionally against a room and/or a registered asset.
  Future<WorkOrder> createWorkOrder(
    String token, {
    int? roomUnitId,
    int? assetId,
    String? assetLabel,
    required String title,
    String? description,
    String priority = 'medium',
    String? reportedBy,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/work-orders')),
        data: {
          if (roomUnitId != null) 'room_unit_id': roomUnitId,
          if (assetId != null) 'asset_id': assetId,
          if (assetLabel != null && assetLabel.isNotEmpty)
            'asset_label': assetLabel,
          'title': title,
          if (description != null && description.isNotEmpty)
            'description': description,
          'priority': priority,
          if (reportedBy != null && reportedBy.isNotEmpty)
            'reported_by': reportedBy,
        },
        options: _auth(token),
      );
      return WorkOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceRepository: createWorkOrder failed — $error');
      throw MaintenanceException('Could not report this fault.');
    }
  }

  /// One order's detail — everything the board shows plus its attachments.
  Future<WorkOrder?> workOrderDetail(String token, int id) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/work-orders/$id')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null ? WorkOrder.fromJson(data) : null;
    } catch (error) {
      debugPrint('MaintenanceRepository: workOrderDetail failed — $error');
      return null;
    }
  }

  /// Attach a photo or video a technician just captured to a work order.
  Future<WorkOrder> uploadAttachment(
    String token,
    int id,
    String filePath,
  ) async {
    try {
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(filePath),
      });
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('maintenance/work-orders/$id/attachments'),
        ),
        data: form,
        options: _auth(token),
      );
      return WorkOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceRepository: uploadAttachment failed — $error');
      throw MaintenanceException('Could not attach this file.');
    }
  }

  Future<WorkOrder> acceptWorkOrder(String token, int id) =>
      _act(token, id, 'accept');

  Future<WorkOrder> startWorkOrder(String token, int id) =>
      _act(token, id, 'start');

  Future<WorkOrder> completeWorkOrder(String token, int id) =>
      _act(token, id, 'complete');

  Future<WorkOrder> _act(String token, int id, String action) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/work-orders/$id/$action')),
        options: _auth(token),
      );
      return WorkOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceRepository: $action failed — $error');
      throw MaintenanceException('Could not update this order.');
    }
  }

  /// The room picker for "report a fault against a room".
  Future<List<MaintenanceRoomOption>> rooms(
    String token, {
    String? search,
  }) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/rooms')).replace(
          queryParameters: (search != null && search.isNotEmpty)
              ? {'search': search}
              : null,
        ),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => MaintenanceRoomOption.fromJson((r as Map).cast()))
          .toList();
    } catch (error) {
      debugPrint('MaintenanceRepository: rooms failed — $error');
      return const [];
    }
  }

  /// The Assets tab list. Optional `search` (name), `category`, and
  /// `dueOnly` (preventive-maintenance due/overdue only).
  Future<List<Asset>> assets(
    String token, {
    String? search,
    String? category,
    bool dueOnly = false,
  }) async {
    try {
      final query = {
        if (search != null && search.isNotEmpty) 'search': search,
        if (category != null && category.isNotEmpty) 'category': category,
        if (dueOnly) 'due': '1',
      };
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('maintenance/assets'),
        ).replace(queryParameters: query.isEmpty ? null : query),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => Asset.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('MaintenanceRepository: assets failed — $error');
      return const [];
    }
  }

  /// Register a new asset.
  Future<Asset> createAsset(
    String token, {
    required String name,
    String? category,
    int? roomUnitId,
    String? locationLabel,
    String? notes,
    int? serviceIntervalDays,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/assets')),
        data: {
          'name': name,
          if (category != null && category.isNotEmpty) 'category': category,
          if (roomUnitId != null) 'room_unit_id': roomUnitId,
          if (locationLabel != null && locationLabel.isNotEmpty)
            'location_label': locationLabel,
          if (notes != null && notes.isNotEmpty) 'notes': notes,
          if (serviceIntervalDays != null)
            'service_interval_days': serviceIntervalDays,
        },
        options: _auth(token),
      );
      return Asset.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceRepository: createAsset failed — $error');
      throw MaintenanceException('Could not register this asset.');
    }
  }

  /// The asset detail screen: its profile plus service history.
  Future<Asset?> assetDetail(String token, int id) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/assets/$id')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null ? Asset.fromJson(data) : null;
    } catch (error) {
      debugPrint('MaintenanceRepository: assetDetail failed — $error');
      return null;
    }
  }

  /// Log that an asset was just serviced, restarting its PM interval.
  Future<Asset> markAssetServiced(String token, int id) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/assets/$id/mark-serviced')),
        options: _auth(token),
      );
      return Asset.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceRepository: markAssetServiced failed — $error');
      throw MaintenanceException('Could not update this asset.');
    }
  }

  /// The Requests tab list. Optional `status` narrows it.
  Future<List<PartsRequest>> partsRequests(
    String token, {
    String? status,
  }) async {
    try {
      final query = {if (status != null && status.isNotEmpty) 'status': status};
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('maintenance/parts-requests'),
        ).replace(queryParameters: query.isEmpty ? null : query),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => PartsRequest.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('MaintenanceRepository: partsRequests failed — $error');
      return const [];
    }
  }

  /// Ask for a part against a work order.
  Future<PartsRequest> createPartsRequest(
    String token,
    int workOrderId, {
    required String partName,
    int quantity = 1,
    String? note,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint(
            'maintenance/work-orders/$workOrderId/parts-requests',
          ),
        ),
        data: {
          'part_name': partName,
          'quantity': quantity,
          if (note != null && note.isNotEmpty) 'note': note,
        },
        options: _auth(token),
      );
      return PartsRequest.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceRepository: createPartsRequest failed — $error');
      throw MaintenanceException('Could not submit this request.');
    }
  }

  Future<PartsRequest> fulfillPartsRequest(String token, int id) =>
      _partsAct(token, id, 'fulfill');

  Future<PartsRequest> denyPartsRequest(String token, int id) =>
      _partsAct(token, id, 'deny');

  Future<PartsRequest> _partsAct(String token, int id, String action) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/parts-requests/$id/$action')),
        options: _auth(token),
      );
      return PartsRequest.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint(
        'MaintenanceRepository: $action (parts request) failed — $error',
      );
      throw MaintenanceException('Could not update this request.');
    }
  }

  Future<WorkOrder> escalateWorkOrder(String token, int id) =>
      _act(token, id, 'escalate');

  /// The manual status override — the Status Update bottom sheet.
  Future<WorkOrder> updateWorkOrderStatus(
    String token,
    int id,
    String status,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/work-orders/$id/status')),
        data: {'status': status},
        options: _auth(token),
      );
      return WorkOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint(
        'MaintenanceRepository: updateWorkOrderStatus failed — $error',
      );
      throw MaintenanceException('Could not update this order.');
    }
  }

  /// Assign the order to a named technician — the Assign Technician bottom sheet.
  Future<WorkOrder> assignWorkOrder(
    String token,
    int id,
    int technicianId,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/work-orders/$id/assign')),
        data: {'technician_id': technicianId},
        options: _auth(token),
      );
      return WorkOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceRepository: assignWorkOrder failed — $error');
      throw MaintenanceException('Could not assign this order.');
    }
  }

  /// The Assign Technician bottom sheet's picker.
  Future<List<Technician>> technicians(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/technicians')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => Technician.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('MaintenanceRepository: technicians failed — $error');
      return const [];
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
