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
import 'package:retirodelrocioapp/features/security/domain/security_overview.dart';
import 'package:retirodelrocioapp/features/security/presentation/dialogs/sos_alert_overlay.dart';
import 'package:retirodelrocioapp/features/security/presentation/screens/incident_response_screen.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/incident_card.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_nav_rail.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_stat_card.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_top_bar.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/visitor_widgets.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The security officer's home dashboard (Figma 204:3089 / 257:1133).
///
/// One frosted screen: the module rail, a hotel-wide header, the headline
/// counters, live SOS incidents (respond / resolve) and — once the Visitor Pass
/// feature lands — today's visitors and pending pass requests. It follows the
/// `sos` realtime channel and re-polls every 12 seconds, so an incoming
/// emergency lands here without anyone refreshing.
class SecurityDashboardScreen extends ConsumerStatefulWidget {
  const SecurityDashboardScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<SecurityDashboardScreen> createState() =>
      _SecurityDashboardScreenState();
}

class _SecurityDashboardScreenState
    extends ConsumerState<SecurityDashboardScreen> {
  /// The incident whose action button is mid-request, so only that one spins.
  int? _busyIncidentId;

  /// Alert ids already surfaced as the priority overlay, so a still-active alert
  /// is not thrown up again on every poll — only genuinely new ones interrupt.
  final Set<int> _announced = {};

  /// True while the priority overlay is on screen, so alerts never stack.
  bool _presenting = false;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) Navigator.of(context).pop();
  }

  void _comingSoon(String title) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ComingSoonScreen(title: title)),
    );
  }

  void _onNav(SecurityNavItem item) {
    switch (item) {
      case SecurityNavItem.dashboard:
        break; // already here
      case SecurityNavItem.incidentResponse:
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => IncidentResponseScreen(session: widget.session),
          ),
        );
      case SecurityNavItem.verifiedPass:
        _comingSoon('Verified Pass');
      case SecurityNavItem.chat:
        _comingSoon('Chat');
    }
  }

  Future<void> _respond(int incidentId) =>
      _run(incidentId, () => ref.read(securityActionsProvider(_token)).respond(incidentId));

  Future<void> _resolve(int incidentId) =>
      _run(incidentId, () => ref.read(securityActionsProvider(_token)).resolve(incidentId));

  Future<void> _run(int incidentId, Future<void> Function() action) async {
    setState(() => _busyIncidentId = incidentId);
    try {
      await action();
    } on SecurityException catch (e) {
      _showFailure(e.message);
    } catch (_) {
      _showFailure('Something went wrong. Please try again.');
    } finally {
      if (mounted) setState(() => _busyIncidentId = null);
    }
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

  /// The SOS trigger: throw up the full-screen priority overlay the instant a
  /// new unacknowledged emergency arrives. Driven off the same
  /// [securityOverviewProvider] the dashboard already watches — which the `sos`
  /// realtime channel refreshes — so it fires within a socket round-trip, and
  /// the 12-second poll is the backstop. Lives on the dashboard (the root
  /// security screen, always mounted), and the overlay uses the root navigator,
  /// so it interrupts wherever the officer is — including Incident Response.
  void _maybeAnnounce(SecurityOverview? overview) {
    if (overview == null || _presenting || !mounted) return;

    SecurityIncident? incoming;
    for (final i in overview.incidents) {
      if (i.isActive && !_announced.contains(i.id)) {
        incoming = i;
        break;
      }
    }
    if (incoming == null) return;

    final incident = incoming;
    _announced.add(incident.id);
    _presenting = true;

    final officerName = overview.officerName != 'Security'
        ? overview.officerName
        : widget.session.name;

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) {
        _presenting = false;
        return;
      }
      await showSosAlertOverlay(
        context,
        incident: incident,
        officerName: officerName,
        onAcknowledge: () =>
            ref.read(securityActionsProvider(_token)).respond(incident.id),
        onCallRoom: () => ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            behavior: SnackBarBehavior.floating,
            backgroundColor: const Color(0xFF1F2937),
            content: Text('Room calling is coming soon.',
                style: AppTypography.style(color: Colors.white, fontSize: 14)),
          ),
        ),
      );
      _presenting = false;
      // A second emergency may have arrived while this one was on screen.
      if (mounted) {
        _maybeAnnounce(ref.read(securityOverviewProvider(_token)).value);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    // Keep the live socket alive for as long as the dashboard is on screen.
    ref.watch(securityRealtimeProvider(_token));

    // Surface any new unacknowledged emergency as the priority overlay. Fires on
    // first load and on every refresh (socket or poll); the guards inside make it
    // announce each alert exactly once.
    ref.listen(securityOverviewProvider(_token), (_, next) {
      _maybeAnnounce(next.value);
    });

    final overviewAsync = ref.watch(securityOverviewProvider(_token));
    final weather = ref.watch(weatherProvider).value;

    // Prefer the session's name until the first fetch resolves the officer.
    final overview = overviewAsync.value;
    final officerName = overview?.officerName != null && overview!.officerName != 'Security'
        ? overview.officerName
        : widget.session.name;

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
                      active: SecurityNavItem.dashboard,
                      onSelect: _onNav,
                      onLogout: _logout,
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          SecurityTopBar(
                            officerName: officerName,
                            officerRole: overview?.officerRole ?? 'Security Office',
                            weather: weather,
                            hasAlert: (overview?.activeIncidents ?? 0) > 0,
                          ),
                          const SizedBox(height: 20),
                          _header(),
                          const SizedBox(height: 20),
                          Expanded(
                            child: overviewAsync.when(
                              data: (data) => _content(data),
                              loading: () => overview != null
                                  ? _content(overview)
                                  : const Center(
                                      child: CircularProgressIndicator(color: AppColors.gold)),
                              error: (_, _) => overview != null
                                  ? _content(overview)
                                  : _errorState(),
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
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Security',
          style: AppTypography.style(color: AppColors.gold, fontSize: 12),
        ),
        const SizedBox(height: 3),
        Text(
          'Dashboard',
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 36,
            fontWeight: FontWeight.w700,
            height: 1.1,
          ),
        ),
      ],
    );
  }

  Widget _content(SecurityOverview data) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(flex: 2, child: _leftColumn(data)),
        const SizedBox(width: 24),
        SizedBox(width: 368, child: _rightColumn(data)),
      ],
    );
  }

  Widget _leftColumn(SecurityOverview data) {
    return SingleChildScrollView(
      padding: const EdgeInsets.only(bottom: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: SecurityStatCard(
                  label: 'ACTIVE INCIDENTS',
                  value: data.activeIncidents,
                  accent: const Color(0xFFFF0000),
                  pulsing: data.activeIncidents > 0,
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: SecurityStatCard(
                  label: 'VISITORS TODAY',
                  value: data.visitorsToday,
                  accent: const Color(0xFF00FF00),
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: SecurityStatCard(
                  label: 'VERIFIED PASS CODE',
                  value: data.verifiedPasses,
                  accent: AppColors.gold,
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          _sectionLabel('OPEN INCIDENTS'),
          const SizedBox(height: 15),
          if (data.incidents.isEmpty)
            const OpenIncidentsEmpty()
          else
            for (final incident in data.incidents) ...[
              IncidentCard(
                incident: incident,
                busy: _busyIncidentId == incident.id,
                onRespond: () => _respond(incident.id),
                onResolve: () => _resolve(incident.id),
              ),
              const SizedBox(height: 15),
            ],
          const SizedBox(height: 9),
          _sectionLabel('VISITORS TODAY'),
          const SizedBox(height: 15),
          if (data.visitors.isEmpty)
            const SectionEmpty(
              icon: Icons.badge_outlined,
              message: 'No visitors checked in today',
            )
          else
            for (final visitor in data.visitors) ...[
              VisitorRow(visitor: visitor),
              const SizedBox(height: 18),
            ],
        ],
      ),
    );
  }

  Widget _rightColumn(SecurityOverview data) {
    return SingleChildScrollView(
      padding: const EdgeInsets.only(bottom: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _sectionLabel('VISITOR PASS REQUESTS'),
          const SizedBox(height: 15),
          if (data.passRequests.isEmpty)
            const SectionEmpty(
              icon: Icons.qr_code_2_rounded,
              message: 'No pending pass requests',
            )
          else
            for (final request in data.passRequests) ...[
              VisitorPassRequestCard(request: request),
              const SizedBox(height: 17),
            ],
        ],
      ),
    );
  }

  Widget _sectionLabel(String text) => Text(
        text,
        style: AppTypography.style(
          color: Colors.white.withValues(alpha: 0.35),
          fontSize: 12,
          fontWeight: FontWeight.w700,
          letterSpacing: 1.4,
        ),
      );

  Widget _errorState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.wifi_off_rounded, size: 30, color: Colors.white.withValues(alpha: 0.4)),
          const SizedBox(height: 12),
          Text(
            'Could not load the dashboard.',
            style: AppTypography.style(color: Colors.white.withValues(alpha: 0.6), fontSize: 15),
          ),
          const SizedBox(height: 16),
          Material(
            color: AppColors.gold,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              onTap: () => ref.invalidate(securityOverviewProvider(_token)),
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
