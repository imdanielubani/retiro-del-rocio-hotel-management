import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/kitchen/application/kitchen_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_staff.dart';

/// The "Assign to Station" bottom sheet — every active kitchen staff
/// member, tap one to assign the ticket. Returns the chosen staffer, or
/// null if dismissed.
Future<KitchenStaff?> showAssignStationSheet(
  BuildContext context, {
  required String token,
}) {
  return showModalBottomSheet<KitchenStaff>(
    context: context,
    backgroundColor: const Color(0xFF1E1E1E),
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (_) => _AssignStationSheet(token: token),
  );
}

class _AssignStationSheet extends ConsumerWidget {
  const _AssignStationSheet({required this.token});

  final String token;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final chefsAsync = ref.watch(kitchenChefsProvider(token));
    final chefs = chefsAsync.value ?? const <KitchenStaff>[];

    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
            child: Text(
              'Assign to Station',
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 16,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          if (chefsAsync.isLoading && chefs.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                child: CircularProgressIndicator(color: AppColors.gold),
              ),
            )
          else if (chefs.isEmpty)
            Padding(
              padding: const EdgeInsets.all(24),
              child: Text(
                'No active kitchen staff found.',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.5),
                  fontSize: 14,
                ),
              ),
            )
          else
            ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.of(context).size.height * 0.5,
              ),
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: chefs.length,
                itemBuilder: (_, i) {
                  final chef = chefs[i];
                  return ListTile(
                    leading: const Icon(
                      Icons.person_outline_rounded,
                      color: AppColors.gold,
                    ),
                    title: Text(
                      chef.name,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 15,
                      ),
                    ),
                    onTap: () => Navigator.of(context).pop(chef),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}
