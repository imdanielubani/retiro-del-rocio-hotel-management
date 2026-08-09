import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/intercom/application/guest_intercom_call_providers.dart';
import 'package:retirodelrocioapp/features/intercom_call/data/intercom_call_repository.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

const _greenColor = Color(0xFF34D399);
const _redColor = Color(0xFFEF4444);

/// 2.9 — Room to Room (Figma 341:6821 / 341:7486 / 341:7595 / 341:8670): a
/// dial pad the guest uses to voice-call another checked-in room directly by
/// its number, no staff involved. Placing the call itself is a single
/// `callRoom()` on the same [guestIntercomCallProvider] `_callReception()`
/// already uses on `GuestIntercomScreen` — the root-level ringing gate in
/// `welcome_screen.dart` watches that exact provider, so it picks up and
/// pushes the shared `IntercomCallScreen` the instant the call starts
/// ringing, the same way an incoming call does. This screen never navigates
/// to the call screen itself.
class RoomToRoomScreen extends ConsumerStatefulWidget {
  const RoomToRoomScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<RoomToRoomScreen> createState() => _RoomToRoomScreenState();
}

class _RoomToRoomScreenState extends ConsumerState<RoomToRoomScreen> {
  static const _maxDigits = 4;
  static const _lookupThreshold = 3;

  String get _token => widget.device.token;

  final _digits = StringBuffer();
  Timer? _debounce;
  RoomLookupResult? _result;
  bool _placingCall = false;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  void _tapDigit(String digit) {
    if (_digits.length >= _maxDigits) return;
    setState(() {
      _digits.write(digit);
      _result = null;
    });
    _scheduleLookup();
  }

  void _backspace() {
    if (_digits.isEmpty) return;
    final next = _digits.toString().substring(0, _digits.length - 1);
    setState(() {
      _digits
        ..clear()
        ..write(next);
      _result = null;
    });
    _scheduleLookup();
  }

  void _scheduleLookup() {
    _debounce?.cancel();
    final number = _digits.toString();
    if (number.length < _lookupThreshold) return;

    _debounce = Timer(const Duration(milliseconds: 300), () async {
      final result = await ref
          .read(guestIntercomCallRepositoryProvider)
          .lookupRoom(_token, number);
      // The guest may have kept typing/backspacing while this was in
      // flight — only apply it if it still matches what's on screen.
      if (mounted && _digits.toString() == number) {
        setState(() => _result = result);
      }
    });
  }

  Future<void> _callRoom() async {
    final result = _result;
    if (_placingCall || result == null || !result.found || result.isOwnRoom) {
      return;
    }
    setState(() => _placingCall = true);
    try {
      await ref
          .read(guestIntercomCallProvider(_token).notifier)
          .callRoom(_digits.toString());
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
    final status = widget.status;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
          const ColoredBox(color: Color.fromARGB(230, 0, 0, 0)),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(25, 24, 25, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GuestTopBar(
                    suiteName: status.suiteName ?? 'Suite',
                    roomNumber:
                        status.roomNumber ?? widget.device.roomNumber ?? '—',
                    guestName: status.guest?.name ?? 'Guest',
                    weather: ref.watch(weatherProvider).value,
                    onNotifications: () {},
                    onProfile: () {},
                  ),
                  const SizedBox(height: 20),
                  _header(),
                  const SizedBox(height: 24),
                  Expanded(child: _card()),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _header() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        _backButton(),
        const SizedBox(width: 15),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Room to Room',
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 36,
                fontWeight: FontWeight.w700,
                height: 1.15,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              'Voice call with guest active rooms',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 15,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _backButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      shape: CircleBorder(
        side: BorderSide(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: InkWell(
        onTap: () => Navigator.of(context).maybePop(),
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 40,
          height: 40,
          child: Icon(
            Icons.arrow_back_rounded,
            size: 18,
            color: Colors.white.withValues(alpha: 0.8),
          ),
        ),
      ),
    );
  }

  Widget _card() {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.2),
          width: 0.8,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 340, child: _dialPad()),
          const SizedBox(width: 40),
          Expanded(child: Center(child: _resultPanel())),
        ],
      ),
    );
  }

  // --- Dial pad -------------------------------------------------------------

  Widget _dialPad() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'DIAL ROOM NUMBER',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 10,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.5,
          ),
        ),
        const SizedBox(height: 10),
        _digitDisplay(),
        const SizedBox(height: 15),
        _digitGrid(),
      ],
    );
  }

  Color get _digitColor => switch ((_digits.length, _result)) {
    (0, _) => Colors.white.withValues(alpha: 0.2),
    (_, null) => AppColors.goldAccent,
    (_, final r) when r!.found && !r.isOwnRoom => _greenColor,
    (_, final r) when r!.found && r.isOwnRoom => _greenColor,
    _ => _redColor,
  };

  Color get _digitBorderColor => switch ((_digits.length, _result)) {
    (0, _) => Colors.white.withValues(alpha: 0.1),
    (_, null) => AppColors.gold.withValues(alpha: 0.4),
    (_, final r) when r!.found => _greenColor.withValues(alpha: 0.4),
    _ => _redColor.withValues(alpha: 0.4),
  };

  Widget _digitDisplay() {
    final text = _digits.isEmpty
        ? '_ _ _ _'
        : _digits.toString().split('').join(' ');

    return Container(
      height: 80,
      width: double.infinity,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _digitBorderColor, width: 1),
      ),
      child: Text(
        text,
        style: AppTypography.style(
          color: _digitColor,
          fontSize: _digits.isEmpty ? 18 : 28,
          fontWeight: FontWeight.w700,
          letterSpacing: 3.36,
        ).copyWith(fontFamily: 'monospace'),
      ),
    );
  }

  Widget _digitGrid() {
    const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫'];

    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 8,
      crossAxisSpacing: 8,
      childAspectRatio: 108 / 65,
      children: keys.map(_dialKey).toList(),
    );
  }

  Widget _dialKey(String key) {
    if (key.isEmpty) return const SizedBox.shrink();

    final isBackspace = key == '⌫';
    final color = isBackspace ? _redColor : Colors.white;

    return Material(
      color: isBackspace
          ? _redColor.withValues(alpha: 0.1)
          : Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: isBackspace ? _backspace : () => _tapDigit(key),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: isBackspace
                  ? _redColor.withValues(alpha: 0.25)
                  : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          alignment: Alignment.center,
          child: Text(
            key,
            style: AppTypography.style(
              color: color,
              fontSize: isBackspace ? 18 : 22,
              fontWeight: FontWeight.w700,
            ).copyWith(fontFamily: isBackspace ? null : 'monospace'),
          ),
        ),
      ),
    );
  }

  // --- Result panel -----------------------------------------------------

  Widget _resultPanel() {
    if (_digits.length < _lookupThreshold) return _placeholder();

    final result = _result;
    if (result == null) {
      // Debounce still pending — Figma shows this state blank too, so no
      // spinner; the result arrives well within the debounce+request time.
      return _placeholder();
    }

    return result.found ? _foundPanel(result) : _notFoundPanel();
  }

  Widget _placeholder() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 80,
          height: 80,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.gold.withValues(alpha: 0.15),
            shape: BoxShape.circle,
          ),
          child: const Icon(
            Icons.call_rounded,
            size: 42,
            color: AppColors.gold,
          ),
        ),
        const SizedBox(height: 12),
        Text(
          'Type a room number\nto call directly',
          textAlign: TextAlign.center,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.25),
            fontSize: 20,
          ),
        ),
      ],
    );
  }

  Widget _notFoundPanel() {
    final number = _digits.toString();
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 80,
          height: 80,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: _redColor.withValues(alpha: 0.15),
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.block_rounded, size: 32, color: _redColor),
        ),
        const SizedBox(height: 12),
        Text(
          'Room $number Not Found',
          style: AppTypography.style(
            color: _redColor,
            fontSize: 20,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          'Check the room number and try again',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.3),
            fontSize: 15,
          ),
        ),
      ],
    );
  }

  Widget _foundPanel(RoomLookupResult result) {
    return SizedBox(
      width: 420,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(17),
            decoration: BoxDecoration(
              color: _greenColor.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: _greenColor.withValues(alpha: 0.2),
                width: 0.8,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'ROOM FOUND',
                  style: AppTypography.style(color: Colors.white, fontSize: 11),
                ),
                const SizedBox(height: 6),
                Text(
                  'Room ${result.number}',
                  style: AppTypography.style(
                    color: _greenColor,
                    fontSize: 22,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (result.suiteLabel != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    [
                      result.suiteLabel,
                      result.roomTypeLabel,
                    ].whereType<String>().join(' · '),
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.4),
                      fontSize: 12,
                    ),
                  ),
                ],
                if (result.guestName != null) ...[
                  const SizedBox(height: 12),
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      _guestAvatar(result.guestName!),
                      const SizedBox(width: 8),
                      Text(
                        result.guestName!,
                        style: AppTypography.style(
                          color: _greenColor,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          result.isOwnRoom ? _ownRoomNotice() : _callRoomButton(result),
        ],
      ),
    );
  }

  Widget _guestAvatar(String name) {
    final parts = name.trim().split(RegExp(r'\s+'));
    final initials = parts
        .where((p) => p.isNotEmpty)
        .take(2)
        .map((p) => p[0])
        .join()
        .toUpperCase();

    return Container(
      width: 28,
      height: 28,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: _greenColor.withValues(alpha: 0.2),
        shape: BoxShape.circle,
      ),
      child: Text(
        initials,
        style: AppTypography.style(
          color: _greenColor,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _callRoomButton(RoomLookupResult result) {
    return Material(
      color: _greenColor.withValues(alpha: 0.18),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: _placingCall ? null : _callRoom,
        child: Container(
          height: 51,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: _greenColor.withValues(alpha: 0.4),
              width: 0.8,
            ),
          ),
          alignment: Alignment.center,
          child: _placingCall
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: _greenColor,
                  ),
                )
              : Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.call_rounded,
                      size: 17,
                      color: _greenColor,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Call Room ${result.number}',
                      style: AppTypography.style(
                        color: _greenColor,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }

  Widget _ownRoomNotice() {
    return Container(
      height: 51,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.home_rounded,
            size: 16,
            color: Colors.white.withValues(alpha: 0.5),
          ),
          const SizedBox(width: 8),
          Text(
            "That's your own room",
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
