import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/chat/domain/chat_message.dart';

/// Front desk's presence green — the same colour reception's own chat screen
/// uses for its online dots, so a guest and a receptionist read the same
/// "someone is here" signal.
const Color chatOnlineGreen = Color(0xFF34D399);

/// The Reception avatar every surface in this screen shares — a gold-tinted
/// circle around a concierge-bell icon (matching the plain [IconData] badges
/// used everywhere else in the guest app, e.g. the Service Request category
/// cards, rather than an emoji glyph), with a presence dot at the corner.
class ChatAvatar extends StatelessWidget {
  const ChatAvatar({super.key, this.size = 46, this.online = false});

  final double size;
  final bool online;

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Container(
          width: size,
          height: size,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.gold.withValues(alpha: 0.1),
            shape: BoxShape.circle,
            border: Border.all(
              color: AppColors.gold.withValues(alpha: 0.45),
              width: 0.8,
            ),
          ),
          child: Icon(
            Icons.room_service_rounded,
            size: size * 0.42,
            color: AppColors.gold,
          ),
        ),
        Positioned(
          right: -1,
          bottom: -1,
          child: Container(
            width: size * 0.28,
            height: size * 0.28,
            decoration: BoxDecoration(
              color: online
                  ? chatOnlineGreen
                  : Colors.white.withValues(alpha: 0.25),
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.background, width: 2),
            ),
          ),
        ),
      ],
    );
  }
}

/// A dot + "Online"/"Offline" label — the same status row reception's chat
/// screen shows for a guest or a department.
class ChatOnlineStatus extends StatelessWidget {
  const ChatOnlineStatus({super.key, required this.online});

  final bool online;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 6,
          height: 6,
          decoration: BoxDecoration(
            color: online
                ? chatOnlineGreen
                : Colors.white.withValues(alpha: 0.3),
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: 7),
        Text(
          online ? 'Online' : 'Offline',
          style: AppTypography.style(
            color: online
                ? chatOnlineGreen
                : Colors.white.withValues(alpha: 0.4),
            fontSize: 12,
          ),
        ),
      ],
    );
  }
}

/// The single conversation in the left-hand list — Reception, tapped to open
/// the thread on the right. Same bordered-tile shape (title + time row,
/// subtitle row, preview + unread row) as `ReceptionChatConversationTile`
/// uses for a guest's row on reception's own Chat screen, so a guest and a
/// receptionist look at the same design either side of the conversation.
class ChatConversationTile extends StatelessWidget {
  const ChatConversationTile({
    super.key,
    required this.online,
    required this.unreadCount,
    required this.preview,
    required this.timeLabel,
    required this.selected,
    required this.onTap,
  });

  final bool online;
  final int unreadCount;
  final String? preview;
  final String? timeLabel;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.1)
          : Colors.transparent,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.35)
                  : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ChatAvatar(online: online),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Reception',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.style(
                              color: Colors.white,
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                        if ((timeLabel ?? '').isNotEmpty) ...[
                          const SizedBox(width: 6),
                          Text(
                            timeLabel!,
                            style: AppTypography.style(
                              color: Colors.white.withValues(alpha: 0.35),
                              fontSize: 10,
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Front Desk',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: AppColors.gold.withValues(alpha: 0.7),
                        fontSize: 11,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            (preview ?? '').isNotEmpty
                                ? preview!
                                : 'Say hello to the front desk',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.style(
                              color: Colors.white.withValues(
                                alpha: (preview ?? '').isNotEmpty ? 0.5 : 0.3,
                              ),
                              fontSize: 12,
                            ),
                          ),
                        ),
                        if (unreadCount > 0) ...[
                          const SizedBox(width: 8),
                          _unreadBadge(),
                        ],
                      ],
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

  Widget _unreadBadge() {
    return Container(
      constraints: const BoxConstraints(minWidth: 18),
      height: 18,
      padding: const EdgeInsets.symmetric(horizontal: 5),
      alignment: Alignment.center,
      decoration: const BoxDecoration(
        color: AppColors.gold,
        shape: BoxShape.circle,
      ),
      child: Text(
        unreadCount > 9 ? '9+' : '$unreadCount',
        style: AppTypography.style(
          color: Colors.black,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

/// "Reception is typing…", shown just above the composer while true.
class ChatTypingIndicator extends StatelessWidget {
  const ChatTypingIndicator({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 0, 24, 8),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: 14,
            height: 14,
            child: CircularProgressIndicator(
              strokeWidth: 1.6,
              color: AppColors.gold.withValues(alpha: 0.7),
            ),
          ),
          const SizedBox(width: 8),
          Text(
            'Reception is typing…',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.45),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

/// One bubble in the thread (Figma 394:902 / 394:908) — gold and
/// right-aligned for the guest's own messages, translucent and left-aligned
/// for the front desk's replies. The sharp corner always sits on the side
/// closest to its sender, the classic chat-bubble "tail".
class ChatBubble extends StatelessWidget {
  const ChatBubble({super.key, required this.message});

  final ChatMessage message;

  @override
  Widget build(BuildContext context) {
    final mine = message.isMine;

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 458),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          decoration: BoxDecoration(
            color: mine ? AppColors.gold : Colors.white.withValues(alpha: 0.09),
            border: mine
                ? null
                : Border.all(
                    color: Colors.white.withValues(alpha: 0.1),
                    width: 0.8,
                  ),
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(16),
              topRight: const Radius.circular(16),
              bottomLeft: Radius.circular(mine ? 16 : 4),
              bottomRight: Radius.circular(mine ? 4 : 16),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                message.body,
                style: AppTypography.style(
                  color: mine
                      ? const Color(0xFF0A0F1E)
                      : Colors.white.withValues(alpha: 0.9),
                  fontSize: 13,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                message.timeLabel,
                style: AppTypography.style(
                  color: mine
                      ? Colors.black.withValues(alpha: 0.5)
                      : Colors.white.withValues(alpha: 0.3),
                  fontSize: 10,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
