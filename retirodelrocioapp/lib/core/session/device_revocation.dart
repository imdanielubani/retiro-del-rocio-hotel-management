import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:retirodelrocioapp/app/app_router.dart';
import 'package:retirodelrocioapp/core/session/device_session_service.dart';

/// If [value] failed because the tablet's pairing was revoked (deleted or
/// unpaired in the admin dashboard), forget the session and send the app back
/// to device setup. Any other failure is left alone — a tablet that has merely
/// lost the network must keep running.
///
/// Call from `build`; the work is deferred to after the frame.
void unpairIfRevoked(BuildContext context, AsyncValue<Object?> value) {
  if (value.error is! DeviceRevokedException) return;

  WidgetsBinding.instance.addPostFrameCallback((_) async {
    await DeviceSessionService().clear();
    if (!context.mounted) return;

    // Drop any imperatively-pushed screens first: `go` swaps go_router's pages
    // but leaves routes pushed on top of them still showing.
    Navigator.of(context).popUntil((route) => route.isFirst);
    context.go(Routes.setup);
  });
}
