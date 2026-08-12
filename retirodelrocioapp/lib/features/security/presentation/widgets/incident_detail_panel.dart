import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';

const Color _red = Color(0xFFFF0000);
const Color _green = Color(0xFF00FF00);

/// The slide-in incident detail (Figma 225:10446 / 225:11720 / 225:12220): the
/// emergency card, a three-step timeline (Triggered → Acknowledged → Resolved),
/// the case details, and the officer's actions.
class IncidentDetailPanel extends StatelessWidget {
  const IncidentDetailPanel({
    super.key,
    required this.incident,
    required this.busy,
    required this.onClose,
    required this.onAcknowledge,
    required this.onResolve,
    required this.onCallRoom,
  });

  final SecurityIncident incident;
  final bool busy;
  final VoidCallback onClose;
  final VoidCallback onAcknowledge;
  final VoidCallback onResolve;
  final VoidCallback onCallRoom;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Align(alignment: Alignment.centerRight, child: _closeButton()),
        const SizedBox(height: 8),
        _emergencyCard(),
        const SizedBox(height: 20),
        Expanded(
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _sectionLabel('TIMELINE'),
                const SizedBox(height: 14),
                _timeline(),
                const SizedBox(height: 22),
                _sectionLabel('DETAILS'),
                const SizedBox(height: 12),
                _details(),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        _actions(),
      ],
    );
  }

  Widget _closeButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.08),
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onClose,
        customBorder: const CircleBorder(),
        child: const SizedBox(
          width: 32,
          height: 32,
          child: Icon(Icons.close_rounded, size: 18, color: Colors.white),
        ),
      ),
    );
  }

  Widget _emergencyCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _red.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _red.withValues(alpha: 0.25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: _red.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: _red.withValues(alpha: 0.3)),
                ),
                child: const Icon(
                  Icons.warning_amber_rounded,
                  size: 20,
                  color: _red,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'SOS EMERGENCY ALERT',
                      style: AppTypography.style(
                        color: _red,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.2,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      incident.roomLabel,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            incident.guestLabel,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            incident.suiteName ?? 'Guest room',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _timeline() {
    final triggeredDone = incident.raisedAt != null;
    final ackDone = incident.acknowledgedAt != null || incident.isClosed;
    final resolvedDone = incident.isResolved;

    return Column(
      children: [
        _TimelineStep(
          title: 'SOS Triggered',
          subtitle: _stamp(incident.raisedAt),
          subtitleColor: _red,
          done: triggeredDone,
          doneColor: _red,
          isFirst: true,
        ),
        _TimelineStep(
          title: 'Acknowledged',
          subtitle: ackDone
              ? _stampWithWho(incident.acknowledgedAt, incident.acknowledgedBy)
              : 'Pending',
          done: ackDone,
          doneColor: AppColors.gold,
        ),
        _TimelineStep(
          title: 'Resolved',
          subtitle: resolvedDone
              ? _stampWithWho(incident.resolvedAt, incident.resolvedBy)
              : (incident.isCancelled ? 'Stood down by guest' : 'Pending'),
          done: resolvedDone,
          doneColor: _green,
          isLast: true,
        ),
      ],
    );
  }

  Widget _details() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.03),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white.withValues(alpha: 0.07)),
      ),
      child: Column(
        children: [
          _detailRow('Case No.', incident.caseNo ?? '—', mono: true),
          _divider(),
          _detailRow('Room', incident.roomNumber ?? '—'),
          _divider(),
          _detailRow('Suite', incident.suiteName ?? '—'),
        ],
      ),
    );
  }

  Widget _detailRow(String label, String value, {bool mono = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 11),
      // Spacer + Flexible would halve the leftover width between them, wrapping
      // the value early and leaving each row at a different right edge.
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 11,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.85),
                fontSize: 11,
                fontWeight: FontWeight.w600,
                letterSpacing: mono ? 0.5 : 0,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _divider() =>
      Container(height: 0.8, color: Colors.white.withValues(alpha: 0.06));

  Widget _actions() {
    return Column(
      children: [
        if (incident.isActive)
          _primaryAction(
            'Acknowledge & Dispatch',
            Icons.campaign_rounded,
            _red,
            onAcknowledge,
          )
        else if (incident.isAcknowledged)
          _primaryAction(
            'Mark Resolved',
            Icons.check_circle_outline_rounded,
            AppColors.gold,
            onResolve,
            onDark: true,
          )
        else
          _closedBanner(),
        const SizedBox(height: 10),
        _callRoomButton(),
      ],
    );
  }

  Widget _primaryAction(
    String label,
    IconData icon,
    Color color,
    VoidCallback onTap, {
    bool onDark = false,
  }) {
    return SizedBox(
      width: double.infinity,
      child: Material(
        color: color,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          onTap: busy ? null : onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            height: 50,
            alignment: Alignment.center,
            child: busy
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        icon,
                        size: 16,
                        color: onDark ? AppColors.onGold : Colors.white,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        label,
                        style: AppTypography.style(
                          color: onDark ? AppColors.onGold : Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }

  Widget _closedBanner() {
    final resolved = incident.isResolved;
    final color = resolved ? _green : Colors.white.withValues(alpha: 0.5);
    return Container(
      width: double.infinity,
      height: 50,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.check_circle_rounded, size: 16, color: color),
          const SizedBox(width: 8),
          Text(
            resolved ? 'Incident Resolved' : 'Stood Down',
            style: AppTypography.style(
              color: color,
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _callRoomButton() {
    return SizedBox(
      width: double.infinity,
      child: Material(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          onTap: onCallRoom,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            height: 46,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.call_rounded,
                  size: 15,
                  color: Colors.white.withValues(alpha: 0.7),
                ),
                const SizedBox(width: 8),
                Text(
                  'Call Room',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.7),
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _sectionLabel(String text) => Text(
    text,
    style: AppTypography.style(
      color: Colors.white.withValues(alpha: 0.35),
      fontSize: 11,
      fontWeight: FontWeight.w700,
      letterSpacing: 1.2,
    ),
  );

  static String _stamp(DateTime? at) {
    if (at == null) return '—';
    return '${DateFormat('MMM d').format(at)} at ${DateFormat('hh:mm a').format(at)}';
  }

  static String _stampWithWho(DateTime? at, String? who) {
    final time = at != null ? DateFormat('hh:mm a').format(at) : '';
    if (who != null && who.isNotEmpty) {
      return time.isNotEmpty ? '$time by $who' : 'by $who';
    }
    return time.isNotEmpty ? time : 'Done';
  }
}

/// One dot-and-connector row in the incident timeline.
class _TimelineStep extends StatelessWidget {
  const _TimelineStep({
    required this.title,
    required this.subtitle,
    required this.done,
    required this.doneColor,
    this.subtitleColor,
    this.isFirst = false,
    this.isLast = false,
  });

  final String title;
  final String subtitle;
  final bool done;
  final Color doneColor;
  final Color? subtitleColor;
  final bool isFirst;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final dotColor = done ? doneColor : Colors.white.withValues(alpha: 0.2);

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 18,
                height: 18,
                decoration: BoxDecoration(
                  color: done
                      ? doneColor.withValues(alpha: 0.15)
                      : Colors.transparent,
                  shape: BoxShape.circle,
                  border: Border.all(color: dotColor, width: 1.5),
                ),
                child: done
                    ? Icon(Icons.check_rounded, size: 11, color: doneColor)
                    : null,
              ),
              if (!isLast)
                Expanded(
                  child: Container(
                    width: 1.5,
                    color: Colors.white.withValues(alpha: 0.1),
                  ),
                ),
            ],
          ),
          const SizedBox(width: 12),
          Padding(
            padding: EdgeInsets.only(bottom: isLast ? 0 : 18, top: 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: AppTypography.style(
                    color: done
                        ? Colors.white
                        : Colors.white.withValues(alpha: 0.5),
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: AppTypography.style(
                    color: done
                        ? (subtitleColor ?? Colors.white.withValues(alpha: 0.5))
                        : Colors.white.withValues(alpha: 0.3),
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
