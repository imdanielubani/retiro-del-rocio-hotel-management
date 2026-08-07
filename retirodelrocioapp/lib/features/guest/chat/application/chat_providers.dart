import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/guest/chat/data/chat_repository.dart';
import 'package:retirodelrocioapp/features/guest/chat/domain/chat_message.dart';

final chatRepositoryProvider = Provider<ChatRepository>(
  (ref) => ChatRepository(),
);

/// This stay's Concierge Chat thread, keyed by the device token. Re-polled
/// every 6 seconds while the screen is open — brisk enough to feel like a
/// live conversation without needing its own socket channel.
final guestChatThreadProvider =
    FutureProvider.family<
      ({List<ChatMessage> messages, int unreadCount, bool receptionOnline}),
      String
    >((ref, deviceToken) async {
      final repo = ref.watch(chatRepositoryProvider);

      final timer = Timer(const Duration(seconds: 6), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.list(deviceToken);
    });

/// True while reception is (recently) typing a reply — flipped on by the
/// realtime `typing` signal on the room's channel (see
/// `roomRealtimeProvider`) and auto-cleared a few seconds after the last
/// one, in case the "stopped typing" edge is ever missed. Not keyed by
/// device token: a guest tablet only ever has the one device for its whole
/// session, so a single (non-family) notifier is enough — same shape as
/// `AuthController`.
class GuestReceptionTypingNotifier extends Notifier<bool> {
  Timer? _timeout;

  @override
  bool build() {
    ref.onDispose(() => _timeout?.cancel());
    return false;
  }

  void staffIsTyping() {
    state = true;
    _timeout?.cancel();
    _timeout = Timer(const Duration(seconds: 5), () => state = false);
  }
}

final guestReceptionTypingProvider =
    NotifierProvider<GuestReceptionTypingNotifier, bool>(
      GuestReceptionTypingNotifier.new,
    );

class ChatActions {
  const ChatActions(this._ref, this._deviceToken);

  final Ref _ref;
  final String _deviceToken;

  Future<ChatMessage> send(String body) async {
    final message = await _ref
        .read(chatRepositoryProvider)
        .send(_deviceToken, body);
    _ref.invalidate(guestChatThreadProvider(_deviceToken));
    return message;
  }

  Future<void> sendTyping() =>
      _ref.read(chatRepositoryProvider).sendTyping(_deviceToken);
}

final chatActionsProvider = Provider.family<ChatActions, String>(
  (ref, deviceToken) => ChatActions(ref, deviceToken),
);
