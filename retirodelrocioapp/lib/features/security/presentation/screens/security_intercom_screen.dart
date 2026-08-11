import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/security/notifications/application/security_notification_providers.dart';
import 'package:retirodelrocioapp/features/security/notifications/presentation/screens/security_notification_screen.dart';
import 'package:retirodelrocioapp/features/security/presentation/screens/incident_response_screen.dart';
import 'package:retirodelrocioapp/features/security/presentation/screens/security_chat_screen.dart';
import 'package:retirodelrocioapp/features/security/presentation/screens/visitor_verification_screen.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_nav_rail.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_top_bar.dart';
import 'package:retirodelrocioapp/features/staff_intercom/presentation/widgets/staff_intercom_body.dart';
import 'package:retirodelrocioapp/features/staff_intercom/presentation/widgets/staff_intercom_call_gate.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The security tablet's Intercom screen — the shared staff directory
/// (voice-call Reception, Housekeeping or Maintenance) inside the same
/// frosted shell every other security screen uses.
class SecurityIntercomScreen extends ConsumerStatefulWidget {
  const SecurityIntercomScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<SecurityIntercomScreen> createState() =>
      _SecurityIntercomScreenState();
}

class _SecurityIntercomScreenState
    extends ConsumerState<SecurityIntercomScreen> {
  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) Navigator.of(context).popUntil((r) => r.isFirst);
  }

  void _onNav(SecurityNavItem item) {
    switch (item) {
      case SecurityNavItem.intercom:
        break; // already here
      case SecurityNavItem.dashboard:
        Navigator.of(context).pop(); // back to the dashboard beneath
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
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => SecurityChatScreen(session: widget.session),
          ),
        );
    }
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SecurityNotificationScreen(
          session: widget.session,
          current: SecurityNavItem.intercom,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(securityNotificationChimeProvider(_token));
    watchStaffIntercomCall(context, ref, widget.session);
    final weather = ref.watch(weatherProvider).value;
    final unreadNotifications = ref.watch(
      securityUnreadNotificationsProvider(_token),
    );

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
                      active: SecurityNavItem.intercom,
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
                            hasAlert: false,
                            hasUnreadNotifications: unreadNotifications > 0,
                            onNotifications: _openNotifications,
                          ),
                          const SizedBox(height: 20),
                          _header(),
                          const SizedBox(height: 20),
                          Expanded(
                            child: StaffIntercomBody(session: widget.session),
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
          'Intercom',
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
}
