import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/reception/chat/domain/reception_chat_message.dart';

/// Per-department icon + accent for the internal channels list — the same
/// three departments reception's own alerts already colour-code.
IconData receptionChatDepartmentIcon(String department) => switch (department) {
  'housekeeping' => Icons.cleaning_services_rounded,
  'maintenance' => Icons.build_rounded,
  'security' => Icons.shield_rounded,
  _ => Icons.groups_rounded,
};

/// A round icon badge, shared by every avatar in this screen — a guest's
/// initials, or a department's icon, on a gold-tinted circle.
class ReceptionChatAvatar extends StatelessWidget {
  const ReceptionChatAvatar({
    super.key,
    this.initials,
    this.icon,
    this.size = 46,
  });

  final String? initials;
  final IconData? icon;
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
      child: initials != null
          ? Text(
              initials!,
              style: AppTypography.style(
                color: AppColors.gold,
                fontSize: size * 0.34,
                fontWeight: FontWeight.w700,
              ),
            )
          : Icon(icon, size: size * 0.42, color: AppColors.gold),
    );
  }
}

/// One row in either conversation list — a guest's Concierge Chat thread, or
/// a department's internal channel. Same shape either way: avatar, title,
/// last-message preview, a time label and an unread badge.
class ReceptionChatConversationTile extends StatelessWidget {
  const ReceptionChatConversationTile({
    super.key,
    required this.avatar,
    required this.title,
    required this.subtitle,
    required this.preview,
    required this.timeLabel,
    required this.unreadCount,
    required this.selected,
    required this.onTap,
  });

  final Widget avatar;
  final String title;
  final String subtitle;
  final String? preview;
  final String? timeLabel;
  final int unreadCount;
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
              avatar,
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
                            title,
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
                      subtitle,
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
                                : 'No messages yet',
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

/// One bubble in the thread — gold and right-aligned for the front desk's own
/// messages, translucent and left-aligned for the guest's or department's.
class ReceptionChatBubble extends StatelessWidget {
  const ReceptionChatBubble({
    super.key,
    required this.message,
    this.showSenderName = false,
  });

  final ReceptionChatMessage message;

  /// True in a staff department channel, where more than one role can post
  /// on the "not mine" side, so the sender's name disambiguates it.
  final bool showSenderName;

  @override
  Widget build(BuildContext context) {
    final mine = message.isMine;

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 460),
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
              if (showSenderName &&
                  !mine &&
                  (message.senderName ?? '').isNotEmpty) ...[
                Text(
                  message.senderName!,
                  style: AppTypography.style(
                    color: AppColors.gold,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
              ],
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
