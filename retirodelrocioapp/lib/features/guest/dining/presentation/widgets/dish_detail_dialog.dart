import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/dining/domain/menu_item.dart';

/// The "Add to Cart" dish detail popup (Figma 128:9779) — the dish photo,
/// price, description (with a "Read More" toggle for long copy), an
/// optional order note, a quantity stepper and the Add to Cart CTA. Returns
/// the [CartLine] the guest built, or `null` if they closed the dialog
/// without adding anything.
Future<CartLine?> showDishDetailDialog(
  BuildContext context, {
  required MenuItem item,
}) {
  return showDialog<CartLine>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.85),
    builder: (_) => _DishDetailDialog(item: item),
  );
}

class _DishDetailDialog extends StatefulWidget {
  const _DishDetailDialog({required this.item});

  final MenuItem item;

  @override
  State<_DishDetailDialog> createState() => _DishDetailDialogState();
}

class _DishDetailDialogState extends State<_DishDetailDialog> {
  int _qty = 1;
  bool _expanded = false;
  final _noteController = TextEditingController();

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  void _addToCart() {
    Navigator.of(
      context,
    ).pop(CartLine(item: widget.item, qty: _qty, note: _noteController.text));
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;
    final description = item.description ?? '';
    final isLong = description.length > 180;
    final shownDescription = !_expanded && isLong
        ? '${description.substring(0, 180)}…'
        : description;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 860, maxHeight: 560),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(24),
          child: ColoredBox(
            color: const Color(0xFF14181C),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(flex: 5, child: _imagePanel(item)),
                Expanded(
                  flex: 6,
                  child: _detailPanel(shownDescription, isLong),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _imagePanel(MenuItem item) {
    return Stack(
      fit: StackFit.expand,
      children: [
        item.imageUrl != null
            ? Image.network(item.imageUrl!, fit: BoxFit.cover)
            : Container(
                color: Colors.white.withValues(alpha: 0.06),
                alignment: Alignment.center,
                child: Icon(
                  Icons.restaurant_rounded,
                  size: 48,
                  color: Colors.white.withValues(alpha: 0.2),
                ),
              ),
        Positioned(
          left: 16,
          top: 16,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.9),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              item.categoryLabel.toUpperCase(),
              style: AppTypography.style(
                color: const Color(0xFF0A0F1E),
                fontSize: 9,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.72,
              ),
            ),
          ),
        ),
        Positioned(
          left: 16,
          right: 16,
          bottom: 16,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.name,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (item.prepLabel != null) ...[
                const SizedBox(height: 4),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.access_time_rounded,
                      size: 13,
                      color: Colors.white.withValues(alpha: 0.6),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      item.prepLabel!,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.6),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _detailPanel(String shownDescription, bool isLong) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  widget.item.priceLabel.replaceFirst('NGN', 'NGN '),
                  style: AppTypography.style(
                    color: AppColors.gold,
                    fontSize: 24,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              _closeButton(),
            ],
          ),
          const SizedBox(height: 12),
          Expanded(
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    shownDescription,
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.7),
                      fontSize: 14,
                      height: 1.5,
                    ),
                  ),
                  if (isLong) ...[
                    const SizedBox(height: 4),
                    InkWell(
                      onTap: () => setState(() => _expanded = !_expanded),
                      child: Text(
                        _expanded ? 'Show Less' : 'Read More',
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.5),
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 20),
                  Text(
                    'Order Note (Optional)',
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _noteController,
                    maxLines: 3,
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 13,
                    ),
                    decoration: InputDecoration(
                      hintText:
                          'Any dietary requirements or special requests...',
                      hintStyle: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.35),
                        fontSize: 13,
                      ),
                      filled: true,
                      fillColor: Colors.white.withValues(alpha: 0.05),
                      contentPadding: const EdgeInsets.all(14),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(14),
                        borderSide: BorderSide(
                          color: Colors.white.withValues(alpha: 0.1),
                          width: 0.8,
                        ),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(14),
                        borderSide: BorderSide(
                          color: Colors.white.withValues(alpha: 0.1),
                          width: 0.8,
                        ),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(14),
                        borderSide: BorderSide(
                          color: AppColors.gold.withValues(alpha: 0.5),
                          width: 1,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _qtyStepper(),
              const SizedBox(width: 16),
              Expanded(child: _addToCartButton()),
            ],
          ),
        ],
      ),
    );
  }

  Widget _closeButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.1),
      shape: const CircleBorder(),
      child: InkWell(
        onTap: () => Navigator.of(context).pop(),
        customBorder: const CircleBorder(),
        child: const SizedBox(
          width: 32,
          height: 32,
          child: Icon(Icons.close_rounded, size: 16, color: Colors.white),
        ),
      ),
    );
  }

  Widget _qtyStepper() {
    return Container(
      height: 44,
      padding: const EdgeInsets.symmetric(horizontal: 4),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _stepperButton(
            Icons.remove_rounded,
            onTap: _qty > 1 ? () => setState(() => _qty--) : null,
          ),
          SizedBox(
            width: 28,
            child: Text(
              '$_qty',
              textAlign: TextAlign.center,
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 14,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          _stepperButton(
            Icons.add_rounded,
            onTap: () => setState(() => _qty++),
          ),
        ],
      ),
    );
  }

  Widget _stepperButton(IconData icon, {required VoidCallback? onTap}) {
    final enabled = onTap != null;
    return Material(
      color: Colors.transparent,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 26,
          height: 26,
          child: Icon(
            icon,
            size: 14,
            color: enabled ? Colors.white : Colors.white.withValues(alpha: 0.2),
          ),
        ),
      ),
    );
  }

  Widget _addToCartButton() {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: _addToCart,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: 50,
          alignment: Alignment.center,
          child: Text(
            'Add to Cart',
            style: AppTypography.style(
              color: Colors.black,
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }
}
