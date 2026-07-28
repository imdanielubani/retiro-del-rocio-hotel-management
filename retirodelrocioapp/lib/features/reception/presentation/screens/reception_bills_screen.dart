import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_bill.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/screens/reception_notification_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/screens/reception_bill_detail_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

/// The Bills module — every checked-in guest's outstanding room-charge
/// balance (rate, pickup, extensions, and any spa session charged to the
/// room), so the desk can see who owes what without opening each folio.
class ReceptionBillsScreen extends ConsumerStatefulWidget {
  const ReceptionBillsScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionBillsScreen> createState() => _ReceptionBillsScreenState();
}

class _ReceptionBillsScreenState extends ConsumerState<ReceptionBillsScreen> {
  String _search = '';

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) ReceptionNavigation.afterLogout(context);
  }

  List<ReceptionBillRow> _filter(List<ReceptionBillRow> all) {
    final q = _search.trim().toLowerCase();
    if (q.isEmpty) return all;
    return all
        .where((b) =>
            b.guestName.toLowerCase().contains(q) || b.roomLabel.toLowerCase().contains(q))
        .toList();
  }

  void _openBill(ReceptionBillRow row) {
    ReceptionNavigation.push(
      context,
      'bills/${row.bookingId}',
      ReceptionBillDetailScreen(session: widget.session, bookingId: row.bookingId),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(receptionBookingsRealtimeProvider(_token));

    final billsAsync = ref.watch(receptionBillsProvider(_token));
    final overview = billsAsync.value ?? ReceptionBillsOverview.empty;
    final unreadNotifications = ref.watch(receptionUnreadNotificationsProvider(_token));

    return ReceptionScaffold(
      session: widget.session,
      active: ReceptionNavItem.bills,
      onNav: (item) => ReceptionNavigation.select(
        context,
        widget.session,
        item,
        current: ReceptionNavItem.bills,
      ),
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Bills',
      trailing: ReceptionSearchField(
        hint: 'Search guest or room',
        onChanged: (v) => setState(() => _search = v),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _summaryCards(overview),
          const SizedBox(height: 16),
          Expanded(
            child: billsAsync.when(
              loading: () => overview.rows.isNotEmpty
                  ? _list(overview)
                  : const Center(child: CircularProgressIndicator(color: AppColors.gold)),
              error: (_, _) => overview.rows.isNotEmpty
                  ? _list(overview)
                  : Center(
                      child: TextButton(
                        onPressed: () => ref.invalidate(receptionBillsProvider(_token)),
                        child: const Text(
                          'Could not load bills. Retry',
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

  Widget _summaryCards(ReceptionBillsOverview overview) {
    return Row(
      children: [
        Expanded(child: _summaryCard('TOTAL OUTSTANDING', overview.totalOutstandingLabel, AppColors.gold)),
        const SizedBox(width: 16),
        Expanded(
          child: _summaryCard(
            'GUESTS WITH A BALANCE',
            '${overview.guestsWithBalance}',
            kReceptionBlue,
          ),
        ),
      ],
    );
  }

  Widget _summaryCard(String label, String value, Color accent) {
    return Container(
      padding: const EdgeInsets.all(20.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.35),
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            value,
            style: AppTypography.style(
              color: accent,
              fontSize: 28,
              fontWeight: FontWeight.w800,
              height: 1,
            ),
          ),
        ],
      ),
    );
  }

  Widget _list(ReceptionBillsOverview overview) {
    final rows = _filter(overview.rows);
    if (rows.isEmpty) {
      return const ReceptionSectionEmpty(
        icon: Icons.receipt_long_rounded,
        message: 'No checked-in guests match this search',
      );
    }
    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(receptionBillsProvider(_token)),
      child: ListView.separated(
        padding: const EdgeInsets.only(bottom: 24),
        itemCount: rows.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) => ReceptionBillRowCard(row: rows[i], onTap: () => _openBill(rows[i])),
      ),
    );
  }

  void _openNotifications() {
    ReceptionNavigation.push(
      context,
      'notifications',
      ReceptionNotificationScreen(session: widget.session, current: ReceptionNavItem.bills),
    );
  }
}
