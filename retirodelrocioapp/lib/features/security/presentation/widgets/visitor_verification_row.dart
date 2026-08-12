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
  VisitorPassStatus.expired => const Color(0x73FFFFFF),
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
    this.onCheckOut,
    this.checkingOut = false,
  });

  final VisitorPassRecord pass;
  final bool expanded;
  final VoidCallback onTap;

  /// Checks this visitor out — only offered while they are actually inside
  /// (verified, not already checked out) and the row is expanded.
  final VoidCallback? onCheckOut;

  /// True while this row's check-out request is in flight.
  final bool checkingOut;

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
              if (expanded &&
                  pass.isVerified &&
                  pass.isInside &&
                  onCheckOut != null) ...[
                const SizedBox(height: 14),
                _checkOutButton(),
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
            if (pass.isVerified && pass.isInside) ...[
              const SizedBox(width: 6),
              _insideTag(),
            ],
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
        // A Wrap, not a Row: the code is read digit by digit at the gate, so it
        // must never be squeezed to fit the reference alongside it. On a narrow
        // list the reference drops to the next line instead.
        Wrap(
          crossAxisAlignment: WrapCrossAlignment.center,
          spacing: 10,
          runSpacing: 2,
          children: [
            Text.rich(
              TextSpan(
                children: [
                  TextSpan(
                    text: pass.hasOnlineCode
                        ? 'Online Code — '
                        : 'Offline Code — ',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.4),
                      fontSize: 12,
                    ),
                  ),
                  TextSpan(
                    text: pass.code,
                    style:
                        AppTypography.style(
                          color: accent,
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 1.5,
                        ).copyWith(
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                  ),
                ],
              ),
            ),
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

  Widget _insideTag() {
    const green = Color(0xFF22C55E);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: green.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        'Inside',
        style: AppTypography.style(
          color: green,
          fontSize: 10,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _checkOutButton() {
    const red = Color(0xFFEF4444);
    return Material(
      color: red.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: checkingOut ? null : onCheckOut,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          height: 40,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: red.withValues(alpha: 0.35), width: 0.8),
          ),
          child: checkingOut
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2, color: red),
                )
              : Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.logout_rounded, size: 15, color: red),
                    const SizedBox(width: 8),
                    Text(
                      'Check Out Visitor',
                      style: AppTypography.style(
                        color: red,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }

  Widget _contactDetails() {
    return Column(
      children: [
        if ((pass.email ?? '').isNotEmpty) _detailRow('Email', pass.email!),
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

  /// Label left, value hard against the right edge.
  ///
  /// A `Spacer` plus a `Flexible` both carry flex 1, so they halve the leftover
  /// width between them: the value gets only half the room it should and starts
  /// ellipsizing — and, because the gap grows with the value, no two rows line
  /// up. `Expanded` on the value alone gives it every remaining pixel and puts
  /// each row's right edge in the same place.
  Widget _detailRow(String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 12,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
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
