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
      ({List<ChatMessage> messages, int unreadCount}),
      String
    >((ref, deviceToken) async {
      final repo = ref.watch(chatRepositoryProvider);

      final timer = Timer(const Duration(seconds: 6), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.list(deviceToken);
    });

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
}

final chatActionsProvider = Provider.family<ChatActions, String>(
  (ref, deviceToken) => ChatActions(ref, deviceToken),
);
