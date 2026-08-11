import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/housekeeping/application/housekeeping_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/application/lost_found_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/domain/lost_found_item.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/presentation/dialogs/claim_lost_found_item_dialog.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/presentation/dialogs/report_found_item_dialog.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/presentation/widgets/lost_found_item_card.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/application/housekeeping_notification_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/presentation/screens/housekeeping_notification_screen.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/housekeeping_navigation.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_nav_rail.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_scaffold.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_widgets.dart';

enum _Filter { all, unclaimed, returned, disposed }

/// Items a housekeeper found while turning over a room — logged, then handed
/// back to their owner or disposed of once policy allows.
class HousekeepingLostFoundScreen extends ConsumerStatefulWidget {
  const HousekeepingLostFoundScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<HousekeepingLostFoundScreen> createState() =>
      _HousekeepingLostFoundScreenState();
}

class _HousekeepingLostFoundScreenState
    extends ConsumerState<HousekeepingLostFoundScreen> {
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
    HousekeepingNavigation.select(
      context,
      widget.session,
      item,
      current: HousekeepingNavItem.lostFound,
    );
  }

  void _openNotifications() {
    HousekeepingNavigation.push(
      context,
      'notifications',
      HousekeepingNotificationScreen(
        session: widget.session,
        current: HousekeepingNavItem.lostFound,
      ),
    );
  }

  List<LostFoundItem> _visible(List<LostFoundItem> all) {
    return switch (_filter) {
      _Filter.all => all,
      _Filter.unclaimed => all.where((i) => i.status == 'unclaimed').toList(),
      _Filter.returned => all.where((i) => i.status == 'returned').toList(),
      _Filter.disposed => all.where((i) => i.status == 'disposed').toList(),
    };
  }

  Future<void> _report() async {
    await showReportFoundItemDialog(context, token: _token);
  }

  Future<void> _markReturned(LostFoundItem item) async {
    final claim = await showClaimLostFoundItemDialog(
      context,
      itemDescription: item.itemDescription,
    );
    if (claim == null) return;

    setState(() => _busyId = item.id);
    try {
      await ref
          .read(lostFoundActionsProvider(_token))
          .markReturned(
            item.id,
            claimantName: claim.name,
            claimantContact: claim.contact,
          );
    } catch (_) {
      _showError('Could not update this item.');
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _markDisposed(LostFoundItem item) async {
    setState(() => _busyId = item.id);
    try {
      await ref.read(lostFoundActionsProvider(_token)).markDisposed(item.id);
    } catch (_) {
      _showError('Could not update this item.');
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  void _showError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: const Color(0xFF7F1D1D),
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

    final itemsAsync = ref.watch(lostFoundItemsProvider(_token));
    final items = itemsAsync.value ?? const <LostFoundItem>[];
    final unclaimedCount = items.where((i) => i.status == 'unclaimed').length;
    final unreadNotifications = ref.watch(
      housekeepingUnreadNotificationsProvider(_token),
    );

    return HousekeepingScaffold(
      session: widget.session,
      active: HousekeepingNavItem.lostFound,
      onNav: _onNav,
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Lost & Found',
      trailing: _reportButton(),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _filterChips(unclaimedCount),
          const SizedBox(height: 16),
          Expanded(
            child: itemsAsync.when(
              data: (data) => _list(data),
              loading: () => items.isNotEmpty
                  ? _list(items)
                  : const Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    ),
              error: (_, _) => items.isNotEmpty
                  ? _list(items)
                  : Center(
                      child: TextButton(
                        onPressed: () =>
                            ref.invalidate(lostFoundItemsProvider(_token)),
                        child: const Text(
                          'Could not load Lost & Found. Retry',
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

  Widget _reportButton() {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: _report,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.add_rounded, size: 18, color: Colors.black),
              const SizedBox(width: 6),
              Text(
                'Log Item',
                style: AppTypography.style(
                  color: Colors.black,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _filterChips(int unclaimedCount) {
    return Row(
      children: [
        _chip('All', _Filter.all),
        const SizedBox(width: 8),
        _chip('Unclaimed', _Filter.unclaimed, badge: unclaimedCount),
        const SizedBox(width: 8),
        _chip('Returned', _Filter.returned),
        const SizedBox(width: 8),
        _chip('Disposed', _Filter.disposed),
      ],
    );
  }

  Widget _chip(String label, _Filter value, {int? badge}) {
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
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
              if (badge != null && badge > 0) ...[
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 7,
                    vertical: 1,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.gold.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    '$badge',
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
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

  Widget _list(List<LostFoundItem> all) {
    final visible = _visible(all);

    if (visible.isEmpty) {
      return const HousekeepingSectionEmpty(
        icon: Icons.search_off_rounded,
        message: 'Nothing logged here yet.',
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(lostFoundItemsProvider(_token)),
      child: ListView.separated(
        padding: const EdgeInsets.only(bottom: 24),
        itemCount: visible.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) {
          final item = visible[i];
          return LostFoundItemCard(
            item: item,
            busy: _busyId == item.id,
            onMarkReturned: () => _markReturned(item),
            onMarkDisposed: () => _markDisposed(item),
          );
        },
      ),
    );
  }
}
