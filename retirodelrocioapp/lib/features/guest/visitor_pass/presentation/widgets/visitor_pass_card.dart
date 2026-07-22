import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/visitor_pass/domain/visitor_pass.dart';

/// The status accent used for the badge, the entry code and dots — green when
/// the visitor was verified, gold while pending, red when denied (Figma 267:2545).
Color visitorStatusColor(VisitorPassStatus status) => switch (status) {
  VisitorPassStatus.verified => const Color(0xFF00FF00),
  VisitorPassStatus.denied => const Color(0xFFFF4D4D),
  VisitorPassStatus.pending => AppColors.gold,
  VisitorPassStatus.cancelled ||
  VisitorPassStatus.expired => const Color(0x73FFFFFF),
};

String visitorStatusLabel(VisitorPassStatus status) => switch (status) {
  VisitorPassStatus.verified => 'Verified',
  VisitorPassStatus.denied => 'Denied',
  VisitorPassStatus.pending => 'Pending',
  VisitorPassStatus.cancelled => 'Cancelled',
  VisitorPassStatus.expired => 'Expired',
};

/// One row in the Visitor History list (Figma 267:2545): avatar initials, name +
/// status badge, contact details, the pass reference & generated time, and the
/// visitor's entry code coloured by status.
class VisitorPassCard extends StatelessWidget {
  const VisitorPassCard({super.key, required this.pass});

  final VisitorPass pass;

  @override
  Widget build(BuildContext context) {
    final accent = visitorStatusColor(pass.status);

    return Container(
      padding: const EdgeInsets.all(20.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _avatar(),
          const SizedBox(width: 16),
          Expanded(child: _details(accent)),
          const SizedBox(width: 12),
          _code(accent),
        ],
      ),
    );
  }

  Widget _avatar() {
    return Container(
      width: 44,
      height: 44,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        shape: BoxShape.circle,
      ),
      child: Text(
        pass.initials,
        style: AppTypography.style(
          color: Colors.white,
          fontSize: 16,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _details(Color accent) {
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
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const SizedBox(width: 12),
            _statusBadge(accent),
          ],
        ),
        const SizedBox(height: 6),
        _contactRow(),
        const SizedBox(height: 6),
        _metaRow(),
      ],
    );
  }

  Widget _statusBadge(Color accent) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        visitorStatusLabel(pass.status),
        style: AppTypography.style(
          color: accent,
          fontSize: 10,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _contactRow() {
    final items = <Widget>[
      if ((pass.visitorEmail ?? '').isNotEmpty)
        _iconText(Icons.mail_outline_rounded, pass.visitorEmail!),
      if ((pass.visitorPhone ?? '').isNotEmpty)
        _iconText(Icons.call_outlined, pass.visitorPhone!),
    ];
    if (items.isEmpty) return const SizedBox.shrink();

    return Wrap(spacing: 12, runSpacing: 4, children: items);
  }

  Widget _iconText(IconData icon, String text) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 10, color: Colors.white.withValues(alpha: 0.35)),
        const SizedBox(width: 4),
        Text(
          text,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 11,
          ),
        ),
      ],
    );
  }

  /// Pass reference · generated time — the low-key provenance line.
  Widget _metaRow() {
    final parts = <String>[
      if (pass.reference.isNotEmpty) pass.reference,
      if (pass.createdAt != null)
        'Generated ${DateFormat('h:mm a').format(pass.createdAt!)}',
    ];

    return Text(
      parts.join('   ·   '),
      style: AppTypography.style(
        color: Colors.white.withValues(alpha: 0.3),
        fontSize: 11,
      ),
    );
  }

  Widget _code(Color accent) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.end,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'CODE',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.25),
            fontSize: 10,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          pass.code,
          style: AppTypography.style(
            color: accent,
            fontSize: 20,
            fontWeight: FontWeight.w700,
            letterSpacing: 3,
          ).copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
        ),
      ],
    );
  }
}
