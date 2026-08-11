import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_chat_message.dart';

/// Per-role icon — every station a staff Chat screen can reach.
IconData staffChatRoleIcon(String role) => switch (role) {
  'reception' => Icons.support_agent_rounded,
  'housekeeping' => Icons.cleaning_services_rounded,
  'maintenance' => Icons.build_rounded,
  'security' => Icons.shield_rounded,
  'bar' => Icons.local_bar_rounded,
  'admin' => Icons.admin_panel_settings_rounded,
  _ => Icons.groups_rounded,
};

/// Presence green, shared by every online dot/label in this module.
const Color staffChatOnlineGreen = Color(0xFF34D399);

/// A round icon badge, shared by every avatar in this screen — a role's
/// icon on a gold-tinted circle, with a presence dot at the corner.
class StaffChatAvatar extends StatelessWidget {
  const StaffChatAvatar({
    super.key,
    required this.role,
    this.size = 46,
    this.online = false,
  });

  final String role;
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
            staffChatRoleIcon(role),
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
                  ? staffChatOnlineGreen
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

/// A dot + "Online"/"Offline" label, used in the thread header.
class StaffOnlineStatus extends StatelessWidget {
  const StaffOnlineStatus({super.key, required this.online});

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
                ? staffChatOnlineGreen
                : Colors.white.withValues(alpha: 0.3),
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: 6),
        Text(
          online ? 'Online' : 'Offline',
          style: AppTypography.style(
            color: online
                ? staffChatOnlineGreen
                : Colors.white.withValues(alpha: 0.4),
            fontSize: 12,
          ),
        ),
      ],
    );
  }
}

/// "X is typing…", shown just above the composer while true.
class StaffTypingIndicator extends StatelessWidget {
  const StaffTypingIndicator({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
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
            label,
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

/// One row in the conversation list — another station, tapped to open its
/// thread: avatar, label, last-message preview, a time label, unread badge.
class StaffChatConversationTile extends StatelessWidget {
  const StaffChatConversationTile({
    super.key,
    required this.role,
    required this.label,
    required this.online,
    required this.preview,
    required this.timeLabel,
    required this.unreadCount,
    required this.selected,
    required this.onTap,
  });

  final String role;
  final String label;
  final bool online;
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
              StaffChatAvatar(role: role, online: online),
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
                            label,
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
                    StaffOnlineStatus(online: online),
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

/// One bubble in the thread — gold and right-aligned for this station's own
/// messages, translucent and left-aligned for the other side's.
class StaffChatBubble extends StatelessWidget {
  const StaffChatBubble({super.key, required this.message});

  final StaffChatMessage message;

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
