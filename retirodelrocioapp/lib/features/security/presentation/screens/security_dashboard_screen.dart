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
import 'package:retirodelrocioapp/features/security/domain/security_visitor.dart';
import 'package:retirodelrocioapp/features/security/presentation/dialogs/sos_alert_overlay.dart';
import 'package:retirodelrocioapp/features/security/presentation/screens/incident_response_screen.dart';
import 'package:retirodelrocioapp/features/security/presentation/screens/visitor_verification_screen.dart';
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
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => ComingSoonScreen(title: title)));
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
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => VisitorVerificationScreen(session: widget.session),
          ),
        );
      case SecurityNavItem.chat:
        _comingSoon('Chat');
    }
  }

  /// Tapping "Verify Pass" on a request hands the officer straight to the gate
  /// screen with that visitor's code already looked up — the same place the nav
  /// rail's Verified Pass goes, just arriving on the visitor instead of a blank
  /// keypad. Refresh on the way back so a granted pass is reflected here.
  Future<void> _verifyRequest(VisitorPassRequest request) async {
    final code = (request.onlineCode ?? '').isNotEmpty
        ? request.onlineCode
        : request.offlineCode;

    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => VisitorVerificationScreen(
          session: widget.session,
          initialCode: code,
        ),
      ),
    );

    if (mounted) ref.invalidate(securityOverviewProvider(_token));
  }

  Future<void> _respond(int incidentId) => _run(
    incidentId,
    () => ref.read(securityActionsProvider(_token)).respond(incidentId),
  );

  Future<void> _resolve(int incidentId) => _run(
    incidentId,
    () => ref.read(securityActionsProvider(_token)).resolve(incidentId),
  );

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
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
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
        token: _token,
        onAcknowledge: () =>
            ref.read(securityActionsProvider(_token)).respond(incident.id),
        onCallRoom: () => ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            behavior: SnackBarBehavior.floating,
            backgroundColor: const Color(0xFF1F2937),
            content: Text(
              'Room calling is coming soon.',
              style: AppTypography.style(color: Colors.white, fontSize: 14),
            ),
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
    final officerName =
        overview?.officerName != null && overview!.officerName != 'Security'
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
                            officerRole:
                                overview?.officerRole ?? 'Security Office',
                            weather: weather,
                            hasAlert: (overview?.activeIncidents ?? 0) > 0,
                          ),
                          const SizedBox(height: 20),
                          _header(stale: overviewAsync.hasError && overview != null),
                          const SizedBox(height: 20),
                          Expanded(
                            child: overviewAsync.when(
                              data: (data) => _content(data),
                              loading: () => overview != null
                                  ? _content(overview)
                                  : const Center(
                                      child: CircularProgressIndicator(
                                        color: AppColors.gold,
                                      ),
                                    ),
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

  Widget _header({bool stale = false}) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
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
          ),
        ),
        if (stale) _staleChip(),
      ],
    );
  }

  /// Shown when the last refresh failed but older data is still on screen.
  ///
  /// Without this the dashboard is indistinguishable from a quiet night: the
  /// sections fall back to "No pending pass requests" and an officer has no way
  /// to tell that the list is simply out of date. During an incident that
  /// distinction is the whole point of the screen.
  Widget _staleChip() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: const Color(0xFFFF0000).withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFFF0000).withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_rounded,
            size: 14,
            color: const Color(0xFFFF0000).withValues(alpha: 0.9),
          ),
          const SizedBox(width: 8),
          Text(
            'Not updating — check the connection',
            style: AppTypography.style(
              color: const Color(0xFFFF0000).withValues(alpha: 0.9),
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _content(SecurityOverview data) {
    return Row(
      // Both columns run the full height so each can own its own scroll.
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(flex: 2, child: _leftColumn(data)),
        const SizedBox(width: 24),
        SizedBox(width: 368, child: _rightColumn(data)),
      ],
    );
  }

  /// The counters and open incidents stay put; Visitors Today takes the rest of
  /// the column and scrolls on its own, so a busy gate never pushes the incident
  /// count off the screen an officer is watching.
  Widget _leftColumn(SecurityOverview data) {
    return Column(
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
        // Incidents take only the room they need, but are capped so a pile-up
        // scrolls within itself rather than swallowing the visitor list.
        ConstrainedBox(
          constraints: const BoxConstraints(maxHeight: 300),
          child: data.incidents.isEmpty
              ? const OpenIncidentsEmpty()
              : ListView.separated(
                  shrinkWrap: true,
                  padding: EdgeInsets.zero,
                  itemCount: data.incidents.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 15),
                  itemBuilder: (_, i) {
                    final incident = data.incidents[i];
                    return IncidentCard(
                      incident: incident,
                      busy: _busyIncidentId == incident.id,
                      onRespond: () => _respond(incident.id),
                      onResolve: () => _resolve(incident.id),
                    );
                  },
                ),
        ),
        const SizedBox(height: 24),
        _sectionLabel('VISITORS TODAY'),
        const SizedBox(height: 15),
        Expanded(
          child: data.visitors.isEmpty
              ? const SingleChildScrollView(
                  child: SectionEmpty(
                    icon: Icons.badge_outlined,
                    message: 'No visitors checked in today',
                  ),
                )
              : ListView.separated(
                  padding: const EdgeInsets.only(bottom: 24),
                  itemCount: data.visitors.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 18),
                  itemBuilder: (_, i) => VisitorRow(visitor: data.visitors[i]),
                ),
        ),
      ],
    );
  }

  /// The requests column: its heading stays pinned while the cards scroll.
  Widget _rightColumn(SecurityOverview data) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionLabel('VISITOR PASS REQUESTS'),
        const SizedBox(height: 15),
        Expanded(
          child: data.passRequests.isEmpty
              ? const SingleChildScrollView(
                  child: SectionEmpty(
                    icon: Icons.qr_code_2_rounded,
                    message: 'No pending pass requests',
                  ),
                )
              : ListView.separated(
                  padding: const EdgeInsets.only(bottom: 24),
                  itemCount: data.passRequests.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 17),
                  itemBuilder: (_, i) {
                    final request = data.passRequests[i];
                    return VisitorPassRequestCard(
                      request: request,
                      onVerify: request.isVerified
                          ? null
                          : () => _verifyRequest(request),
                    );
                  },
                ),
        ),
      ],
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
          Icon(
            Icons.wifi_off_rounded,
            size: 30,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          const SizedBox(height: 12),
          Text(
            'Could not load the dashboard.',
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
              onTap: () => ref.invalidate(securityOverviewProvider(_token)),
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
