import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/incident_status_style.dart';

/// One row in the SOS Alert Logs (Figma 225:9610): a status-tinted card with the
/// SOS badge, room + status, suite, time + case number, and the stacked action
/// buttons (Acknowledge / Resolve / Closed, plus View).
class IncidentLogRow extends StatelessWidget {
  const IncidentLogRow({
    super.key,
    required this.incident,
    required this.busy,
    required this.selected,
    required this.onAcknowledge,
    required this.onResolve,
    required this.onView,
  });

  final SecurityIncident incident;
  final bool busy;
  final bool selected;
  final VoidCallback onAcknowledge;
  final VoidCallback onResolve;
  final VoidCallback onView;

  @override
  Widget build(BuildContext context) {
    final style = IncidentStatusStyle.of(incident);

    return Container(
      padding: const EdgeInsets.all(17),
      decoration: BoxDecoration(
        color: style.color.withValues(alpha: 0.03),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: style.color.withValues(alpha: selected ? 0.5 : 0.21),
          width: selected ? 1.2 : 0.8,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _badge(style),
          const SizedBox(width: 16),
          Expanded(child: _details(style)),
          const SizedBox(width: 16),
          _actions(style),
        ],
      ),
    );
  }

  Widget _badge(IncidentStatusStyle style) {
    final active = incident.isActive;
    return Container(
      width: 56,
      height: 56,
      decoration: BoxDecoration(
        color: style.color.withValues(alpha: active ? 0.15 : 0.06),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: style.color.withValues(alpha: active ? 0.3 : 0.1)),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Image.asset(style.sosAsset, width: 18, height: 18),
          const SizedBox(height: 2),
          Text(
            'SOS',
            style: AppTypography.style(
              color: style.color,
              fontSize: 9,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _details(IncidentStatusStyle style) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Text(
              incident.roomLabel,
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 16,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(width: 8),
            Text('—',
                style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4), fontSize: 13)),
            const SizedBox(width: 8),
            _statusChip(style),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          incident.suiteName ?? 'Guest room',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 12,
          ),
        ),
        const SizedBox(height: 4),
        Row(
          children: [
            Icon(Icons.schedule_rounded,
                size: 11, color: Colors.white.withValues(alpha: 0.35)),
            const SizedBox(width: 4),
            Text(
              _when(incident.raisedAt),
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.35),
                fontSize: 11,
              ),
            ),
            const SizedBox(width: 12),
            Text('·',
                style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.2), fontSize: 11)),
            const SizedBox(width: 12),
            Text(
              incident.caseNo ?? '',
              style: AppTypography.style(
                color: style.color,
                fontSize: 11,
                letterSpacing: 0.5,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _statusChip(IncidentStatusStyle style) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      decoration: BoxDecoration(
        color: style.color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        style.label,
        style: AppTypography.style(
          color: style.color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _actions(IncidentStatusStyle style) {
    return SizedBox(
      width: 116,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (incident.isActive)
            _primary('Acknowledge', style.color, onAcknowledge)
          else if (incident.isAcknowledged)
            _primary('Resolve', style.color, onResolve)
          else if (incident.isResolved)
            _closedChip('Closed', style.color)
          else
            _closedChip('Cancelled', style.color),
          const SizedBox(height: 6),
          _viewButton(),
        ],
      ),
    );
  }

  Widget _primary(String label, Color color, VoidCallback onTap) {
    return SizedBox(
      width: double.infinity,
      child: Material(
        color: color.withValues(alpha: incident.isActive ? 0.15 : 0.1),
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: busy ? null : onTap,
          borderRadius: BorderRadius.circular(10),
          child: Container(
            height: 30,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: color.withValues(alpha: incident.isActive ? 0.35 : 0.3)),
            ),
            child: busy
                ? SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(strokeWidth: 2, color: color),
                  )
                : Text(
                    label,
                    style: AppTypography.style(
                      color: color,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
          ),
        ),
      ),
    );
  }

  Widget _closedChip(String label, Color color) {
    return Container(
      width: double.infinity,
      height: 30,
      alignment: Alignment.center,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.check_circle_outline_rounded, size: 12, color: color),
          const SizedBox(width: 4),
          Text(
            label,
            style: AppTypography.style(color: color, fontSize: 10),
          ),
        ],
      ),
    );
  }

  Widget _viewButton() {
    return SizedBox(
      width: double.infinity,
      child: Material(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: onView,
          borderRadius: BorderRadius.circular(10),
          child: Container(
            height: 30,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
            ),
            child: Text(
              'View',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ),
      ),
    );
  }

  static String _when(DateTime? at) {
    if (at == null) return 'Unknown time';
    return '${DateFormat('MMM d').format(at)} at ${DateFormat('hh:mm a').format(at)}';
  }
}

/// The Filter pill in the header (Figma 225:10106).
class IncidentFilterButton extends StatelessWidget {
  const IncidentFilterButton({super.key, required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(100),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(100),
        child: Container(
          height: 38,
          padding: const EdgeInsets.symmetric(horizontal: 21),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(100),
            border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.tune_rounded, size: 16, color: Colors.white),
              const SizedBox(width: 8),
              Text(
                label,
                style: AppTypography.style(
                  color: Colors.white,
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
}
