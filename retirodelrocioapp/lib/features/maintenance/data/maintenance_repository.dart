import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/maintenance_overview.dart';
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
      return data != null ? MaintenanceOverview.fromJson(data) : MaintenanceOverview.empty;
    } catch (error) {
      debugPrint('MaintenanceRepository: overview failed — $error');
      return MaintenanceOverview.empty;
    }
  }

  /// Every work order, optionally narrowed by [status] or [priority].
  Future<List<WorkOrder>> workOrders(String token, {String? status, String? priority}) async {
    try {
      final query = {
        if (status != null && status.isNotEmpty) 'status': status,
        if (priority != null && priority.isNotEmpty) 'priority': priority,
      };
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/work-orders'))
            .replace(queryParameters: query.isEmpty ? null : query),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => WorkOrder.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('MaintenanceRepository: workOrders failed — $error');
      return const [];
    }
  }

  /// Report a fault, optionally against a room.
  Future<WorkOrder> createWorkOrder(
    String token, {
    int? roomUnitId,
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
          if (assetLabel != null && assetLabel.isNotEmpty) 'asset_label': assetLabel,
          'title': title,
          if (description != null && description.isNotEmpty) 'description': description,
          'priority': priority,
          if (reportedBy != null && reportedBy.isNotEmpty) 'reported_by': reportedBy,
        },
        options: _auth(token),
      );
      return WorkOrder.fromJson((response.data!['data'] as Map).cast<String, dynamic>());
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceRepository: createWorkOrder failed — $error');
      throw MaintenanceException('Could not report this fault.');
    }
  }

  Future<WorkOrder> acceptWorkOrder(String token, int id) => _act(token, id, 'accept');

  Future<WorkOrder> startWorkOrder(String token, int id) => _act(token, id, 'start');

  Future<WorkOrder> completeWorkOrder(String token, int id) => _act(token, id, 'complete');

  Future<WorkOrder> _act(String token, int id, String action) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/work-orders/$id/$action')),
        options: _auth(token),
      );
      return WorkOrder.fromJson((response.data!['data'] as Map).cast<String, dynamic>());
    } on DioException catch (error) {
      throw MaintenanceException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceRepository: $action failed — $error');
      throw MaintenanceException('Could not update this order.');
    }
  }

  /// The room picker for "report a fault against a room".
  Future<List<MaintenanceRoomOption>> rooms(String token, {String? search}) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/rooms')).replace(
          queryParameters: (search != null && search.isNotEmpty) ? {'search': search} : null,
        ),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => MaintenanceRoomOption.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('MaintenanceRepository: rooms failed — $error');
      return const [];
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
