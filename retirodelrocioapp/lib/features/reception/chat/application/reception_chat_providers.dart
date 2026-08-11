import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/room_channel.dart';
import 'package:retirodelrocioapp/features/reception/chat/data/reception_chat_repository.dart';
import 'package:retirodelrocioapp/features/reception/chat/domain/reception_chat_message.dart';
import 'package:retirodelrocioapp/features/reception/chat/domain/reception_conversation.dart';

final receptionChatRepositoryProvider = Provider<ReceptionChatRepository>(
  (ref) => ReceptionChatRepository(),
);

/// Every in-house guest's conversation, keyed by the receptionist's token.
/// Re-polled every 10 seconds — brisk enough that a new guest message shows
/// up on the list without the desk needing to open the thread to find it.
final receptionGuestConversationsProvider =
    FutureProvider.family<List<ReceptionGuestConversation>, String>((
      ref,
      token,
    ) async {
      final repo = ref.watch(receptionChatRepositoryProvider);

      final timer = Timer(const Duration(seconds: 10), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.guestConversations(token);
    });

/// Every department's channel, keyed by the receptionist's token.
final receptionStaffConversationsProvider =
    FutureProvider.family<List<ReceptionStaffConversation>, String>((
      ref,
      token,
    ) async {
      final repo = ref.watch(receptionChatRepositoryProvider);

      final timer = Timer(const Duration(seconds: 10), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.staffConversations(token);
    });

/// One guest's thread, keyed by (token, bookingId). Re-polled every 6 seconds
/// while the thread is open — the same cadence the guest tablet's own Chat
/// screen uses.
final receptionGuestThreadProvider =
    FutureProvider.family<
      List<ReceptionChatMessage>,
      (String token, int bookingId)
    >((ref, key) async {
      final repo = ref.watch(receptionChatRepositoryProvider);

      final timer = Timer(const Duration(seconds: 6), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.guestMessages(key.$1, key.$2);
    });

/// One department's thread, keyed by (token, department).
final receptionStaffThreadProvider =
    FutureProvider.family<
      List<ReceptionChatMessage>,
      (String token, String department)
    >((ref, key) async {
      final repo = ref.watch(receptionChatRepositoryProvider);

      final timer = Timer(const Duration(seconds: 6), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.staffMessages(key.$1, key.$2);
    });

class ReceptionChatActions {
  const ReceptionChatActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<ReceptionChatMessage> sendToGuest(int bookingId, String body) async {
    final message = await _ref
        .read(receptionChatRepositoryProvider)
        .sendToGuest(_token, bookingId, body);
    _ref.invalidate(receptionGuestThreadProvider((_token, bookingId)));
    _ref.invalidate(receptionGuestConversationsProvider(_token));
    return message;
  }

  Future<ReceptionChatMessage> sendToDepartment(
    String department,
    String body,
  ) async {
    final message = await _ref
        .read(receptionChatRepositoryProvider)
        .sendToDepartment(_token, department, body);
    _ref.invalidate(receptionStaffThreadProvider((_token, department)));
    _ref.invalidate(receptionStaffConversationsProvider(_token));
    return message;
  }

  Future<void> sendTypingToGuest(int bookingId) => _ref
      .read(receptionChatRepositoryProvider)
      .sendTypingToGuest(_token, bookingId);
}

final receptionChatActionsProvider =
    Provider.family<ReceptionChatActions, String>(
      (ref, token) => ReceptionChatActions(ref, token),
    );

/// True while the guest in [roomUnitId]'s room is (recently) typing —
/// opens a transient realtime subscription to that room's channel for as
/// long as reception's thread panel is showing that conversation, and closes
/// it the moment the desk switches away (a plain — not autoDispose-suppressed
/// — family provider in Riverpod 3 already tears down once nothing watches
/// it). Auto-clears a few seconds after the last signal, in case the
/// "stopped typing" edge is ever missed.
class ReceptionGuestTypingNotifier extends Notifier<bool> {
  ReceptionGuestTypingNotifier(this.roomUnitId);

  final int roomUnitId;

  RoomChannel? _channel;
  Timer? _timeout;

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

    final channel = RoomChannel(config: config, roomUnitId: roomUnitId);
    _channel = channel;
    channel.connect(
      onChanged: () {},
      onTyping: (from) {
        // The desk's own typing signal echoes back on this same channel;
        // only the guest's is worth showing here.
        if (from != 'guest') return;

        state = true;
        _timeout?.cancel();
        _timeout = Timer(const Duration(seconds: 5), () => state = false);
      },
    );
  }
}

final receptionGuestTypingProvider =
    NotifierProvider.family<ReceptionGuestTypingNotifier, bool, int>(
      ReceptionGuestTypingNotifier.new,
    );
