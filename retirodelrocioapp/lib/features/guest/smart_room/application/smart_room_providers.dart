import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/sos_channel.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/data/smart_room_repository.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/domain/smart_device.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/domain/smart_scene.dart';

final smartRoomRepositoryProvider = Provider<SmartRoomRepository>(
  (ref) => SmartRoomRepository(),
);

/// Every device in this tablet's room. Keyed by the [ProvisionedDevice]
/// itself — not the bare token string other guest features key on — because
/// the repository call needs the full device and the realtime listener below
/// needs `roomUnitId`; this mirrors the same choice
/// `activeSosRealtimeProvider` already makes in `sos_providers.dart`.
/// Polled every 20 seconds so the screen keeps advancing even if the
/// realtime socket never connects — the accelerator, not the source of
/// truth.
final smartDevicesProvider =
    FutureProvider.family<List<SmartDevice>, ProvisionedDevice>((
      ref,
      device,
    ) async {
      final repo = ref.watch(smartRoomRepositoryProvider);

      final timer = Timer(const Duration(seconds: 20), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.fetchDevices(device);
    });

/// This room's scenes — no poll backstop; the Room Scenes screen is a
/// short-lived list the guest opens right before tapping "Activate", so a
/// stale scene list (which rarely changes) isn't worth the traffic.
final smartScenesProvider =
    FutureProvider.family<List<SmartScene>, ProvisionedDevice>((
      ref,
      device,
    ) async {
      final repo = ref.watch(smartRoomRepositoryProvider);
      return repo.fetchScenes(device);
    });

/// Subscribes to this room's existing `rooms.{id}` channel — the same one
/// `activeSosRealtimeProvider` and check-in/out already listen on, reusing
/// [ProvisionedDevice.roomUnitId] rather than opening a new socket — and
/// invalidates [smartDevicesProvider] on `smart_device.status_changed`. The
/// broadcast payload is never trusted; a full refetch always follows. Pure
/// accelerator: with no broadcaster configured, or a dropped socket, the
/// 20-second poll still carries the screen.
final smartRoomRealtimeProvider =
    FutureProvider.family<void, ProvisionedDevice>((ref, device) async {
      final roomUnitId = device.roomUnitId;
      if (roomUnitId == null) return; // not bound to a room

      final config = (await ref.watch(appConfigProvider.future)).realtime;
      if (config == null) {
        debugPrint(
          'smartRoomRealtime: no broadcaster configured — polling only.',
        );
        return;
      }

      final channel = SosChannel(
        config: config,
        channel: 'rooms.$roomUnitId',
        events: const {'smart_device.status_changed'},
      );
      channel.connect(
        onChanged: () => ref.invalidate(smartDevicesProvider(device)),
      );

      ref.onDispose(channel.dispose);
    });

/// Sends device commands and activates scenes, then invalidates
/// [smartDevicesProvider] so the UI reconciles against the server's own
/// answer — never the guessed optimistic value the control page painted on
/// tap.
class SmartRoomActions {
  const SmartRoomActions(this._ref, this._device);

  final Ref _ref;
  final ProvisionedDevice _device;

  Future<SmartDevice> sendCommand(
    int deviceId,
    String capability,
    dynamic value,
  ) async {
    final updated = await _ref
        .read(smartRoomRepositoryProvider)
        .sendCommand(_device, deviceId, capability, value);
    _ref.invalidate(smartDevicesProvider(_device));
    return updated;
  }

  Future<SceneActivationResult> activateScene(int sceneId) async {
    final result = await _ref
        .read(smartRoomRepositoryProvider)
        .activateScene(_device, sceneId);
    _ref.invalidate(smartDevicesProvider(_device));
    return result;
  }
}

final smartRoomActionsProvider =
    Provider.family<SmartRoomActions, ProvisionedDevice>(
      (ref, device) => SmartRoomActions(ref, device),
    );
