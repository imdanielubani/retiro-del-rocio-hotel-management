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
import 'package:retirodelrocioapp/features/maintenance/presentation/maintenance_navigation.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/screens/asset_detail_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_scaffold.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';

/// The preventive-maintenance schedule: every asset on a service interval,
/// grouped by how soon it's due — overdue first, then due within a week,
/// then everything still scheduled further out. Assets with no interval set
/// aren't tracked here (see the Assets tab for those).
class PmScheduleScreen extends ConsumerStatefulWidget {
  const PmScheduleScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<PmScheduleScreen> createState() => _PmScheduleScreenState();
}

class _PmScheduleScreenState extends ConsumerState<PmScheduleScreen> {
  int? _markingId;

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

  Future<void> _markServiced(Asset asset) async {
    setState(() => _markingId = asset.id);
    try {
      await ref
          .read(maintenanceActionsProvider(_token))
          .markAssetServiced(asset.id);
    } on MaintenanceException catch (e) {
      _showFailure(e.message);
    } catch (_) {
      _showFailure('Could not update this asset.');
    } finally {
      if (mounted) setState(() => _markingId = null);
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

  void _openDetail(Asset asset) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            AssetDetailScreen(session: widget.session, assetId: asset.id),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final assetsAsync = ref.watch(maintenanceAssetsProvider(_token));
    final assets = (assetsAsync.value ?? const <Asset>[])
        .where((a) => a.isOnScheduledMaintenance)
        .toList();

    return MaintenanceScaffold(
      session: widget.session,
      active: MaintenanceNavItem.assets,
      onNav: _onNav,
      onLogout: _logout,
      onBack: () => Navigator.of(context).maybePop(),
      title: 'PM Schedule',
      body: assetsAsync.when(
        data: (_) => _content(assets),
        loading: () => const Center(
          child: CircularProgressIndicator(color: AppColors.gold),
        ),
        error: (_, _) => assets.isNotEmpty
            ? _content(assets)
            : Center(
                child: TextButton(
                  onPressed: () =>
                      ref.invalidate(maintenanceAssetsProvider(_token)),
                  child: const Text(
                    'Could not load the schedule. Retry',
                    style: TextStyle(color: AppColors.gold),
                  ),
                ),
              ),
      ),
    );
  }

  Widget _content(List<Asset> assets) {
    if (assets.isEmpty) {
      return const MaintenanceSectionEmpty(
        icon: Icons.event_available_outlined,
        message: 'No assets are on a preventive-maintenance schedule yet',
      );
    }

    final overdue = <Asset>[];
    final dueSoon = <Asset>[];
    final scheduled = <Asset>[];

    for (final asset in assets) {
      if (asset.isDueForService) {
        overdue.add(asset);
      } else if (asset.nextServiceDueAt != null &&
          asset.nextServiceDueAt!.difference(DateTime.now()).inDays <= 7) {
        dueSoon.add(asset);
      } else {
        scheduled.add(asset);
      }
    }
    for (final group in [overdue, dueSoon, scheduled]) {
      group.sort(
        (a, b) => (a.nextServiceDueAt ?? DateTime(9999)).compareTo(
          b.nextServiceDueAt ?? DateTime(9999),
        ),
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(maintenanceAssetsProvider(_token)),
      child: ListView(
        padding: const EdgeInsets.only(bottom: 24),
        children: [
          if (overdue.isNotEmpty) _group('OVERDUE', overdue, kMtRed),
          if (dueSoon.isNotEmpty)
            _group('DUE WITHIN 7 DAYS', dueSoon, AppColors.gold),
          if (scheduled.isNotEmpty) _group('SCHEDULED', scheduled, kMtSlate),
        ],
      ),
    );
  }

  Widget _group(String label, List<Asset> assets, Color accent) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(
                  color: accent,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 8),
              Text(
                label,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.5),
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.2,
                ),
              ),
              const SizedBox(width: 6),
              Text(
                '${assets.length}',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.3),
                  fontSize: 12,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ...assets.map(
            (asset) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _row(asset, accent),
            ),
          ),
        ],
      ),
    );
  }

  Widget _row(Asset asset, Color accent) {
    final busy = _markingId == asset.id;
    final due = asset.nextServiceDueAt;
    final dueLabel = due == null
        ? 'No due date'
        : '${due.day}/${due.month}/${due.year}';

    return Material(
      color: Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: () => _openDetail(asset),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: accent.withValues(alpha: 0.25),
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
                      asset.name,
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
                      '${asset.locationLabel}  ·  Due $dueLabel',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.45),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Material(
                color: AppColors.gold.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(10),
                child: InkWell(
                  onTap: busy ? null : () => _markServiced(asset),
                  borderRadius: BorderRadius.circular(10),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 8,
                    ),
                    child: busy
                        ? const SizedBox(
                            width: 14,
                            height: 14,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppColors.gold,
                            ),
                          )
                        : Text(
                            'Mark Serviced',
                            style: AppTypography.style(
                              color: AppColors.gold,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
