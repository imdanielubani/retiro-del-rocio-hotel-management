import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/asset.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';

enum _AttachmentChoice { photoCamera, photoGallery, videoCamera, videoGallery }

/// "Report Fault" — create a work order, optionally against a room; a free
/// "asset" label covers anything hotel-wide (a lobby generator, a lift).
/// Returns the created order, or null if cancelled.
Future<WorkOrder?> showReportFaultDialog(
  BuildContext context, {
  required String token,
}) {
  return showDialog<WorkOrder>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.6),
    builder: (_) => ReportFaultDialog(token: token),
  );
}

class ReportFaultDialog extends ConsumerStatefulWidget {
  const ReportFaultDialog({super.key, required this.token});

  final String token;

  @override
  ConsumerState<ReportFaultDialog> createState() => _ReportFaultDialogState();
}

class _ReportFaultDialogState extends ConsumerState<ReportFaultDialog> {
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _assetController = TextEditingController();
  MaintenanceRoomOption? _room;
  Asset? _asset;
  String _priority = 'medium';
  bool _submitting = false;
  String? _error;
  final List<XFile> _attachments = [];

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _assetController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final title = _titleController.text.trim();
    if (title.isEmpty) {
      setState(() => _error = 'Describe the fault in a few words.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final order = await ref
          .read(maintenanceActionsProvider(widget.token))
          .createWorkOrder(
            roomUnitId: _room?.id,
            assetId: _asset?.id,
            assetLabel:
                _asset == null && _assetController.text.trim().isNotEmpty
                ? _assetController.text.trim()
                : null,
            title: title,
            description: _descriptionController.text.trim().isEmpty
                ? null
                : _descriptionController.text.trim(),
            priority: _priority,
          );

      // The order already exists at this point — an attachment upload
      // failing shouldn't undo the report or block the technician from
      // moving on, so failures here are swallowed rather than surfaced as
      // the dialog's own error.
      for (final file in _attachments) {
        try {
          await ref
              .read(maintenanceActionsProvider(widget.token))
              .uploadAttachment(order.id, file.path);
        } catch (_) {
          // Best-effort — the order was reported either way.
        }
      }

      if (mounted) Navigator.of(context).pop(order);
    } on MaintenanceException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Could not report this fault.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _pickAttachment() async {
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
      if (picked != null && mounted) setState(() => _attachments.add(picked));
    } catch (_) {
      if (mounted)
        setState(() => _error = 'Could not open the camera or gallery.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final roomsAsync = ref.watch(maintenanceRoomsProvider(widget.token));
    final rooms = roomsAsync.value ?? const <MaintenanceRoomOption>[];
    final assetsAsync = ref.watch(maintenanceAssetsProvider(widget.token));
    final assets = assetsAsync.value ?? const <Asset>[];

    return Dialog(
      backgroundColor: const Color(0xFF141414),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Report Fault',
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 16),
                _field(controller: _titleController, label: 'What\'s wrong'),
                const SizedBox(height: 12),
                _field(
                  controller: _descriptionController,
                  label: 'Details (optional)',
                  maxLines: 3,
                ),
                const SizedBox(height: 12),
                _roomDropdown(rooms),
                const SizedBox(height: 12),
                _assetDropdown(assets),
                const SizedBox(height: 12),
                _field(
                  controller: _assetController,
                  label:
                      'Or a new asset name, e.g. "Lobby generator" (optional)',
                  enabled: _asset == null,
                ),
                const SizedBox(height: 12),
                _priorityPicker(),
                const SizedBox(height: 12),
                _attachmentPicker(),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(
                    _error!,
                    style: AppTypography.style(
                      color: const Color(0xFFFF6B6B),
                      fontSize: 12,
                    ),
                  ),
                ],
                const SizedBox(height: 20),
                Row(
                  children: [
                    Expanded(
                      child: TextButton(
                        onPressed: _submitting
                            ? null
                            : () => Navigator.of(context).pop(),
                        child: Text(
                          'Cancel',
                          style: AppTypography.style(
                            color: Colors.white70,
                            fontSize: 14,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Material(
                        color: AppColors.gold,
                        borderRadius: BorderRadius.circular(12),
                        child: InkWell(
                          onTap: _submitting ? null : _submit,
                          borderRadius: BorderRadius.circular(12),
                          child: Container(
                            height: 44,
                            alignment: Alignment.center,
                            child: _submitting
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.black,
                                    ),
                                  )
                                : Text(
                                    'Report',
                                    style: AppTypography.style(
                                      color: Colors.black,
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String label,
    int maxLines = 1,
    bool enabled = true,
  }) {
    return TextField(
      controller: controller,
      maxLines: maxLines,
      enabled: enabled,
      style: AppTypography.style(color: Colors.white, fontSize: 14),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: AppTypography.style(
          color: Colors.white.withValues(alpha: 0.5),
          fontSize: 13,
        ),
        filled: true,
        fillColor: Colors.white.withValues(alpha: 0.05),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 12,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
        ),
      ),
    );
  }

  Widget _assetDropdown(List<Asset> assets) {
    return DropdownButtonFormField<Asset?>(
      initialValue: _asset,
      dropdownColor: const Color(0xFF141414),
      style: AppTypography.style(color: Colors.white, fontSize: 14),
      decoration: InputDecoration(
        labelText: 'A registered asset (optional)',
        labelStyle: AppTypography.style(
          color: Colors.white.withValues(alpha: 0.5),
          fontSize: 13,
        ),
        filled: true,
        fillColor: Colors.white.withValues(alpha: 0.05),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
        ),
      ),
      items: [
        const DropdownMenuItem<Asset?>(value: null, child: Text('None')),
        for (final asset in assets)
          DropdownMenuItem<Asset?>(value: asset, child: Text(asset.name)),
      ],
      onChanged: (value) => setState(() {
        _asset = value;
        if (value != null) _assetController.clear();
      }),
    );
  }

  Widget _roomDropdown(List<MaintenanceRoomOption> rooms) {
    return DropdownButtonFormField<MaintenanceRoomOption?>(
      initialValue: _room,
      dropdownColor: const Color(0xFF141414),
      style: AppTypography.style(color: Colors.white, fontSize: 14),
      decoration: InputDecoration(
        labelText: 'Room (optional)',
        labelStyle: AppTypography.style(
          color: Colors.white.withValues(alpha: 0.5),
          fontSize: 13,
        ),
        filled: true,
        fillColor: Colors.white.withValues(alpha: 0.05),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
        ),
      ),
      items: [
        const DropdownMenuItem<MaintenanceRoomOption?>(
          value: null,
          child: Text('None'),
        ),
        for (final room in rooms)
          DropdownMenuItem<MaintenanceRoomOption?>(
            value: room,
            child: Text(
              'Room ${room.number}${(room.roomName ?? '').isNotEmpty ? ' · ${room.roomName}' : ''}',
            ),
          ),
      ],
      onChanged: (value) => setState(() => _room = value),
    );
  }

  Widget _attachmentPicker() {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        for (var i = 0; i < _attachments.length; i++) _attachmentChip(i),
        Material(
          color: Colors.white.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(999),
          child: InkWell(
            onTap: _pickAttachment,
            borderRadius: BorderRadius.circular(999),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(999),
                border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(
                    Icons.add_a_photo_outlined,
                    size: 14,
                    color: AppColors.gold,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    'Add photo/video',
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
        ),
      ],
    );
  }

  Widget _attachmentChip(int index) {
    final file = _attachments[index];
    final isVideo = [
      '.mp4',
      '.mov',
      '.webm',
    ].any((ext) => file.path.toLowerCase().endsWith(ext));
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isVideo ? Icons.videocam_rounded : Icons.image_rounded,
            size: 14,
            color: Colors.white70,
          ),
          const SizedBox(width: 6),
          Text(
            isVideo ? 'Video' : 'Photo',
            style: AppTypography.style(color: Colors.white70, fontSize: 12),
          ),
          const SizedBox(width: 4),
          InkWell(
            onTap: () => setState(() => _attachments.removeAt(index)),
            child: const Icon(
              Icons.close_rounded,
              size: 14,
              color: Colors.white38,
            ),
          ),
        ],
      ),
    );
  }

  Widget _priorityPicker() {
    const priorities = ['low', 'medium', 'high', 'urgent'];
    return Wrap(
      spacing: 8,
      children: [
        for (final p in priorities)
          ChoiceChip(
            label: Text(_label(p)),
            selected: _priority == p,
            onSelected: (_) => setState(() => _priority = p),
            backgroundColor: Colors.white.withValues(alpha: 0.05),
            selectedColor: AppColors.gold.withValues(alpha: 0.2),
            labelStyle: AppTypography.style(
              color: _priority == p ? AppColors.gold : Colors.white70,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
            side: BorderSide(
              color: _priority == p
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.1),
            ),
          ),
      ],
    );
  }

  String _label(String priority) =>
      priority[0].toUpperCase() + priority.substring(1);
}
