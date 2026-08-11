import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/technician.dart';

/// The "Assign Technician" bottom sheet — every active technician, tap one
/// to reassign the order. Returns the chosen technician, or null if
/// dismissed.
Future<Technician?> showAssignTechnicianSheet(BuildContext context, {required String token}) {
  return showModalBottomSheet<Technician>(
    context: context,
    backgroundColor: const Color(0xFF1E1E1E),
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (_) => _AssignTechnicianSheet(token: token),
  );
}

class _AssignTechnicianSheet extends ConsumerWidget {
  const _AssignTechnicianSheet({required this.token});

  final String token;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final techniciansAsync = ref.watch(maintenanceTechniciansProvider(token));
    final technicians = techniciansAsync.value ?? const <Technician>[];

    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
            child: Text(
              'Assign Technician',
              style: AppTypography.style(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
            ),
          ),
          if (techniciansAsync.isLoading && technicians.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator(color: AppColors.gold)),
            )
          else if (technicians.isEmpty)
            Padding(
              padding: const EdgeInsets.all(24),
              child: Text(
                'No active technicians found.',
                style: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 14),
              ),
            )
          else
            ConstrainedBox(
              constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.5),
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: technicians.length,
                itemBuilder: (_, i) {
                  final technician = technicians[i];
                  return ListTile(
                    leading: const Icon(Icons.person_outline_rounded, color: AppColors.gold),
                    title: Text(technician.name, style: AppTypography.style(color: Colors.white, fontSize: 15)),
                    onTap: () => Navigator.of(context).pop(technician),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}
