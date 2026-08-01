import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/security/notifications/application/security_notification_providers.dart';
import 'package:retirodelrocioapp/features/security/notifications/data/security_notification_repository.dart';
import 'package:retirodelrocioapp/features/security/notifications/domain/security_notification.dart';
import 'package:retirodelrocioapp/features/security/presentation/screens/incident_response_screen.dart';
import 'package:retirodelrocioapp/features/security/presentation/screens/visitor_verification_screen.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_nav_rail.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_top_bar.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The "All" / "Unread" tabs sit alongside the category chips but aren't
/// [SecurityNotificationCategory] values, so the filter needs one more state
/// than the enum alone provides.
enum _Filter { all, unread }

/// Security's real gate-relevant feed (`GET /security/notifications`): an
/// unread count, category filter chips and the notification list — same
/// visual language as the reception tablet's Notifications screen, inside
/// the security dashboard's own shell (nav rail + top bar). Today the only
/// event that ever lands here is a guest inviting a visitor. A new arrival is
/// pushed live over the hotel-wide `security` realtime channel (see
/// `securityNotificationsRealtimeProvider`); `securityNotificationChimeProvider`
/// (watched by the security dashboard) is what rings the chime and lights
/// the bell's badge — this screen just renders the feed and lets the officer
/// act on it.
class SecurityNotificationScreen extends ConsumerStatefulWidget {
  const SecurityNotificationScreen({
    super.key,
    required this.session,
    this.current = SecurityNavItem.dashboard,
  });

  final StaffSession session;

  /// The security screen the officer was on before opening the bell — kept
  /// so the nav rail still highlights it.
  final SecurityNavItem current;

  @override
  ConsumerState<SecurityNotificationScreen> createState() =>
      _SecurityNotificationScreenState();
}

class _SecurityNotificationScreenState
    extends ConsumerState<SecurityNotificationScreen> {
  Object _filter = _Filter.all;

  /// The notification whose read-state change is mid-request, so only that
  /// card reacts while it's in flight.
  int? _busyId;
  bool _markingAll = false;

  String get _token => widget.session.token;

  List<SecurityNotification> _visible(List<SecurityNotification> all) {
    if (_filter == _Filter.all) return all;
    if (_filter == _Filter.unread) {
      return all.where((n) => !n.read).toList();
    }
    final category = _filter as SecurityNotificationCategory;
    return all.where((n) => n.category == category).toList();
  }

  Future<void> _markAllRead() async {
    setState(() => _markingAll = true);
    try {
      await ref
          .read(securityNotificationActionsProvider(_token))
          .markAllRead();
    } on SecurityNotificationException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) {
        _toast('Something went wrong. Please try again.', error: true);
      }
    } finally {
      if (mounted) setState(() => _markingAll = false);
    }
  }

  Future<void> _open(SecurityNotification n) async {
    if (n.read) return;
    setState(() => _busyId = n.id);
    try {
      await ref
          .read(securityNotificationActionsProvider(_token))
          .markRead(n.id);
    } on SecurityNotificationException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) {
        _toast('Something went wrong. Please try again.', error: true);
      }
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  void _toast(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: error
            ? const Color(0xFF7F1D1D)
            : const Color(0xFF14532D),
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) Navigator.of(context).popUntil((r) => r.isFirst);
  }

  void _onNav(SecurityNavItem item) {
    switch (item) {
      case SecurityNavItem.dashboard:
        Navigator.of(context).pop(); // back to the dashboard beneath
      case SecurityNavItem.verifiedPass:
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => VisitorVerificationScreen(session: widget.session),
          ),
        );
      case SecurityNavItem.incidentResponse:
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => IncidentResponseScreen(session: widget.session),
          ),
        );
      case SecurityNavItem.chat:
        Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const ComingSoonScreen(title: 'Chat')),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final notificationsAsync = ref.watch(securityNotificationsProvider(_token));
    final notifications = notificationsAsync.value ?? const [];
    final unreadCount = notifications.where((n) => !n.read).length;
    final weather = ref.watch(weatherProvider).value;

    return Scaffold(
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
                    active: widget.current,
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
                        ),
                        const SizedBox(height: 20),
                        _header(unreadCount),
                        const SizedBox(height: 16),
                        _unreadPill(unreadCount),
                        const SizedBox(height: 16),
                        _filterChips(unreadCount),
                        const SizedBox(height: 19),
                        Expanded(
                          child: notificationsAsync.when(
                            data: (data) => _list(data),
                            loading: () => notifications.isNotEmpty
                                ? _list(notifications)
                                : const Center(
                                    child: CircularProgressIndicator(
                                      color: AppColors.gold,
                                    ),
                                  ),
                            error: (_, _) => notifications.isNotEmpty
                                ? _list(notifications)
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
    );
  }

  Widget _header(int unreadCount) {
    return Row(
      children: [
        IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
        ),
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
                'Notifications',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 28,
                  fontWeight: FontWeight.w700,
                  height: 1.1,
                ),
              ),
            ],
          ),
        ),
        _markAllReadButton(unreadCount),
      ],
    );
  }

  Widget _unreadPill(int unreadCount) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: const BoxDecoration(
            color: AppColors.gold,
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: 8),
        Text(
          unreadCount == 1 ? '1 unread' : '$unreadCount unread',
          style: AppTypography.style(color: AppColors.gold, fontSize: 15),
        ),
      ],
    );
  }

  Widget _markAllReadButton(int unreadCount) {
    final enabled = unreadCount > 0 && !_markingAll;
    return Material(
      color: Colors.white.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: enabled ? _markAllRead : null,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 11),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.2),
              width: 0.8,
            ),
          ),
          child: _markingAll
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.gold,
                  ),
                )
              : Text(
                  'Mark all read',
                  style: AppTypography.style(
                    color: enabled
                        ? AppColors.gold
                        : AppColors.gold.withValues(alpha: 0.4),
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                  ),
                ),
        ),
      ),
    );
  }

  Widget _filterChips(int unreadCount) {
    return SizedBox(
      height: 41,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          _chip(label: 'All', value: _Filter.all),
          const SizedBox(width: 8),
          _chip(label: 'Unread', value: _Filter.unread, badge: unreadCount),
          for (final category in SecurityNotificationCategory.values) ...[
            const SizedBox(width: 8),
            _chip(label: category.label, value: category),
          ],
        ],
      ),
    );
  }

  Widget _chip({required String label, required Object value, int? badge}) {
    final selected = _filter == value;

    return Material(
      color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _filter = value),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.5)
                  : Colors.white.withValues(alpha: 0.2),
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
                      ? const Color(0xFF0A0F1E)
                      : Colors.white.withValues(alpha: 0.6),
                  fontSize: 13,
                  fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
                ),
              ),
              if (badge != null && badge > 0) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 2,
                  ),
                  decoration: const BoxDecoration(
                    color: Color(0xFFEF4444),
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '$badge',
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 12,
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

  Widget _list(List<SecurityNotification> all) {
    final items = _visible(all);

    if (items.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.notifications_off_outlined,
              size: 32,
              color: Colors.white.withValues(alpha: 0.3),
            ),
            const SizedBox(height: 12),
            Text(
              'No notifications here',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 14,
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async =>
          ref.invalidate(securityNotificationsProvider(_token)),
      child: ListView.separated(
        padding: EdgeInsets.zero,
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 19),
        itemBuilder: (_, i) => _card(items[i]),
      ),
    );
  }

  Widget _card(SecurityNotification n) {
    final busy = _busyId == n.id;

    return Material(
      color: n.read
          ? Colors.white.withValues(alpha: 0.04)
          : Colors.white.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: busy ? null : () => _open(n),
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(21),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: n.read
                  ? Colors.white.withValues(alpha: 0.2)
                  : AppColors.gold.withValues(alpha: 0.14),
              width: 0.8,
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.09),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: busy
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.gold,
                        ),
                      )
                    : Icon(n.category.icon, size: 20, color: AppColors.gold),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            n.title,
                            style: AppTypography.style(
                              color: n.read
                                  ? Colors.white.withValues(alpha: 0.7)
                                  : Colors.white,
                              fontSize: 14,
                              fontWeight: n.read
                                  ? FontWeight.w500
                                  : FontWeight.w600,
                            ),
                          ),
                        ),
                        if (!n.read) ...[
                          const SizedBox(width: 8),
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: AppColors.gold,
                              shape: BoxShape.circle,
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      n.message,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.45),
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _timeAgo(n.time),
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.25),
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _errorState() {
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
            'Could not load the notifications.',
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
                  ref.invalidate(securityNotificationsProvider(_token)),
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

  String _timeAgo(DateTime time) {
    final diff = DateTime.now().difference(time);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
    if (diff.inHours < 24) {
      return diff.inHours == 1 ? '1 hr ago' : '${diff.inHours} hrs ago';
    }
    return diff.inDays == 1 ? '1 day ago' : '${diff.inDays} days ago';
  }
}
