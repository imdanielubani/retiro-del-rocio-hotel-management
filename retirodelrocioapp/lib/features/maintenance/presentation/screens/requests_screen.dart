import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/parts_request.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/application/maintenance_notification_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/presentation/screens/maintenance_notification_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/maintenance_navigation.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_scaffold.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';

enum _Filter { all, pending, fulfilled, denied }

/// The Requests tab — every part a technician has asked for, across every
/// work order, with fulfil/deny actions while a request is still pending.
class RequestsScreen extends ConsumerStatefulWidget {
  const RequestsScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<RequestsScreen> createState() => _RequestsScreenState();
}

class _RequestsScreenState extends ConsumerState<RequestsScreen> {
  _Filter _filter = _Filter.all;
  int? _busyId;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) MaintenanceNavigation.afterLogout(context);
  }

  void _onNav(MaintenanceNavItem item) {
    if (item == MaintenanceNavItem.chat) {
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ComingSoonScreen(title: 'Chat')));
      return;
    }
    MaintenanceNavigation.select(context, widget.session, item, current: MaintenanceNavItem.requests);
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MaintenanceNotificationScreen(session: widget.session, current: MaintenanceNavItem.requests),
      ),
    );
  }

  List<PartsRequest> _visible(List<PartsRequest> all) {
    return switch (_filter) {
      _Filter.all => all,
      _Filter.pending => all.where((r) => r.isPending).toList(),
      _Filter.fulfilled => all.where((r) => r.isFulfilled).toList(),
      _Filter.denied => all.where((r) => r.isDenied).toList(),
    };
  }

  Future<void> _fulfill(PartsRequest request) async {
    setState(() => _busyId = request.id);
    try {
      await ref.read(maintenanceActionsProvider(_token)).fulfillPartsRequest(request.id);
    } catch (_) {
      _showFailure('Could not update this request.');
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _deny(PartsRequest request) async {
    setState(() => _busyId = request.id);
    try {
      await ref.read(maintenanceActionsProvider(_token)).denyPartsRequest(request.id);
    } catch (_) {
      _showFailure('Could not update this request.');
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  void _showFailure(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF7F1D1D),
        behavior: SnackBarBehavior.floating,
        content: Text(message, style: AppTypography.style(color: Colors.white, fontSize: 14)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(maintenanceNotificationsRealtimeProvider(_token));
    ref.watch(maintenanceNotificationChimeProvider(_token));
    ref.watch(maintenanceSlaBreachChimeProvider(_token));

    final requestsAsync = ref.watch(maintenancePartsRequestsProvider(_token));
    final requests = requestsAsync.value ?? const <PartsRequest>[];
    final pendingCount = requests.where((r) => r.isPending).length;
    final unreadNotifications = ref.watch(maintenanceUnreadNotificationsProvider(_token));

    return MaintenanceScaffold(
      session: widget.session,
      active: MaintenanceNavItem.requests,
      onNav: _onNav,
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Requests',
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _filterChips(pendingCount),
          const SizedBox(height: 16),
          Expanded(
            child: requestsAsync.when(
              data: (data) => _list(data),
              loading: () => requests.isNotEmpty
                  ? _list(requests)
                  : const Center(child: CircularProgressIndicator(color: AppColors.gold)),
              error: (_, _) => requests.isNotEmpty
                  ? _list(requests)
                  : Center(
                      child: TextButton(
                        onPressed: () => ref.invalidate(maintenancePartsRequestsProvider(_token)),
                        child: const Text('Could not load requests. Retry', style: TextStyle(color: AppColors.gold)),
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _filterChips(int pendingCount) {
    return Row(
      children: [
        _chip('All', _Filter.all),
        const SizedBox(width: 8),
        _chip('Pending', _Filter.pending, badge: pendingCount),
        const SizedBox(width: 8),
        _chip('Fulfilled', _Filter.fulfilled),
        const SizedBox(width: 8),
        _chip('Denied', _Filter.denied),
      ],
    );
  }

  Widget _chip(String label, _Filter value, {int? badge}) {
    final selected = _filter == value;
    return Material(
      color: selected ? AppColors.gold.withValues(alpha: 0.16) : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _filter = value),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected ? AppColors.gold.withValues(alpha: 0.4) : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                style: AppTypography.style(
                  color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.6),
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
              if (badge != null && badge > 0) ...[
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
                  decoration: BoxDecoration(
                    color: AppColors.gold.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    '$badge',
                    style: AppTypography.style(color: AppColors.gold, fontSize: 11, fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _list(List<PartsRequest> all) {
    final rows = _visible(all);
    if (rows.isEmpty) {
      return const MaintenanceSectionEmpty(icon: Icons.handyman_outlined, message: 'No parts requests match this filter');
    }
    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(maintenancePartsRequestsProvider(_token)),
      child: ListView.separated(
        padding: const EdgeInsets.only(bottom: 24),
        itemCount: rows.length,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (_, i) {
          final request = rows[i];
          return PartsRequestCard(
            request: request,
            busy: _busyId == request.id,
            onFulfill: () => _fulfill(request),
            onDeny: () => _deny(request),
          );
        },
      ),
    );
  }
}
