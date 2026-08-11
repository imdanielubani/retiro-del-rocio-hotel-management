import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/dining/application/dining_providers.dart';
import 'package:retirodelrocioapp/features/guest/dining/domain/menu_item.dart';
import 'package:retirodelrocioapp/features/guest/dining/presentation/screens/guest_dining_orders_screen.dart';
import 'package:retirodelrocioapp/features/guest/dining/presentation/widgets/cart_drawer.dart';
import 'package:retirodelrocioapp/features/guest/dining/presentation/widgets/dining_dialogs.dart';
import 'package:retirodelrocioapp/features/guest/dining/presentation/widgets/dish_detail_dialog.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/screens/paystack_checkout_screen.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// Place Order (Dining) — browse the real restaurant menu, build a cart,
/// then pay — charged straight to the room, or paid directly via Paystack.
/// Follows the same payment pattern as Cinema and Spa & Wellness (Figma
/// 115:2578 / 124:8256 / 126:8615 / 128:9779).
class GuestDiningScreen extends ConsumerStatefulWidget {
  const GuestDiningScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<GuestDiningScreen> createState() => _GuestDiningScreenState();
}

class _GuestDiningScreenState extends ConsumerState<GuestDiningScreen> {
  String _search = '';
  String _category = 'All';
  bool _cartOpen = false;
  final List<CartLine> _cart = [];
  bool _placingOrder = false;

  static final _naira = NumberFormat('#,###');

  String get _token => widget.device.token;

  String get _roomLabel => widget.status.roomNumber != null
      ? 'Room ${widget.status.roomNumber}'
      : (widget.device.roomNumber != null
            ? 'Room ${widget.device.roomNumber}'
            : 'your room');

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GuestNotificationScreen(
          device: widget.device,
          status: widget.status,
        ),
      ),
    );
  }

  /// Opens My Orders and, if the guest tapped "Reorder" there, adds that
  /// order's dishes back to the cart at today's menu price/availability —
  /// the historical order only supplies which dishes and how many, not the
  /// price actually charged (matching what "reorder" means everywhere
  /// else: the same dishes again, not a frozen receipt).
  Future<void> _openOrders() async {
    final reorderItems = await Navigator.of(context).push<List<OrderLineItem>>(
      MaterialPageRoute(
        builder: (_) => GuestDiningOrdersScreen(
          device: widget.device,
          status: widget.status,
        ),
      ),
    );
    if (reorderItems == null || reorderItems.isEmpty || !mounted) return;

    final menu = ref.read(diningMenuProvider(_token)).value;
    if (menu == null) return;

    var addedAny = false;
    for (final line in reorderItems) {
      final match = menu.items
          .where((m) => m.id == line.menuItemId)
          .firstOrNull;
      if (match == null) continue;
      _addToCart(CartLine(item: match, qty: line.qty));
      addedAny = true;
    }

    if (addedAny && mounted) {
      setState(() => _cartOpen = true);
    } else if (mounted) {
      _toast('Those dishes are no longer on the menu.', error: true);
    }
  }

  void _toast(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: error
            ? const Color(0xFF7F1D1D)
            : const Color(0xFF14532D),
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  Future<void> _openDishDetail(MenuItem item) async {
    final line = await showDishDetailDialog(context, item: item);
    if (line == null || !mounted) return;
    _addToCart(line);
  }

  void _addToCart(CartLine line) {
    setState(() {
      final index = _cart.indexWhere((l) => l.item.id == line.item.id);
      if (index == -1) {
        _cart.add(line);
      } else {
        _cart[index] = _cart[index].copyWith(
          qty: _cart[index].qty + line.qty,
          note: (line.note ?? '').isNotEmpty ? line.note : _cart[index].note,
        );
      }
    });
  }

  void _increment(CartLine line) {
    setState(() {
      final index = _cart.indexWhere((l) => l.item.id == line.item.id);
      if (index != -1) {
        _cart[index] = _cart[index].copyWith(qty: _cart[index].qty + 1);
      }
    });
  }

  void _decrement(CartLine line) {
    setState(() {
      final index = _cart.indexWhere((l) => l.item.id == line.item.id);
      if (index == -1) return;
      if (_cart[index].qty <= 1) {
        _cart.removeAt(index);
      } else {
        _cart[index] = _cart[index].copyWith(qty: _cart[index].qty - 1);
      }
    });
  }

  void _remove(CartLine line) {
    setState(() => _cart.removeWhere((l) => l.item.id == line.item.id));
  }

  Map<String, dynamic> _buildPayload() => {
    'items': _cart.map((l) => l.toJson()).toList(),
  };

  Future<void> _placeOrder(int serviceFee) async {
    if (_cart.isEmpty || _placingOrder) return;
    setState(() => _placingOrder = true);

    final subtotal = _cart.fold<int>(0, (sum, l) => sum + l.lineTotal);
    // 7.5% VAT, matching every other guest checkout (Spa, Cinema, Room) —
    // the server recomputes and is authoritative; this is just a preview.
    final vat = (subtotal * 0.075).round();
    final total = subtotal + vat + serviceFee;
    final itemsLabel = _cart.map((l) => l.item.name).join(', ');
    final payload = _buildPayload();

    final confirmation = await showDiningPaymentDialog(
      context,
      itemsLabel: itemsLabel,
      subtotalLabel: 'NGN ${_naira.format(subtotal)}',
      vatLabel: 'NGN ${_naira.format(vat)}',
      serviceFeeLabel: 'NGN ${_naira.format(serviceFee)}',
      totalLabel: 'NGN ${_naira.format(total)}',
      onConfirmRoom: () => _bookToRoom(payload),
      onConfirmPaystack: () => _payWithPaystack(payload),
    );

    if (!mounted) {
      setState(() => _placingOrder = false);
      return;
    }
    setState(() => _placingOrder = false);

    if (confirmation == null) return;

    await showDiningOrderConfirmedDialog(context, confirmation: confirmation);
    if (mounted) {
      setState(() {
        _cart.clear();
        _cartOpen = false;
      });
      ref.invalidate(guestNotificationsProvider(_token));
    }
  }

  Future<DiningOrderConfirmation?> _bookToRoom(Map<String, dynamic> payload) {
    return ref.read(diningActionsProvider(_token)).bookToRoom(payload);
  }

  Future<DiningOrderConfirmation?> _payWithPaystack(
    Map<String, dynamic> payload,
  ) async {
    final quote = await ref
        .read(diningActionsProvider(_token))
        .initializePaystack(payload);
    if (!mounted) return null;

    final paid = await showPaystackCheckout(
      context,
      authorizationUrl: quote.authorizationUrl,
      callbackUrl: quote.callbackUrl,
    );
    if (!paid) return null;

    return ref
        .read(diningActionsProvider(_token))
        .confirmPaystack(quote.reference);
  }

  @override
  Widget build(BuildContext context) {
    final menuAsync = ref.watch(diningMenuProvider(_token));
    final menu = menuAsync.value ?? DiningMenu.empty;
    final weather = ref.watch(weatherProvider).value;
    final guest = widget.status.guest;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
          const ColoredBox(color: Color.fromARGB(243, 0, 0, 0)),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(25, 24, 25, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GuestTopBar(
                    suiteName: widget.status.suiteName ?? 'Suite',
                    roomNumber:
                        widget.status.roomNumber ??
                        widget.device.roomNumber ??
                        '—',
                    guestName: guest?.name ?? 'Guest',
                    weather: weather,
                    onNotifications: _openNotifications,
                    onProfile: () {},
                    hasUnreadNotifications:
                        ref.watch(guestUnreadNotificationsProvider(_token)) > 0,
                  ),
                  const SizedBox(height: 20),
                  _header(),
                  const SizedBox(height: 19),
                  _searchAndFilterRow(),
                  const SizedBox(height: 15),
                  _categoryChips(menu.categories),
                  const SizedBox(height: 20),
                  Expanded(
                    child: menuAsync.when(
                      data: (data) => _grid(data),
                      loading: () => menu.items.isNotEmpty
                          ? _grid(menu)
                          : const Center(
                              child: CircularProgressIndicator(
                                color: AppColors.gold,
                              ),
                            ),
                      error: (_, _) =>
                          menu.items.isNotEmpty ? _grid(menu) : _errorState(),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (_cartOpen) ...[
            Positioned.fill(
              child: GestureDetector(
                onTap: () => setState(() => _cartOpen = false),
                child: ColoredBox(color: Colors.black.withValues(alpha: 0.8)),
              ),
            ),
            Positioned(
              top: 0,
              right: 0,
              bottom: 0,
              child: CartDrawer(
                cart: _cart,
                serviceFee: menu.serviceFee,
                roomLabel: _roomLabel,
                busy: _placingOrder,
                onClose: () => setState(() => _cartOpen = false),
                onIncrement: _increment,
                onDecrement: _decrement,
                onRemove: _remove,
                onPlaceOrder: () => _placeOrder(menu.serviceFee),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _header() {
    final itemCount = _cart.fold<int>(0, (sum, l) => sum + l.qty);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Material(
          color: Colors.white.withValues(alpha: 0.06),
          shape: CircleBorder(
            side: BorderSide(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: InkWell(
            onTap: () => Navigator.of(context).maybePop(),
            customBorder: const CircleBorder(),
            child: SizedBox(
              width: 40,
              height: 40,
              child: Icon(
                Icons.arrow_back_rounded,
                size: 18,
                color: Colors.white.withValues(alpha: 0.8),
              ),
            ),
          ),
        ),
        const SizedBox(width: 15),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'In Suite Dining',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                'The Retiro Del Rocio Restaurant • Room Service Available 24/7',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        _pillButton(
          icon: Icons.history_rounded,
          label: 'My Orders',
          onTap: _openOrders,
        ),
        const SizedBox(width: 12),
        _cartButton(itemCount),
      ],
    );
  }

  Widget _pillButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.white.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 11),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.2),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 15, color: Colors.white.withValues(alpha: 0.7)),
              const SizedBox(width: 8),
              Text(
                label,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.7),
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _cartButton(int itemCount) {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: () => setState(() => _cartOpen = true),
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.shopping_cart_rounded,
                size: 16,
                color: Colors.black,
              ),
              const SizedBox(width: 8),
              Text(
                itemCount > 0 ? 'Cart ($itemCount)' : 'Cart',
                style: AppTypography.style(
                  color: Colors.black,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _searchAndFilterRow() {
    return Row(
      children: [
        Expanded(
          child: Container(
            height: 50,
            padding: const EdgeInsets.symmetric(horizontal: 17),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.2),
                width: 0.8,
              ),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.search_rounded,
                  size: 16,
                  color: Colors.white.withValues(alpha: 0.6),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    onChanged: (v) => setState(() => _search = v),
                    style: AppTypography.style(
                      color: Colors.white,
                      fontSize: 14,
                    ),
                    decoration: InputDecoration(
                      isDense: true,
                      border: InputBorder.none,
                      hintText: 'Search dishes and drinks…',
                      hintStyle: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.5),
                        fontSize: 14,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _categoryChips(List<String> categories) {
    final all = ['All', ...categories.map(_titleCase)];
    return SizedBox(
      height: 41,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: all.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (_, i) => _categoryChip(all[i]),
      ),
    );
  }

  String _titleCase(String s) =>
      s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';

  Widget _categoryChip(String label) {
    final selected = label == _category;
    return Material(
      color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _category = label),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.5)
                  : Colors.white.withValues(alpha: 0.2),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected
                  ? const Color(0xFF0A0F1E)
                  : Colors.white.withValues(alpha: 0.6),
              fontSize: 13,
              fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
            ),
          ),
        ),
      ),
    );
  }

  List<MenuItem> _filtered(DiningMenu menu) {
    return menu.items.where((item) {
      final matchesCategory =
          _category == 'All' || item.categoryLabel == _category;
      final matchesSearch =
          _search.trim().isEmpty ||
          item.name.toLowerCase().contains(_search.trim().toLowerCase());
      return matchesCategory && matchesSearch;
    }).toList();
  }

  Widget _grid(DiningMenu menu) {
    final items = _filtered(menu);
    if (items.isEmpty) return _emptyState();

    return SingleChildScrollView(
      child: Wrap(
        spacing: 20,
        runSpacing: 20,
        children: [
          for (final item in items)
            SizedBox(width: 260, child: _dishCard(item)),
        ],
      ),
    );
  }

  Widget _dishCard(MenuItem item) {
    return Material(
      color: const Color(0xFF1A211A),
      borderRadius: BorderRadius.circular(24),
      child: InkWell(
        onTap: () => _openDishDetail(item),
        borderRadius: BorderRadius.circular(24),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(24),
            border: Border.all(
              color: AppColors.gold.withValues(alpha: 0.2),
              width: 0.8,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  ClipRRect(
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(23),
                    ),
                    child: SizedBox(
                      height: 160,
                      width: double.infinity,
                      child: item.imageUrl != null
                          ? Image.network(item.imageUrl!, fit: BoxFit.cover)
                          : Container(
                              color: Colors.white.withValues(alpha: 0.05),
                              alignment: Alignment.center,
                              child: Icon(
                                Icons.restaurant_rounded,
                                size: 32,
                                color: Colors.white.withValues(alpha: 0.2),
                              ),
                            ),
                    ),
                  ),
                  Positioned(
                    left: 12,
                    top: 12,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.gold.withValues(alpha: 0.9),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        item.categoryLabel,
                        style: AppTypography.style(
                          color: const Color(0xFF0A0F1E),
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.72,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Text(
                          item.priceLabel,
                          style: AppTypography.style(
                            color: AppColors.gold,
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const Spacer(),
                        if (item.prepLabel != null)
                          Text(
                            item.prepLabel!,
                            style: AppTypography.style(
                              color: Colors.white.withValues(alpha: 0.5),
                              fontSize: 10,
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _emptyState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.restaurant_menu_rounded,
            size: 32,
            color: Colors.white.withValues(alpha: 0.3),
          ),
          const SizedBox(height: 12),
          Text(
            'No dishes match your search.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  Widget _errorState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_rounded,
            size: 30,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          const SizedBox(height: 12),
          Text(
            'Could not load the menu.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.6),
              fontSize: 15,
            ),
          ),
          const SizedBox(height: 16),
          Material(
            color: AppColors.gold,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              onTap: () => ref.invalidate(diningMenuProvider(_token)),
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 24,
                  vertical: 12,
                ),
                child: Text(
                  'Retry',
                  style: AppTypography.style(
                    color: AppColors.onGold,
                    fontSize: 14,
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
}
