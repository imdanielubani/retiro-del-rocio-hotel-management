import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show ProviderListenable;
import 'package:retirodelrocioapp/core/audio/sos_alarm.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';

const Color _red = Color(0xFFFF0000);
const Color _redGlow = Color(0xFFEF4444);
const Color _green = Color(0xFF22C55E);
const Color _blue = Color(0xFF60A5FA);

/// Full-screen scrim behind the modal — a red wash for the incoming alert
/// (Figma rgba(60,0,0,0.55)), a soft green once acknowledged (rgba(3,96,3,0.13)).
const Color _redScrim = Color(0x8C3C0000);
const Color _greenScrim = Color(0x21036003);

/// The priority SOS trigger (Figma 258:1471 / 258:1791) — a full-screen alert
/// that interrupts the security officer the instant a guest raises an emergency.
///
/// Two states in one modal: the loud red "PRIORITY ALERT", and — once the
/// officer acknowledges — the green "Alert Acknowledged" confirmation with the
/// dispatch details. Returns when the officer closes or dismisses it.
///
/// [onAcknowledge] performs the real acknowledge (security or reception
/// respond), so the guest tablet flips to "help is on the way" off the same
/// action.
///
/// [activeIncidents] is the live source of open incidents the overlay watches to
/// self-dismiss the instant its alert is stood down or taken by another station
/// — security passes its dashboard overview's incidents, reception its own.
Future<void> showSosAlertOverlay(
  BuildContext context, {
  required SecurityIncident incident,
  required String officerName,
  required ProviderListenable<AsyncValue<List<SecurityIncident>>>
  activeIncidents,
  required Future<void> Function() onAcknowledge,
  VoidCallback? onCallRoom,
}) {
  return showDialog<void>(
    context: context,
    // Must be acted on — no tap-away. A guest is waiting.
    barrierDismissible: false,
    // The scrim is painted inside the overlay, not here, so it can change with
    // state: a red wash for the incoming alert, a green one once acknowledged.
    barrierColor: Colors.transparent,
    builder: (_) => _SosAlertOverlay(
      incident: incident,
      officerName: officerName,
      activeIncidents: activeIncidents,
      onAcknowledge: onAcknowledge,
      onCallRoom: onCallRoom,
    ),
  );
}

class _SosAlertOverlay extends ConsumerStatefulWidget {
  const _SosAlertOverlay({
    required this.incident,
    required this.officerName,
    required this.activeIncidents,
    required this.onAcknowledge,
    this.onCallRoom,
  });

  final SecurityIncident incident;
  final String officerName;
  final ProviderListenable<AsyncValue<List<SecurityIncident>>> activeIncidents;
  final Future<void> Function() onAcknowledge;
  final VoidCallback? onCallRoom;

  @override
  ConsumerState<_SosAlertOverlay> createState() => _SosAlertOverlayState();
}

class _SosAlertOverlayState extends ConsumerState<_SosAlertOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
  )..repeat(reverse: true);

  final SosAlarm _alarm = SosAlarm();

  bool _busy = false;
  bool _acknowledged = false;

  /// True once we've begun auto-dismissing, so it only happens once.
  bool _closing = false;
  String? _error;

  SecurityIncident get incident => widget.incident;

  @override
  void initState() {
    super.initState();
    // If it was already acknowledged elsewhere (another officer got there
    // first), open straight into the confirmation — and stay silent.
    _acknowledged = !incident.isActive;
    if (!_acknowledged) _alarm.start();
  }

  @override
  void dispose() {
    _alarm.dispose();
    _pulse.dispose();
    super.dispose();
  }

  Future<void> _acknowledge() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.onAcknowledge();
      await _alarm.stop(); // help is dispatched — silence the siren
      if (mounted) setState(() => _acknowledged = true);
    } on MessagedException catch (e) {
      // Surface the real reason (e.g. session expired, no connection) rather
      // than a blanket "could not acknowledge".
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Could not acknowledge. Please try again.');
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    // If the emergency is no longer active — the guest stood it down, or another
    // station already took it — dismiss and silence the siren on our own. Only
    // while showing the red alert (not our own green confirmation, and not while
    // our acknowledge is in flight).
    final incidents = ref.watch(widget.activeIncidents).value;
    if (incidents != null && !_acknowledged && !_busy && !_closing) {
      final stillActive = incidents.any(
        (i) => i.id == incident.id && i.isActive,
      );
      if (!stillActive) {
        _closing = true;
        final navigator = Navigator.of(context);
        WidgetsBinding.instance.addPostFrameCallback((_) async {
          await _alarm.stop();
          if (mounted) navigator.pop();
        });
      }
    }

    final accent = _acknowledged ? _green : _redGlow;

    return Stack(
      fit: StackFit.expand,
      children: [
        // The scrim, painted here so it follows the state (red → green).
        AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          color: _acknowledged ? _greenScrim : _redScrim,
        ),
        Center(
          child: SingleChildScrollView(
            child: Dialog(
              backgroundColor: Colors.transparent,
              elevation: 0,
              insetPadding: const EdgeInsets.all(24),
              child: Container(
                width: 420,
                padding: const EdgeInsets.all(34),
                decoration: BoxDecoration(
                  color: _acknowledged
                      ? const Color(0xF20A0F0A)
                      : const Color(0xF7060101),
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(
                    color: accent.withValues(alpha: 0.6),
                    width: 2,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: accent.withValues(alpha: 0.25),
                      blurRadius: 80,
                    ),
                  ],
                ),
                child: _acknowledged ? _acknowledgedView() : _priorityView(),
              ),
            ),
          ),
        ),
      ],
    );
  }

  // --- Red: incoming priority alert -----------------------------------------

  Widget _priorityView() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        _pulsingSiren(),
        const SizedBox(height: 20),
        Text(
          '⚠ PRIORITY ALERT',
          style: AppTypography.style(
            color: _red,
            fontSize: 11,
            fontWeight: FontWeight.w800,
            letterSpacing: 2.2,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          'SOS FROM ${incident.roomLabel.toUpperCase()}',
          textAlign: TextAlign.center,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 26,
            fontWeight: FontWeight.w700,
            height: 39 / 26,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          incident.suiteName ?? 'Guest room',
          textAlign: TextAlign.center,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.75),
            fontSize: 17,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'Guest pressed the emergency SOS button',
          textAlign: TextAlign.center,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.3),
            fontSize: 12,
          ),
        ),
        if (_error != null) ...[
          const SizedBox(height: 12),
          Text(
            _error!,
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: _redGlow,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
        const SizedBox(height: 28),
        _primaryButton(
          label: 'Acknowledge & Dispatch',
          icon: Icons.campaign_rounded,
          color: _red,
          onTap: _acknowledge,
        ),
        const SizedBox(height: 12),
        _callRoomButton(),
        const SizedBox(height: 12),
        TextButton(
          onPressed: _busy ? null : () => Navigator.of(context).pop(),
          child: Text(
            'Dismiss',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.22),
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ],
    );
  }

  Widget _pulsingSiren() {
    return AnimatedBuilder(
      animation: _pulse,
      builder: (context, child) {
        final t = _pulse.value;
        return Container(
          width: 96,
          height: 96,
          decoration: BoxDecoration(
            color: _redGlow.withValues(alpha: 0.18),
            shape: BoxShape.circle,
            border: Border.all(color: _red.withValues(alpha: 0.55), width: 3),
            boxShadow: [
              BoxShadow(
                color: _redGlow.withValues(alpha: 0.25 + 0.25 * t),
                blurRadius: 30 + 20 * t,
              ),
            ],
          ),
          child: child,
        );
      },
      child: Center(
        child: Image.asset('assets/icons/sos red.png', width: 40, height: 40),
      ),
    );
  }

  // --- Green: acknowledged ---------------------------------------------------

  Widget _acknowledgedView() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 96,
          height: 96,
          decoration: BoxDecoration(
            color: _green.withValues(alpha: 0.15),
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(color: _green.withValues(alpha: 0.3), blurRadius: 40),
            ],
          ),
          child: const Icon(
            Icons.check_circle_outline_rounded,
            size: 52,
            color: _green,
          ),
        ),
        const SizedBox(height: 22),
        Text(
          'Alert Acknowledged',
          textAlign: TextAlign.center,
          style: AppTypography.style(
            color: _green,
            fontSize: 22,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 10),
        Text(
          '${widget.officerName} dispatched to ${incident.roomLabel}',
          textAlign: TextAlign.center,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.75),
            fontSize: 14,
            height: 1.5,
          ),
        ),
        if (incident.suiteName != null)
          Text(
            incident.suiteName!,
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.55),
              fontSize: 13,
              height: 1.5,
            ),
          ),
        const SizedBox(height: 2),
        Text(
          'ETA ~ 2 minutes',
          textAlign: TextAlign.center,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 13,
          ),
        ),
        const SizedBox(height: 26),
        _primaryButton(
          label: 'Close',
          color: _green,
          onTap: () async => Navigator.of(context).pop(),
        ),
      ],
    );
  }

  // --- Shared ---------------------------------------------------------------

  Widget _primaryButton({
    required String label,
    required Color color,
    required Future<void> Function() onTap,
    IconData? icon,
  }) {
    return SizedBox(
      width: double.infinity,
      child: Material(
        color: color,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: _busy ? null : () => onTap(),
          borderRadius: BorderRadius.circular(16),
          child: Container(
            height: 56,
            alignment: Alignment.center,
            child: _busy
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.5,
                      color: Colors.white,
                    ),
                  )
                : Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (icon != null) ...[
                        Icon(icon, size: 18, color: Colors.white),
                        const SizedBox(width: 12),
                      ],
                      Text(
                        label,
                        style: AppTypography.style(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }

  Widget _callRoomButton() {
    return SizedBox(
      width: double.infinity,
      child: Material(
        color: _blue.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          onTap: _busy ? null : widget.onCallRoom,
          borderRadius: BorderRadius.circular(14),
          child: Container(
            height: 56,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: _blue.withValues(alpha: 0.3)),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.call_rounded, size: 14, color: _blue),
                const SizedBox(width: 8),
                Text(
                  'Call Room',
                  style: AppTypography.style(
                    color: _blue,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
