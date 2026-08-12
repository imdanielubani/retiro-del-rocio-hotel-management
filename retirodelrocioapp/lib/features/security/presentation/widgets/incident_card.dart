import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';

const Color _red = Color(0xFFFF0000);

/// The Open Incidents banner (Figma 252:146). An unacknowledged SOS shows loud
/// and red with a Respond action; once acknowledged it calms to amber, names the
/// responding officer and offers Resolve.
class IncidentCard extends StatelessWidget {
  const IncidentCard({
    super.key,
    required this.incident,
    required this.busy,
    required this.onRespond,
    required this.onResolve,
  });

  final SecurityIncident incident;
  final bool busy;
  final VoidCallback onRespond;
  final VoidCallback onResolve;

  @override
  Widget build(BuildContext context) {
    final active = incident.isActive;
    final accent = active ? _red : AppColors.gold;

    return Container(
      padding: const EdgeInsets.all(20.8),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: active ? 0.11 : 0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: accent.withValues(alpha: active ? 0.21 : 0.25),
          width: 0.8,
        ),
      ),
      child: Row(
        children: [
          _badge(accent),
          const SizedBox(width: 16),
          Expanded(child: _details(accent)),
          const SizedBox(width: 16),
          _action(accent),
        ],
      ),
    );
  }

  Widget _badge(Color accent) {
    return Container(
      width: 48,
      height: 48,
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: accent.withValues(alpha: 0.3)),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.warning_amber_rounded, size: 18, color: accent),
          Text(
            'SOS',
            style: AppTypography.style(
              color: accent,
              fontSize: 9,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _details(Color accent) {
    final title = incident.isActive
        ? 'Guest Emergency Alert'
        : 'Responding — help on the way';
    final officer = incident.acknowledgedBy;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          incident.isActive
              ? 'INCOMING SOS — ${incident.roomLabel.toUpperCase()}'
              : 'ACKNOWLEDGED — ${incident.roomLabel.toUpperCase()}',
          style: AppTypography.style(
            color: accent,
            fontSize: 11,
            fontWeight: FontWeight.w800,
            letterSpacing: 1.54,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          title,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.w700,
            height: 1.5,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          [
            incident.guestLabel,
            if (incident.suiteName != null) incident.suiteName!,
            if (officer != null && !incident.isActive) 'Officer: $officer',
            _relativeTime(incident.raisedAt),
          ].join('  ·  '),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.5),
            fontSize: 12,
            height: 1.5,
          ),
        ),
      ],
    );
  }

  Widget _action(Color accent) {
    final label = incident.isActive ? 'Respond' : 'Resolve';
    final icon = incident.isActive
        ? Icons.campaign_rounded
        : Icons.check_circle_outline_rounded;

    return Material(
      color: accent,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: busy ? null : (incident.isActive ? onRespond : onResolve),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          alignment: Alignment.center,
          constraints: const BoxConstraints(minWidth: 118),
          child: busy
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(icon, size: 14, color: Colors.white),
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

  static String _relativeTime(DateTime? at) {
    if (at == null) return 'Just now';
    final diff = DateTime.now().difference(at);
    if (diff.inSeconds < 45) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
    if (diff.inHours < 24) return '${diff.inHours} hr ago';
    return '${diff.inDays} d ago';
  }
}

/// The reassuring "nothing to see" state (Figma 257:1133) — shown whenever no
/// SOS is open.
class OpenIncidentsEmpty extends StatelessWidget {
  const OpenIncidentsEmpty({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 96,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.03),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.07),
          width: 0.8,
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.check_circle_outline_rounded,
            size: 26,
            color: AppColors.success,
          ),
          const SizedBox(height: 8),
          Text(
            'All clear — no unacknowledged SOS alerts',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }
}
