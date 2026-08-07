import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/audio/notification_chime.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/named_typing_channel.dart';
import 'package:retirodelrocioapp/core/realtime/staff_chat_inbox_channel.dart';
import 'package:retirodelrocioapp/features/staff_chat/data/staff_chat_repository.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_channel.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_chat_message.dart';
import 'package:retirodelrocioapp/features/staff_chat/presentation/widgets/staff_chat_toast.dart';

final staffChatRepositoryProvider = Provider<StaffChatRepository>(
  (ref) => StaffChatRepository(),
);

/// Every other station's channel, keyed by this staffer's own token.
/// Re-polled every 10 seconds — brisk enough that a new message shows up on
/// the list without needing to open the thread to find it.
final staffChatChannelsProvider =
    FutureProvider.family<List<StaffChannel>, String>((ref, token) async {
      final repo = ref.watch(staffChatRepositoryProvider);

      final timer = Timer(const Duration(seconds: 1), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.channels(token);
    });

/// One channel's thread, keyed by (token, otherRole). Re-polled every 6
/// seconds while the thread is open.
final staffChatThreadProvider =
    FutureProvider.family<List<StaffChatMessage>, (String token, String role)>((
      ref,
      key,
    ) async {
      final repo = ref.watch(staffChatRepositoryProvider);

      final timer = Timer(const Duration(seconds: 6), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.messages(key.$1, key.$2);
    });

/// Rings the chime and toasts any channel whose unread count just went up —
/// watched from every station's scaffold (and its dashboard, which uses its
/// own inline layout rather than the scaffold) so a staffer hears a new
/// staff-chat message no matter which screen they're on, the same pattern
/// `housekeepingNotificationChimeProvider` uses for department notifications.
/// Reacts to [staffChatChannelsProvider] refreshing by any means — the
/// realtime `staff-chat-inbox` socket (see [staffChatRealtimeProvider])
/// invalidates it the instant a message actually lands, but this listener
/// reacts to the resulting refresh either way, including the plain poll, so
/// a staffer still hears the chime even when the socket is down.
final staffChatChimeProvider = Provider.family<void, String>((ref, token) {
  final chime = NotificationChime();
  ref.onDispose(chime.dispose);

  Map<String, int>? previousUnread;

  ref.listen<AsyncValue<List<StaffChannel>>>(staffChatChannelsProvider(token), (
    previous,
    next,
  ) {
    final channels = next.value;
    if (channels == null) return;

    final unread = {for (final c in channels) c.role: c.unreadCount};
    final known = previousUnread;
    if (known != null) {
      final arrived = channels.where(
        (c) => c.unreadCount > (known[c.role] ?? 0),
      );
      if (arrived.isNotEmpty) {
        unawaited(chime.play());
        for (final c in arrived) {
          showStaffChatToast(c);
        }
      }
    }
    previousUnread = unread;
  }, fireImmediately: true);
});

/// Subscribes to this station's own Staff Chat inbox (`staff-chat-inbox.
/// {myRole}`) and refreshes the instant another station's message actually
/// lands — no more waiting on [staffChatChannelsProvider]'s poll tick.
/// Refreshes the channel list unconditionally, and the sender's own thread
/// too so a conversation that's already open updates immediately rather
/// than up to 6 seconds later. [staffChatChimeProvider] reacts to whichever
/// of those refreshes actually changes something, so the chime/toast fire
/// off this the same way they already do off the poll — pure accelerator,
/// not a replacement: if no broadcaster is configured, or the socket drops,
/// the periodic polls still carry every screen. Watched from the same
/// mount points as [staffChatChimeProvider] so the subscription is live
/// wherever the user is in the module.
final staffChatRealtimeProvider =
    FutureProvider.family<void, (String token, String myRole)>((
      ref,
      key,
    ) async {
      final (token, myRole) = key;

      final config = (await ref.watch(appConfigProvider.future)).realtime;
      if (config == null) {
        debugPrint(
          'staffChatRealtime: no broadcaster configured — polling only.',
        );
        return;
      }

      final channel = StaffChatInboxChannel(
        config: config,
        channel: 'staff-chat-inbox.$myRole',
      );
      channel.connect(
        onMessage: (from) {
          ref.invalidate(staffChatChannelsProvider(token));
          ref.invalidate(staffChatThreadProvider((token, from)));
        },
      );

      ref.onDispose(channel.dispose);
    });

class StaffChatActions {
  const StaffChatActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<StaffChatMessage> send(String role, String body) async {
    final message = await _ref
        .read(staffChatRepositoryProvider)
        .send(_token, role, body);
    _ref.invalidate(staffChatThreadProvider((_token, role)));
    _ref.invalidate(staffChatChannelsProvider(_token));
    return message;
  }

  Future<void> sendTyping(String role) =>
      _ref.read(staffChatRepositoryProvider).sendTyping(_token, role);
}

final staffChatActionsProvider = Provider.family<StaffChatActions, String>(
  (ref, token) => StaffChatActions(ref, token),
);

/// True while the other station in ([myRole], [otherRole])'s channel is
/// (recently) typing — opens a transient realtime subscription to that
/// channel for as long as the thread panel is showing it, auto-clearing a
/// few seconds after the last signal.
class StaffChatTypingNotifier extends Notifier<bool> {
  StaffChatTypingNotifier(this.myRole, this.otherRole);

  final String myRole;
  final String otherRole;

  NamedTypingChannel? _channel;
  Timer? _timeout;

  String get _channelKey {
    final pair = [myRole, otherRole]..sort();
    return pair.join('_');
  }

  @override
  bool build() {
    ref.onDispose(() {
      _timeout?.cancel();
      _channel?.dispose();
    });
    unawaited(_connect());
    return false;
  }

  Future<void> _connect() async {
    final config = (await ref.read(appConfigProvider.future)).realtime;
    if (config == null) return;

    final channel = NamedTypingChannel(
      config: config,
      channel: 'staff-chat.$_channelKey',
    );
    _channel = channel;
    channel.connect(
      onTyping: (from) {
        // Our own typing signal echoes back on this same channel; only the
        // other side's is worth showing here.
        if (from != otherRole) return;

        state = true;
        _timeout?.cancel();
        _timeout = Timer(const Duration(seconds: 5), () => state = false);
      },
    );
  }
}

final staffChatTypingProvider =
    NotifierProvider.family<
      StaffChatTypingNotifier,
      bool,
      (String myRole, String otherRole)
    >((arg) => StaffChatTypingNotifier(arg.$1, arg.$2));
