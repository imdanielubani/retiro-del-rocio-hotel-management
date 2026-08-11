import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_menu_item.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_order.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_overview.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_tab.dart';
import 'package:retirodelrocioapp/features/bar/domain/bartender.dart';

/// Raised when a bar action could not be completed, carrying a user-facing
/// [message].
class BarException implements MessagedException {
  BarException(this.message);
  @override
  final String message;
  @override
  String toString() => message;
}

/// Talks to the Bar Tablet endpoints with the signed-in waiter/bartender's
/// staff JWT — the role is enforced server-side on every call.
class BarRepository {
  BarRepository({Dio? dio})
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

  Future<BarOverview> overview(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/overview')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null ? BarOverview.fromJson(data) : BarOverview.empty;
    } catch (error) {
      debugPrint('BarRepository: overview failed — $error');
      return BarOverview.empty;
    }
  }

  /// The Live Orders board. Optional [status] narrows it; otherwise every
  /// still-open order (new/preparing/served-pending) is returned.
  Future<List<BarOrder>> liveOrders(String token, {String? status}) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/orders')).replace(
          queryParameters: (status != null && status.isNotEmpty)
              ? {'status': status}
              : null,
        ),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => BarOrder.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('BarRepository: liveOrders failed — $error');
      return const [];
    }
  }

  Future<BarOrder?> orderDetail(String token, int id) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/orders/$id')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null ? BarOrder.fromJson(data) : null;
    } catch (error) {
      debugPrint('BarRepository: orderDetail failed — $error');
      return null;
    }
  }

  Future<BarOrder> markPreparing(String token, int id) =>
      _orderAct(token, id, 'prepare');

  Future<BarOrder> markServed(String token, int id) =>
      _orderAct(token, id, 'serve');

  Future<BarOrder> verifyAge(String token, int id) =>
      _orderAct(token, id, 'verify-age');

  Future<BarOrder> _orderAct(String token, int id, String action) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/orders/$id/$action')),
        options: _auth(token),
      );
      return BarOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BarException(_messageFrom(error));
    } catch (error) {
      debugPrint('BarRepository: $action failed — $error');
      throw BarException('Could not update this order.');
    }
  }

  /// Void one line item — the Void Item dialog.
  Future<BarOrder> voidItem(
    String token,
    int orderId,
    int itemIndex, {
    String? reason,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/orders/$orderId/void-item')),
        data: {
          'item_index': itemIndex,
          if (reason != null && reason.isNotEmpty) 'reason': reason,
        },
        options: _auth(token),
      );
      return BarOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BarException(_messageFrom(error));
    } catch (error) {
      debugPrint('BarRepository: voidItem failed — $error');
      throw BarException('Could not void this item.');
    }
  }

  /// Assign the order to a named bartender — the Assign Bartender bottom sheet.
  Future<BarOrder> assignOrder(
    String token,
    int orderId,
    int bartenderId,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/orders/$orderId/assign')),
        data: {'bartender_id': bartenderId},
        options: _auth(token),
      );
      return BarOrder.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BarException(_messageFrom(error));
    } catch (error) {
      debugPrint('BarRepository: assignOrder failed — $error');
      throw BarException('Could not assign this order.');
    }
  }

  /// The Assign Bartender bottom sheet's picker.
  Future<List<Bartender>> bartenders(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/bartenders')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => Bartender.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('BarRepository: bartenders failed — $error');
      return const [];
    }
  }

  /* ---------------- Tabs / POS ---------------- */

  Future<List<BarTab>> tabs(String token, {String status = 'open'}) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('bar/tabs'),
        ).replace(queryParameters: {'status': status}),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => BarTab.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('BarRepository: tabs failed — $error');
      return const [];
    }
  }

  Future<BarTab?> tabDetail(String token, int id) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/tabs/$id')),
        options: _auth(token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null ? BarTab.fromJson(data) : null;
    } catch (error) {
      debugPrint('BarRepository: tabDetail failed — $error');
      return null;
    }
  }

  /// A waiter opens a new tab.
  Future<BarTab> openTab(
    String token, {
    String? tableLabel,
    String? guestName,
    bool isVip = false,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/tabs')),
        data: {
          if (tableLabel != null && tableLabel.isNotEmpty)
            'table_label': tableLabel,
          if (guestName != null && guestName.isNotEmpty)
            'guest_name': guestName,
          'is_vip': isVip,
        },
        options: _auth(token),
      );
      return BarTab.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BarException(_messageFrom(error));
    } catch (error) {
      debugPrint('BarRepository: openTab failed — $error');
      throw BarException('Could not open a new tab.');
    }
  }

  /// Ring up drinks against an open tab — the POS "add to order" flow.
  Future<BarTab> addOrderToTab(
    String token,
    int tabId,
    List<Map<String, dynamic>> items,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/tabs/$tabId/orders')),
        data: {'items': items},
        options: _auth(token),
      );
      return BarTab.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BarException(_messageFrom(error));
    } catch (error) {
      debugPrint('BarRepository: addOrderToTab failed — $error');
      throw BarException('Could not add this order.');
    }
  }

  /// Close and settle a tab — the Close Tab confirm → Settle Payment flow.
  Future<BarTab> closeTab(String token, int tabId, String paymentMethod) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/tabs/$tabId/close')),
        data: {'payment_method': paymentMethod},
        options: _auth(token),
      );
      return BarTab.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BarException(_messageFrom(error));
    } catch (error) {
      debugPrint('BarRepository: closeTab failed — $error');
      throw BarException('Could not close this tab.');
    }
  }

  Future<BarTab> toggleVip(String token, int tabId) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/tabs/$tabId/vip')),
        options: _auth(token),
      );
      return BarTab.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BarException(_messageFrom(error));
    } catch (error) {
      debugPrint('BarRepository: toggleVip failed — $error');
      throw BarException('Could not update the VIP flag.');
    }
  }

  /* ---------------- Menu ---------------- */

  Future<List<BarMenuItem>> menu(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/menu')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => BarMenuItem.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('BarRepository: menu failed — $error');
      return const [];
    }
  }

  Future<BarMenuItem> toggleMenuItemAvailability(String token, int id) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/menu/$id/toggle')),
        options: _auth(token),
      );
      return BarMenuItem.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BarException(_messageFrom(error));
    } catch (error) {
      debugPrint('BarRepository: toggleMenuItemAvailability failed — $error');
      throw BarException('Could not update this drink.');
    }
  }

  /* ---------------- History ---------------- */

  /// Returns `(settledTabs, roomOrders)`.
  Future<(List<BarTab>, List<BarOrder>)> history(
    String token, {
    String? search,
  }) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/history')).replace(
          queryParameters: (search != null && search.isNotEmpty)
              ? {'search': search}
              : null,
        ),
        options: _auth(token),
      );
      final data =
          (response.data?['data'] as Map?)?.cast<String, dynamic>() ?? const {};
      final tabs = ((data['tabs'] as List?) ?? const [])
          .map((r) => BarTab.fromJson((r as Map).cast()))
          .toList();
      final roomOrders = ((data['room_orders'] as List?) ?? const [])
          .map((r) => BarOrder.fromJson((r as Map).cast()))
          .toList();
      return (tabs, roomOrders);
    } catch (error) {
      debugPrint('BarRepository: history failed — $error');
      return (<BarTab>[], <BarOrder>[]);
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
