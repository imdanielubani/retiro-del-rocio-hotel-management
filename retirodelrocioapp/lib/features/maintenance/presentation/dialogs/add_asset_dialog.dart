import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/asset.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';

const List<String> kMaintenanceAssetCategories = [
  'HVAC',
  'Electrical',
  'Plumbing',
  'Kitchen Equipment',
  'Elevator',
  'Furniture',
  'Other',
];

/// Register a new asset for the picker, service history, and
/// preventive-maintenance tracking. Returns the created asset, or null if
/// cancelled.
Future<Asset?> showAddAssetDialog(
  BuildContext context, {
  required String token,
}) {
  return showDialog<Asset>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.6),
    builder: (_) => AddAssetDialog(token: token),
  );
}

class AddAssetDialog extends ConsumerStatefulWidget {
  const AddAssetDialog({super.key, required this.token});

  final String token;

  @override
  ConsumerState<AddAssetDialog> createState() => _AddAssetDialogState();
}

class _AddAssetDialogState extends ConsumerState<AddAssetDialog> {
  final _nameController = TextEditingController();
  final _locationController = TextEditingController();
  final _notesController = TextEditingController();
  final _intervalController = TextEditingController();
  MaintenanceRoomOption? _room;
  String? _category;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _nameController.dispose();
    _locationController.dispose();
    _notesController.dispose();
    _intervalController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Name the asset.');
      return;
    }

    final interval = int.tryParse(_intervalController.text.trim());

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final asset = await ref
          .read(maintenanceActionsProvider(widget.token))
          .createAsset(
            name: name,
            category: _category,
            roomUnitId: _room?.id,
            locationLabel: _locationController.text.trim().isEmpty
                ? null
                : _locationController.text.trim(),
            notes: _notesController.text.trim().isEmpty
                ? null
                : _notesController.text.trim(),
            serviceIntervalDays: interval,
          );
      if (mounted) Navigator.of(context).pop(asset);
    } on MaintenanceException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Could not register this asset.');
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
                  'Register Asset',
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 16),
                _field(
                  controller: _nameController,
                  label: 'Asset name, e.g. "Lobby Generator"',
                ),
                const SizedBox(height: 12),
                _categoryDropdown(),
                const SizedBox(height: 12),
                _roomDropdown(rooms),
                const SizedBox(height: 12),
                _field(
                  controller: _locationController,
                  label: 'Or a location, e.g. "Rooftop" (optional)',
                ),
                const SizedBox(height: 12),
                _field(
                  controller: _intervalController,
                  label: 'Service every N days (optional)',
                  keyboardType: TextInputType.number,
                ),
                const SizedBox(height: 12),
                _field(
                  controller: _notesController,
                  label: 'Notes (optional)',
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
                                    'Register',
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
    TextInputType? keyboardType,
  }) {
    return TextField(
      controller: controller,
      maxLines: maxLines,
      keyboardType: keyboardType,
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

  Widget _categoryDropdown() {
    return DropdownButtonFormField<String?>(
      initialValue: _category,
      dropdownColor: const Color(0xFF141414),
      style: AppTypography.style(color: Colors.white, fontSize: 14),
      decoration: InputDecoration(
        labelText: 'Category (optional)',
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
        const DropdownMenuItem<String?>(value: null, child: Text('None')),
        for (final c in kMaintenanceAssetCategories)
          DropdownMenuItem<String?>(value: c, child: Text(c)),
      ],
      onChanged: (value) => setState(() => _category = value),
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
}
