import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/asset.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/application/maintenance_notification_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/presentation/screens/maintenance_notification_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/maintenance_navigation.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_scaffold.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';

/// One asset's profile and full service history — every work order ever
/// raised against it, plus a "Mark Serviced" action that restarts its
/// preventive-maintenance interval.
class AssetDetailScreen extends ConsumerStatefulWidget {
  const AssetDetailScreen({
    super.key,
    required this.session,
    required this.assetId,
  });

  final StaffSession session;
  final int assetId;

  @override
  ConsumerState<AssetDetailScreen> createState() => _AssetDetailScreenState();
}

class _AssetDetailScreenState extends ConsumerState<AssetDetailScreen> {
  bool _marking = false;

  String get _token => widget.session.token;

  void _onNav(MaintenanceNavItem item) {
    MaintenanceNavigation.select(
      context,
      widget.session,
      item,
      current: MaintenanceNavItem.assets,
    );
  }

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) MaintenanceNavigation.afterLogout(context);
  }

  Future<void> _markServiced() async {
    setState(() => _marking = true);
    try {
      await ref
          .read(maintenanceActionsProvider(_token))
          .markAssetServiced(widget.assetId);
    } on MaintenanceException catch (e) {
      _showFailure(e.message);
    } catch (_) {
      _showFailure('Could not update this asset.');
    } finally {
      if (mounted) setState(() => _marking = false);
    }
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

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MaintenanceNotificationScreen(
          session: widget.session,
          current: MaintenanceNavItem.assets,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(maintenanceNotificationsRealtimeProvider(_token));
    ref.watch(maintenanceNotificationChimeProvider(_token));
    ref.watch(maintenanceSlaBreachChimeProvider(_token));

    final assetAsync = ref.watch(
      maintenanceAssetDetailProvider((_token, widget.assetId)),
    );
    final unreadNotifications = ref.watch(
      maintenanceUnreadNotificationsProvider(_token),
    );

    return MaintenanceScaffold(
      session: widget.session,
      active: MaintenanceNavItem.assets,
      onNav: _onNav,
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: assetAsync.value?.name ?? 'Asset',
      onBack: () => Navigator.of(context).maybePop(),
      trailing:
          assetAsync.value != null && assetAsync.value!.isOnScheduledMaintenance
          ? _markServicedButton()
          : null,
      body: assetAsync.when(
        data: (data) => data == null ? _notFound() : _content(data),
        loading: () => const Center(
          child: CircularProgressIndicator(color: AppColors.gold),
        ),
        error: (_, _) => _notFound(),
      ),
    );
  }

  Widget _markServicedButton() {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: _marking ? null : _markServiced,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
          alignment: Alignment.center,
          child: _marking
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.black,
                  ),
                )
              : Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.check_circle_outline_rounded,
                      size: 16,
                      color: Colors.black,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'Mark Serviced',
                      style: AppTypography.style(
                        color: Colors.black,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }

  Widget _notFound() {
    return Center(
      child: Text(
        'Could not load this asset.',
        style: AppTypography.style(
          color: Colors.white.withValues(alpha: 0.6),
          fontSize: 15,
        ),
      ),
    );
  }

  Widget _content(Asset asset) {
    return SingleChildScrollView(
      padding: const EdgeInsets.only(bottom: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _profileCard(asset),
          const SizedBox(height: 24),
          Text(
            'SERVICE HISTORY',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.35),
              fontSize: 12,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.4,
            ),
          ),
          const SizedBox(height: 12),
          if (asset.serviceHistory.isEmpty)
            const MaintenanceSectionEmpty(
              icon: Icons.history_rounded,
              message: 'No work orders raised against this asset yet',
            )
          else
            ...asset.serviceHistory.map(
              (order) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _historyRow(order),
              ),
            ),
        ],
      ),
    );
  }

  Widget _profileCard(Asset asset) {
    return Container(
      padding: const EdgeInsets.all(18),
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
          Row(
            children: [
              if ((asset.category ?? '').isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: _tag(asset.category!, kMtBlue),
                ),
              if (asset.isDueForService) _tag('Service Due', kMtRed),
            ],
          ),
          if ((asset.category ?? '').isNotEmpty || asset.isDueForService)
            const SizedBox(height: 10),
          _infoRow('Location', asset.locationLabel),
          if (asset.isOnScheduledMaintenance) ...[
            _infoRow(
              'Service interval',
              'Every ${asset.serviceIntervalDays} days',
            ),
            _infoRow('Last serviced', asset.lastServicedLabel ?? 'Never'),
          ],
          if ((asset.notes ?? '').isNotEmpty) _infoRow('Notes', asset.notes!),
        ],
      ),
    );
  }

  Widget _tag(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: AppTypography.style(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 130,
            child: Text(
              label,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 13,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _historyRow(WorkOrder order) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.08),
          width: 0.8,
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  order.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  [
                    order.createdLabel ?? '',
                    if ((order.assignedToName ?? '').isNotEmpty)
                      order.assignedToName!,
                  ].where((s) => s.isNotEmpty).join('  ·  '),
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.45),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          _tag(order.statusLabel, maintenanceStatusColor(order.status)),
        ],
      ),
    );
  }
}
