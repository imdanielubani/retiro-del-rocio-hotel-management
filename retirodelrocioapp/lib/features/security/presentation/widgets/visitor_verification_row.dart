import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/security/domain/visitor_pass_record.dart';

/// The status accent for a visitor row — gold while pending, green once verified,
/// red if denied (Figma 229:13150).
Color visitorStatusColor(VisitorPassStatus status) => switch (status) {
      VisitorPassStatus.verified => const Color(0xFF22C55E),
      VisitorPassStatus.denied => const Color(0xFFFF4D4D),
      VisitorPassStatus.pending => AppColors.gold,
      VisitorPassStatus.cancelled ||
      VisitorPassStatus.expired =>
        const Color(0x73FFFFFF),
    };

String visitorStatusLabel(VisitorPassStatus status) => switch (status) {
      VisitorPassStatus.verified => 'Verified',
      VisitorPassStatus.denied => 'Denied',
      VisitorPassStatus.pending => 'Pending',
      VisitorPassStatus.cancelled => 'Cancelled',
      VisitorPassStatus.expired => 'Expired',
    };

/// One row in the Visitor Verification list. Tapping a pending row expands it to
/// reveal the visitor's contact details, so the officer can confirm identity.
class VisitorVerificationRow extends StatelessWidget {
  const VisitorVerificationRow({
    super.key,
    required this.pass,
    required this.expanded,
    required this.onTap,
  });

  final VisitorPassRecord pass;
  final bool expanded;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = visitorStatusColor(pass.status);

    return Material(
      color: Colors.white.withValues(alpha: expanded ? 0.05 : 0.03),
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: expanded && pass.isPending
                  ? AppColors.gold.withValues(alpha: 0.5)
                  : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _avatar(),
                  const SizedBox(width: 12),
                  Expanded(child: _summary(accent)),
                ],
              ),
              if (expanded && _hasContact) ...[
                const SizedBox(height: 14),
                Divider(color: Colors.white.withValues(alpha: 0.08), height: 1),
                const SizedBox(height: 12),
                _contactDetails(),
              ],
            ],
          ),
        ),
      ),
    );
  }

  bool get _hasContact =>
      (pass.email ?? '').isNotEmpty ||
      (pass.phone ?? '').isNotEmpty ||
      (pass.submittedLabel ?? '').isNotEmpty;

  Widget _avatar() {
    return Container(
      width: 40,
      height: 40,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        shape: BoxShape.circle,
      ),
      child: Text(
        pass.initials,
        style: AppTypography.style(
          color: Colors.white,
          fontSize: 14,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _summary(Color accent) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Flexible(
              child: Text(
                pass.visitorName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 10),
            _badge(accent),
          ],
        ),
        const SizedBox(height: 5),
        Text(
          _metaLine,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 12,
          ),
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            Text(
              pass.hasOnlineCode ? 'Online Code — ' : 'Offline Code — ',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 12,
              ),
            ),
            Text(
              pass.code,
              style: AppTypography.style(
                color: accent,
                fontSize: 13,
                fontWeight: FontWeight.w700,
                letterSpacing: 1.5,
              ).copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
            ),
            const SizedBox(width: 10),
            Text(
              pass.reference,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.25),
                fontSize: 11,
              ),
            ),
          ],
        ),
      ],
    );
  }

  String get _metaLine {
    final parts = <String>[
      if ((pass.suiteName ?? '').isNotEmpty) pass.suiteName!,
      if ((pass.roomNumber ?? '').isNotEmpty) 'Room ${pass.roomNumber}',
      if ((pass.submittedLabel ?? '').isNotEmpty) pass.submittedLabel!,
    ];
    return parts.join('  ·  ');
  }

  Widget _badge(Color accent) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (pass.isVerified) ...[
            Icon(Icons.check_rounded, size: 11, color: accent),
            const SizedBox(width: 3),
          ],
          Text(
            visitorStatusLabel(pass.status),
            style: AppTypography.style(
              color: accent,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _contactDetails() {
    return Column(
      children: [
        if ((pass.email ?? '').isNotEmpty)
          _detailRow('Email', pass.email!),
        if ((pass.phone ?? '').isNotEmpty) ...[
          const SizedBox(height: 8),
          _detailRow('WhatsApp Number', pass.phone!),
        ],
        if ((pass.submittedLabel ?? '').isNotEmpty) ...[
          const SizedBox(height: 8),
          _detailRow('Submitted', pass.submittedLabel!),
        ],
      ],
    );
  }

  Widget _detailRow(String label, String value) {
    return Row(
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 12,
          ),
        ),
        const Spacer(),
        Flexible(
          child: Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.right,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.7),
              fontSize: 12,
            ),
          ),
        ),
      ],
    );
  }
}
