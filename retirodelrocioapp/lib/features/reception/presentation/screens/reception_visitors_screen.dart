import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_visitor.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/screens/reception_notification_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

/// The Visitor Pass module — every visitor invited or arrived, so the desk
/// can see who is coming as well as who is already inside. Read-only: gate
/// verification (granting or denying entry) stays with security's tablet.
class ReceptionVisitorsScreen extends ConsumerStatefulWidget {
  const ReceptionVisitorsScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionVisitorsScreen> createState() =>
      _ReceptionVisitorsScreenState();
}

enum _VisitorFilter { all, expected, inside }

class _ReceptionVisitorsScreenState
    extends ConsumerState<ReceptionVisitorsScreen> {
  String _search = '';
  _VisitorFilter _filter = _VisitorFilter.all;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) ReceptionNavigation.afterLogout(context);
  }

  List<ReceptionVisitor> _filtered(List<ReceptionVisitor> all) {
    var rows = all;
    switch (_filter) {
      case _VisitorFilter.expected:
        rows = rows.where((v) => v.status == 'pending').toList();
      case _VisitorFilter.inside:
        rows = rows.where((v) => v.isInside).toList();
      case _VisitorFilter.all:
        break;
    }

    final q = _search.trim().toLowerCase();
    if (q.isEmpty) return rows;
    return rows
        .where(
          (v) =>
              v.visitorName.toLowerCase().contains(q) ||
              (v.hostName ?? '').toLowerCase().contains(q) ||
              (v.roomNumber ?? '').toLowerCase().contains(q) ||
              (v.suiteName ?? '').toLowerCase().contains(q),
        )
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final visitorsAsync = ref.watch(receptionVisitorsProvider(_token));
    final overview = visitorsAsync.value ?? ReceptionVisitorsOverview.empty;
    final unreadNotifications = ref.watch(
      receptionUnreadNotificationsProvider(_token),
    );

    return ReceptionScaffold(
      session: widget.session,
      active: ReceptionNavItem.visitorPass,
      onNav: (item) => ReceptionNavigation.select(
        context,
        widget.session,
        item,
        current: ReceptionNavItem.visitorPass,
      ),
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Visitor Pass',
      trailing: ReceptionSearchField(
        hint: 'Search visitor, host or room',
        onChanged: (v) => setState(() => _search = v),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _summaryCards(overview),
          const SizedBox(height: 16),
          _filterChips(),
          const SizedBox(height: 12),
          Expanded(
            child: visitorsAsync.when(
              loading: () => overview.visitors.isNotEmpty
                  ? _list(overview)
                  : const Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    ),
              error: (_, _) => overview.visitors.isNotEmpty
                  ? _list(overview)
                  : Center(
                      child: TextButton(
                        onPressed: () =>
                            ref.invalidate(receptionVisitorsProvider(_token)),
                        child: const Text(
                          'Could not load visitors. Retry',
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

  Widget _summaryCards(ReceptionVisitorsOverview overview) {
    return Row(
      children: [
        Expanded(
          child: _summaryCard(
            'EXPECTED',
            '${overview.expected}',
            AppColors.gold,
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: _summaryCard(
            'CURRENTLY INSIDE',
            '${overview.inside}',
            kReceptionBlue,
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: _summaryCard('TODAY', '${overview.today}', kReceptionGreen),
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
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.08),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: Colors.white54,
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            value,
            style: TextStyle(
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

  Widget _filterChips() {
    return Row(
      children: [
        _chip('All', _VisitorFilter.all),
        const SizedBox(width: 8),
        _chip('Expected', _VisitorFilter.expected),
        const SizedBox(width: 8),
        _chip('Inside', _VisitorFilter.inside),
      ],
    );
  }

  Widget _chip(String label, _VisitorFilter value) {
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
            style: TextStyle(
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

  Widget _list(ReceptionVisitorsOverview overview) {
    final rows = _filtered(overview.visitors);
    if (rows.isEmpty) {
      return const ReceptionSectionEmpty(
        icon: Icons.badge_rounded,
        message: 'No visitors match this filter',
      );
    }
    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(receptionVisitorsProvider(_token)),
      child: ListView.separated(
        padding: const EdgeInsets.only(bottom: 24),
        itemCount: rows.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) => ReceptionVisitorRowCard(visitor: rows[i]),
      ),
    );
  }

  void _openNotifications() {
    ReceptionNavigation.push(
      context,
      'notifications',
      ReceptionNotificationScreen(
        session: widget.session,
        current: ReceptionNavItem.visitorPass,
      ),
    );
  }
}
