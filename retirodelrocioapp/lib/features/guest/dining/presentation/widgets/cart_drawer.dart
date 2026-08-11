import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/dining/domain/menu_item.dart';

/// The slide-in "Your Order" cart drawer (Figma 124:8256 filled / 126:8615
/// empty) — the line items with a qty stepper and remove button, the
/// subtotal/service-fee/total breakdown, a delivery note, and the Place
/// Order CTA. Cart state itself lives on the parent screen; this widget is
/// purely a presenter over it, same as Cinema's sidebar panels.
class CartDrawer extends StatelessWidget {
  const CartDrawer({
    super.key,
    required this.cart,
    required this.serviceFee,
    required this.roomLabel,
    required this.busy,
    required this.onClose,
    required this.onIncrement,
    required this.onDecrement,
    required this.onRemove,
    required this.onPlaceOrder,
  });

  final List<CartLine> cart;
  final int serviceFee;
  final String roomLabel;
  final bool busy;
  final VoidCallback onClose;
  final ValueChanged<CartLine> onIncrement;
  final ValueChanged<CartLine> onDecrement;
  final ValueChanged<CartLine> onRemove;
  final VoidCallback onPlaceOrder;

  static final _naira = NumberFormat('#,###');

  int get _subtotal => cart.fold(0, (sum, line) => sum + line.lineTotal);

  /// 7.5% VAT on the subtotal, matching every other guest checkout — the
  /// server recomputes and is authoritative; this is just a preview.
  int get _vat => cart.isEmpty ? 0 : (_subtotal * 0.075).round();
  int get _total => _subtotal + _vat + (cart.isEmpty ? 0 : serviceFee);
  int get _itemCount => cart.fold(0, (sum, line) => sum + line.qty);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 380,
      color: const Color(0xFF1B1D1A).withValues(alpha: 0.96),
      child: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _header(),
            Expanded(child: cart.isEmpty ? _emptyState() : _lineItems()),
            if (cart.isNotEmpty) _footer(),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 16, 16),
      child: Row(
        children: [
          Icon(Icons.shopping_cart_rounded, size: 18, color: AppColors.gold),
          const SizedBox(width: 8),
          Text(
            'Your Order',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(width: 10),
          if (_itemCount > 0)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(999),
                border: Border.all(
                  color: AppColors.gold.withValues(alpha: 0.3),
                  width: 0.8,
                ),
              ),
              child: Text(
                '$_itemCount item${_itemCount == 1 ? '' : 's'}',
                style: AppTypography.style(
                  color: AppColors.gold,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          const Spacer(),
          Material(
            color: Colors.white.withValues(alpha: 0.2),
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              onTap: onClose,
              borderRadius: BorderRadius.circular(16),
              child: const SizedBox(
                width: 32,
                height: 32,
                child: Icon(Icons.close_rounded, size: 16, color: Colors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _emptyState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.shopping_cart_outlined,
            size: 40,
            color: Colors.white.withValues(alpha: 0.3),
          ),
          const SizedBox(height: 16),
          Text(
            'Your cart is empty.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.7),
              fontSize: 14,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Add dishes to get started.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.7),
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  Widget _lineItems() {
    return ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      itemCount: cart.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (_, i) => _lineItem(cart[i]),
    );
  }

  Widget _lineItem(CartLine line) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.07),
          width: 0.8,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.06),
              borderRadius: BorderRadius.circular(14),
              image: line.item.imageUrl != null
                  ? DecorationImage(
                      image: NetworkImage(line.item.imageUrl!),
                      fit: BoxFit.cover,
                    )
                  : null,
            ),
            child: line.item.imageUrl == null
                ? Icon(
                    Icons.restaurant_rounded,
                    size: 20,
                    color: Colors.white.withValues(alpha: 0.25),
                  )
                : null,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        line.item.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.style(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    InkWell(
                      onTap: () => onRemove(line),
                      child: Icon(
                        Icons.delete_outline_rounded,
                        size: 17,
                        color: Colors.white.withValues(alpha: 0.35),
                      ),
                    ),
                  ],
                ),
                Text(
                  'NGN ${_naira.format(line.item.price)}',
                  style: AppTypography.style(
                    color: AppColors.gold,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if ((line.note ?? '').isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    line.note!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.4),
                      fontSize: 11,
                    ),
                  ),
                ],
                const SizedBox(height: 6),
                _qtyStepper(line),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _qtyStepper(CartLine line) {
    return Row(
      children: [
        _stepperButton(Icons.remove_rounded, () => onDecrement(line)),
        SizedBox(
          width: 28,
          child: Text(
            '${line.qty}',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        _stepperButton(Icons.add_rounded, () => onIncrement(line)),
      ],
    );
  }

  Widget _stepperButton(IconData icon, VoidCallback onTap) {
    return Material(
      color: Colors.white.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          width: 26,
          height: 26,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: Icon(icon, size: 13, color: Colors.white),
        ),
      ),
    );
  }

  Widget _footer() {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
      decoration: BoxDecoration(
        border: Border(
          top: BorderSide(
            color: Colors.white.withValues(alpha: 0.08),
            width: 0.8,
          ),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _summaryRow('Subtotal', 'NGN ${_naira.format(_subtotal)}'),
          const SizedBox(height: 8),
          _summaryRow('VAT (7.5%)', 'NGN ${_naira.format(_vat)}'),
          const SizedBox(height: 8),
          _summaryRow('Room Service Fee', 'NGN ${_naira.format(serviceFee)}'),
          const SizedBox(height: 12),
          Container(height: 1, color: Colors.white.withValues(alpha: 0.08)),
          const SizedBox(height: 12),
          Row(
            children: [
              Text(
                'Total',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const Spacer(),
              Text(
                'NGN ${_naira.format(_total)}',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.2),
                width: 0.8,
              ),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.delivery_dining_rounded,
                  size: 15,
                  color: Colors.white.withValues(alpha: 0.7),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Delivered to $roomLabel • Est. 25–35 mins',
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Material(
            color: AppColors.gold,
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              onTap: busy ? null : onPlaceOrder,
              borderRadius: BorderRadius.circular(16),
              child: Container(
                height: 50,
                alignment: Alignment.center,
                child: busy
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.2,
                          color: Colors.black,
                        ),
                      )
                    : Text(
                        'Place Order',
                        style: AppTypography.style(
                          color: const Color(0xFF0A0F1E),
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _summaryRow(String label, String value) {
    return Row(
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 13,
          ),
        ),
        const Spacer(),
        Text(
          value,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}
