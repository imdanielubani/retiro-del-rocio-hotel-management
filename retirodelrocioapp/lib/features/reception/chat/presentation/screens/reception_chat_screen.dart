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

/// Which of the two conversation lists the left panel is showing.
enum _ChatTab { guests, staff }

/// A single selected conversation — either a guest's booking or a staff
/// department — so the thread panel always knows what it's rendering and
/// which endpoint to poll/send against.
sealed class _Selection {
  const _Selection();
}

class _GuestSelection extends _Selection {
  const _GuestSelection(this.bookingId, this.guestName, this.roomLabel);
  final int bookingId;
  final String guestName;
  final String roomLabel;
}

class _StaffSelection extends _Selection {
  const _StaffSelection(this.department, this.label);
  final String department;
  final String label;
}

/// Reception's Chat screen — Concierge Chat threads with every in-house
/// guest, and internal channels with each staff department, side by side in
/// the same list → thread → composer shape the guest tablet's own Chat
/// screen uses.
class ReceptionChatScreen extends ConsumerStatefulWidget {
  const ReceptionChatScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionChatScreen> createState() =>
      _ReceptionChatScreenState();
}

class _ReceptionChatScreenState extends ConsumerState<ReceptionChatScreen> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();

  _ChatTab _tab = _ChatTab.guests;
  _Selection? _selection;
  bool _sending = false;
  int _lastMessageCount = 0;

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
    setState(() {
      _tab = tab;
      _selection = null;
      _lastMessageCount = 0;
    });
  }

  void _selectGuest(ReceptionGuestConversation c) {
    setState(() {
      _selection = _GuestSelection(c.bookingId, c.guestName, c.roomLabel);
      _lastMessageCount = 0;
    });
  }

  void _selectDepartment(ReceptionStaffConversation c) {
    setState(() {
      _selection = _StaffSelection(c.department, c.label);
      _lastMessageCount = 0;
    });
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    final selection = _selection;
    if (body.isEmpty || _sending || selection == null) return;

    setState(() => _sending = true);
    try {
      final actions = ref.read(receptionChatActionsProvider(_token));
      switch (selection) {
        case _GuestSelection(:final bookingId):
          await actions.sendToGuest(bookingId, body);
        case _StaffSelection(:final department):
          await actions.sendToDepartment(department, body);
      }
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
      body: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(width: 340, child: _conversationPanel()),
          const SizedBox(width: 20),
          Expanded(child: _threadPanel()),
        ],
      ),
    );
  }

  // --- Left: conversation lists -----------------------------------------

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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _tabToggle(),
          const SizedBox(height: 14),
          Expanded(
            child: _tab == _ChatTab.guests ? _guestList() : _staffList(),
          ),
        ],
      ),
    );
  }

  Widget _tabToggle() {
    return Container(
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
        final selection = _selection;
        final selected =
            selection is _GuestSelection && selection.bookingId == c.bookingId;
        return ReceptionChatConversationTile(
          avatar: ReceptionChatAvatar(initials: c.initials),
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

  Widget _staffList() {
    final conversationsAsync = ref.watch(
      receptionStaffConversationsProvider(_token),
    );
    final conversations =
        conversationsAsync.value ?? const <ReceptionStaffConversation>[];

    if (conversationsAsync.isLoading && conversations.isEmpty) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.gold),
      );
    }

    return ListView.separated(
      padding: EdgeInsets.zero,
      itemCount: conversations.length,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (_, i) {
        final c = conversations[i];
        final selection = _selection;
        final selected =
            selection is _StaffSelection &&
            selection.department == c.department;
        return ReceptionChatConversationTile(
          avatar: ReceptionChatAvatar(
            icon: receptionChatDepartmentIcon(c.department),
          ),
          title: c.label,
          subtitle: 'Internal Channel',
          preview: c.lastMessage,
          timeLabel: c.lastMessageLabel,
          unreadCount: c.unreadCount,
          selected: selected,
          onTap: () => _selectDepartment(c),
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

  // --- Right: thread + composer -----------------------------------------

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
            'Select a guest or a department to start messaging.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  Widget _thread(_Selection selection) {
    final messagesAsync = switch (selection) {
      _GuestSelection(:final bookingId) => ref.watch(
        receptionGuestThreadProvider((_token, bookingId)),
      ),
      _StaffSelection(:final department) => ref.watch(
        receptionStaffThreadProvider((_token, department)),
      ),
    };
    final messages = messagesAsync.value ?? const <ReceptionChatMessage>[];
    _maybeScrollToBottom(messages.length);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _threadHeader(selection),
        Divider(
          height: 1,
          thickness: 0.8,
          color: Colors.white.withValues(alpha: 0.08),
        ),
        Expanded(
          child: _messages(
            messages,
            isStaffChannel: selection is _StaffSelection,
          ),
        ),
        Divider(
          height: 1,
          thickness: 0.8,
          color: Colors.white.withValues(alpha: 0.08),
        ),
        _composer(selection),
      ],
    );
  }

  Widget _threadHeader(_Selection selection) {
    final (avatar, title, subtitle) = switch (selection) {
      _GuestSelection(:final guestName, :final roomLabel) => (
        ReceptionChatAvatar(initials: _initialsOf(guestName)),
        guestName,
        roomLabel,
      ),
      _StaffSelection(:final department, :final label) => (
        ReceptionChatAvatar(icon: receptionChatDepartmentIcon(department)),
        label,
        'Internal Channel',
      ),
    };

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
      child: Row(
        children: [
          avatar,
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.45),
                    fontSize: 12,
                  ),
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

  Widget _messages(
    List<ReceptionChatMessage> messages, {
    required bool isStaffChannel,
  }) {
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
      itemBuilder: (_, i) => ReceptionChatBubble(
        message: messages[i],
        showSenderName: isStaffChannel,
      ),
    );
  }

  Widget _composer(_Selection selection) {
    final hint = switch (selection) {
      _GuestSelection(:final guestName) => 'Message $guestName...',
      _StaffSelection(:final label) => 'Message $label...',
    };

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
                cursorColor: AppColors.gold,
                style: AppTypography.style(color: Colors.white, fontSize: 14),
                decoration: InputDecoration(
                  isCollapsed: true,
                  border: InputBorder.none,
                  contentPadding: const EdgeInsets.symmetric(vertical: 13),
                  hintText: hint,
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
