import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/bar/application/bar_providers.dart';
import 'package:retirodelrocioapp/features/bar/domain/bartender.dart';

/// The "Assign Bartender" bottom sheet — every active bar staff member, tap
/// one to assign the order. Returns the chosen bartender, or null if
/// dismissed.
Future<Bartender?> showAssignBartenderSheet(
  BuildContext context, {
  required String token,
}) {
  return showModalBottomSheet<Bartender>(
    context: context,
    backgroundColor: const Color(0xFF1E1E1E),
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (_) => _AssignBartenderSheet(token: token),
  );
}

class _AssignBartenderSheet extends ConsumerWidget {
  const _AssignBartenderSheet({required this.token});

  final String token;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bartendersAsync = ref.watch(barBartendersProvider(token));
    final bartenders = bartendersAsync.value ?? const <Bartender>[];

    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
            child: Text(
              'Assign Bartender',
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 16,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          if (bartendersAsync.isLoading && bartenders.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                child: CircularProgressIndicator(color: AppColors.gold),
              ),
            )
          else if (bartenders.isEmpty)
            Padding(
              padding: const EdgeInsets.all(24),
              child: Text(
                'No active bar staff found.',
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
                itemCount: bartenders.length,
                itemBuilder: (_, i) {
                  final bartender = bartenders[i];
                  return ListTile(
                    leading: const Icon(
                      Icons.person_outline_rounded,
                      color: AppColors.gold,
                    ),
                    title: Text(
                      bartender.name,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 15,
                      ),
                    ),
                    onTap: () => Navigator.of(context).pop(bartender),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}
