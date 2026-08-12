import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/security/domain/security_visitor.dart';

const Color _green = Color(0xFF00FF00);

/// A verified visitor in the Visitors Today list (Figma 252:243): initials
/// avatar, name + verified chip, suite/room/time, pass code, and a presence
/// pill — Inside, Exited, or Not Inside (still pending).
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
            const SizedBox(width: 8),
            // A visitor who has not arrived yet is Pending, not unlabelled —
            // dropping the chip left those rows looking like a rendering fault.
            visitor.isVerified
                ? _chip('✓ Verified', _green)
                : _chip('Pending', AppColors.gold),
          ],
        ),
        const SizedBox(height: 2),
        // Figma 257:1270 — suite at 12px, room/arrival a size down beside it.
        Row(
          crossAxisAlignment: CrossAxisAlignment.baseline,
          textBaseline: TextBaseline.alphabetic,
          children: [
            if (visitor.suiteName != null)
              Flexible(
                child: Text(
                  visitor.suiteName!,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 12,
                  ),
                ),
              ),
            if (visitor.suiteName != null) const SizedBox(width: 5),
            Flexible(
              child: Text(
                [
                  if (visitor.roomNumber != null) 'Room ${visitor.roomNumber}',
                  if (visitor.arrivalLabel != null) visitor.arrivalLabel!,
                ].join(' · '),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.35),
                  fontSize: 11,
                ),
              ),
            ),
          ],
        ),
        if ((visitor.reference ?? visitor.passCode) != null) ...[
          const SizedBox(height: 4),
          Text(
            visitor.reference ?? visitor.passCode!,
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
    // Three distinct states: still inside, checked out, or never arrived yet
    // (pending) — a departed visitor must never read the same as one who
    // simply hasn't shown up.
    final (label, color) = switch ((visitor.isInside, visitor.isExited)) {
      (true, _) => ('Inside', _green),
      (false, true) => ('Exited', Colors.white.withValues(alpha: 0.5)),
      (false, false) => ('Not Inside', Colors.white),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: visitor.isInside
            ? _green.withValues(alpha: 0.15)
            : Colors.white.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: AppTypography.style(
          color: color,
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
  const VisitorPassRequestCard({
    super.key,
    required this.request,
    this.onVerify,
  });

  final VisitorPassRequest request;
  final VoidCallback? onVerify;

  @override
  Widget build(BuildContext context) {
    final pending = !request.isVerified;
    final accent = pending ? AppColors.gold : _green;

    // Figma 257:1337 / 257:1377 / 257:1421 — a pending request is called out with
    // a gold left edge; an already-verified one sits back in a plain hairline.
    //
    // The accent edge is painted as its own bar rather than as a coloured
    // BorderSide: Flutter refuses to paint a rounded box whose sides are
    // different colours ("a borderRadius can only be given on borders with
    // uniform colors"), which threw on every card and left the column blank.
    final edge = Colors.white.withValues(alpha: 0.07);
    final radius = BorderRadius.circular(16);

    return ClipRRect(
      borderRadius: radius,
      child: Stack(
        children: [
          Container(
            padding: EdgeInsets.fromLTRB(pending ? 17 : 15, 15, 15, 15),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.03),
              borderRadius: radius,
              border: Border.all(color: edge),
            ),
            child: _body(pending, accent),
          ),
          if (pending)
            Positioned(
              left: 0,
              top: 0,
              bottom: 0,
              width: 3,
              child: ColoredBox(color: AppColors.gold),
            ),
        ],
      ),
    );
  }

  Widget _body(bool pending, Color accent) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _avatar(request.initials),
            const SizedBox(width: 12),
            Expanded(child: _details(accent)),
          ],
        ),
        // How to reach the visitor, and when they were invited (Figma
        // 257:1359). Only worth the space while somebody still has to be let
        // in — once verified the officer is done with them.
        if (pending && _hasContactDetails) ...[
          const SizedBox(height: 12),
          _contactBlock(),
        ],
        if (pending) ...[const SizedBox(height: 15), _verifyButton()],
      ],
    );
  }

  bool get _hasContactDetails =>
      (request.email ?? '').isNotEmpty ||
      (request.whatsapp ?? '').isNotEmpty ||
      (request.submittedLabel ?? '').isNotEmpty;

  Widget _contactBlock() {
    return Container(
      padding: const EdgeInsets.only(top: 13),
      decoration: BoxDecoration(
        border: Border(
          top: BorderSide(color: Colors.white.withValues(alpha: 0.07)),
        ),
      ),
      child: Column(
        children: [
          if ((request.email ?? '').isNotEmpty)
            _contactRow('Email', request.email!),
          if ((request.whatsapp ?? '').isNotEmpty)
            _contactRow('WhatsApp Number', request.whatsapp!),
          if ((request.submittedLabel ?? '').isNotEmpty)
            _contactRow('Submitted', request.submittedLabel!),
        ],
      ),
    );
  }

  Widget _contactRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.3),
              fontSize: 11,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.65),
                fontSize: 11,
              ),
            ),
          ),
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
        const SizedBox(height: 2),
        // Figma 257:1347 — the suite sits a size above the room/time, not all
        // one flat run of dot-separated text.
        Row(
          crossAxisAlignment: CrossAxisAlignment.baseline,
          textBaseline: TextBaseline.alphabetic,
          children: [
            if (request.suiteName != null)
              Flexible(
                child: Text(
                  request.suiteName!,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 12,
                  ),
                ),
              ),
            if (request.suiteName != null) const SizedBox(width: 5),
            Flexible(
              child: Text(
                [
                  if (request.roomNumber != null) 'Room ${request.roomNumber}',
                  if (request.isVerified &&
                      (request.arrivalLabel ?? '').isNotEmpty)
                    request.arrivalLabel!
                  else if ((request.submittedLabel ?? '').isNotEmpty)
                    request.submittedLabel!,
                ].join(' · '),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.35),
                  fontSize: 11,
                ),
              ),
            ),
          ],
        ),
        if (request.onlineCode != null || request.offlineCode != null) ...[
          const SizedBox(height: 4),
          _codeRow(accent),
        ],
      ],
    );
  }

  /// "Online Code  -  482913  ·  VP-2401-101" (Figma 257:1351).
  Widget _codeRow(Color accent) {
    final online = (request.onlineCode ?? '').isNotEmpty;
    final code = online ? request.onlineCode! : (request.offlineCode ?? '');

    // A Wrap, not a Row: the code is the one thing on this card an officer has
    // to read character for character, so it must never be ellipsized to make
    // room for the case number. On a narrow column the reference drops to the
    // next line instead of overflowing the card.
    return Wrap(
      crossAxisAlignment: WrapCrossAlignment.center,
      spacing: 8,
      runSpacing: 2,
      children: [
        Text.rich(
          TextSpan(
            children: [
              TextSpan(
                text: online ? 'Online Code  -  ' : 'Offline Code  -  ',
                style: AppTypography.style(color: accent, fontSize: 12),
              ),
              TextSpan(
                text: code,
                style: AppTypography.style(
                  color: accent,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.4,
                ),
              ),
            ],
          ),
        ),
        if ((request.reference ?? '').isNotEmpty)
          Text.rich(
            TextSpan(
              children: [
                TextSpan(
                  text: '·  ',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.2),
                    fontSize: 10,
                  ),
                ),
                TextSpan(
                  text: request.reference!,
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.25),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
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
