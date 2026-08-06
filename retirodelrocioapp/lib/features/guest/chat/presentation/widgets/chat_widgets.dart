import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/chat/domain/chat_message.dart';

/// Front desk's presence green (Figma 394:859) — distinct from
/// [AppColors.success], which is used for confirmation states elsewhere.
const Color chatOnlineGreen = Color(0xFF34D399);

/// The Reception avatar every surface in this screen shares — a gold-tinted
/// circle around a concierge-bell icon, matching the plain [IconData] badges
/// used everywhere else in the guest app (e.g. the Service Request category
/// cards) rather than an emoji glyph.
class ChatAvatar extends StatelessWidget {
  const ChatAvatar({super.key, this.size = 56});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
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
    );
  }
}

/// The single conversation in the left-hand list (Figma 394:866) — Reception,
/// with a live online dot and an unread badge when there's something new.
class ChatConversationCard extends StatelessWidget {
  const ChatConversationCard({super.key, required this.unreadCount});

  final int unreadCount;

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(100),
          ),
          child: Row(
            children: [
              const ChatAvatar(),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
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
                          style: AppTypography.style(
                            color: Colors.white.withValues(alpha: 0.4),
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        // Floats over the card's top-right corner, the same overlapping
        // treatment as an unread badge on a chat-app avatar (Figma 394:871).
        if (unreadCount > 0)
          Positioned(top: -4, right: -4, child: _unreadBadge()),
      ],
    );
  }

  Widget _unreadBadge() {
    return Container(
      width: 18,
      height: 18,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.gold,
        shape: BoxShape.circle,
        border: Border.all(color: AppColors.background, width: 2),
      ),
      child: Text(
        unreadCount > 9 ? '9+' : '$unreadCount',
        style: AppTypography.style(
          color: Colors.black,
          fontSize: 8,
          fontWeight: FontWeight.w800,
        ),
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
