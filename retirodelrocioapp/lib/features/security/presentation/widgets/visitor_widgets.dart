import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/security/domain/security_visitor.dart';

const Color _green = Color(0xFF00FF00);

/// A verified visitor in the Visitors Today list (Figma 252:243): initials
/// avatar, name + verified chip, suite/room/time, pass code, and an
/// Inside / Not Inside pill.
class VisitorRow extends StatelessWidget {
  const VisitorRow({super.key, required this.visitor});

  final SecurityVisitor visitor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          _avatar(visitor.initials),
          const SizedBox(width: 14),
          Expanded(child: _details()),
          _presence(),
        ],
      ),
    );
  }

  Widget _details() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Flexible(
              child: Text(
                visitor.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            if (visitor.isVerified) ...[
              const SizedBox(width: 8),
              _chip('✓ Verified', _green),
            ],
          ],
        ),
        const SizedBox(height: 3),
        Text(
          [
            if (visitor.suiteName != null) visitor.suiteName!,
            if (visitor.roomNumber != null) 'Room ${visitor.roomNumber}',
            if (visitor.arrivalLabel != null) visitor.arrivalLabel!,
          ].join('  ·  '),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 12,
          ),
        ),
        if (visitor.passCode != null) ...[
          const SizedBox(height: 4),
          Text(
            visitor.passCode!,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.25),
              fontSize: 12,
            ),
          ),
        ],
      ],
    );
  }

  Widget _presence() {
    final inside = visitor.isInside;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: inside ? _green.withValues(alpha: 0.15) : Colors.white.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        inside ? 'Inside' : 'Not Inside',
        style: AppTypography.style(
          color: inside ? _green : Colors.white,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  static Widget _avatar(String initials) => Container(
        width: 49,
        height: 49,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(14),
        ),
        alignment: Alignment.center,
        child: Text(
          initials,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
}

/// A pending visitor-pass request in the right column (Figma 254:584): guest,
/// status chip, pass codes and a Verify Pass action.
class VisitorPassRequestCard extends StatelessWidget {
  const VisitorPassRequestCard({super.key, required this.request, this.onVerify});

  final VisitorPassRequest request;
  final VoidCallback? onVerify;

  @override
  Widget build(BuildContext context) {
    final pending = !request.isVerified;
    final accent = pending ? AppColors.gold : _green;

    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(16),
        border: Border(
          left: BorderSide(color: accent, width: pending ? 3 : 0.8),
          top: BorderSide(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
          right: BorderSide(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
          bottom: BorderSide(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _avatar(request.initials),
              const SizedBox(width: 12),
              Expanded(child: _details(accent)),
            ],
          ),
          if (pending) ...[
            const SizedBox(height: 12),
            _verifyButton(),
          ],
        ],
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
                request.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const SizedBox(width: 8),
            _chip(request.isVerified ? '✓ Verified' : 'Pending', accent),
          ],
        ),
        const SizedBox(height: 3),
        Text(
          [
            if (request.suiteName != null) request.suiteName!,
            if (request.roomNumber != null) 'Room ${request.roomNumber}',
            if (request.submittedLabel != null) request.submittedLabel!,
          ].join('  ·  '),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 12,
          ),
        ),
        if (request.onlineCode != null || request.offlineCode != null) ...[
          const SizedBox(height: 4),
          Text(
            request.onlineCode != null
                ? 'Online Code  -  ${request.onlineCode}'
                : 'Offline Code  -  ${request.offlineCode}',
            style: AppTypography.style(
              color: accent,
              fontSize: 12,
              fontWeight: FontWeight.w600,
              letterSpacing: 1.2,
            ),
          ),
        ],
      ],
    );
  }

  Widget _verifyButton() {
    return SizedBox(
      width: double.infinity,
      child: Material(
        color: AppColors.gold.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: onVerify,
          borderRadius: BorderRadius.circular(10),
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: 8),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppColors.gold.withValues(alpha: 0.25)),
            ),
            child: Text(
              'Verify Pass',
              style: AppTypography.style(
                color: AppColors.gold,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ),
      ),
    );
  }

  static Widget _avatar(String initials) => Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(14),
        ),
        alignment: Alignment.center,
        child: Text(
          initials,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
}

Widget _chip(String label, Color color) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: AppTypography.style(
          color: color,
          fontSize: 9,
          fontWeight: FontWeight.w600,
        ),
      ),
    );

/// A quiet placeholder for a list section that has nothing in it yet.
class SectionEmpty extends StatelessWidget {
  const SectionEmpty({super.key, required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 26, horizontal: 16),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.03),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.white.withValues(alpha: 0.06)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 22, color: Colors.white.withValues(alpha: 0.25)),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.35),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}
