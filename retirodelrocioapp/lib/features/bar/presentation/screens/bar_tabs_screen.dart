import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_tab.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_pos_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_tab_detail_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_page_header.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_tab_card.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_widgets.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/reservation_lookup_sheet.dart';

/// The open Tabs list — a waiter's POS entry point: start a new sale on the
/// full Point of Sale screen, or jump into an existing tab to keep ringing
/// up drinks or close it out.
class BarTabsScreen extends ConsumerStatefulWidget {
  const BarTabsScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<BarTabsScreen> createState() => _BarTabsScreenState();
}

class _BarTabsScreenState extends ConsumerState<BarTabsScreen> {
  String _search = '';

  String get _token => widget.session.token;

  List<BarTab> _filtered(List<BarTab> tabs) {
    final q = _search.trim().toLowerCase();
    if (q.isEmpty) return tabs;
    return tabs
        .where(
          (t) =>
              t.code.toLowerCase().contains(q) ||
              (t.tableLabel?.toLowerCase().contains(q) ?? false) ||
              (t.guestName?.toLowerCase().contains(q) ?? false),
        )
        .toList();
  }

  Future<void> _openTab(BarTab tab) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            BarTabDetailScreen(session: widget.session, tabId: tab.id),
      ),
    );
  }

  Future<void> _newSale() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => BarPosScreen(session: widget.session)),
    );
  }

  /// Every walk-in books a table or lounge ahead of arriving now — this
  /// looks up that reservation and pushes it straight into a pre-filled
  /// tab, then drops the waiter into the POS on that same tab to start
  /// ringing up drinks, rather than opening a second, disconnected tab.
  Future<void> _fromReservation() async {
    final tab = await showReservationLookupSheet(context, token: _token);
    if (tab == null || !mounted) return;

    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => BarPosScreen(
          session: widget.session,
          tabId: tab.id,
          tabLabel: tab.tableLabel ?? tab.code,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tabsAsync = ref.watch(barOpenTabsProvider(_token));
    final tabs = tabsAsync.value ?? const <BarTab>[];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 4),
        BarPageHeader(
          title: 'Tabs',
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Material(
                color: Colors.white.withValues(alpha: 0.06),
                borderRadius: BorderRadius.circular(12),
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: _newSale,
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 14, vertical: 11),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.point_of_sale_rounded,
                          size: 18,
                          color: AppColors.gold,
                        ),
                        SizedBox(width: 6),
                        Text(
                          'Walk-in',
                          style: TextStyle(
                            color: AppColors.gold,
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Material(
                color: AppColors.gold,
                borderRadius: BorderRadius.circular(12),
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: _fromReservation,
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16, vertical: 11),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.event_seat_rounded,
                          size: 18,
                          color: Color(0xFF0A0F1E),
                        ),
                        SizedBox(width: 6),
                        Text(
                          'From Reservation',
                          style: TextStyle(
                            color: Color(0xFF0A0F1E),
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        BarSearchField(
          hintText: 'Search table or guest…',
          onChanged: (v) => setState(() => _search = v),
        ),
        const SizedBox(height: 14),
        Expanded(
          child: tabsAsync.when(
            data: (data) => _list(_filtered(data)),
            loading: () => tabs.isNotEmpty
                ? _list(_filtered(tabs))
                : const Center(
                    child: CircularProgressIndicator(color: AppColors.gold),
                  ),
            error: (_, _) => tabs.isNotEmpty
                ? _list(_filtered(tabs))
                : const BarEmptyState(
                    icon: Icons.wifi_off_rounded,
                    message: 'Could not load tabs. Pull to retry.',
                  ),
          ),
        ),
      ],
    );
  }

  Widget _list(List<BarTab> tabs) {
    if (tabs.isEmpty) {
      return const BarEmptyState(
        icon: Icons.table_bar_outlined,
        message: 'No open tabs right now.',
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(barOpenTabsProvider(_token)),
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: tabs.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) =>
            BarTabCard(tab: tabs[i], onTap: () => _openTab(tabs[i])),
      ),
    );
  }
}
