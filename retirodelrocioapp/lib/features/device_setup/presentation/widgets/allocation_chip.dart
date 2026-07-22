import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';

/// The gold allocation badge — suite + room number for guest tablets, or the
/// locked role for staff tablets (Figma nodes 171:5512 / 75:3048).
class AllocationChip extends StatelessWidget {
  const AllocationChip({super.key, required this.device});

  final ProvisionedDevice device;

  @override
  Widget build(BuildContext context) {
    final title = device.isGuest
        ? (device.suiteName ?? 'Suite')
        : (device.role ?? device.allocation ?? 'Staff');

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 11),
      decoration: BoxDecoration(
        color: AppColors.goldAccent.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.goldAccent.withValues(alpha: 0.3), width: 0.8),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            title.toUpperCase(),
            style: AppTypography.style(
              color: AppColors.gold,
              fontSize: 16,
              fontWeight: FontWeight.w500,
            ),
          ),
          if (device.isGuest && device.roomNumber != null)
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'ROOM',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.5),
                    fontSize: 13,
                    letterSpacing: 1.56,
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  device.roomNumber!,
                  style: AppTypography.style(
                    color: AppColors.gold,
                    fontSize: 24,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            )
          else if (device.isStaff)
            Text(
              'STAFF STATION',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.5),
                fontSize: 13,
                letterSpacing: 1.56,
              ),
            ),
        ],
      ),
    );
  }
}
