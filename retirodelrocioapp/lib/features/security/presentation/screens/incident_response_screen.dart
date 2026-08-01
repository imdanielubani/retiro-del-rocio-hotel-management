import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/security/application/security_providers.dart';
import 'package:retirodelrocioapp/features/security/data/security_repository.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';
import 'package:retirodelrocioapp/features/security/notifications/application/security_notification_providers.dart';
import 'package:retirodelrocioapp/features/security/notifications/presentation/screens/security_notification_screen.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/incident_detail_panel.dart';
import 'package:retirodelrocioapp/features/security/presentation/screens/visitor_verification_screen.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/incident_log_row.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_nav_rail.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_top_bar.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// Incident Response — the SOS Alert Logs (Figma 222:8280) with the slide-in
/// detail panel (225:10446). Every emergency ever raised, filterable by status,
/// each acknowledgeable and resolvable in place; the whole screen follows the
/// `sos` realtime channel and re-polls every 15 seconds.
class IncidentResponseScreen extends ConsumerStatefulWidget {
  const IncidentResponseScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<IncidentResponseScreen> createState() =>
      _IncidentResponseScreenState();
}

class _IncidentResponseScreenState
    extends ConsumerState<IncidentResponseScreen> {
  int? _selectedId;
  int? _busyId;

  /// Null = all statuses; otherwise the [IncidentStatus] name being shown.
  IncidentStatus? _filter;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) Navigator.of(context).popUntil((r) => r.isFirst);
  }

  void _onNav(SecurityNavItem item) {
    switch (item) {
      case SecurityNavItem.incidentResponse:
        break; // already here
      case SecurityNavItem.dashboard:
        Navigator.of(context).pop(); // back to the dashboard beneath
      case SecurityNavItem.verifiedPass:
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => VisitorVerificationScreen(session: widget.session),
          ),
        );
      case SecurityNavItem.chat:
        _comingSoon('Chat');
    }
  }

  void _comingSoon(String title) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ComingSoonScreen(title: title)),
    );
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SecurityNotificationScreen(
          session: widget.session,
          current: SecurityNavItem.incidentResponse,
        ),
      ),
    );
  }

  Future<void> _acknowledge(int id) =>
      _run(id, () => ref.read(securityActionsProvider(_token)).respond(id));

  Future<void> _resolve(int id) =>
      _run(id, () => ref.read(securityActionsProvider(_token)).resolve(id));

  Future<void> _run(int id, Future<void> Function() action) async {
    setState(() => _busyId = id);
    try {
      await action();
    } on SecurityException catch (e) {
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
        content: Text('Room calling is coming soon.',
            style: AppTypography.style(color: Colors.white, fontSize: 14)),
      ),
    );
  }

  void _showFailure(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF7F1D1D),
        behavior: SnackBarBehavior.floating,
        content: Text(message, style: AppTypography.style(color: Colors.white, fontSize: 14)),
      ),
    );
  }

  Future<void> _pickFilter() async {
    final box = context.findRenderObject() as RenderBox?;
    final overlay = Overlay.of(context).context.findRenderObject() as RenderBox?;
    if (box == null || overlay == null) return;

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
                  _filter == value ? Icons.check_rounded : Icons.circle_outlined,
                  size: 16,
                  color: _filter == value ? AppColors.gold : Colors.white.withValues(alpha: 0.3),
                ),
                const SizedBox(width: 10),
                Text(label, style: AppTypography.style(color: Colors.white, fontSize: 14)),
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
    ref.watch(securityRealtimeProvider(_token));
    ref.watch(securityNotificationsRealtimeProvider(_token));
    ref.watch(securityNotificationChimeProvider(_token));
    final logsAsync = ref.watch(incidentLogsProvider(_token));
    final weather = ref.watch(weatherProvider).value;
    final unreadNotifications =
        ref.watch(securityUnreadNotificationsProvider(_token));

    final all = logsAsync.value ?? const <SecurityIncident>[];
    final visible =
        _filter == null ? all : all.where((i) => i.status == _filter).toList();

    // Keep the detail panel honest: if the selected incident falls out of the
    // list (e.g. filtered away), close it.
    final selected = _selectedId == null
        ? null
        : all.where((i) => i.id == _selectedId).firstOrNull;

    return SessionGuard(
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
            const ColoredBox(color: Color(0xF2000000)),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    SecurityNavRail(
                      active: SecurityNavItem.incidentResponse,
                      onSelect: _onNav,
                      onLogout: _logout,
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          SecurityTopBar(
                            officerName: widget.session.name,
                            officerRole: 'Security Office',
                            weather: weather,
                            hasAlert: all.any((i) => i.isActive),
                            hasUnreadNotifications: unreadNotifications > 0,
                            onNotifications: _openNotifications,
                          ),
                          const SizedBox(height: 20),
                          _header(),
                          const SizedBox(height: 20),
                          Expanded(
                            child: logsAsync.when(
                              data: (_) => _body(visible, selected),
                              loading: () => all.isEmpty
                                  ? const Center(
                                      child: CircularProgressIndicator(color: AppColors.gold))
                                  : _body(visible, selected),
                              error: (_, _) => all.isEmpty
                                  ? _errorState()
                                  : _body(visible, selected),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Security', style: AppTypography.style(color: AppColors.gold, fontSize: 12)),
              const SizedBox(height: 3),
              Text(
                'SOS Alert Logs',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.1,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                'Emergency alerts triggered from guest rooms',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.35),
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
        IncidentFilterButton(label: _filterLabel, onTap: _pickFilter),
      ],
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
            Icon(Icons.shield_outlined, size: 30, color: Colors.white.withValues(alpha: 0.3)),
            const SizedBox(height: 12),
            Text(
              _filter == null
                  ? 'No SOS alerts have been raised.'
                  : 'No ${_filterLabel.toLowerCase()} alerts.',
              style: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 15),
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
          Icon(Icons.wifi_off_rounded, size: 30, color: Colors.white.withValues(alpha: 0.4)),
          const SizedBox(height: 12),
          Text(
            'Could not load the alert logs.',
            style: AppTypography.style(color: Colors.white.withValues(alpha: 0.6), fontSize: 15),
          ),
          const SizedBox(height: 16),
          Material(
            color: AppColors.gold,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              onTap: () => ref.invalidate(incidentLogsProvider(_token)),
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
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
