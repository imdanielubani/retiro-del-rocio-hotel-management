import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/staff_chat/application/staff_chat_providers.dart';
import 'package:retirodelrocioapp/features/staff_chat/data/staff_chat_repository.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_channel.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_chat_message.dart';
import 'package:retirodelrocioapp/features/staff_chat/presentation/widgets/staff_chat_widgets.dart';

/// The Chat screen's whole content — a conversation list on the left and the
/// selected channel's thread + composer on the right — shared by every
/// station's tablet (Reception, Housekeeping, Maintenance, Security). Each
/// station's own screen just embeds this inside its own scaffold shell.
class StaffChatBody extends ConsumerStatefulWidget {
  const StaffChatBody({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<StaffChatBody> createState() => _StaffChatBodyState();
}

class _StaffChatBodyState extends ConsumerState<StaffChatBody> {
  /// The height the two-pane body stops shrinking at and starts scrolling
  /// instead — same rule every other tablet screen in the app uses, so the
  /// composer never overflows when the keyboard eats into the available height.
  static const double _minBodyHeight = 380;

  final _controller = TextEditingController();
  final _scrollController = ScrollController();

  StaffChannel? _selected;
  bool _sending = false;
  int _lastMessageCount = 0;
  DateTime? _lastTypingSentAt;

  String get _token => widget.session.token;

  int get _myUserId => widget.session.userId;

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _select(StaffChannel c) {
    setState(() {
      _selected = c;
      _lastMessageCount = 0;
    });
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    final selected = _selected;
    if (body.isEmpty || _sending || selected == null) return;

    setState(() => _sending = true);
    try {
      await ref
          .read(staffChatActionsProvider(_token))
          .send(selected.userId, body);
      _controller.clear();
    } on StaffChatException catch (error) {
      _toast(error.message, error: true);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  /// Debounced "I'm typing" signal — fires at most once every 2.5 seconds
  /// while actively typing.
  void _onComposerChanged(String text) {
    final selected = _selected;
    if (text.trim().isEmpty || selected == null) return;

    final now = DateTime.now();
    if (_lastTypingSentAt != null &&
        now.difference(_lastTypingSentAt!) <
            const Duration(milliseconds: 2500)) {
      return;
    }
    _lastTypingSentAt = now;
    unawaited(
      ref.read(staffChatActionsProvider(_token)).sendTyping(selected.userId),
    );
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

  // --- Left: conversation list -------------------------------------------

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
      child: _channelList(),
    );
  }

  Widget _channelList() {
    final channelsAsync = ref.watch(staffChatChannelsProvider(_token));
    final channels = channelsAsync.value ?? const <StaffChannel>[];

    if (channelsAsync.isLoading && channels.isEmpty) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.gold),
      );
    }

    return ListView.separated(
      padding: EdgeInsets.zero,
      itemCount: channels.length,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (_, i) {
        final c = channels[i];
        return StaffChatConversationTile(
          role: c.role,
          label: c.name,
          roleLabel: c.roleLabel,
          online: c.online,
          preview: c.lastMessage,
          timeLabel: c.lastMessageLabel,
          unreadCount: c.unreadCount,
          selected: _selected?.userId == c.userId,
          onTap: () => _select(c),
        );
      },
    );
  }

  // --- Right: thread + composer -------------------------------------------

  Widget _threadPanel() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.03),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.06),
          width: 0.8,
        ),
      ),
      child: _selected == null ? _noSelection() : _thread(_selected!),
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
            'Select a station to start messaging.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  Widget _thread(StaffChannel selected) {
    final messagesAsync = ref.watch(
      staffChatThreadProvider((_token, selected.userId)),
    );
    final messages = messagesAsync.value ?? const <StaffChatMessage>[];
    _maybeScrollToBottom(messages.length);

    final typing = ref.watch(
      staffChatTypingProvider((_myUserId, selected.userId)),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _threadHeader(selected),
        _divider(),
        Expanded(child: _messages(messages)),
        if (typing) StaffTypingIndicator(label: '${selected.name} is typing…'),
        _divider(),
        _composer(selected),
      ],
    );
  }

  Widget _threadHeader(StaffChannel selected) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
      child: Row(
        children: [
          StaffChatAvatar(role: selected.role, online: selected.online),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  selected.name,
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
                  children: [
                    Text(
                      '${selected.roleLabel} · ',
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.4),
                        fontSize: 12,
                      ),
                    ),
                    StaffOnlineStatus(online: selected.online),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _divider() => Divider(
    height: 1,
    thickness: 0.8,
    color: Colors.white.withValues(alpha: 0.08),
  );

  Widget _messages(List<StaffChatMessage> messages) {
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
      itemBuilder: (_, i) => StaffChatBubble(message: messages[i]),
    );
  }

  Widget _composer(StaffChannel selected) {
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
                  hintText: 'Message ${selected.name}...',
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
