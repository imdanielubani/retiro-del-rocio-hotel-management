import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/asset.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/application/maintenance_notification_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/presentation/screens/maintenance_notification_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/dialogs/add_asset_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/maintenance_navigation.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/screens/asset_detail_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/screens/pm_schedule_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_scaffold.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';

enum _Filter { all, due }

/// The Assets tab — every registered asset, searchable by name, filterable
/// to just what's due for preventive maintenance, tapping through to its
/// service history.
class AssetsScreen extends ConsumerStatefulWidget {
  const AssetsScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<AssetsScreen> createState() => _AssetsScreenState();
}

class _AssetsScreenState extends ConsumerState<AssetsScreen> {
  String _search = '';
  _Filter _filter = _Filter.all;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) MaintenanceNavigation.afterLogout(context);
  }

  void _onNav(MaintenanceNavItem item) {
    MaintenanceNavigation.select(
      context,
      widget.session,
      item,
      current: MaintenanceNavItem.assets,
    );
  }

  Future<void> _addAsset() async {
    await showAddAssetDialog(context, token: _token);
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

  void _openDetail(Asset asset) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            AssetDetailScreen(session: widget.session, assetId: asset.id),
      ),
    );
  }

  void _openPmSchedule() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PmScheduleScreen(session: widget.session),
      ),
    );
  }

  List<Asset> _visible(List<Asset> all) {
    var rows = all;
    if (_filter == _Filter.due) {
      rows = rows.where((a) => a.isDueForService).toList();
    }
    final q = _search.trim().toLowerCase();
    if (q.isNotEmpty) {
      rows = rows.where((a) => a.name.toLowerCase().contains(q)).toList();
    }
    return rows;
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(maintenanceNotificationsRealtimeProvider(_token));
    ref.watch(maintenanceNotificationChimeProvider(_token));
    ref.watch(maintenanceSlaBreachChimeProvider(_token));

    final assetsAsync = ref.watch(maintenanceAssetsProvider(_token));
    final assets = assetsAsync.value ?? const <Asset>[];
    final dueCount = assets.where((a) => a.isDueForService).length;
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
      title: 'Assets',
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _pmScheduleButton(),
          const SizedBox(width: 10),
          _addButton(),
        ],
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: _searchField()),
              const SizedBox(width: 12),
              _filterChips(dueCount),
            ],
          ),
          const SizedBox(height: 16),
          Expanded(
            child: assetsAsync.when(
              data: (data) => _grid(data),
              loading: () => assets.isNotEmpty
                  ? _grid(assets)
                  : const Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    ),
              error: (_, _) => assets.isNotEmpty
                  ? _grid(assets)
                  : Center(
                      child: TextButton(
                        onPressed: () =>
                            ref.invalidate(maintenanceAssetsProvider(_token)),
                        child: const Text(
                          'Could not load assets. Retry',
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

  Widget _pmScheduleButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: _openPmSchedule,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.event_available_outlined,
                size: 16,
                color: Colors.white70,
              ),
              const SizedBox(width: 6),
              Text(
                'PM Schedule',
                style: AppTypography.style(
                  color: Colors.white70,
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

  Widget _addButton() {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: _addAsset,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.add_rounded, size: 16, color: Colors.black),
              const SizedBox(width: 6),
              Text(
                'Add Asset',
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

  Widget _searchField() {
    return SizedBox(
      height: 44,
      child: TextField(
        onChanged: (v) => setState(() => _search = v),
        cursorColor: AppColors.gold,
        style: AppTypography.style(color: Colors.white, fontSize: 14),
        decoration: InputDecoration(
          isDense: true,
          filled: true,
          fillColor: Colors.white.withValues(alpha: 0.06),
          hintText: 'Search asset',
          hintStyle: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 14,
          ),
          prefixIcon: Icon(
            Icons.search_rounded,
            size: 18,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          contentPadding: const EdgeInsets.symmetric(vertical: 12),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(
              color: AppColors.gold.withValues(alpha: 0.5),
              width: 1,
            ),
          ),
        ),
      ),
    );
  }

  Widget _filterChips(int dueCount) {
    return Row(
      children: [
        _chip('All', _Filter.all),
        const SizedBox(width: 8),
        _chip('Service Due', _Filter.due, badge: dueCount),
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
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
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
                    color: kMtRed.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    '$badge',
                    style: AppTypography.style(
                      color: kMtRed,
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

  Widget _grid(List<Asset> all) {
    final rows = _visible(all);
    if (rows.isEmpty) {
      return const MaintenanceSectionEmpty(
        icon: Icons.inventory_2_outlined,
        message: 'No assets match this filter',
      );
    }
    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(maintenanceAssetsProvider(_token)),
      child: GridView.builder(
        padding: const EdgeInsets.only(bottom: 24),
        gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
          maxCrossAxisExtent: 320,
          mainAxisExtent: 150,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
        ),
        itemCount: rows.length,
        itemBuilder: (_, i) {
          final asset = rows[i];
          return AssetCard(asset: asset, onTap: () => _openDetail(asset));
        },
      ),
    );
  }
}
