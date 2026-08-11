import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_order.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_tab.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_order_card.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_page_header.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_tab_card.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_widgets.dart';

/// Order History — settled tabs and served/cancelled room drink orders.
class BarHistoryScreen extends ConsumerStatefulWidget {
  const BarHistoryScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<BarHistoryScreen> createState() => _BarHistoryScreenState();
}

class _BarHistoryScreenState extends ConsumerState<BarHistoryScreen> {
  String _search = '';
  String _type = ''; // '' | tabs | room

  String get _token => widget.session.token;

  @override
  Widget build(BuildContext context) {
    final historyAsync = ref.watch(barHistoryProvider(_token));
    final (tabs, roomOrders) =
        historyAsync.value ?? (const <BarTab>[], const <BarOrder>[]);

    final q = _search.trim().toLowerCase();
    final filteredTabs = tabs
        .where(
          (t) =>
              q.isEmpty ||
              t.code.toLowerCase().contains(q) ||
              (t.tableLabel?.toLowerCase().contains(q) ?? false) ||
              (t.guestName?.toLowerCase().contains(q) ?? false),
        )
        .toList();
    final filteredOrders = roomOrders
        .where(
          (o) =>
              q.isEmpty ||
              o.code.toLowerCase().contains(q) ||
              (o.guestName?.toLowerCase().contains(q) ?? false),
        )
        .toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 4),
        const BarPageHeader(title: 'History'),
        const SizedBox(height: 14),
        BarSearchField(
          hintText: 'Search tab, order or guest…',
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
                selected: _type.isEmpty,
                onTap: () => setState(() => _type = ''),
              ),
              BarFilterChip(
                label: 'Settled Tabs',
                selected: _type == 'tabs',
                onTap: () => setState(() => _type = 'tabs'),
              ),
              BarFilterChip(
                label: 'Room Orders',
                selected: _type == 'room',
                onTap: () => setState(() => _type = 'room'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Expanded(
          child: historyAsync.when(
            data: (_) => _content(filteredTabs, filteredOrders),
            loading: () => tabs.isEmpty && roomOrders.isEmpty
                ? const Center(
                    child: CircularProgressIndicator(color: AppColors.gold),
                  )
                : _content(filteredTabs, filteredOrders),
            error: (_, _) => tabs.isEmpty && roomOrders.isEmpty
                ? const BarEmptyState(
                    icon: Icons.wifi_off_rounded,
                    message: 'Could not load history.',
                  )
                : _content(filteredTabs, filteredOrders),
          ),
        ),
      ],
    );
  }

  Widget _content(List<BarTab> tabs, List<BarOrder> orders) {
    final showTabs = _type != 'room';
    final showOrders = _type != 'tabs';
    final items = <Widget>[
      if (showTabs) ...tabs.map((t) => BarTabCard(tab: t, onTap: () {})),
      if (showOrders)
        ...orders.map((o) => BarOrderCard(order: o, onTap: () {})),
    ];

    if (items.isEmpty) {
      return const BarEmptyState(
        icon: Icons.history_rounded,
        message: 'Nothing settled yet.',
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(barHistoryProvider(_token)),
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) => items[i],
      ),
    );
  }
}
