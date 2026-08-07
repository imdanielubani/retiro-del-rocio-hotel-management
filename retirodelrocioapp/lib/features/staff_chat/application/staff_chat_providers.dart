import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/named_typing_channel.dart';
import 'package:retirodelrocioapp/features/staff_chat/data/staff_chat_repository.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_channel.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_chat_message.dart';

final staffChatRepositoryProvider = Provider<StaffChatRepository>(
  (ref) => StaffChatRepository(),
);

/// Every other station's channel, keyed by this staffer's own token.
/// Re-polled every 10 seconds — brisk enough that a new message shows up on
/// the list without needing to open the thread to find it.
final staffChatChannelsProvider =
    FutureProvider.family<List<StaffChannel>, String>((ref, token) async {
      final repo = ref.watch(staffChatRepositoryProvider);

      final timer = Timer(const Duration(seconds: 10), ref.invalidateSelf);
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
