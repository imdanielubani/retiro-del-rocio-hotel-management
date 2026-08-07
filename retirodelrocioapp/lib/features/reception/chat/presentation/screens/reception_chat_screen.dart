import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/reception/chat/application/reception_chat_providers.dart';
import 'package:retirodelrocioapp/features/reception/chat/data/reception_chat_repository.dart';
import 'package:retirodelrocioapp/features/reception/chat/domain/reception_chat_message.dart';
import 'package:retirodelrocioapp/features/reception/chat/domain/reception_conversation.dart';
import 'package:retirodelrocioapp/features/reception/chat/presentation/widgets/reception_chat_widgets.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/screens/reception_notification_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_scaffold.dart';
import 'package:retirodelrocioapp/features/staff_chat/presentation/widgets/staff_chat_body.dart';

/// Which conversation set the screen is showing — Concierge Chat with guests
/// (Reception's own thing), or the shared internal Staff Chat with every
/// other station.
enum _ChatTab { guests, staff }

/// The guest conversation currently open in the thread panel.
class _GuestSelection {
  const _GuestSelection(
    this.bookingId,
    this.roomUnitId,
    this.guestName,
    this.roomLabel,
    this.online,
  );
  final int bookingId;
  final int? roomUnitId;
  final String guestName;
  final String roomLabel;
  final bool online;
}

/// Reception's Chat screen — Concierge Chat threads with every in-house
/// guest (this screen's own bespoke UI), and the shared Staff Chat with
/// every other station (Housekeeping, Maintenance, Security, Admin), the
/// exact same widget every other tablet's Chat screen embeds.
class ReceptionChatScreen extends ConsumerStatefulWidget {
  const ReceptionChatScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionChatScreen> createState() =>
      _ReceptionChatScreenState();
}

class _ReceptionChatScreenState extends ConsumerState<ReceptionChatScreen> {
  /// The height the two-pane body stops shrinking at and starts scrolling
  /// instead — same rule the guest tablet's Service Request screen uses.
  static const double _minBodyHeight = 440;

  final _controller = TextEditingController();
  final _scrollController = ScrollController();

  _ChatTab _tab = _ChatTab.guests;
  _GuestSelection? _selection;
  bool _sending = false;
  int _lastMessageCount = 0;
  DateTime? _lastTypingSentAt;

  String get _token => widget.session.token;

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

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
        current: ReceptionNavItem.chat,
      ),
    );
  }

  void _selectTab(_ChatTab tab) {
    if (_tab == tab) return;
    setState(() => _tab = tab);
  }

  void _selectGuest(ReceptionGuestConversation c) {
    setState(() {
      _selection = _GuestSelection(
        c.bookingId,
        c.roomUnitId,
        c.guestName,
        c.roomLabel,
        c.guestOnline,
      );
      _lastMessageCount = 0;
    });
  }

  /// Debounced "I'm typing" signal — fires at most once every 2.5 seconds
  /// while the desk is actively typing to a guest.
  void _onComposerChanged(String text) {
    final selection = _selection;
    if (text.trim().isEmpty || selection == null) return;

    final now = DateTime.now();
    if (_lastTypingSentAt != null &&
        now.difference(_lastTypingSentAt!) <
            const Duration(milliseconds: 2500)) {
      return;
    }
    _lastTypingSentAt = now;
    unawaited(
      ref
          .read(receptionChatActionsProvider(_token))
          .sendTypingToGuest(selection.bookingId),
    );
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    final selection = _selection;
    if (body.isEmpty || _sending || selection == null) return;

    setState(() => _sending = true);
    try {
      await ref
          .read(receptionChatActionsProvider(_token))
          .sendToGuest(selection.bookingId, body);
      _controller.clear();
    } on ReceptionChatException catch (error) {
      _toast(error.message, error: true);
    } finally {
      if (mounted) setState(() => _sending = false);
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

  void _maybeScrollToBottom(int messageCount) {
    if (messageCount == _lastMessageCount) return;
    _lastMessageCount = messageCount;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.jumpTo(_scrollController.position.maxScrollExtent);
    });
  }

  @override
  Widget build(BuildContext context) {
    final unreadNotifications = ref.watch(
      receptionUnreadNotificationsProvider(_token),
    );

    return ReceptionScaffold(
      session: widget.session,
      active: ReceptionNavItem.chat,
      onNav: (item) => ReceptionNavigation.select(
        context,
        widget.session,
        item,
        current: ReceptionNavItem.chat,
      ),
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Chat',
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _tabToggle(),
          const SizedBox(height: 16),
          Expanded(
            child: _tab == _ChatTab.guests
                ? _guestTab()
                : StaffChatBody(session: widget.session),
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
          Expanded(child: _tabButton('Guests', _ChatTab.guests)),
          Expanded(child: _tabButton('Staff', _ChatTab.staff)),
        ],
      ),
    );
  }

  Widget _tabButton(String label, _ChatTab tab) {
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

  // --- Guests tab: Concierge Chat -----------------------------------------

  Widget _guestTab() {
    // A messaging screen's own body has to stay usable even when the
    // available height is squeezed tighter than its content wants (the
    // on-screen keyboard opening for the composer is the common case) —
    // same guard `GuestServiceRequestScreen` uses, rather than letting a
    // fixed-height Row overflow.
    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          child: SizedBox(
            height: math.max(constraints.maxHeight, _minBodyHeight),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(width: 320, child: _conversationPanel()),
                const SizedBox(width: 20),
                Expanded(child: _threadPanel()),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _conversationPanel() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.03),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.06),
          width: 0.8,
        ),
      ),
      child: _guestList(),
    );
  }

  Widget _guestList() {
    final conversationsAsync = ref.watch(
      receptionGuestConversationsProvider(_token),
    );
    final conversations =
        conversationsAsync.value ?? const <ReceptionGuestConversation>[];

    if (conversationsAsync.isLoading && conversations.isEmpty) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.gold),
      );
    }
    if (conversations.isEmpty) {
      return _emptyList(
        Icons.chat_bubble_outline_rounded,
        'No guests are checked in right now.',
      );
    }

    return ListView.separated(
      padding: EdgeInsets.zero,
      itemCount: conversations.length,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (_, i) {
        final c = conversations[i];
        final selected = _selection?.bookingId == c.bookingId;
        return ReceptionChatConversationTile(
          avatar: ReceptionChatAvatar(
            initials: c.initials,
            online: c.guestOnline,
          ),
          title: c.guestName,
          subtitle: c.roomLabel,
          preview: c.lastMessage,
          timeLabel: c.lastMessageLabel,
          unreadCount: c.unreadCount,
          selected: selected,
          onTap: () => _selectGuest(c),
        );
      },
    );
  }

  Widget _emptyList(IconData icon, String message) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 24, color: Colors.white.withValues(alpha: 0.25)),
          const SizedBox(height: 10),
          Text(
            message,
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.35),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _threadPanel() {
    final selection = _selection;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.03),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.06),
          width: 0.8,
        ),
      ),
      child: selection == null ? _noSelection() : _thread(selection),
    );
  }

  Widget _noSelection() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.forum_outlined,
            size: 30,
            color: Colors.white.withValues(alpha: 0.25),
          ),
          const SizedBox(height: 12),
          Text(
            'Select a guest to start messaging.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  Widget _thread(_GuestSelection selection) {
    final messagesAsync = ref.watch(
      receptionGuestThreadProvider((_token, selection.bookingId)),
    );
    final messages = messagesAsync.value ?? const <ReceptionChatMessage>[];
    _maybeScrollToBottom(messages.length);

    final typing =
        selection.roomUnitId != null &&
        ref.watch(receptionGuestTypingProvider(selection.roomUnitId!));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _threadHeader(selection),
        Divider(
          height: 1,
          thickness: 0.8,
          color: Colors.white.withValues(alpha: 0.08),
        ),
        Expanded(child: _messages(messages)),
        if (typing)
          ReceptionTypingIndicator(label: '${selection.guestName} is typing…'),
        Divider(
          height: 1,
          thickness: 0.8,
          color: Colors.white.withValues(alpha: 0.08),
        ),
        _composer(selection),
      ],
    );
  }

  Widget _threadHeader(_GuestSelection selection) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
      child: Row(
        children: [
          ReceptionChatAvatar(
            initials: _initialsOf(selection.guestName),
            online: selection.online,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  selection.guestName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Flexible(
                      child: Text(
                        selection.roomLabel,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.45),
                          fontSize: 12,
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    ReceptionOnlineStatus(online: selection.online),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _initialsOf(String name) {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((p) => p.isNotEmpty)
        .toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
        .toUpperCase();
  }

  Widget _messages(List<ReceptionChatMessage> messages) {
    if (messages.isEmpty) {
      return Center(
        child: Text(
          'No messages yet — say hello.',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 13,
          ),
        ),
      );
    }

    return ListView.separated(
      controller: _scrollController,
      padding: const EdgeInsets.all(20),
      itemCount: messages.length,
      separatorBuilder: (_, _) => const SizedBox(height: 14),
      itemBuilder: (_, i) => ReceptionChatBubble(message: messages[i]),
    );
  }

  Widget _composer(_GuestSelection selection) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 18),
      child: Row(
        children: [
          Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.06),
                borderRadius: BorderRadius.circular(100),
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.12),
                  width: 0.8,
                ),
              ),
              child: TextField(
                controller: _controller,
                minLines: 1,
                maxLines: 4,
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => _send(),
                onChanged: _onComposerChanged,
                cursorColor: AppColors.gold,
                style: AppTypography.style(color: Colors.white, fontSize: 14),
                decoration: InputDecoration(
                  isCollapsed: true,
                  border: InputBorder.none,
                  contentPadding: const EdgeInsets.symmetric(vertical: 13),
                  hintText: 'Message ${selection.guestName}...',
                  hintStyle: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.5),
                    fontSize: 14,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Material(
            color: AppColors.gold,
            shape: const CircleBorder(),
            child: InkWell(
              onTap: _sending ? null : _send,
              customBorder: const CircleBorder(),
              child: SizedBox(
                width: 42,
                height: 42,
                child: Center(
                  child: _sending
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.black,
                          ),
                        )
                      : const Icon(
                          Icons.send_rounded,
                          size: 18,
                          color: Colors.black,
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
