import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';

const _statuses = ['new', 'accepted', 'in_progress', 'done'];
const _statusLabels = {'new': 'New', 'accepted': 'Accepted', 'in_progress': 'In Progress', 'done': 'Done'};

/// The "Status Update" bottom sheet — a manual override for the desk to jump
/// straight to a given status, skipping the accept/start/complete chain
/// when a technician needs to correct a mis-tap. Returns the chosen status,
/// or null if dismissed.
Future<String?> showStatusUpdateSheet(BuildContext context, {required WorkOrder order}) {
  return showModalBottomSheet<String>(
    context: context,
    backgroundColor: const Color(0xFF1E1E1E),
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (_) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
            child: Text(
              'Update Status',
              style: AppTypography.style(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
            ),
          ),
          for (final status in _statuses)
            ListTile(
              leading: Icon(
                status == order.status ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
                color: status == order.status ? maintenanceStatusColor(status) : Colors.white38,
              ),
              title: Text(_statusLabels[status]!, style: AppTypography.style(color: Colors.white, fontSize: 15)),
              onTap: status == order.status ? null : () => Navigator.of(context).pop(status),
            ),
        ],
      ),
    ),
  );
}
