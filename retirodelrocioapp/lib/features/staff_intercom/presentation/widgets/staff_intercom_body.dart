import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/intercom_call/data/intercom_call_repository.dart';
import 'package:retirodelrocioapp/features/staff_chat/application/staff_chat_providers.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_channel.dart';
import 'package:retirodelrocioapp/features/staff_chat/presentation/widgets/staff_chat_widgets.dart';
import 'package:retirodelrocioapp/features/staff_intercom/application/staff_intercom_call_providers.dart';

/// The reusable body of every non-Reception station's Intercom screen — a
/// directory of the other stations, each with a real, ringing "Call"
/// (Figma 335:4684 / 335:5021, adapted for staff-to-staff). Embedded by
/// each tablet's own Intercom screen inside its own scaffold, the same
/// pattern `StaffChatBody` already follows for Chat. Reuses
/// `staffChatChannelsProvider` purely for the directory list (label +
/// real online status per station) — placing/receiving the call itself
/// goes through `staffIntercomCallProvider`, a completely separate signal
/// from chat.
class StaffIntercomBody extends ConsumerStatefulWidget {
  const StaffIntercomBody({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<StaffIntercomBody> createState() => _StaffIntercomBodyState();
}

class _StaffIntercomBodyState extends ConsumerState<StaffIntercomBody> {
  bool _placingCall = false;

  String get _token => widget.session.token;
  int get _myUserId => widget.session.userId;

  Future<void> _call(int userId) async {
    if (_placingCall) return;
    setState(() => _placingCall = true);
    try {
      await ref
          .read(staffIntercomCallProvider((_token, _myUserId)).notifier)
          .place(userId);
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
    final channelsAsync = ref.watch(staffChatChannelsProvider(_token));
    final channels = channelsAsync.value ?? const <StaffChannel>[];

    if (channelsAsync.isLoading && channels.isEmpty) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.gold),
      );
    }
    if (channels.isEmpty) {
      return _emptyState();
    }

    return GridView.builder(
      padding: EdgeInsets.zero,
      itemCount: channels.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: 16,
        crossAxisSpacing: 16,
        childAspectRatio: 1.5,
      ),
      itemBuilder: (_, i) => _card(channels[i]),
    );
  }

  Widget _card(StaffChannel channel) {
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
          StaffChatAvatar(role: channel.role, online: channel.online),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  channel.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  channel.roleLabel,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 11,
                  ),
                ),
                const SizedBox(height: 3),
                StaffOnlineStatus(online: channel.online),
              ],
            ),
          ),
          const SizedBox(width: 8),
          _callButton(channel),
        ],
      ),
    );
  }

  Widget _callButton(StaffChannel channel) {
    final online = channel.online;
    final color = online
        ? staffChatOnlineGreen
        : Colors.white.withValues(alpha: 0.3);

    return Material(
      color: color.withValues(alpha: online ? 0.13 : 0.06),
      shape: const CircleBorder(),
      child: InkWell(
        onTap: (online && !_placingCall) ? () => _call(channel.userId) : null,
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

  Widget _emptyState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.groups_rounded,
            size: 30,
            color: Colors.white.withValues(alpha: 0.25),
          ),
          const SizedBox(height: 12),
          Text(
            'No other stations to call right now.',
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
