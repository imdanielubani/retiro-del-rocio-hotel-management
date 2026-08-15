import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_menu_item.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_order.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_overview.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_staff.dart';

/// Raised when a kitchen action could not be completed, carrying a
/// user-facing [message].
class KitchenException implements MessagedException {
  KitchenException(this.message);
  @override
  final String message;
  @override
  String toString() => message;
}

/// Talks to the Kitchen Tablet endpoints with the signed-in chef/kitchen
/// staffer's staff JWT — the role is enforced server-side on every call.
class KitchenRepository {
  KitchenRepository({Dio? dio})
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

  Future<KitchenOverview> overview(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/overview')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null
          ? KitchenOverview.fromJson(data)
          : KitchenOverview.empty;
    } catch (error) {
      debugPrint('KitchenRepository: overview failed — $error');
      return KitchenOverview.empty;
    }
  }

  /// The Live Board. Optional [status] narrows it; otherwise every
  /// still-open ticket (new/preparing/served-pending) is returned.
  Future<List<KitchenOrder>> liveOrders(String token, {String? status}) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/orders')).replace(
          queryParameters: (status != null && status.isNotEmpty)
              ? {'status': status}
              : null,
        ),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => KitchenOrder.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('KitchenRepository: liveOrders failed — $error');
      return const [];
    }
  }

  Future<KitchenOrder?> orderDetail(String token, int id) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/orders/$id')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null ? KitchenOrder.fromJson(data) : null;
    } catch (error) {
      debugPrint('KitchenRepository: orderDetail failed — $error');
      return null;
    }
  }

  Future<KitchenOrder> markPreparing(String token, int id) =>
      _orderAct(token, id, 'prepare');

  /// Ready for pickup — not yet actually served to the guest, which is the
  /// Bar Tablet's own action once the waiter collects it (or, for a plain
  /// guest-tablet order with no waiter involved, this finalises it directly).
  Future<KitchenOrder> markReady(String token, int id) =>
      _orderAct(token, id, 'serve');

  /// Dispatch a pure room-service ticket (no waiter/bar tab running it) for
  /// delivery to the guest's room. Rejected server-side for a dine-in/POS
  /// order — that hand-off is the Bar Tablet's own action instead.
  Future<KitchenOrder> markOnTheWay(String token, int id) =>
      _orderAct(token, id, 'on-way');

  /// Confirm a room-service ticket reached the guest.
  Future<KitchenOrder> markDelivered(String token, int id) =>
      _orderAct(token, id, 'deliver');

  /// Set or increase how long this ticket still needs.
  Future<KitchenOrder> setEta(String token, int id, int minutes) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/orders/$id/eta')),
        data: {'minutes': minutes},
        options: _auth(token),
      );
      return KitchenOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw KitchenException(_messageFrom(error));
    } catch (error) {
      debugPrint('KitchenRepository: setEta failed — $error');
      throw KitchenException('Could not update the ready time.');
    }
  }

  Future<KitchenOrder> _orderAct(String token, int id, String action) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/orders/$id/$action')),
        options: _auth(token),
      );
      return KitchenOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw KitchenException(_messageFrom(error));
    } catch (error) {
      debugPrint('KitchenRepository: $action failed — $error');
      throw KitchenException('Could not update this ticket.');
    }
  }

  /// Void one line item — the Void/Cancel Item dialog.
  Future<KitchenOrder> voidItem(
    String token,
    int orderId,
    int itemIndex, {
    String? reason,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/orders/$orderId/void-item')),
        data: {
          'item_index': itemIndex,
          if (reason != null && reason.isNotEmpty) 'reason': reason,
        },
        options: _auth(token),
      );
      return KitchenOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw KitchenException(_messageFrom(error));
    } catch (error) {
      debugPrint('KitchenRepository: voidItem failed — $error');
      throw KitchenException('Could not void this item.');
    }
  }

  /// Assign the ticket to a named chef — the Assign to Station bottom sheet.
  Future<KitchenOrder> assignOrder(
    String token,
    int orderId,
    int chefId,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/orders/$orderId/assign')),
        data: {'chef_id': chefId},
        options: _auth(token),
      );
      return KitchenOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw KitchenException(_messageFrom(error));
    } catch (error) {
      debugPrint('KitchenRepository: assignOrder failed — $error');
      throw KitchenException('Could not assign this ticket.');
    }
  }

  /// The Assign to Station bottom sheet's picker.
  Future<List<KitchenStaff>> chefs(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/chefs')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => KitchenStaff.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('KitchenRepository: chefs failed — $error');
      return const [];
    }
  }

  /* ---------------- Menu Availability ---------------- */

  Future<List<KitchenMenuItem>> menu(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/menu')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => KitchenMenuItem.fromJson((r as Map).cast()))
          .toList();
    } catch (error) {
      debugPrint('KitchenRepository: menu failed — $error');
      return const [];
    }
  }

  /// The 86-item confirm's availability toggle.
  Future<KitchenMenuItem> toggleMenuItemAvailability(
    String token,
    int id,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/menu/$id/toggle')),
        options: _auth(token),
      );
      return KitchenMenuItem.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw KitchenException(_messageFrom(error));
    } catch (error) {
      debugPrint(
        'KitchenRepository: toggleMenuItemAvailability failed — $error',
      );
      throw KitchenException('Could not update this dish.');
    }
  }

  /* ---------------- History ---------------- */

  Future<List<KitchenOrder>> history(String token, {String? search}) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/history')).replace(
          queryParameters: (search != null && search.isNotEmpty)
              ? {'search': search}
              : null,
        ),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => KitchenOrder.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('KitchenRepository: history failed — $error');
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
