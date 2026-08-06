import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/chat/application/chat_providers.dart';
import 'package:retirodelrocioapp/features/guest/chat/data/chat_repository.dart';
import 'package:retirodelrocioapp/features/guest/chat/domain/chat_message.dart';
import 'package:retirodelrocioapp/features/guest/chat/presentation/widgets/chat_widgets.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/glass_panel.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// Concierge Chat (Figma 394:19) — the guest's messaging thread with the
/// front desk: a single "Reception" conversation on the left, the thread and
/// composer on the right, inside the same frosted shell every guest screen
/// shares.
class GuestChatScreen extends ConsumerStatefulWidget {
  const GuestChatScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<GuestChatScreen> createState() => _GuestChatScreenState();
}

class _GuestChatScreenState extends ConsumerState<GuestChatScreen> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  bool _sending = false;
  int _lastMessageCount = 0;

  String get _token => widget.device.token;

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GuestNotificationScreen(
          device: widget.device,
          status: widget.status,
        ),
      ),
    );
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    if (body.isEmpty || _sending) return;

    setState(() => _sending = true);
    try {
      await ref.read(chatActionsProvider(_token)).send(body);
      _controller.clear();
    } on ChatException catch (error) {
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

  /// Keeps the thread pinned to the newest message — on first load, and again
  /// whenever a message arrives, whether it's the guest's own send or a
  /// staff reply landing on the next poll.
  void _maybeScrollToBottom(int messageCount) {
    if (messageCount == _lastMessageCount) return;
    _lastMessageCount = messageCount;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final threadAsync = ref.watch(guestChatThreadProvider(_token));
    final weather = ref.watch(weatherProvider).value;
    final unreadNotifications = ref.watch(
      guestUnreadNotificationsProvider(_token),
    );

    final thread = threadAsync.value;
    if (thread != null) _maybeScrollToBottom(thread.messages.length);

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
          const ColoredBox(color: Color(0xF2000000)),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(25, 24, 25, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GuestTopBar(
                    suiteName: widget.status.suiteName ?? 'Suite',
                    roomNumber:
                        widget.status.roomNumber ??
                        widget.device.roomNumber ??
                        '—',
                    guestName: widget.status.guest?.name ?? 'Guest',
                    weather: weather,
                    onNotifications: _openNotifications,
                    onProfile: () {},
                    hasUnreadNotifications: unreadNotifications > 0,
                  ),
                  const SizedBox(height: 22),
                  _headerRow(),
                  const SizedBox(height: 20),
                  Expanded(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Expanded(
                          flex: 34,
                          child: _conversationList(thread?.unreadCount ?? 0),
                        ),
                        const SizedBox(width: 20),
                        Expanded(flex: 66, child: _thread(thread)),
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

  // --- Header ---------------------------------------------------------

  Widget _headerRow() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        _backButton(),
        const SizedBox(width: 15),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Concierge Chat',
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 36,
                fontWeight: FontWeight.w700,
                height: 1.15,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              'Message the team',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 15,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _backButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      shape: CircleBorder(
        side: BorderSide(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: InkWell(
        onTap: () => Navigator.of(context).maybePop(),
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 40,
          height: 40,
          child: Icon(
            Icons.arrow_back_rounded,
            size: 18,
            color: Colors.white.withValues(alpha: 0.8),
          ),
        ),
      ),
    );
  }

  // --- Left: conversation list -----------------------------------------

  Widget _conversationList(int unreadCount) {
    return GlassPanel(
      borderRadius: 24,
      opacity: 0.06,
      borderOpacity: 0.2,
      blur: 17,
      padding: const EdgeInsets.all(12),
      child: ChatConversationCard(unreadCount: unreadCount),
    );
  }

  // --- Right: thread + composer -----------------------------------------

  Widget _thread(({List<ChatMessage> messages, int unreadCount})? thread) {
    return GlassPanel(
      borderRadius: 24,
      opacity: 0.06,
      borderOpacity: 0.2,
      blur: 17,
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _threadHeader(),
          _divider(),
          Expanded(child: _messages(thread?.messages ?? const [])),
          _divider(),
          _composer(),
        ],
      ),
    );
  }

  Widget _threadHeader() {
    return const Padding(
      padding: EdgeInsets.fromLTRB(24, 20, 24, 20),
      child: Row(
        children: [ChatAvatar(), SizedBox(width: 14), _ThreadHeaderText()],
      ),
    );
  }

  Widget _divider() => Divider(
    height: 1,
    thickness: 0.8,
    color: Colors.white.withValues(alpha: 0.08),
  );

  Widget _messages(List<ChatMessage> messages) {
    if (messages.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.chat_bubble_outline_rounded,
              size: 28,
              color: Colors.white.withValues(alpha: 0.25),
            ),
            const SizedBox(height: 10),
            Text(
              'Say hello — the front desk usually replies within minutes.',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.35),
                fontSize: 13,
              ),
            ),
          ],
        ),
      );
    }

    return ListView.separated(
      controller: _scrollController,
      padding: const EdgeInsets.all(24),
      itemCount: messages.length,
      separatorBuilder: (_, _) => const SizedBox(height: 16),
      itemBuilder: (_, i) => ChatBubble(message: messages[i]),
    );
  }

  Widget _composer() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 16, 24, 20),
      child: Row(
        children: [
          Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.07),
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
                  contentPadding: const EdgeInsets.symmetric(vertical: 14),
                  hintText: 'Message Reception...',
                  hintStyle: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.5),
                    fontSize: 14,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          _roundButton(
            icon: Icons.send_rounded,
            background: AppColors.gold,
            iconColor: Colors.black,
            onTap: _send,
            busy: _sending,
          ),
        ],
      ),
    );
  }

  Widget _roundButton({
    required IconData icon,
    required Color background,
    required Color iconColor,
    required VoidCallback onTap,
    Color? borderColor,
    bool busy = false,
  }) {
    return Material(
      color: background,
      shape: CircleBorder(
        side: borderColor != null
            ? BorderSide(color: borderColor, width: 0.8)
            : BorderSide.none,
      ),
      child: InkWell(
        onTap: busy ? null : onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 44,
          height: 44,
          child: Center(
            child: busy
                ? SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: iconColor,
                    ),
                  )
                : Icon(icon, size: 18, color: iconColor),
          ),
        ),
      ),
    );
  }
}

/// "Reception" + the live online dot, split out so the thread header stays
/// `const`-constructible above.
class _ThreadHeaderText extends StatelessWidget {
  const _ThreadHeaderText();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Reception',
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 20,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 5),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 6,
              height: 6,
              decoration: const BoxDecoration(
                color: chatOnlineGreen,
                shape: BoxShape.circle,
              ),
            ),
            const SizedBox(width: 7),
            Text(
              'Online',
              style: AppTypography.style(color: chatOnlineGreen, fontSize: 12),
            ),
          ],
        ),
      ],
    );
  }
}
