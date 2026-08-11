import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/dining/domain/menu_item.dart';

/// The "Add to Cart" dish detail popup (Figma 128:9779 → modal node
/// 121:7975) — the dish photo, price, description (with a "Read More"
/// toggle for long copy), an optional order note, a quantity stepper and
/// the Add to Cart CTA. Returns the [CartLine] the guest built, or `null`
/// if they closed the dialog without adding anything.
Future<CartLine?> showDishDetailDialog(
  BuildContext context, {
  required MenuItem item,
}) {
  return showDialog<CartLine>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.8),
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
        constraints: const BoxConstraints(maxWidth: 879, maxHeight: 563),
        child: Container(
          decoration: BoxDecoration(
            color: const Color(0xFF1B1D1A).withValues(alpha: 0.97),
            borderRadius: BorderRadius.circular(28),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(28),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(width: 368, child: _imagePanel(item)),
                Expanded(child: _detailPanel(shownDescription, isLong)),
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
        // Bottom-to-top scrim so the name/prep-time text reads over any photo.
        DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.bottomCenter,
              end: Alignment.topCenter,
              colors: [
                const Color(0xFF1B1D1A).withValues(alpha: 0.88),
                const Color(0xFF1B1D1A).withValues(alpha: 0.2),
                const Color(0xFF1B1D1A).withValues(alpha: 0),
              ],
              stops: const [0, 0.5, 1],
            ),
          ),
        ),
        Positioned(
          left: 16,
          top: 16,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.92),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              item.categoryLabel.toUpperCase(),
              style: AppTypography.style(
                color: const Color(0xFF0A0F1E),
                fontSize: 10,
                fontWeight: FontWeight.w700,
                letterSpacing: 1,
              ),
            ),
          ),
        ),
        Positioned(
          left: 20,
          right: 20,
          bottom: 20,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                item.name,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (item.prepLabel != null) ...[
                const SizedBox(height: 10),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.access_time_rounded,
                      size: 13,
                      color: Colors.white.withValues(alpha: 0.55),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      item.prepLabel!,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.55),
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
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  widget.item.priceLabel.replaceFirst('NGN', 'NGN '),
                  style: AppTypography.style(
                    color: AppColors.gold,
                    fontSize: 26,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              _closeButton(),
            ],
          ),
        ),
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 0, 24, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  shownDescription,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 16,
                    height: 1.34,
                  ),
                ),
                if (isLong) ...[
                  const SizedBox(height: 5),
                  InkWell(
                    onTap: () => setState(() => _expanded = !_expanded),
                    child: Text(
                      _expanded ? 'Show Less' : 'Read More',
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.5),
                        fontSize: 13,
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 30),
                Text(
                  'Order Note (Optional)',
                  style: AppTypography.style(color: Colors.white, fontSize: 13),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _noteController,
                  minLines: 2,
                  maxLines: 3,
                  style: AppTypography.style(color: Colors.white, fontSize: 13),
                  decoration: InputDecoration(
                    hintText: 'Any dietary requirements or special requests…',
                    hintStyle: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.37),
                      fontSize: 12,
                    ),
                    filled: true,
                    fillColor: Colors.white.withValues(alpha: 0.04),
                    contentPadding: const EdgeInsets.all(15),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: BorderSide(
                        color: Colors.white.withValues(alpha: 0.08),
                        width: 0.8,
                      ),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: BorderSide(
                        color: Colors.white.withValues(alpha: 0.08),
                        width: 0.8,
                      ),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: BorderSide(
                        color: AppColors.gold.withValues(alpha: 0.5),
                        width: 1,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
              ],
            ),
          ),
        ),
        Container(
          padding: const EdgeInsets.fromLTRB(24, 16.8, 24, 16),
          decoration: BoxDecoration(
            border: Border(
              top: BorderSide(
                color: Colors.white.withValues(alpha: 0.07),
                width: 0.8,
              ),
            ),
          ),
          child: Row(
            children: [
              _qtyStepper(),
              const SizedBox(width: 12),
              Expanded(child: _addToCartButton()),
            ],
          ),
        ),
      ],
    );
  }

  Widget _closeButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: () => Navigator.of(context).pop(),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          alignment: Alignment.center,
          child: const Icon(Icons.close_rounded, size: 16, color: Colors.white),
        ),
      ),
    );
  }

  Widget _qtyStepper() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12.8, vertical: 8.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(16),
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
            background: Colors.white.withValues(alpha: 0.08),
            iconColor: _qty > 1
                ? Colors.white
                : Colors.white.withValues(alpha: 0.25),
            onTap: _qty > 1 ? () => setState(() => _qty--) : null,
          ),
          const SizedBox(width: 12),
          SizedBox(
            width: 20,
            child: Text(
              '$_qty',
              textAlign: TextAlign.center,
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 15,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(width: 12),
          _stepperButton(
            Icons.add_rounded,
            background: AppColors.gold,
            iconColor: const Color(0xFF0A0F1E),
            onTap: () => setState(() => _qty++),
          ),
        ],
      ),
    );
  }

  Widget _stepperButton(
    IconData icon, {
    required Color background,
    required Color iconColor,
    required VoidCallback? onTap,
  }) {
    return Material(
      color: background,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: SizedBox(
          width: 28,
          height: 28,
          child: Icon(icon, size: 12, color: iconColor),
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
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: Center(
            child: Text(
              'Add to Cart',
              style: AppTypography.style(
                color: const Color(0xFF1B1D1A),
                fontSize: 14,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
