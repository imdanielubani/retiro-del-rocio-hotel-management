import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/staff_top_bar.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/housekeeping/application/housekeeping_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_guest_request.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_overview.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_room.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/application/housekeeping_notification_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/presentation/screens/housekeeping_notification_screen.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/dialogs/report_fault_dialog.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/housekeeping_navigation.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_nav_rail.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_widgets.dart';
import 'package:retirodelrocioapp/features/staff_chat/application/staff_chat_providers.dart';
import 'package:retirodelrocioapp/features/staff_intercom/presentation/widgets/staff_intercom_call_gate.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The housekeeper's home dashboard — the rooms needing attention next
/// (checkout-today rooms first) and the guest requests still waiting,
/// inside the same nav-rail + top-bar shell reception and security use.
class HousekeepingDashboardScreen extends ConsumerStatefulWidget {
  const HousekeepingDashboardScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<HousekeepingDashboardScreen> createState() =>
      _HousekeepingDashboardScreenState();
}

class _HousekeepingDashboardScreenState
    extends ConsumerState<HousekeepingDashboardScreen> {
  int? _busyRoomId;
  int? _busyRequestId;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) Navigator.of(context).pop();
  }

  void _onNav(HousekeepingNavItem item) {
    if (item == HousekeepingNavItem.dashboard) return; // already here
    HousekeepingNavigation.open(context, widget.session, item);
  }

  Future<void> _markRoomStatus(HousekeepingRoom room, String status) async {
    setState(() => _busyRoomId = room.id);
    try {
      await ref
          .read(housekeepingActionsProvider(_token))
          .updateRoomStatus(room.id, status);
    } catch (_) {
      _showFailure('Could not update this room.');
    } finally {
      if (mounted) setState(() => _busyRoomId = null);
    }
  }

  Future<void> _completeRequest(HousekeepingGuestRequest request) async {
    setState(() => _busyRequestId = request.id);
    try {
      await ref
          .read(housekeepingActionsProvider(_token))
          .completeRequest(request.id);
    } catch (_) {
      _showFailure('Could not update this request.');
    } finally {
      if (mounted) setState(() => _busyRequestId = null);
    }
  }

  void _openNotifications() {
    HousekeepingNavigation.push(
      context,
      'notifications',
      HousekeepingNotificationScreen(
        session: widget.session,
        current: HousekeepingNavItem.dashboard,
      ),
    );
  }

  Future<void> _reportFault(HousekeepingRoom room) async {
    await showHousekeepingReportFaultDialog(context, token: _token, room: room);
  }

  void _showFailure(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF7F1D1D),
        behavior: SnackBarBehavior.floating,
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(housekeepingNotificationsRealtimeProvider(_token));
    ref.watch(housekeepingNotificationChimeProvider(_token));
    ref.watch(staffChatChimeProvider(_token));
    ref.watch(staffChatRealtimeProvider((_token, widget.session.userId)));
    watchStaffIntercomCall(context, ref, widget.session);

    final overviewAsync = ref.watch(housekeepingOverviewProvider(_token));
    final overview = overviewAsync.value ?? HousekeepingOverview.empty;
    final weather = ref.watch(weatherProvider).value;
    final unreadNotifications = ref.watch(
      housekeepingUnreadNotificationsProvider(_token),
    );

    return SessionGuard(
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
            const ColoredBox(color: Color(0xF2000000)),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    HousekeepingNavRail(
                      active: HousekeepingNavItem.dashboard,
                      onSelect: _onNav,
                      onLogout: _logout,
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          StaffTopBar(
                            name: widget.session.name,
                            role: 'Housekeeping',
                            brandLabel: 'HOUSEKEEPING',
                            weather: weather,
                            hasAlert: overview.outOfOrder > 0,
                            hasUnreadNotifications: unreadNotifications > 0,
                            onNotifications: _openNotifications,
                            initialsFallback: 'HK',
                          ),
                          const SizedBox(height: 20),
                          _header(),
                          const SizedBox(height: 20),
                          Expanded(
                            child: overviewAsync.when(
                              data: (data) => _content(data),
                              loading: () =>
                                  overview.rooms.isNotEmpty ||
                                      overview.requests.isNotEmpty
                                  ? _content(overview)
                                  : const Center(
                                      child: CircularProgressIndicator(
                                        color: AppColors.gold,
                                      ),
                                    ),
                              error: (_, _) =>
                                  overview.rooms.isNotEmpty ||
                                      overview.requests.isNotEmpty
                                  ? _content(overview)
                                  : _errorState(),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Housekeeping',
          style: AppTypography.style(color: AppColors.gold, fontSize: 12),
        ),
        const SizedBox(height: 3),
        Text(
          'Dashboard',
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 36,
            fontWeight: FontWeight.w700,
            height: 1.1,
          ),
        ),
      ],
    );
  }

  Widget _content(HousekeepingOverview data) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(flex: 3, child: _statsAndRooms(data)),
        const SizedBox(width: 24),
        SizedBox(width: 360, child: _requestsColumn(data)),
      ],
    );
  }

  Widget _statsAndRooms(HousekeepingOverview data) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: HousekeepingStatCard(
                label: 'NEEDS ATTENTION',
                value: data.needsAttention,
                accent: AppColors.gold,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: HousekeepingStatCard(
                label: 'OUT OF ORDER',
                value: data.outOfOrder,
                accent: kHkRed,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: HousekeepingStatCard(
                label: 'PENDING REQUESTS',
                value: data.pendingRequests,
                accent: kHkBlue,
              ),
            ),
          ],
        ),
        const SizedBox(height: 24),
        Expanded(
          child: HousekeepingSectionPanel(
            title: 'ROOMS NEEDING ATTENTION',
            trailing: _countBadge(data.rooms.length),
            child: data.rooms.isEmpty
                ? const HousekeepingSectionEmpty(
                    icon: Icons.check_circle_outline_rounded,
                    message: 'No rooms need attention',
                  )
                : ListView.separated(
                    padding: EdgeInsets.zero,
                    itemCount: data.rooms.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 14),
                    itemBuilder: (_, i) {
                      final room = data.rooms[i];
                      return HousekeepingRoomCard(
                        room: room,
                        busy: _busyRoomId == room.id,
                        onMarkStatus: (status) => _markRoomStatus(room, status),
                        onReportFault: () => _reportFault(room),
                      );
                    },
                  ),
          ),
        ),
      ],
    );
  }

  Widget _requestsColumn(HousekeepingOverview data) {
    return HousekeepingSectionPanel(
      title: 'GUEST REQUESTS',
      trailing: _countBadge(data.requests.length),
      child: data.requests.isEmpty
          ? const HousekeepingSectionEmpty(
              icon: Icons.room_service_outlined,
              message: 'No pending requests',
            )
          : ListView.separated(
              padding: EdgeInsets.zero,
              itemCount: data.requests.length,
              separatorBuilder: (_, _) => const SizedBox(height: 14),
              itemBuilder: (_, i) {
                final request = data.requests[i];
                return HousekeepingRequestCard(
                  request: request,
                  busy: _busyRequestId == request.id,
                  onComplete: () => _completeRequest(request),
                );
              },
            ),
    );
  }

  Widget _countBadge(int count) {
    if (count == 0) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 2),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        '$count',
        style: AppTypography.style(
          color: AppColors.gold,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _errorState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.wifi_off_rounded,
            size: 30,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          const SizedBox(height: 12),
          Text(
            'Could not load the dashboard.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.6),
              fontSize: 15,
            ),
          ),
          const SizedBox(height: 16),
          Material(
            color: AppColors.gold,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              onTap: () => ref.invalidate(housekeepingOverviewProvider(_token)),
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 24,
                  vertical: 12,
                ),
                child: Text(
                  'Retry',
                  style: AppTypography.style(
                    color: AppColors.onGold,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
