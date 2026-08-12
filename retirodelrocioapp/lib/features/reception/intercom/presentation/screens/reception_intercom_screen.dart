import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/intercom_call/data/intercom_call_repository.dart';
import 'package:retirodelrocioapp/features/reception/chat/application/reception_chat_providers.dart';
import 'package:retirodelrocioapp/features/reception/chat/domain/reception_conversation.dart';
import 'package:retirodelrocioapp/features/reception/chat/presentation/widgets/reception_chat_widgets.dart';
import 'package:retirodelrocioapp/features/reception/intercom/application/reception_intercom_call_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/screens/reception_notification_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';
import 'package:retirodelrocioapp/features/staff_chat/application/staff_chat_providers.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_channel.dart';
import 'package:retirodelrocioapp/features/staff_chat/presentation/widgets/staff_chat_widgets.dart';
import 'package:retirodelrocioapp/features/staff_intercom/application/staff_intercom_call_providers.dart';

/// Which directory the screen is showing — every other station reception can
/// reach, or every in-house guest.
enum _IntercomTab { staff, guests }

/// Reception's Intercom (Figma 335:4684 / 335:5021, adapted for the front
/// desk): a voice-call directory to the other stations (Housekeeping,
/// Maintenance, Security — the same mesh Staff Chat already reaches, now all
/// three with their own Intercom screen too) and to any in-house guest —
/// both tabs place a real, ringing call. Staff-tab calls go through the
/// shared `staff/intercom/calls` endpoint (`staffIntercomCallProvider`,
/// used for placing only); the resulting call is then picked up by this
/// screen's own `receptionIntercomCallProvider` — the single source of
/// truth the root-level ringing gate watches — via a refresh, exactly the
/// same table either provider reads from. Real presence data already
/// exists for every target here (staff heartbeats and guest-tablet
/// heartbeats), so Call is disabled for whoever is currently offline
/// instead of always reading "available".
class ReceptionIntercomScreen extends ConsumerStatefulWidget {
  const ReceptionIntercomScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionIntercomScreen> createState() =>
      _ReceptionIntercomScreenState();
}

class _ReceptionIntercomScreenState
    extends ConsumerState<ReceptionIntercomScreen> {
  _IntercomTab _tab = _IntercomTab.staff;
  bool _placingCall = false;

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) ReceptionNavigation.afterLogout(context);
  }

  void _openNotifications() {
    ReceptionNavigation.push(
      context,
      'notifications',
      ReceptionNotificationScreen(
        session: widget.session,
        current: ReceptionNavItem.intercom,
      ),
    );
  }

  void _selectTab(_IntercomTab tab) {
    if (_tab == tab) return;
    setState(() => _tab = tab);
  }

  Future<void> _callStaff(int userId) async {
    if (_placingCall) return;
    setState(() => _placingCall = true);
    try {
      await ref.read(staffIntercomCallRepositoryProvider).place(_token, {
        'user_id': userId,
      });
      // Pull the just-placed call into this screen's own single source of
      // truth, the same provider the root-level ringing gate watches —
      // otherwise the two providers would sit on independent state for the
      // exact same underlying call.
      await ref.read(receptionIntercomCallProvider(_token).notifier).refresh();
    } on IntercomCallException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) {
        _toast('Something went wrong. Please try again.', error: true);
      }
    } finally {
      if (mounted) setState(() => _placingCall = false);
    }
  }

  Future<void> _callGuest(int bookingId) async {
    if (_placingCall) return;
    setState(() => _placingCall = true);
    try {
      await ref
          .read(receptionIntercomCallProvider(_token).notifier)
          .place(bookingId);
    } on IntercomCallException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) {
        _toast('Something went wrong. Please try again.', error: true);
      }
    } finally {
      if (mounted) setState(() => _placingCall = false);
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

  @override
  Widget build(BuildContext context) {
    final unreadNotifications = ref.watch(
      receptionUnreadNotificationsProvider(_token),
    );

    return ReceptionScaffold(
      session: widget.session,
      active: ReceptionNavItem.intercom,
      onNav: (item) => ReceptionNavigation.select(
        context,
        widget.session,
        item,
        current: ReceptionNavItem.intercom,
      ),
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Intercom',
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _tabToggle(),
          const SizedBox(height: 20),
          Expanded(
            child: _tab == _IntercomTab.staff ? _staffGrid() : _guestGrid(),
          ),
        ],
      ),
    );
  }

  Widget _tabToggle() {
    return Container(
      width: 220,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Expanded(child: _tabButton('Staff', _IntercomTab.staff)),
          Expanded(child: _tabButton('Guests', _IntercomTab.guests)),
        ],
      ),
    );
  }

  Widget _tabButton(String label, _IntercomTab tab) {
    final selected = _tab == tab;
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.16)
          : Colors.transparent,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: () => _selectTab(tab),
        borderRadius: BorderRadius.circular(10),
        child: Container(
          height: 36,
          alignment: Alignment.center,
          child: Text(
            label,
            style: AppTypography.style(
              color: selected
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: 0.5),
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  // --- Staff directory -----------------------------------------------------

  Widget _staffGrid() {
    final channelsAsync = ref.watch(staffChatChannelsProvider(_token));
    final channels = channelsAsync.value ?? const <StaffChannel>[];

    if (channelsAsync.isLoading && channels.isEmpty) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.gold),
      );
    }
    if (channels.isEmpty) {
      return _emptyState(
        Icons.groups_rounded,
        'No other stations to call right now.',
      );
    }

    return _grid(
      itemCount: channels.length,
      itemBuilder: (i) {
        final c = channels[i];
        return _card(
          avatar: StaffChatAvatar(role: c.role, online: c.online),
          title: c.name,
          subtitle: c.roleLabel,
          online: c.online,
          onCall: () => _callStaff(c.userId),
        );
      },
    );
  }

  // --- Guest directory -------------------------------------------------------

  Widget _guestGrid() {
    final conversationsAsync = ref.watch(
      receptionGuestConversationsProvider(_token),
    );
    final guests =
        conversationsAsync.value ?? const <ReceptionGuestConversation>[];

    if (conversationsAsync.isLoading && guests.isEmpty) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.gold),
      );
    }
    if (guests.isEmpty) {
      return _emptyState(
        Icons.person_off_rounded,
        'No guests are checked in right now.',
      );
    }

    return _grid(
      itemCount: guests.length,
      itemBuilder: (i) {
        final g = guests[i];
        return _card(
          avatar: ReceptionChatAvatar(
            initials: g.initials,
            online: g.guestOnline,
          ),
          title: g.guestName,
          subtitle: g.roomLabel,
          online: g.guestOnline,
          onCall: () => _callGuest(g.bookingId),
        );
      },
    );
  }

  // --- Shared --------------------------------------------------------------

  Widget _grid({
    required int itemCount,
    required Widget Function(int index) itemBuilder,
  }) {
    return GridView.builder(
      padding: EdgeInsets.zero,
      itemCount: itemCount,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: 16,
        crossAxisSpacing: 16,
        childAspectRatio: 1.7,
      ),
      itemBuilder: (_, i) => itemBuilder(i),
    );
  }

  Widget _card({
    required Widget avatar,
    required String title,
    String? subtitle,
    required bool online,
    required VoidCallback onCall,
  }) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.08),
          width: 0.8,
        ),
      ),
      child: Row(
        children: [
          avatar,
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 3),
                if (subtitle != null) ...[
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTypography.style(
                      color: AppColors.gold.withValues(alpha: 0.7),
                      fontSize: 11,
                    ),
                  ),
                  const SizedBox(height: 3),
                ],
                Text(
                  online ? 'Online' : 'Offline',
                  style: AppTypography.style(
                    color: online
                        ? receptionChatOnlineGreen
                        : Colors.white.withValues(alpha: 0.4),
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          _callButton(online: online, onTap: online ? onCall : null),
        ],
      ),
    );
  }

  Widget _callButton({required bool online, required VoidCallback? onTap}) {
    final color = online
        ? receptionChatOnlineGreen
        : Colors.white.withValues(alpha: 0.3);

    return Material(
      color: color.withValues(alpha: online ? 0.13 : 0.06),
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: Container(
          width: 42,
          height: 42,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(
              color: color.withValues(alpha: online ? 0.35 : 0.15),
              width: 0.8,
            ),
          ),
          child: Icon(Icons.call_rounded, size: 18, color: color),
        ),
      ),
    );
  }

  Widget _emptyState(IconData icon, String message) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 30, color: Colors.white.withValues(alpha: 0.25)),
          const SizedBox(height: 12),
          Text(
            message,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }
}
