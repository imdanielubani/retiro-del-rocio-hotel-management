import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';

const Color kMtGreen = Color(0xFF22C55E);
const Color kMtBlue = Color(0xFF3B82F6);
const Color kMtRed = Color(0xFFEF4444);
const Color kMtSlate = Color(0xFF94A3B8);

Color maintenanceStatusColor(String status) => switch (status) {
  'accepted' => kMtBlue,
  'in_progress' => AppColors.gold,
  'done' => kMtGreen,
  _ => kMtSlate, // new
};

Color maintenancePriorityColor(String priority) => switch (priority) {
  'urgent' => kMtRed,
  'high' => AppColors.gold,
  'low' => kMtSlate,
  _ => kMtBlue, // medium
};

/// A headline counter, matching the reception/security/housekeeping
/// dashboards' stat card language.
class MaintenanceStatCard extends StatelessWidget {
  const MaintenanceStatCard({super.key, required this.label, required this.value, required this.accent});

  final String label;
  final int value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.35),
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            '$value',
            style: AppTypography.style(color: accent, fontSize: 28, fontWeight: FontWeight.w800, height: 1),
          ),
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(999)),
      child: Text(label, style: AppTypography.style(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }
}

/// One work order in the board — its location, priority, status, and the
/// one next action available for its current state (Accept → Start →
/// Complete).
class WorkOrderCard extends StatelessWidget {
  const WorkOrderCard({
    super.key,
    required this.order,
    required this.onAccept,
    required this.onStart,
    required this.onComplete,
    this.busy = false,
  });

  final WorkOrder order;
  final VoidCallback onAccept;
  final VoidCallback onStart;
  final VoidCallback onComplete;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: order.priority == 'urgent'
              ? kMtRed.withValues(alpha: 0.35)
              : Colors.white.withValues(alpha: 0.08),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _Pill(label: order.priorityLabel, color: maintenancePriorityColor(order.priority)),
              const SizedBox(width: 8),
              _Pill(label: order.statusLabel, color: maintenanceStatusColor(order.status)),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            order.title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          Text(
            [
              order.locationLabel,
              if ((order.assignedToName ?? '').isNotEmpty) order.assignedToName!,
              if ((order.createdLabel ?? '').isNotEmpty) order.createdLabel!,
            ].join('  ·  '),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(color: Colors.white.withValues(alpha: 0.45), fontSize: 12),
          ),
          if ((order.description ?? '').isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              order.description!,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.style(color: Colors.white.withValues(alpha: 0.55), fontSize: 12),
            ),
          ],
          const SizedBox(height: 10),
          Align(alignment: Alignment.centerRight, child: _action()),
        ],
      ),
    );
  }

  Widget _action() {
    if (busy) {
      return const SizedBox(
        width: 16,
        height: 16,
        child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.gold),
      );
    }
    if (order.isDone) {
      return Text('Done', style: AppTypography.style(color: kMtSlate, fontSize: 12, fontWeight: FontWeight.w600));
    }
    if (order.isNew) return _button('Accept', kMtBlue, onAccept);
    if (order.isAccepted) return _button('Start', AppColors.gold, onStart);
    return _button('Complete', kMtGreen, onComplete);
  }

  Widget _button(String label, Color color, VoidCallback onTap) {
    return Material(
      color: color.withValues(alpha: 0.14),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          child: Text(label, style: AppTypography.style(color: color, fontSize: 12, fontWeight: FontWeight.w700)),
        ),
      ),
    );
  }
}

/// A quiet placeholder for a section with nothing in it yet.
class MaintenanceSectionEmpty extends StatelessWidget {
  const MaintenanceSectionEmpty({super.key, required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 22, color: Colors.white.withValues(alpha: 0.25)),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: AppTypography.style(color: Colors.white.withValues(alpha: 0.35), fontSize: 12),
          ),
        ],
      ),
    );
  }
}
