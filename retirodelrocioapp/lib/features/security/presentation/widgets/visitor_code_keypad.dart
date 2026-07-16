import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';

/// How the six code boxes read: entering (neutral, next box focused), error
/// (code not recognised — red), or success (a live match — green).
enum CodeBoxStyle { neutral, error, success }

const Color _green = Color(0xFF22C55E);
const Color _red = Color(0xFFFF4D4D);

/// The six-digit entry-code display (Figma 229:13150 / 232:13909 / 237:14208).
class VisitorCodeBoxes extends StatelessWidget {
  const VisitorCodeBoxes({
    super.key,
    required this.code,
    required this.style,
    this.length = 6,
  });

  final String code;
  final CodeBoxStyle style;
  final int length;

  @override
  Widget build(BuildContext context) {
    final accent = switch (style) {
      CodeBoxStyle.error => _red,
      CodeBoxStyle.success => _green,
      CodeBoxStyle.neutral => AppColors.gold,
    };

    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (var i = 0; i < length; i++) ...[
          if (i > 0) const SizedBox(width: 12),
          _box(i, accent),
        ],
      ],
    );
  }

  Widget _box(int index, Color accent) {
    final filled = index < code.length;
    final isFocus = style == CodeBoxStyle.neutral && index == code.length;

    // Neutral empty boxes stay quiet; the next-to-fill one takes the gold border.
    final borderColor = switch (style) {
      CodeBoxStyle.error => _red,
      CodeBoxStyle.success => _green,
      CodeBoxStyle.neutral =>
        isFocus ? AppColors.gold : Colors.white.withValues(alpha: 0.12),
    };

    final textColor = switch (style) {
      CodeBoxStyle.error => _red,
      CodeBoxStyle.success => _green,
      CodeBoxStyle.neutral => Colors.white,
    };

    return Container(
      width: 44,
      height: 56,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: borderColor, width: isFocus ? 1.4 : 1),
      ),
      child: Text(
        filled ? code[index] : '',
        style: AppTypography.style(
          color: textColor,
          fontSize: 24,
          fontWeight: FontWeight.w700,
        ).copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
      ),
    );
  }
}

/// The numeric keypad: 1–9, then 0 and a backspace. Disabled while a lookup is in
/// flight so a second digit cannot race the verify request.
class VisitorNumericPad extends StatelessWidget {
  const VisitorNumericPad({
    super.key,
    required this.onDigit,
    required this.onBackspace,
    this.enabled = true,
  });

  final ValueChanged<String> onDigit;
  final VoidCallback onBackspace;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (final row in const [
          ['1', '2', '3'],
          ['4', '5', '6'],
          ['7', '8', '9'],
        ]) ...[
          _row([for (final d in row) _digitKey(d)]),
          const SizedBox(height: 8),
        ],
        _row([
          const Spacer(),
          Expanded(child: _digitKey('0')),
          Expanded(
            child: _key(
              child: Icon(
                Icons.backspace_outlined,
                size: 20,
                color: Colors.white.withValues(alpha: enabled ? 0.7 : 0.25),
              ),
              onTap: enabled ? onBackspace : null,
            ),
          ),
        ]),
      ],
    );
  }

  Widget _row(List<Widget> children) {
    return Row(
      children: [
        for (var i = 0; i < children.length; i++) ...[
          if (i > 0) const SizedBox(width: 8),
          if (children[i] is Expanded || children[i] is Spacer)
            children[i]
          else
            Expanded(child: children[i]),
        ],
      ],
    );
  }

  Widget _digitKey(String digit) {
    return _key(
      onTap: enabled ? () => onDigit(digit) : null,
      child: Text(
        digit,
        style: AppTypography.style(
          color: Colors.white.withValues(alpha: enabled ? 1 : 0.3),
          fontSize: 22,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _key({required Widget child, VoidCallback? onTap}) {
    return Material(
      color: Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          height: 60,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: child,
        ),
      ),
    );
  }
}
