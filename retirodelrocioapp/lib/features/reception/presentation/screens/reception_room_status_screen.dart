import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_room_status.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/screens/reception_notification_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

/// The read-only Room Status board — every room's occupancy (from its
/// booking), housekeeping's cleanliness flag, and whether maintenance has an
/// open fault against it, in one hotel-wide view. Reception watches
/// housekeeping and maintenance work here; it doesn't change either — status
/// changes stay owned by the housekeeping and maintenance tablets.
class ReceptionRoomStatusScreen extends ConsumerStatefulWidget {
  const ReceptionRoomStatusScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionRoomStatusScreen> createState() =>
      _ReceptionRoomStatusScreenState();
}

enum _Filter { all, occupied, maintenance, preparing, dirty, ready }

class _ReceptionRoomStatusScreenState
    extends ConsumerState<ReceptionRoomStatusScreen> {
  String _search = '';
  _Filter _filter = _Filter.all;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) ReceptionNavigation.afterLogout(context);
  }

  void _openNotifications() {
    ReceptionNavigation.push(
      context,
      'notifications',
      ReceptionNotificationScreen(
        session: widget.session,
        current: ReceptionNavItem.roomStatus,
      ),
    );
  }

  List<ReceptionRoomStatusEntry> _filtered(List<ReceptionRoomStatusEntry> all) {
    var rows = all;
    if (_filter != _Filter.all) {
      rows = rows.where((r) => r.boardStatus.name == _filter.name).toList();
    }

    final q = _search.trim().toLowerCase();
    if (q.isEmpty) return rows;
    return rows
        .where(
          (r) =>
              r.number.toLowerCase().contains(q) ||
              (r.roomName ?? '').toLowerCase().contains(q) ||
              (r.guestName ?? '').toLowerCase().contains(q),
        )
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final boardAsync = ref.watch(receptionRoomStatusProvider(_token));
    final board = boardAsync.value ?? ReceptionRoomStatusBoard.empty;
    final unreadNotifications = ref.watch(
      receptionUnreadNotificationsProvider(_token),
    );

    return ReceptionScaffold(
      session: widget.session,
      active: ReceptionNavItem.roomStatus,
      onNav: (item) => ReceptionNavigation.select(
        context,
        widget.session,
        item,
        current: ReceptionNavItem.roomStatus,
      ),
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Room Status',
      trailing: ReceptionSearchField(
        hint: 'Search room, suite or guest',
        onChanged: (v) => setState(() => _search = v),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _summaryCards(board),
          const SizedBox(height: 16),
          _filterChips(board),
          const SizedBox(height: 12),
          Expanded(
            child: boardAsync.when(
              loading: () => board.rooms.isNotEmpty
                  ? _list(board)
                  : const Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    ),
              error: (_, _) => board.rooms.isNotEmpty
                  ? _list(board)
                  : Center(
                      child: TextButton(
                        onPressed: () =>
                            ref.invalidate(receptionRoomStatusProvider(_token)),
                        child: const Text(
                          'Could not load room status. Retry',
                          style: TextStyle(color: AppColors.gold),
                        ),
                      ),
                    ),
              data: (data) => _list(data),
            ),
          ),
        ],
      ),
    );
  }

  Widget _summaryCards(ReceptionRoomStatusBoard board) {
    return Row(
      children: [
        Expanded(
          child: ReceptionStatCard(
            label: 'OCCUPIED',
            value: board.occupied,
            accent: kReceptionBlue,
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: ReceptionStatCard(
            label: 'MAINTENANCE',
            value: board.maintenance,
            accent: kReceptionRed,
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: ReceptionStatCard(
            label: 'PREPARING',
            value: board.preparing,
            accent: kReceptionBlue,
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: ReceptionStatCard(
            label: 'DIRTY',
            value: board.dirty,
            accent: AppColors.gold,
          ),
        ),
      ],
    );
  }

  Widget _filterChips(ReceptionRoomStatusBoard board) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          _chip('All', _Filter.all, board.rooms.length),
          const SizedBox(width: 8),
          _chip('Occupied', _Filter.occupied, board.occupied),
          const SizedBox(width: 8),
          _chip('Maintenance', _Filter.maintenance, board.maintenance),
          const SizedBox(width: 8),
          _chip('Preparing', _Filter.preparing, board.preparing),
          const SizedBox(width: 8),
          _chip('Dirty', _Filter.dirty, board.dirty),
          const SizedBox(width: 8),
          _chip('Ready', _Filter.ready, null),
        ],
      ),
    );
  }

  Widget _chip(String label, _Filter value, int? badge) {
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
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                style: AppTypography.style(
                  color: selected
                      ? AppColors.gold
                      : Colors.white.withValues(alpha: 0.6),
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
              if (badge != null && badge > 0) ...[
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 2,
                  ),
                  decoration: const BoxDecoration(
                    color: AppColors.gold,
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '$badge',
                    style: AppTypography.style(
                      color: const Color.fromARGB(255, 3, 3, 3),
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _list(ReceptionRoomStatusBoard board) {
    final rows = _filtered(board.rooms);
    if (rows.isEmpty) {
      return const ReceptionSectionEmpty(
        icon: Icons.meeting_room_outlined,
        message: 'No rooms match this filter',
      );
    }
    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async =>
          ref.invalidate(receptionRoomStatusProvider(_token)),
      child: GridView.builder(
        padding: const EdgeInsets.only(bottom: 24),
        gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
          maxCrossAxisExtent: 260,
          mainAxisExtent: 128,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
        ),
        itemCount: rows.length,
        itemBuilder: (_, i) => ReceptionRoomStatusCard(room: rows[i]),
      ),
    );
  }
}
