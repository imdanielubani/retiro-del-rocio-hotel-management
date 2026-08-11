import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/data/bar_repository.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_menu_item.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_widgets.dart';

/// Menu Availability — the drinks catalog the admin Bar & Lounge dashboard
/// manages, with a quick on/off toggle for when something runs out.
class BarMenuScreen extends ConsumerStatefulWidget {
  const BarMenuScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<BarMenuScreen> createState() => _BarMenuScreenState();
}

class _BarMenuScreenState extends ConsumerState<BarMenuScreen> {
  String _search = '';
  int? _busyId;

  String get _token => widget.session.token;

  Future<void> _toggle(BarMenuItem item) async {
    setState(() => _busyId = item.id);
    try {
      await ref
          .read(barActionsProvider(_token))
          .toggleMenuItemAvailability(item.id);
    } on BarException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: const Color(0xFF7F1D1D),
            behavior: SnackBarBehavior.floating,
            content: Text(e.message),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final menuAsync = ref.watch(barMenuProvider(_token));
    final menu = menuAsync.value ?? const <BarMenuItem>[];
    final filtered = menu
        .where(
          (m) =>
              _search.isEmpty ||
              m.name.toLowerCase().contains(_search.toLowerCase()),
        )
        .toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 4),
        Text(
          'Drinks Menu',
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 22,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 14),
        BarSearchField(
          hintText: 'Search drinks…',
          onChanged: (v) => setState(() => _search = v),
        ),
        const SizedBox(height: 14),
        Expanded(
          child: menuAsync.when(
            data: (_) => _list(filtered),
            loading: () => menu.isNotEmpty
                ? _list(filtered)
                : const Center(
                    child: CircularProgressIndicator(color: AppColors.gold),
                  ),
            error: (_, _) => menu.isNotEmpty
                ? _list(filtered)
                : const BarEmptyState(
                    icon: Icons.wifi_off_rounded,
                    message: 'Could not load the menu.',
                  ),
          ),
        ),
      ],
    );
  }

  Widget _list(List<BarMenuItem> items) {
    if (items.isEmpty) {
      return const BarEmptyState(
        icon: Icons.local_bar_outlined,
        message: 'No drinks match your search.',
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () async => ref.invalidate(barMenuProvider(_token)),
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (_, i) {
          final item = items[i];
          return Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.05),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: [
                Icon(
                  item.isAlcoholic
                      ? Icons.local_bar_rounded
                      : Icons.local_drink_rounded,
                  color: item.isAlcoholic ? Colors.amber : AppColors.gold,
                  size: 20,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.name,
                        style: AppTypography.style(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      Text(
                        '${item.categoryLabel} · ${item.priceLabel}',
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.5),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                _busyId == item.id
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.gold,
                        ),
                      )
                    : Switch(
                        value: item.isActive,
                        onChanged: (_) => _toggle(item),
                        activeThumbColor: AppColors.gold,
                      ),
              ],
            ),
          );
        },
      ),
    );
  }
}
