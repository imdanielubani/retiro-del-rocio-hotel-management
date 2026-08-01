import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/housekeeping/application/housekeeping_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_guest_request.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/application/housekeeping_notification_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/presentation/screens/housekeeping_notification_screen.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/housekeeping_navigation.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_nav_rail.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_scaffold.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_widgets.dart';

enum _Filter { all, pending, completed }

/// Every guest request — towels, amenities, DND, make-up room — with one tap
/// to clear it.
class HousekeepingRequestsScreen extends ConsumerStatefulWidget {
  const HousekeepingRequestsScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<HousekeepingRequestsScreen> createState() => _HousekeepingRequestsScreenState();
}

class _HousekeepingRequestsScreenState extends ConsumerState<HousekeepingRequestsScreen> {
  _Filter _filter = _Filter.all;
  int? _busyId;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) HousekeepingNavigation.afterLogout(context);
  }

  void _onNav(HousekeepingNavItem item) {
    if (item == HousekeepingNavItem.chat) {
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ComingSoonScreen(title: 'Chat')));
      return;
    }
    HousekeepingNavigation.select(context, widget.session, item, current: HousekeepingNavItem.requests);
  }

  void _openNotifications() {
    HousekeepingNavigation.push(
      context,
      'notifications',
      HousekeepingNotificationScreen(session: widget.session, current: HousekeepingNavItem.requests),
    );
  }

  List<HousekeepingGuestRequest> _visible(List<HousekeepingGuestRequest> all) {
    return switch (_filter) {
      _Filter.pending => all.where((r) => r.isPending).toList(),
      _Filter.completed => all.where((r) => !r.isPending).toList(),
      _Filter.all => all,
    };
  }

  Future<void> _complete(HousekeepingGuestRequest request) async {
    setState(() => _busyId = request.id);
    try {
      await ref.read(housekeepingActionsProvider(_token)).completeRequest(request.id);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            behavior: SnackBarBehavior.floating,
            backgroundColor: const Color(0xFF7F1D1D),
            content: Text(
              'Could not update this request.',
              style: AppTypography.style(color: Colors.white, fontSize: 14),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(housekeepingNotificationsRealtimeProvider(_token));

    final requestsAsync = ref.watch(housekeepingRequestsProvider(_token));
    final requests = requestsAsync.value ?? const <HousekeepingGuestRequest>[];
    final pendingCount = requests.where((r) => r.isPending).length;
    final unreadNotifications = ref.watch(housekeepingUnreadNotificationsProvider(_token));

    return HousekeepingScaffold(
      session: widget.session,
      active: HousekeepingNavItem.requests,
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
                        onPressed: () => ref.invalidate(housekeepingRequestsProvider(_token)),
                        child: const Text(
                          'Could not load requests. Retry',
                          style: TextStyle(color: AppColors.gold),
                        ),
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
        _chip('Completed', _Filter.completed),
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
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
              if (badge != null && badge > 0) ...[
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: const BoxDecoration(color: Color(0xFFEF4444), shape: BoxShape.circle),
                  child: Text('$badge', style: AppTypography.style(color: Colors.white, fontSize: 11)),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _list(List<HousekeepingGuestRequest> all) {
    final rows = _visible(all);
    if (rows.isEmpty) {
      return const HousekeepingSectionEmpty(icon: Icons.room_service_outlined, message: 'No requests here');
    }
    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(housekeepingRequestsProvider(_token)),
      child: ListView.separated(
        padding: const EdgeInsets.only(bottom: 24),
        itemCount: rows.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) {
          final request = rows[i];
          return HousekeepingRequestCard(
            request: request,
            busy: _busyId == request.id,
            onComplete: () => _complete(request),
          );
        },
      ),
    );
  }
}
