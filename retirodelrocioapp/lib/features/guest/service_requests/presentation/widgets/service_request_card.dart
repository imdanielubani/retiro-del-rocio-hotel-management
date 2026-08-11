import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/domain/guest_service_request.dart';

const Color _mtBlue = Color(0xFF3B82F6);

/// The status accent — gold while open, green once done/completed.
Color serviceRequestStatusColor(GuestServiceRequest request) =>
    request.isOpen ? AppColors.gold : const Color(0xFF00FF00);

/// One row in the Request History list — which service, what was asked,
/// and where it stands.
class ServiceRequestCard extends StatelessWidget {
  const ServiceRequestCard({super.key, required this.request});

  final GuestServiceRequest request;

  @override
  Widget build(BuildContext context) {
    final accent = serviceRequestStatusColor(request);
    final categoryColor = request.isHousekeeping ? AppColors.gold : _mtBlue;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _icon(categoryColor),
          const SizedBox(width: 14),
          Expanded(child: _details(accent, categoryColor)),
        ],
      ),
    );
  }

  Widget _icon(Color color) {
    return Container(
      width: 40,
      height: 40,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        shape: BoxShape.circle,
      ),
      child: Icon(
        request.isHousekeeping
            ? Icons.cleaning_services_rounded
            : Icons.build_rounded,
        size: 18,
        color: color,
      ),
    );
  }

  Widget _details(Color accent, Color categoryColor) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Flexible(
              child: Text(
                request.title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const SizedBox(width: 10),
            _statusBadge(accent),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          request.isHousekeeping ? 'Housekeeping' : 'Maintenance',
          style: AppTypography.style(
            color: categoryColor.withValues(alpha: 0.8),
            fontSize: 11,
            fontWeight: FontWeight.w600,
          ),
        ),
        if ((request.detail ?? '').isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(
            request.detail!,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 12,
            ),
          ),
        ],
        if (request.time != null) ...[
          const SizedBox(height: 6),
          Text(
            DateFormat('MMM d, h:mm a').format(request.time!),
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.3),
              fontSize: 11,
            ),
          ),
        ],
      ],
    );
  }

  Widget _statusBadge(Color accent) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        request.statusLabel,
        style: AppTypography.style(
          color: accent,
          fontSize: 10,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
