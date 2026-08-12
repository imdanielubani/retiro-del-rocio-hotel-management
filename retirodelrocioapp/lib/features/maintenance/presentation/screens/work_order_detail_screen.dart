import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/application/maintenance_notification_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/presentation/screens/maintenance_notification_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/dialogs/accept_order_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/dialogs/close_order_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/dialogs/escalate_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/dialogs/parts_request_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/maintenance_navigation.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/assign_technician_sheet.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/attachment_viewer.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_scaffold.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/status_update_sheet.dart';

/// One work order in full — its details plus every photo/video a technician
/// has attached, with actions to move it through accept → start → complete
/// and to capture a new attachment.
class WorkOrderDetailScreen extends ConsumerStatefulWidget {
  const WorkOrderDetailScreen({
    super.key,
    required this.session,
    required this.orderId,
  });

  final StaffSession session;
  final int orderId;

  @override
  ConsumerState<WorkOrderDetailScreen> createState() =>
      _WorkOrderDetailScreenState();
}

class _WorkOrderDetailScreenState extends ConsumerState<WorkOrderDetailScreen> {
  bool _busy = false;
  bool _uploading = false;

  String get _token => widget.session.token;

  void _onNav(MaintenanceNavItem item) {
    MaintenanceNavigation.select(
      context,
      widget.session,
      item,
      current: MaintenanceNavItem.workOrders,
    );
  }

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) MaintenanceNavigation.afterLogout(context);
  }

  Future<void> _run(Future<WorkOrder> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
    } on MaintenanceException catch (e) {
      _showFailure(e.message);
    } catch (_) {
      _showFailure('Could not update this order.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _addAttachment() async {
    final choice = await showModalBottomSheet<_AttachmentChoice>(
      context: context,
      backgroundColor: const Color(0xFF1E1E1E),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.only(top: 16, bottom: 4),
              child: SizedBox.shrink(),
            ),
            ListTile(
              leading: const Icon(
                Icons.photo_camera_rounded,
                color: AppColors.gold,
              ),
              title: Text(
                'Take a photo',
                style: AppTypography.style(color: Colors.white, fontSize: 15),
              ),
              onTap: () =>
                  Navigator.of(context).pop(_AttachmentChoice.photoCamera),
            ),
            ListTile(
              leading: const Icon(
                Icons.photo_library_rounded,
                color: AppColors.gold,
              ),
              title: Text(
                'Choose a photo',
                style: AppTypography.style(color: Colors.white, fontSize: 15),
              ),
              onTap: () =>
                  Navigator.of(context).pop(_AttachmentChoice.photoGallery),
            ),
            ListTile(
              leading: const Icon(
                Icons.videocam_rounded,
                color: AppColors.gold,
              ),
              title: Text(
                'Record a video',
                style: AppTypography.style(color: Colors.white, fontSize: 15),
              ),
              onTap: () =>
                  Navigator.of(context).pop(_AttachmentChoice.videoCamera),
            ),
            ListTile(
              leading: const Icon(
                Icons.video_library_rounded,
                color: AppColors.gold,
              ),
              title: Text(
                'Choose a video',
                style: AppTypography.style(color: Colors.white, fontSize: 15),
              ),
              onTap: () =>
                  Navigator.of(context).pop(_AttachmentChoice.videoGallery),
            ),
          ],
        ),
      ),
    );
    if (choice == null) return;

    try {
      final picker = ImagePicker();
      final XFile? picked = switch (choice) {
        _AttachmentChoice.photoCamera => await picker.pickImage(
          source: ImageSource.camera,
          imageQuality: 80,
        ),
        _AttachmentChoice.photoGallery => await picker.pickImage(
          source: ImageSource.gallery,
          imageQuality: 80,
        ),
        _AttachmentChoice.videoCamera => await picker.pickVideo(
          source: ImageSource.camera,
          maxDuration: const Duration(minutes: 2),
        ),
        _AttachmentChoice.videoGallery => await picker.pickVideo(
          source: ImageSource.gallery,
        ),
      };
      if (picked == null || !mounted) return;

      setState(() => _uploading = true);
      await ref
          .read(maintenanceActionsProvider(_token))
          .uploadAttachment(widget.orderId, picked.path);
    } on MaintenanceException catch (e) {
      _showFailure(e.message);
    } catch (_) {
      _showFailure('Could not capture this attachment.');
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  void _showFailure(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF7F1D1D),
        behavior: SnackBarBehavior.floating,
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  void _showSuccess(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: kMtGreen.withValues(alpha: 0.9),
        behavior: SnackBarBehavior.floating,
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MaintenanceNotificationScreen(
          session: widget.session,
          current: MaintenanceNavItem.workOrders,
        ),
      ),
    );
  }

  Future<void> _escalate(WorkOrder order) async {
    final confirmed = await showEscalateDialog(
      context,
      orderTitle: order.title,
      currentPriority: order.priority,
    );
    if (!confirmed || !mounted) return;
    await _run(
      () => ref.read(maintenanceActionsProvider(_token)).escalate(order.id),
    );
  }

  Future<void> _updateStatus(WorkOrder order) async {
    final status = await showStatusUpdateSheet(context, order: order);
    if (status == null || !mounted) return;
    await _run(
      () => ref
          .read(maintenanceActionsProvider(_token))
          .updateStatus(order.id, status),
    );
  }

  Future<void> _assignTechnician() async {
    final technician = await showAssignTechnicianSheet(context, token: _token);
    if (technician == null || !mounted) return;
    await _run(
      () => ref
          .read(maintenanceActionsProvider(_token))
          .assign(widget.orderId, technician.id),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(maintenanceNotificationsRealtimeProvider(_token));
    ref.watch(maintenanceNotificationChimeProvider(_token));
    ref.watch(maintenanceSlaBreachChimeProvider(_token));

    final orderAsync = ref.watch(
      maintenanceWorkOrderDetailProvider((_token, widget.orderId)),
    );
    final unreadNotifications = ref.watch(
      maintenanceUnreadNotificationsProvider(_token),
    );

    return MaintenanceScaffold(
      session: widget.session,
      active: MaintenanceNavItem.workOrders,
      onNav: _onNav,
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: orderAsync.value?.title ?? 'Work Order',
      onBack: () => Navigator.of(context).maybePop(),
      body: orderAsync.when(
        data: (data) => data == null ? _notFound() : _content(data),
        loading: () => const Center(
          child: CircularProgressIndicator(color: AppColors.gold),
        ),
        error: (_, _) => _notFound(),
      ),
    );
  }

  Widget _notFound() {
    return Center(
      child: Text(
        'Could not load this work order.',
        style: AppTypography.style(
          color: Colors.white.withValues(alpha: 0.6),
          fontSize: 15,
        ),
      ),
    );
  }

  Widget _content(WorkOrder order) {
    return SingleChildScrollView(
      padding: const EdgeInsets.only(bottom: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _profileCard(order),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(child: _actionRow(order)),
              if (!order.isDone) ...[
                const SizedBox(width: 12),
                _requestPartsButton(order),
              ],
            ],
          ),
          if (!order.isDone) ...[
            const SizedBox(height: 10),
            _secondaryActionsRow(order),
          ],
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: Text(
                  'ATTACHMENTS',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.35),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.4,
                  ),
                ),
              ),
              _addAttachmentButton(),
            ],
          ),
          const SizedBox(height: 12),
          if (order.attachments.isEmpty)
            const MaintenanceSectionEmpty(
              icon: Icons.attach_file_rounded,
              message: 'No photos or videos attached yet',
            )
          else
            _attachmentGrid(order),
        ],
      ),
    );
  }

  Widget _requestPartsButton(WorkOrder order) {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: () => showPartsRequestDialog(
          context,
          token: _token,
          workOrderId: order.id,
        ),
        borderRadius: BorderRadius.circular(12),
        child: Container(
          height: 46,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.handyman_outlined,
                size: 16,
                color: Colors.white70,
              ),
              const SizedBox(width: 6),
              Text(
                'Request Parts',
                style: AppTypography.style(
                  color: Colors.white70,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _secondaryActionsRow(WorkOrder order) {
    return Row(
      children: [
        Expanded(
          child: _secondaryButton(
            'Escalate',
            Icons.arrow_circle_up_rounded,
            () => _escalate(order),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _secondaryButton(
            'Assign',
            Icons.person_outline_rounded,
            _assignTechnician,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _secondaryButton(
            'Status',
            Icons.sync_alt_rounded,
            () => _updateStatus(order),
          ),
        ),
      ],
    );
  }

  Widget _secondaryButton(String label, IconData icon, VoidCallback onTap) {
    return Material(
      color: Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: _busy ? null : onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          height: 38,
          alignment: Alignment.center,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 14, color: Colors.white.withValues(alpha: 0.6)),
              const SizedBox(width: 6),
              Text(
                label,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.6),
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _addAttachmentButton() {
    return Material(
      color: AppColors.gold.withValues(alpha: 0.14),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: _uploading ? null : _addAttachment,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: _uploading
              ? const SizedBox(
                  width: 14,
                  height: 14,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.gold,
                  ),
                )
              : Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.add_a_photo_outlined,
                      size: 14,
                      color: AppColors.gold,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'Add',
                      style: AppTypography.style(
                        color: AppColors.gold,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }

  Widget _profileCard(WorkOrder order) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(16),
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
              _tag(
                order.priorityLabel,
                maintenancePriorityColor(order.priority),
              ),
              const SizedBox(width: 8),
              _tag(order.statusLabel, maintenanceStatusColor(order.status)),
              if (order.slaBreached) ...[
                const SizedBox(width: 8),
                _tag('SLA Breached', kMtRed),
              ],
            ],
          ),
          const SizedBox(height: 12),
          if ((order.description ?? '').isNotEmpty)
            _infoRow('Details', order.description!),
          _infoRow('Location', order.locationLabel),
          if ((order.reportedBy ?? '').isNotEmpty)
            _infoRow('Reported by', order.reportedBy!),
          if ((order.assignedToName ?? '').isNotEmpty)
            _infoRow('Technician', order.assignedToName!),
          if ((order.createdLabel ?? '').isNotEmpty)
            _infoRow('Reported', order.createdLabel!),
        ],
      ),
    );
  }

  Widget _tag(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: AppTypography.style(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 13,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _actionRow(WorkOrder order) {
    if (order.isDone) {
      return Row(
        children: [
          const Icon(Icons.check_circle_rounded, size: 18, color: kMtGreen),
          const SizedBox(width: 8),
          Text(
            'Completed',
            style: AppTypography.style(
              color: kMtGreen,
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      );
    }

    final actions = ref.read(maintenanceActionsProvider(_token));
    final (label, color, onTap) = order.isNew
        ? (
            'Accept',
            kMtBlue,
            () async {
              final confirmed = await showAcceptOrderDialog(
                context,
                orderTitle: order.title,
              );
              if (!confirmed || !mounted) return;
              await _run(() => actions.accept(order.id));
            },
          )
        : order.isAccepted
        ? ('Start', AppColors.gold, () => _run(() => actions.start(order.id)))
        : (
            'Complete',
            kMtGreen,
            () async {
              final confirmed = await showCloseOrderDialog(
                context,
                orderTitle: order.title,
              );
              if (!confirmed || !mounted) return;
              await _run(() async {
                final result = await actions.complete(order.id);
                _showSuccess('Order completed.');
                return result;
              });
            },
          );

    return SizedBox(
      width: double.infinity,
      child: Material(
        color: color.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          onTap: _busy ? null : onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            height: 46,
            alignment: Alignment.center,
            child: _busy
                ? SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: color,
                    ),
                  )
                : Text(
                    label,
                    style: AppTypography.style(
                      color: color,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
          ),
        ),
      ),
    );
  }

  Widget _attachmentGrid(WorkOrder order) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
        maxCrossAxisExtent: 180,
        mainAxisExtent: 130,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
      ),
      itemCount: order.attachments.length,
      itemBuilder: (_, i) {
        final attachment = order.attachments[i];
        return AttachmentThumbnail(
          attachment: attachment,
          onTap: () => showAttachmentViewer(context, attachment),
        );
      },
    );
  }
}

enum _AttachmentChoice { photoCamera, photoGallery, videoCamera, videoGallery }
