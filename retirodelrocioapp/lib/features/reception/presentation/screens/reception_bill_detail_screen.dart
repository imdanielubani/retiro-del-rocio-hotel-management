import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/guest/bills/presentation/widgets/bill_category_card.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_bill.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';

/// One checked-in guest's itemised folio — the same real numbers their own
/// tablet's My Bills screen shows them, for the desk to review or settle in
/// person. Read-only: no "Pay Now" here — payment stays either on the guest's
/// own tablet or handled at the desk directly.
class ReceptionBillDetailScreen extends ConsumerStatefulWidget {
  const ReceptionBillDetailScreen({
    super.key,
    required this.session,
    required this.bookingId,
  });

  final StaffSession session;
  final int bookingId;

  @override
  ConsumerState<ReceptionBillDetailScreen> createState() =>
      _ReceptionBillDetailScreenState();
}

class _ReceptionBillDetailScreenState
    extends ConsumerState<ReceptionBillDetailScreen> {
  late Future<ReceptionBillDetail> _future;

  String get _token => widget.session.token;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<ReceptionBillDetail> _load() => ref
      .read(receptionRepositoryProvider)
      .bookingBill(_token, widget.bookingId);

  void _retry() => setState(() => _future = _load());

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) ReceptionNavigation.afterLogout(context);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<ReceptionBillDetail>(
      future: _future,
      builder: (context, snapshot) {
        final bill = snapshot.data;
        final subtitle = [
          if (bill?.roomName != null) bill!.roomName,
          if (bill?.unitLabel != null) bill!.unitLabel,
        ].whereType<String>().join(' - ');

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
          onBack: () => Navigator.of(context).maybePop(),
          title: bill?.guestName ?? 'Bill',
          subtitle: subtitle.isEmpty ? 'Bills' : subtitle,
          body: _body(snapshot),
        );
      },
    );
  }

  Widget _body(AsyncSnapshot<ReceptionBillDetail> snapshot) {
    if (snapshot.connectionState != ConnectionState.done) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.gold),
      );
    }
    if (snapshot.hasError || snapshot.data == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.cloud_off_rounded,
              size: 30,
              color: Colors.white.withValues(alpha: 0.4),
            ),
            const SizedBox(height: 12),
            Text(
              'Could not load this bill.',
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
                onTap: _retry,
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

    final bill = snapshot.data!;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: SingleChildScrollView(
            child: Column(
              children: [
                for (var i = 0; i < bill.categories.length; i++) ...[
                  if (i > 0) const SizedBox(height: 16),
                  BillCategoryCard(
                    category: bill.categories[i],
                    initiallyExpanded: i == 0,
                  ),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(width: 24),
        SizedBox(width: 320, child: _summaryCard(bill)),
      ],
    );
  }

  Widget _summaryCard(ReceptionBillDetail bill) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.2),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Bill Summary',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          for (final line in bill.summaryLines) ...[
            _summaryRow(line.label, line.amountLabel),
            const SizedBox(height: 10),
          ],
          const SizedBox(height: 4),
          Container(height: 1, color: Colors.white.withValues(alpha: 0.1)),
          const SizedBox(height: 16),
          Row(
            children: [
              Text(
                'Total Due',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerRight,
                  child: Text(
                    bill.totalDueLabel,
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 22,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ],
          ),
          if (bill.checkInLabel != null || bill.checkOutLabel != null) ...[
            const SizedBox(height: 20),
            Container(height: 1, color: Colors.white.withValues(alpha: 0.1)),
            const SizedBox(height: 16),
            if (bill.checkInLabel != null)
              _summaryRow('Check-in', bill.checkInLabel!),
            if (bill.checkOutLabel != null) ...[
              const SizedBox(height: 10),
              _summaryRow('Check-out', bill.checkOutLabel!),
            ],
          ],
        ],
      ),
    );
  }

  Widget _summaryRow(String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 13,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Text(
          value,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.8),
            fontSize: 13,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}
