import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/housekeeping/application/housekeeping_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_room.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/application/housekeeping_notification_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/presentation/screens/housekeeping_notification_screen.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/dialogs/report_fault_dialog.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/housekeeping_navigation.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_nav_rail.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_scaffold.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_widgets.dart';

enum _StatusFilter { all, dirty, preparing, clean, inspected, outOfOrder }

/// The full room grid — every room, filterable by cleanliness and
/// searchable by number, with the same mark-clean/inspected/dirty/out-of-
/// order action the dashboard's "needs attention" list offers.
class HousekeepingRoomsScreen extends ConsumerStatefulWidget {
  const HousekeepingRoomsScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<HousekeepingRoomsScreen> createState() =>
      _HousekeepingRoomsScreenState();
}

class _HousekeepingRoomsScreenState
    extends ConsumerState<HousekeepingRoomsScreen> {
  String _search = '';
  _StatusFilter _filter = _StatusFilter.all;
  int? _busyRoomId;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) HousekeepingNavigation.afterLogout(context);
  }

  void _onNav(HousekeepingNavItem item) {
    HousekeepingNavigation.select(
      context,
      widget.session,
      item,
      current: HousekeepingNavItem.rooms,
    );
  }

  void _openNotifications() {
    HousekeepingNavigation.push(
      context,
      'notifications',
      HousekeepingNotificationScreen(
        session: widget.session,
        current: HousekeepingNavItem.rooms,
      ),
    );
  }

  List<HousekeepingRoom> _visible(List<HousekeepingRoom> all) {
    var rows = all;
    final statusValue = switch (_filter) {
      _StatusFilter.dirty => 'dirty',
      _StatusFilter.preparing => 'preparing',
      _StatusFilter.clean => 'clean',
      _StatusFilter.inspected => 'inspected',
      _StatusFilter.outOfOrder => 'out_of_order',
      _StatusFilter.all => null,
    };
    if (statusValue != null) {
      rows = rows.where((r) => r.housekeepingStatus == statusValue).toList();
    }
    final q = _search.trim().toLowerCase();
    if (q.isNotEmpty) {
      rows = rows.where((r) => r.number.toLowerCase().contains(q)).toList();
    }
    return rows;
  }

  Future<void> _markRoomStatus(HousekeepingRoom room, String status) async {
    setState(() => _busyRoomId = room.id);
    try {
      await ref
          .read(housekeepingActionsProvider(_token))
          .updateRoomStatus(room.id, status);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            behavior: SnackBarBehavior.floating,
            backgroundColor: const Color(0xFF7F1D1D),
            content: Text(
              'Could not update this room.',
              style: AppTypography.style(color: Colors.white, fontSize: 14),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busyRoomId = null);
    }
  }

  Future<void> _reportFault(HousekeepingRoom room) async {
    await showHousekeepingReportFaultDialog(context, token: _token, room: room);
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(housekeepingNotificationsRealtimeProvider(_token));

    final roomsAsync = ref.watch(housekeepingRoomsProvider(_token));
    final rooms = roomsAsync.value ?? const <HousekeepingRoom>[];
    final unreadNotifications = ref.watch(
      housekeepingUnreadNotificationsProvider(_token),
    );

    return HousekeepingScaffold(
      session: widget.session,
      active: HousekeepingNavItem.rooms,
      onNav: _onNav,
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Rooms',
      trailing: HousekeepingSearchField(
        hint: 'Search room',
        onChanged: (v) => setState(() => _search = v),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _filterChips(),
          const SizedBox(height: 16),
          Expanded(
            child: roomsAsync.when(
              data: (data) => _grid(data),
              loading: () => rooms.isNotEmpty
                  ? _grid(rooms)
                  : const Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    ),
              error: (_, _) => rooms.isNotEmpty
                  ? _grid(rooms)
                  : Center(
                      child: TextButton(
                        onPressed: () =>
                            ref.invalidate(housekeepingRoomsProvider(_token)),
                        child: const Text(
                          'Could not load rooms. Retry',
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

  Widget _filterChips() {
    return SizedBox(
      height: 38,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          _chip('All', _StatusFilter.all),
          const SizedBox(width: 8),
          _chip('Dirty', _StatusFilter.dirty),
          const SizedBox(width: 8),
          _chip('Preparing', _StatusFilter.preparing),
          const SizedBox(width: 8),
          _chip('Clean', _StatusFilter.clean),
          const SizedBox(width: 8),
          _chip('Inspected', _StatusFilter.inspected),
          const SizedBox(width: 8),
          _chip('Out of Order', _StatusFilter.outOfOrder),
        ],
      ),
    );
  }

  Widget _chip(String label, _StatusFilter value) {
    final selected = _filter == value;
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.16)
          : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _filter = value),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: 0.6),
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  Widget _grid(List<HousekeepingRoom> all) {
    final rows = _visible(all);
    if (rows.isEmpty) {
      return const HousekeepingSectionEmpty(
        icon: Icons.meeting_room_outlined,
        message: 'No rooms match this filter',
      );
    }
    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(housekeepingRoomsProvider(_token)),
      child: GridView.builder(
        padding: const EdgeInsets.only(bottom: 24),
        gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
          maxCrossAxisExtent: 320,
          mainAxisExtent: 230,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
        ),
        itemCount: rows.length,
        itemBuilder: (_, i) {
          final room = rows[i];
          return HousekeepingRoomCard(
            room: room,
            busy: _busyRoomId == room.id,
            onMarkStatus: (status) => _markRoomStatus(room, status),
            onReportFault: () => _reportFault(room),
          );
        },
      ),
    );
  }
}
