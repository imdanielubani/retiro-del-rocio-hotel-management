import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_order.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_overview.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_order_detail_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_order_card.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_page_header.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_widgets.dart';

/// The drink order board — New → Preparing → Served, grouped from the exact
/// same `DiningOrder` rows the admin Bar & Lounge dashboard manages, whether
/// placed via this tablet's POS or a guest's room tablet.
class BarLiveOrdersScreen extends ConsumerStatefulWidget {
  const BarLiveOrdersScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<BarLiveOrdersScreen> createState() =>
      _BarLiveOrdersScreenState();
}

class _BarLiveOrdersScreenState extends ConsumerState<BarLiveOrdersScreen> {
  String _search = '';
  String _column = ''; // '' | new | preparing | served

  String get _token => widget.session.token;

  List<BarOrder> _filtered(List<BarOrder> orders) {
    final q = _search.trim().toLowerCase();
    return orders.where((o) {
      final matchesColumn = _column.isEmpty || o.boardColumn == _column;
      final matchesSearch =
          q.isEmpty ||
          o.code.toLowerCase().contains(q) ||
          (o.tabCode?.toLowerCase().contains(q) ?? false) ||
          (o.tableLabel?.toLowerCase().contains(q) ?? false) ||
          (o.guestName?.toLowerCase().contains(q) ?? false);
      return matchesColumn && matchesSearch;
    }).toList();
  }

  Future<void> _openDetail(BarOrder order) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            BarOrderDetailScreen(session: widget.session, orderId: order.id),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ordersAsync = ref.watch(barLiveOrdersProvider(_token));
    final orders = ordersAsync.value ?? const <BarOrder>[];
    final overview = ref.watch(barOverviewProvider(_token)).value;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 4),
        const BarPageHeader(title: 'Live Orders'),
        const SizedBox(height: 18),
        _stats(overview ?? BarOverview.empty),
        const SizedBox(height: 18),
        BarSearchField(
          hintText: 'Search order, tab or guest…',
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
                selected: _column.isEmpty,
                onTap: () => setState(() => _column = ''),
              ),
              BarFilterChip(
                label: 'New',
                selected: _column == 'new',
                onTap: () => setState(() => _column = 'new'),
              ),
              BarFilterChip(
                label: 'Preparing',
                selected: _column == 'preparing',
                onTap: () => setState(() => _column = 'preparing'),
              ),
              BarFilterChip(
                label: 'Served',
                selected: _column == 'served',
                onTap: () => setState(() => _column = 'served'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Expanded(
          child: ordersAsync.when(
            data: (data) => _board(_filtered(data)),
            loading: () => orders.isNotEmpty
                ? _board(_filtered(orders))
                : const Center(
                    child: CircularProgressIndicator(color: AppColors.gold),
                  ),
            error: (_, _) => orders.isNotEmpty
                ? _board(_filtered(orders))
                : const BarEmptyState(
                    icon: Icons.wifi_off_rounded,
                    message: 'Could not load orders. Pull to retry.',
                  ),
          ),
        ),
      ],
    );
  }

  Widget _stats(BarOverview overview) {
    return Row(
      children: [
        Expanded(
          child: BarStatCard(
            label: 'NEW',
            value: '${overview.newCount}',
            accent: barBoardColumnColor('new'),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: BarStatCard(
            label: 'PREPARING',
            value: '${overview.preparingCount}',
            accent: barBoardColumnColor('preparing'),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: BarStatCard(
            label: 'SERVED TODAY',
            value: '${overview.servedToday}',
            accent: barBoardColumnColor('served'),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: BarStatCard(
            label: 'OPEN TABS',
            value: '${overview.openTabs}',
            accent: AppColors.gold,
          ),
        ),
      ],
    );
  }

  Widget _board(List<BarOrder> orders) {
    if (orders.isEmpty) {
      return const BarEmptyState(
        icon: Icons.local_bar_outlined,
        message: 'No open orders right now.',
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(barLiveOrdersProvider(_token)),
      child: GridView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
          maxCrossAxisExtent: 280,
          mainAxisExtent: 112,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
        ),
        itemCount: orders.length,
        itemBuilder: (_, i) =>
            BarOrderCard(order: orders[i], onTap: () => _openDetail(orders[i])),
      ),
    );
  }
}
