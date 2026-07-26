import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/data/reception_repository.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/incident_detail_panel.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/incident_log_row.dart';

/// Reception's Incident Response — the same SOS Alert Logs the security tablet
/// works, on the front-desk shell. SOS alerts are hotel-wide, so the desk sees
/// every emergency ever raised, filterable by status, and can acknowledge and
/// resolve each in place. Follows the `sos` realtime channel and re-polls, so an
/// incoming emergency lands here without a manual refresh.
class ReceptionIncidentResponseScreen extends ConsumerStatefulWidget {
  const ReceptionIncidentResponseScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionIncidentResponseScreen> createState() =>
      _ReceptionIncidentResponseScreenState();
}

class _ReceptionIncidentResponseScreenState
    extends ConsumerState<ReceptionIncidentResponseScreen> {
  int? _selectedId;
  int? _busyId;

  /// Null = all statuses; otherwise the [IncidentStatus] being shown.
  IncidentStatus? _filter;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) ReceptionNavigation.afterLogout(context);
  }

  Future<void> _acknowledge(int id) => _run(
    id,
    () => ref.read(receptionActionsProvider(_token)).respondIncident(id),
  );

  Future<void> _resolve(int id) => _run(
    id,
    () => ref.read(receptionActionsProvider(_token)).resolveIncident(id),
  );

  Future<void> _run(int id, Future<void> Function() action) async {
    setState(() => _busyId = id);
    try {
      await action();
    } on ReceptionException catch (e) {
      _showFailure(e.message);
    } catch (_) {
      _showFailure('Something went wrong. Please try again.');
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  void _callRoom() {
    // Room telephony is not wired yet — surface it plainly rather than fail.
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: const Color(0xFF1F2937),
        content: Text(
          'Room calling is coming soon.',
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
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

  Future<void> _pickFilter() async {
    final overlay =
        Overlay.of(context).context.findRenderObject() as RenderBox?;
    if (overlay == null) return;

    const items = <(IncidentStatus?, String)>[
      (null, 'All alerts'),
      (IncidentStatus.active, 'Unacknowledged'),
      (IncidentStatus.acknowledged, 'Acknowledged'),
      (IncidentStatus.resolved, 'Resolved'),
    ];

    final selected = await showMenu<IncidentStatus?>(
      context: context,
      color: const Color(0xFF141414),
      position: RelativeRect.fromLTRB(overlay.size.width - 320, 150, 40, 0),
      items: [
        for (final (value, label) in items)
          PopupMenuItem<IncidentStatus?>(
            value: value,
            child: Row(
              children: [
                Icon(
                  _filter == value
                      ? Icons.check_rounded
                      : Icons.circle_outlined,
                  size: 16,
                  color: _filter == value
                      ? AppColors.gold
                      : Colors.white.withValues(alpha: 0.3),
                ),
                const SizedBox(width: 10),
                Text(
                  label,
                  style: AppTypography.style(color: Colors.white, fontSize: 14),
                ),
              ],
            ),
          ),
      ],
    );

    if (mounted) setState(() => _filter = selected);
  }

  String get _filterLabel => switch (_filter) {
    null => 'Filter',
    IncidentStatus.active => 'Unacknowledged',
    IncidentStatus.acknowledged => 'Acknowledged',
    IncidentStatus.resolved => 'Resolved',
    IncidentStatus.cancelled => 'Cancelled',
  };

  @override
  Widget build(BuildContext context) {
    // Keep the sos socket alive while this screen is up so incidents refresh live.
    ref.watch(receptionRealtimeProvider(_token));
    final logsAsync = ref.watch(receptionIncidentLogsProvider(_token));

    final all = logsAsync.value ?? const <SecurityIncident>[];
    final visible = _filter == null
        ? all
        : all.where((i) => i.status == _filter).toList();

    // Keep the detail panel honest: if the selected incident falls out of the
    // list (e.g. filtered away), close it.
    final selected = _selectedId == null
        ? null
        : all.where((i) => i.id == _selectedId).firstOrNull;

    return ReceptionScaffold(
      session: widget.session,
      active: ReceptionNavItem.incidentResponse,
      onNav: (item) => ReceptionNavigation.select(
        context,
        widget.session,
        item,
        current: ReceptionNavItem.incidentResponse,
      ),
      onLogout: _logout,
      title: 'SOS Alert Logs',
      trailing: IncidentFilterButton(label: _filterLabel, onTap: _pickFilter),
      body: logsAsync.when(
        data: (_) => _body(visible, selected),
        loading: () => all.isEmpty
            ? const Center(
                child: CircularProgressIndicator(color: AppColors.gold),
              )
            : _body(visible, selected),
        error: (_, _) => all.isEmpty ? _errorState() : _body(visible, selected),
      ),
    );
  }

  Widget _body(List<SecurityIncident> visible, SecurityIncident? selected) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(child: _list(visible)),
        if (selected != null) ...[
          const SizedBox(width: 24),
          SizedBox(
            width: 340,
            child: IncidentDetailPanel(
              incident: selected,
              busy: _busyId == selected.id,
              onClose: () => setState(() => _selectedId = null),
              onAcknowledge: () => _acknowledge(selected.id),
              onResolve: () => _resolve(selected.id),
              onCallRoom: _callRoom,
            ),
          ),
        ],
      ],
    );
  }

  Widget _list(List<SecurityIncident> visible) {
    if (visible.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.shield_outlined,
              size: 30,
              color: Colors.white.withValues(alpha: 0.3),
            ),
            const SizedBox(height: 12),
            Text(
              _filter == null
                  ? 'No SOS alerts have been raised.'
                  : 'No ${_filterLabel.toLowerCase()} alerts.',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.5),
                fontSize: 15,
              ),
            ),
          ],
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.only(bottom: 24),
      itemCount: visible.length,
      separatorBuilder: (_, _) => const SizedBox(height: 20),
      itemBuilder: (context, i) {
        final incident = visible[i];
        return IncidentLogRow(
          incident: incident,
          busy: _busyId == incident.id,
          selected: _selectedId == incident.id,
          onAcknowledge: () => _acknowledge(incident.id),
          onResolve: () => _resolve(incident.id),
          onView: () => setState(() => _selectedId = incident.id),
        );
      },
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
            'Could not load the alert logs.',
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
              onTap: () =>
                  ref.invalidate(receptionIncidentLogsProvider(_token)),
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
