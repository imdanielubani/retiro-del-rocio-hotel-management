import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/data/bar_repository.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_menu_item.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_tab_detail_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_scaffold.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_widgets.dart';

/// One drink on the cart, before it's rung up as a real order.
class _CartLine {
  _CartLine({required this.item});

  final BarMenuItem item;
  int qty = 1;
  String? note;

  int get lineTotal => item.price * qty;
}

/// The Bar Tablet's full Point of Sale — category-browse the drinks menu,
/// build a cart, and ring it up either against an existing open tab
/// ([tabId] set) or as a brand-new sale ([tabId] null — the checkout step
/// collects the table/guest info and opens the tab itself).
class BarPosScreen extends ConsumerStatefulWidget {
  const BarPosScreen({
    super.key,
    required this.session,
    this.tabId,
    this.tabLabel,
  });

  final StaffSession session;
  final int? tabId;
  final String? tabLabel;

  @override
  ConsumerState<BarPosScreen> createState() => _BarPosScreenState();
}

class _BarPosScreenState extends ConsumerState<BarPosScreen> {
  String _search = '';
  String _category = '';
  final List<_CartLine> _cart = [];
  bool _checkingOut = false;

  String get _token => widget.session.token;

  int get _subtotal => _cart.fold(0, (sum, l) => sum + l.lineTotal);

  /// 7.5% VAT on the subtotal, matching the guest tablet's Place Order —
  /// the server recomputes and is authoritative; this is just a preview.
  int get _vat => _cart.isEmpty ? 0 : (_subtotal * 0.075).round();

  void _addToCart(BarMenuItem item) {
    setState(() {
      final existing = _cart.indexWhere(
        (l) => l.item.id == item.id && (l.note == null || l.note!.isEmpty),
      );
      if (existing != -1) {
        _cart[existing].qty++;
      } else {
        _cart.add(_CartLine(item: item));
      }
    });
  }

  Future<void> _editLine(_CartLine line) async {
    final result = await showModalBottomSheet<_LineEditResult>(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF1E1E1E),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (_) => _CartLineEditor(line: line),
    );
    if (result == null) return;

    setState(() {
      if (result.remove) {
        _cart.remove(line);
      } else {
        line.qty = result.qty;
        line.note = result.note;
      }
    });
  }

  void _showFailure(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF7F1D1D),
        behavior: SnackBarBehavior.floating,
        content: Text(message),
      ),
    );
  }

  List<Map<String, dynamic>> get _cartPayload => _cart
      .map(
        (l) => {
          'menu_item_id': l.item.id,
          'qty': l.qty,
          if (l.note != null && l.note!.isNotEmpty) 'note': l.note,
        },
      )
      .toList();

  Future<void> _checkout() async {
    if (_cart.isEmpty) return;
    setState(() => _checkingOut = true);

    try {
      if (widget.tabId != null) {
        await ref
            .read(barActionsProvider(_token))
            .addOrderToTab(widget.tabId!, _cartPayload);
        if (mounted) Navigator.of(context).pop(true);
        return;
      }

      // Brand-new sale — collect table/guest info, open the tab, then send the cart.
      final details = await showModalBottomSheet<(String?, String?, bool)>(
        context: context,
        isScrollControlled: true,
        backgroundColor: const Color(0xFF1E1E1E),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        builder: (_) => const _CheckoutDetailsSheet(),
      );
      if (details == null || !mounted) {
        setState(() => _checkingOut = false);
        return;
      }

      final tab = await ref
          .read(barActionsProvider(_token))
          .openTab(
            tableLabel: details.$1,
            guestName: details.$2,
            isVip: details.$3,
          );
      await ref
          .read(barActionsProvider(_token))
          .addOrderToTab(tab.id, _cartPayload);

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) =>
              BarTabDetailScreen(session: widget.session, tabId: tab.id),
        ),
      );
    } on BarException catch (e) {
      _showFailure(e.message);
    } finally {
      if (mounted) setState(() => _checkingOut = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final menuAsync = ref.watch(barMenuProvider(_token));
    final menu = (menuAsync.value ?? const <BarMenuItem>[])
        .where((m) => m.isActive)
        .toList();

    final categories = menu.map((m) => m.category).toSet().toList()..sort();
    final categoryLabels = {for (final m in menu) m.category: m.categoryLabel};

    final filtered = menu.where((m) {
      final matchesCategory = _category.isEmpty || m.category == _category;
      final matchesSearch =
          _search.isEmpty ||
          m.name.toLowerCase().contains(_search.toLowerCase());
      return matchesCategory && matchesSearch;
    }).toList();

    return BarScaffold(
      session: widget.session,
      title: widget.tabLabel ?? 'Point of Sale',
      onBack: () => Navigator.of(context).pop(),
      body: LayoutBuilder(
        builder: (context, constraints) {
          final wide = constraints.maxWidth >= 760;
          final menuPane = _menuPane(
            menu,
            categories,
            categoryLabels,
            filtered,
          );
          final cartPane = _cartPane();

          if (wide) {
            return Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(flex: 3, child: menuPane),
                const SizedBox(width: 16),
                SizedBox(width: 340, child: cartPane),
              ],
            );
          }

          return Column(
            children: [
              Expanded(flex: 3, child: menuPane),
              const SizedBox(height: 12),
              SizedBox(height: 260, child: cartPane),
            ],
          );
        },
      ),
    );
  }

  Widget _menuPane(
    List<BarMenuItem> menu,
    List<String> categories,
    Map<String, String> categoryLabels,
    List<BarMenuItem> filtered,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        BarSearchField(
          hintText: 'Search drinks…',
          onChanged: (v) => setState(() => _search = v),
        ),
        const SizedBox(height: 10),
        SizedBox(
          height: 36,
          child: ListView(
            scrollDirection: Axis.horizontal,
            children: [
              BarFilterChip(
                label: 'All',
                selected: _category.isEmpty,
                onTap: () => setState(() => _category = ''),
              ),
              for (final c in categories)
                BarFilterChip(
                  label: categoryLabels[c] ?? c,
                  selected: _category == c,
                  onTap: () =>
                      setState(() => _category = _category == c ? '' : c),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Expanded(
          child: menu.isEmpty
              ? const BarEmptyState(
                  icon: Icons.local_bar_outlined,
                  message: 'No drinks are available right now.',
                )
              : filtered.isEmpty
              ? const BarEmptyState(
                  icon: Icons.search_off_rounded,
                  message: 'No drinks match.',
                )
              : GridView.builder(
                  gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: 190,
                    mainAxisExtent: 210,
                    crossAxisSpacing: 14,
                    mainAxisSpacing: 14,
                  ),
                  itemCount: filtered.length,
                  itemBuilder: (_, i) => _MenuTile(
                    item: filtered[i],
                    qtyInCart: _cart
                        .where((l) => l.item.id == filtered[i].id)
                        .fold<int>(0, (n, l) => n + l.qty),
                    onTap: () => _addToCart(filtered[i]),
                  ),
                ),
        ),
      ],
    );
  }

  Widget _cartPane() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF1B1D1A).withValues(alpha: 0.96),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.15),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.shopping_cart_rounded,
                size: 16,
                color: AppColors.gold,
              ),
              const SizedBox(width: 8),
              Text(
                'Cart',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const Spacer(),
              Text(
                '${_cart.fold<int>(0, (n, l) => n + l.qty)} items',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.5),
                  fontSize: 12,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Expanded(
            child: _cart.isEmpty
                ? Center(
                    child: Text(
                      'Tap a drink to add it here.',
                      textAlign: TextAlign.center,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.4),
                        fontSize: 13,
                      ),
                    ),
                  )
                : ListView.separated(
                    itemCount: _cart.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (_, i) {
                      final line = _cart[i];
                      return InkWell(
                        onTap: () => _editLine(line),
                        borderRadius: BorderRadius.circular(10),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: Row(
                            children: [
                              Container(
                                width: 44,
                                height: 44,
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.06),
                                  borderRadius: BorderRadius.circular(11),
                                  image: line.item.imageUrl != null
                                      ? DecorationImage(
                                          image: NetworkImage(
                                            line.item.imageUrl!,
                                          ),
                                          fit: BoxFit.cover,
                                        )
                                      : null,
                                ),
                                alignment: Alignment.center,
                                child: line.item.imageUrl == null
                                    ? Icon(
                                        Icons.local_bar_rounded,
                                        size: 16,
                                        color: Colors.white.withValues(
                                          alpha: 0.25,
                                        ),
                                      )
                                    : null,
                              ),
                              const SizedBox(width: 10),
                              Container(
                                width: 20,
                                height: 20,
                                alignment: Alignment.center,
                                decoration: BoxDecoration(
                                  color: AppColors.gold.withValues(alpha: 0.14),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  '${line.qty}',
                                  style: AppTypography.style(
                                    color: AppColors.gold,
                                    fontSize: 10,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      line.item.name,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: AppTypography.style(
                                        color: Colors.white,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                    if (line.note != null &&
                                        line.note!.isNotEmpty)
                                      Text(
                                        line.note!,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: AppTypography.style(
                                          color: Colors.white.withValues(
                                            alpha: 0.45,
                                          ),
                                          fontSize: 11,
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                              Text(
                                '₦${line.lineTotal}',
                                style: AppTypography.style(
                                  color: Colors.white,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
          const SizedBox(height: 10),
          Container(height: 1, color: Colors.white.withValues(alpha: 0.08)),
          const SizedBox(height: 10),
          _SummaryRow(label: 'Subtotal', value: '₦$_subtotal'),
          const SizedBox(height: 6),
          _SummaryRow(label: 'VAT (7.5%)', value: '₦$_vat'),
          const SizedBox(height: 4),
          Text(
            '+ service fee applied at checkout',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.35),
              fontSize: 11,
            ),
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: Material(
              color: _cart.isEmpty
                  ? Colors.white.withValues(alpha: 0.08)
                  : AppColors.gold,
              borderRadius: BorderRadius.circular(14),
              child: InkWell(
                borderRadius: BorderRadius.circular(14),
                onTap: _cart.isEmpty || _checkingOut ? null : _checkout,
                child: Center(
                  child: _checkingOut
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: AppColors.gold,
                          ),
                        )
                      : Text(
                          widget.tabId != null
                              ? 'Add to Tab'
                              : 'Start Tab & Send',
                          style: AppTypography.style(
                            color: _cart.isEmpty
                                ? Colors.white.withValues(alpha: 0.4)
                                : const Color(0xFF0A0F1E),
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                          ),
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

/// A drink tile on the POS grid — mirrors the guest tablet's Place Order
/// dish card (image, category badge, name + price) so the two menus read as
/// the same product, just for different audiences.
/// A "Subtotal / VAT / Total"-style row in the cart footer.
class _SummaryRow extends StatelessWidget {
  const _SummaryRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.6),
            fontSize: 13,
          ),
        ),
        Text(
          value,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 13,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _MenuTile extends StatelessWidget {
  const _MenuTile({
    required this.item,
    required this.qtyInCart,
    required this.onTap,
  });

  final BarMenuItem item;
  final int qtyInCart;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFF1A211A),
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
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
                      top: Radius.circular(19),
                    ),
                    child: SizedBox(
                      height: 100,
                      width: double.infinity,
                      child: item.imageUrl != null
                          ? Image.network(item.imageUrl!, fit: BoxFit.cover)
                          : Container(
                              color: Colors.white.withValues(alpha: 0.05),
                              alignment: Alignment.center,
                              child: Icon(
                                item.isAlcoholic
                                    ? Icons.local_bar_rounded
                                    : Icons.local_drink_rounded,
                                size: 26,
                                color: Colors.white.withValues(alpha: 0.2),
                              ),
                            ),
                    ),
                  ),
                  Positioned(
                    left: 8,
                    top: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 7,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.gold.withValues(alpha: 0.9),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        item.categoryLabel,
                        style: AppTypography.style(
                          color: const Color(0xFF0A0F1E),
                          fontSize: 8,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ),
                  ),
                  if (qtyInCart > 0)
                    Positioned(
                      right: 8,
                      top: 8,
                      child: Container(
                        width: 22,
                        height: 22,
                        alignment: Alignment.center,
                        decoration: const BoxDecoration(
                          color: AppColors.gold,
                          shape: BoxShape.circle,
                        ),
                        child: Text(
                          '$qtyInCart',
                          style: AppTypography.style(
                            color: const Color(0xFF0A0F1E),
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        Text(
                          item.priceLabel,
                          style: AppTypography.style(
                            color: AppColors.gold,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const Spacer(),
                        if (item.isAlcoholic)
                          Icon(
                            Icons.local_bar_rounded,
                            size: 12,
                            color: Colors.amber.withValues(alpha: 0.8),
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
}

class _LineEditResult {
  const _LineEditResult({
    required this.qty,
    required this.note,
    this.remove = false,
  });

  final int qty;
  final String? note;
  final bool remove;
}

class _CartLineEditor extends StatefulWidget {
  const _CartLineEditor({required this.line});

  final _CartLine line;

  @override
  State<_CartLineEditor> createState() => _CartLineEditorState();
}

class _CartLineEditorState extends State<_CartLineEditor> {
  late int _qty = widget.line.qty;
  late final TextEditingController _noteController = TextEditingController(
    text: widget.line.note,
  );

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                widget.line.item.name,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Quantity',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.7),
                      fontSize: 14,
                    ),
                  ),
                  Row(
                    children: [
                      _StepButton(
                        icon: Icons.remove_rounded,
                        onTap: () {
                          if (_qty > 1) setState(() => _qty--);
                        },
                      ),
                      SizedBox(
                        width: 40,
                        child: Text(
                          '$_qty',
                          textAlign: TextAlign.center,
                          style: AppTypography.style(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      _StepButton(
                        icon: Icons.add_rounded,
                        onTap: () => setState(() => _qty++),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Text(
                'Note',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.7),
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 8),
              Container(
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.06),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.1),
                    width: 0.8,
                  ),
                ),
                child: TextField(
                  controller: _noteController,
                  maxLines: 2,
                  style: AppTypography.style(color: Colors.white, fontSize: 14),
                  decoration: InputDecoration(
                    isDense: true,
                    contentPadding: const EdgeInsets.all(12),
                    border: InputBorder.none,
                    hintText: 'e.g. No ice, extra lime',
                    hintStyle: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.3),
                      fontSize: 13,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: SizedBox(
                      height: 48,
                      child: Material(
                        color: const Color(0xFFDC2626).withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(14),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () => Navigator.of(context).pop(
                            const _LineEditResult(
                              qty: 0,
                              note: null,
                              remove: true,
                            ),
                          ),
                          child: Center(
                            child: Text(
                              'Remove',
                              style: AppTypography.style(
                                color: const Color(0xFFDC2626),
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: SizedBox(
                      height: 48,
                      child: Material(
                        color: AppColors.gold,
                        borderRadius: BorderRadius.circular(14),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () => Navigator.of(context).pop(
                            _LineEditResult(
                              qty: _qty,
                              note: _noteController.text.trim(),
                            ),
                          ),
                          child: Center(
                            child: Text(
                              'Update',
                              style: AppTypography.style(
                                color: const Color(0xFF0A0F1E),
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StepButton extends StatelessWidget {
  const _StepButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Icon(icon, size: 16, color: Colors.white),
        ),
      ),
    );
  }
}

class _CheckoutDetailsSheet extends StatefulWidget {
  const _CheckoutDetailsSheet();

  @override
  State<_CheckoutDetailsSheet> createState() => _CheckoutDetailsSheetState();
}

class _CheckoutDetailsSheetState extends State<_CheckoutDetailsSheet> {
  final _tableController = TextEditingController();
  final _guestController = TextEditingController();
  bool _isVip = false;

  @override
  void dispose() {
    _tableController.dispose();
    _guestController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Start This Sale',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 16),
              _field('Table / Seat', _tableController, hint: 'e.g. Table 4'),
              const SizedBox(height: 12),
              _field(
                'Guest name (optional)',
                _guestController,
                hint: 'e.g. Walk-in',
              ),
              const SizedBox(height: 12),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _isVip,
                onChanged: (v) => setState(() => _isVip = v),
                activeThumbColor: AppColors.gold,
                title: Text(
                  'VIP guest',
                  style: AppTypography.style(color: Colors.white, fontSize: 14),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: Material(
                  color: AppColors.gold,
                  borderRadius: BorderRadius.circular(14),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: () => Navigator.of(context).pop((
                      _tableController.text.trim().isEmpty
                          ? null
                          : _tableController.text.trim(),
                      _guestController.text.trim().isEmpty
                          ? null
                          : _guestController.text.trim(),
                      _isVip,
                    )),
                    child: Center(
                      child: Text(
                        'Send to Bar',
                        style: AppTypography.style(
                          color: const Color(0xFF0A0F1E),
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _field(
    String label,
    TextEditingController controller, {
    required String hint,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.7),
            fontSize: 13,
          ),
        ),
        const SizedBox(height: 6),
        Container(
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.06),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: TextField(
            controller: controller,
            style: AppTypography.style(color: Colors.white, fontSize: 14),
            decoration: InputDecoration(
              isDense: true,
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 12,
                vertical: 12,
              ),
              border: InputBorder.none,
              hintText: hint,
              hintStyle: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.3),
                fontSize: 13,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
