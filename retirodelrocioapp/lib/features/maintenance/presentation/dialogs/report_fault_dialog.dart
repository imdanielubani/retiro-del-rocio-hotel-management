import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';

/// "Report Fault" — create a work order, optionally against a room; a free
/// "asset" label covers anything hotel-wide (a lobby generator, a lift).
/// Returns the created order, or null if cancelled.
Future<WorkOrder?> showReportFaultDialog(BuildContext context, {required String token}) {
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
  String _priority = 'medium';
  bool _submitting = false;
  String? _error;

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
            assetLabel: _assetController.text.trim().isEmpty ? null : _assetController.text.trim(),
            title: title,
            description: _descriptionController.text.trim().isEmpty ? null : _descriptionController.text.trim(),
            priority: _priority,
          );
      if (mounted) Navigator.of(context).pop(order);
    } on MaintenanceException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Could not report this fault.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final roomsAsync = ref.watch(maintenanceRoomsProvider(widget.token));
    final rooms = roomsAsync.value ?? const <MaintenanceRoomOption>[];

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
                  style: AppTypography.style(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 16),
                _field(controller: _titleController, label: 'What\'s wrong'),
                const SizedBox(height: 12),
                _field(controller: _descriptionController, label: 'Details (optional)', maxLines: 3),
                const SizedBox(height: 12),
                _roomDropdown(rooms),
                const SizedBox(height: 12),
                _field(controller: _assetController, label: 'Or an asset, e.g. "Lobby generator" (optional)'),
                const SizedBox(height: 12),
                _priorityPicker(),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(_error!, style: AppTypography.style(color: const Color(0xFFFF6B6B), fontSize: 12)),
                ],
                const SizedBox(height: 20),
                Row(
                  children: [
                    Expanded(
                      child: TextButton(
                        onPressed: _submitting ? null : () => Navigator.of(context).pop(),
                        child: Text('Cancel', style: AppTypography.style(color: Colors.white70, fontSize: 14)),
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
                                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black),
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

  Widget _field({required TextEditingController controller, required String label, int maxLines = 1}) {
    return TextField(
      controller: controller,
      maxLines: maxLines,
      style: AppTypography.style(color: Colors.white, fontSize: 14),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 13),
        filled: true,
        fillColor: Colors.white.withValues(alpha: 0.05),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
        ),
      ),
    );
  }

  Widget _roomDropdown(List<MaintenanceRoomOption> rooms) {
    return DropdownButtonFormField<MaintenanceRoomOption?>(
      initialValue: _room,
      dropdownColor: const Color(0xFF141414),
      style: AppTypography.style(color: Colors.white, fontSize: 14),
      decoration: InputDecoration(
        labelText: 'Room (optional)',
        labelStyle: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 13),
        filled: true,
        fillColor: Colors.white.withValues(alpha: 0.05),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
        ),
      ),
      items: [
        const DropdownMenuItem<MaintenanceRoomOption?>(value: null, child: Text('None')),
        for (final room in rooms)
          DropdownMenuItem<MaintenanceRoomOption?>(
            value: room,
            child: Text('Room ${room.number}${(room.roomName ?? '').isNotEmpty ? ' · ${room.roomName}' : ''}'),
          ),
      ],
      onChanged: (value) => setState(() => _room = value),
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
              color: _priority == p ? AppColors.gold.withValues(alpha: 0.4) : Colors.white.withValues(alpha: 0.1),
            ),
          ),
      ],
    );
  }

  String _label(String priority) => priority[0].toUpperCase() + priority.substring(1);
}
