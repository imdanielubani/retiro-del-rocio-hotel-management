import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/sos/application/sos_providers.dart';
import 'package:retirodelrocioapp/features/guest/sos/data/sos_repository.dart';
import 'package:retirodelrocioapp/features/guest/sos/domain/sos_alert.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/dialogs/confirm_emergency_dialog.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/widgets/sos_button.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

const Color _sosGreen = Color(0xFF22C55E);

/// 1.2 / 1.4 — Emergency SOS (Figma 113:725, 113:1837, 113:2382).
///
/// One screen, two states: the SOS button, and — once an alert is open — the
/// reassurance that help is coming. The open alert is read from the server, not
/// held in the widget, so a tablet that reboots mid-emergency comes back showing
/// "Help is on the way" rather than pretending nothing happened.
class SosScreen extends ConsumerStatefulWidget {
  const SosScreen({super.key, required this.device, required this.status});

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<SosScreen> createState() => _SosScreenState();
}

class _SosScreenState extends ConsumerState<SosScreen> {
  bool _busy = false;

  ProvisionedDevice get device => widget.device;
  RoomStatus get status => widget.status;

  String? get _roomNumber => status.roomNumber ?? device.roomNumber;

  Future<void> _raise() async {
    final confirmed = await showConfirmEmergencyDialog(
      context,
      roomNumber: _roomNumber,
    );
    if (!confirmed || !mounted) return;

    setState(() => _busy = true);
    try {
      await ref.read(sosActionsProvider(device.token)).raise();
    } on SosException catch (error) {
      _showFailure(error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cancel(SosAlert alert) async {
    setState(() => _busy = true);
    try {
      await ref.read(sosActionsProvider(device.token)).cancel(alert.id);
    } on SosException catch (error) {
      _showFailure(error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// A failed alert is the one thing a guest must never be left guessing about.
  void _showFailure(String message) {
    if (!mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: kSosRed,
        duration: const Duration(seconds: 6),
        content: Text(
          message,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    // Follow this room in realtime, so an officer acknowledging the alert flips
    // the screen to "Security is on their way" at once — the poll is the backstop.
    ref.watch(activeSosRealtimeProvider(device));

    final alert = ref.watch(activeSosAlertProvider(device.token)).value;
    final busy = _busy;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
          const ColoredBox(color: Color(0xE6000000)),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(25, 24, 25, 30),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GuestTopBar(
                    suiteName: status.suiteName ?? 'Suite',
                    roomNumber: _roomNumber ?? '—',
                    guestName: status.guest?.name ?? 'Guest',
                    weather: ref.watch(weatherProvider).value,
                    onNotifications: () {},
                    onProfile: () {},
                  ),
                  const SizedBox(height: 27),
                  // Back to the guest home (Figma 113:725). Sits in the screen's
                  // chrome, so it is there in both states.
                  Align(alignment: Alignment.centerLeft, child: _backButton()),
                  Expanded(
                    child: Center(
                      child: SingleChildScrollView(
                        child: alert != null && alert.isOpen
                            ? _helpIsOnTheWay(alert, busy)
                            : _emergencyButton(busy),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // --- Idle: raise the alarm -------------------------------------------------

  Widget _emergencyButton(bool busy) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Emergency SOS',
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 40,
            fontWeight: FontWeight.w700,
            height: 48 / 40,
          ),
        ),
        const SizedBox(height: 14),
        Text(
          '${_roomNumber != null ? 'Room $_roomNumber' : 'Room'} Emergency Services',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 20,
          ),
        ),
        const SizedBox(height: 37),
        Text(
          'EMERGENCY BUTTON',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 12,
            letterSpacing: 1.2,
          ),
        ),
        const SizedBox(height: 24),
        busy
            ? const SizedBox(
                width: SosButton.diameter,
                height: SosButton.diameter,
                child: Center(child: CircularProgressIndicator(color: kSosRed)),
              )
            : SosButton(onPressed: _raise),
        const SizedBox(height: 40),
        _warningCard(),
      ],
    );
  }

  ///Leaving the screen only navigates —
  /// an alert already raised stays open on the server, so a guest wandering back
  /// to the dashboard cannot accidentally stand security down. Only "Cancel
  /// Alert" does that.
  Widget _backButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      shape: CircleBorder(
        side: BorderSide(
          color: Colors.white.withValues(alpha: 0.12),
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

  Widget _warningCard() {
    return Container(
      width: 260,
      padding: const EdgeInsets.all(16.8),
      decoration: BoxDecoration(
        color: kSosGlow.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: kSosGlow.withValues(alpha: 0.15), width: 0.8),
      ),
      child: Column(
        children: [
          Text(
            'Press to alert security immediately',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: kSosRed,
              fontSize: 13,
              fontWeight: FontWeight.w700,
              height: 19.5 / 13,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Use Only In Genuine Emergencies',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 13,
              height: 16.5 / 13,
            ),
          ),
        ],
      ),
    );
  }

  // --- Active: help is coming ------------------------------------------------

  Widget _helpIsOnTheWay(SosAlert alert, bool busy) {
    final room = alert.roomNumber ?? _roomNumber ?? 'your room';

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 176,
          height: 176,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: _sosGreen.withValues(alpha: 0.15),
            boxShadow: [
              BoxShadow(
                color: _sosGreen.withValues(alpha: 0.3),
                blurRadius: 60,
              ),
            ],
          ),
          child: const Icon(
            Icons.check_circle_outline_rounded,
            size: 96,
            color: _sosGreen,
          ),
        ),
        const SizedBox(height: 40),
        Text(
          'Help is on the way!',
          textAlign: TextAlign.center,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 40,
            fontWeight: FontWeight.w700,
            height: 48 / 40,
          ),
        ),
        const SizedBox(height: 16),
        SizedBox(
          width: 420,
          child: Text(
            'Hotel Security and Emergency Services have been notified to '
            'room $room. Stay calm.',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 18,
              height: 1.4,
            ),
          ),
        ),
        const SizedBox(height: 24),
        _etaChip(alert),
        const SizedBox(height: 32),
        _cancelAlertButton(alert, busy),
        const SizedBox(height: 14),
        // Leaving the screen only navigates — the alert stays open on the
        // server, so wandering back to the dashboard cannot stand security down.
        // Only "Cancel Alert" above does that.
        _backToHomeButton(),
      ],
    );
  }

  Widget _backToHomeButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: () => Navigator.of(context).maybePop(),
        borderRadius: BorderRadius.circular(16),
        child: Container(
          width: 320,
          height: 52,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.12),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                Icons.grid_view_rounded,
                size: 16,
                color: Colors.white.withValues(alpha: 0.7),
              ),
              const SizedBox(width: 10),
              Text(
                'Back to Home',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.7),
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Once security acknowledges, say so — that is the single most reassuring
  /// thing the screen can tell a frightened guest.
  Widget _etaChip(SosAlert alert) {
    final label = alert.isAcknowledged
        ? 'Security is on their way'
        : 'ETA: 2-3 minutes';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: _sosGreen.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(100),
        border: Border.all(color: _sosGreen.withValues(alpha: 0.3), width: 0.8),
      ),
      child: Text(
        label,
        style: AppTypography.style(
          color: _sosGreen,
          fontSize: 13,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _cancelAlertButton(SosAlert alert, bool busy) {
    return Material(
      color: _sosGreen.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: busy ? null : () => _cancel(alert),
        borderRadius: BorderRadius.circular(16),
        child: Container(
          width: 320,
          height: 56,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: _sosGreen.withValues(alpha: 0.4),
              width: 0.8,
            ),
          ),
          child: busy
              ? const SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: _sosGreen,
                  ),
                )
              : Text(
                  'Cancel Alert',
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
        ),
      ),
    );
  }
}
