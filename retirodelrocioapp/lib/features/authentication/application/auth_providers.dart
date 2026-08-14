import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/app/app_router.dart';
import 'package:retirodelrocioapp/core/storage/auth_session_store.dart';
import 'package:retirodelrocioapp/core/storage/device_session_store.dart';
import 'package:retirodelrocioapp/features/authentication/data/auth_repository.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(),
);
final authSessionStoreProvider = Provider<AuthSessionStore>(
  (ref) => AuthSessionStore(),
);
final deviceSessionStoreProvider = Provider<DeviceSessionStore>(
  (ref) => DeviceSessionStore(),
);

/// Holds the current staff session. Loads any persisted session on start so a
/// signed-in staffer goes straight to their dashboard.
class AuthController extends AsyncNotifier<StaffSession?> {
  AuthRepository get _repo => ref.read(authRepositoryProvider);
  AuthSessionStore get _store => ref.read(authSessionStoreProvider);

  @override
  Future<StaffSession?> build() => _store.read();

  /// Signs in with either [password] or [pin] (exactly one); throws
  /// [AuthException] on failure (caught by the screen). A PIN sign-in needs
  /// no [email] at all — the PIN alone identifies the staffer.
  Future<StaffSession> login({
    required String deviceToken,
    String? email,
    String? password,
    String? pin,
    required String activeRole,
  }) async {
    final session = await _repo.staffLogin(
      deviceToken: deviceToken,
      email: email,
      password: password,
      pin: pin,
      activeRole: activeRole,
    );
    await _store.save(session);
    state = AsyncData(session);
    return session;
  }

  /// Re-verify the current staffer with either [password] or [pin] (exactly
  /// one) — session lock / "stay signed in". Reuses the persisted device
  /// token + the signed-in email; refreshes the JWT.
  Future<StaffSession> reauthenticate({String? password, String? pin}) async {
    // Read the pairing from disk, not [bootstrapDeviceProvider]: that snapshot is
    // fixed at launch, so a tablet paired *during* this run (fresh setup → sign
    // in) would have a null snapshot and fail here. The store is always current —
    // provisioning writes it, and the device verify re-saves it.
    final device =
        await ref.read(deviceSessionStoreProvider).read() ??
        ref.read(bootstrapDeviceProvider);
    final current = state.value;
    if (device == null || current == null) {
      throw AuthException('Session unavailable. Please sign in again.');
    }
    final session = await _repo.staffLogin(
      deviceToken: device.token,
      email: current.email,
      password: password,
      pin: pin,
      activeRole: current.role,
    );
    await _store.save(session);
    state = AsyncData(session);
    return session;
  }

  Future<void> logout() async {
    await _store.clear();
    state = const AsyncData(null);
  }

  bool get isSignedIn => state.value != null;
}

final authControllerProvider =
    AsyncNotifierProvider<AuthController, StaffSession?>(AuthController.new);
