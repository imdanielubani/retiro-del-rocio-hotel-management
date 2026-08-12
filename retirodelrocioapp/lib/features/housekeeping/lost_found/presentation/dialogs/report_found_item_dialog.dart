import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/housekeeping/application/housekeeping_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_room.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/application/lost_found_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/data/lost_found_repository.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/domain/lost_found_item.dart';

/// "Report a Found Item" — log something a housekeeper found while turning
/// over a room, optionally against that room; left blank for something found
/// in a common area. Returns the created item, or null if cancelled.
Future<LostFoundItem?> showReportFoundItemDialog(
  BuildContext context, {
  required String token,
}) {
  return showDialog<LostFoundItem>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.6),
    builder: (_) => ReportFoundItemDialog(token: token),
  );
}

class ReportFoundItemDialog extends ConsumerStatefulWidget {
  const ReportFoundItemDialog({super.key, required this.token});

  final String token;

  @override
  ConsumerState<ReportFoundItemDialog> createState() =>
      _ReportFoundItemDialogState();
}

class _ReportFoundItemDialogState extends ConsumerState<ReportFoundItemDialog> {
  final _descriptionController = TextEditingController();
  final _notesController = TextEditingController();
  HousekeepingRoom? _room;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _descriptionController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final description = _descriptionController.text.trim();
    if (description.isEmpty) {
      setState(() => _error = 'Describe what was found.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final item = await ref
          .read(lostFoundActionsProvider(widget.token))
          .createItem(
            roomUnitId: _room?.id,
            itemDescription: description,
            notes: _notesController.text.trim().isEmpty
                ? null
                : _notesController.text.trim(),
          );
      if (mounted) Navigator.of(context).pop(item);
    } on LostFoundException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Could not log this item.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final roomsAsync = ref.watch(housekeepingRoomsProvider(widget.token));
    final rooms = roomsAsync.value ?? const <HousekeepingRoom>[];

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
                  'Report a Found Item',
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 16),
                _field(
                  controller: _descriptionController,
                  label: 'What did you find',
                ),
                const SizedBox(height: 12),
                _roomDropdown(rooms),
                const SizedBox(height: 12),
                _field(
                  controller: _notesController,
                  label: 'Where exactly, or other detail (optional)',
                  maxLines: 3,
                ),
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
                                    'Log Item',
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
  }) {
    return TextField(
      controller: controller,
      maxLines: maxLines,
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

  Widget _roomDropdown(List<HousekeepingRoom> rooms) {
    return DropdownButtonFormField<HousekeepingRoom?>(
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
        const DropdownMenuItem<HousekeepingRoom?>(
          value: null,
          child: Text('None — common area'),
        ),
        for (final room in rooms)
          DropdownMenuItem<HousekeepingRoom?>(
            value: room,
            child: Text(
              'Room ${room.number}${(room.roomName ?? '').isNotEmpty ? ' · ${room.roomName}' : ''}',
            ),
          ),
      ],
      onChanged: (value) => setState(() => _room = value),
    );
  }
}
