import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/maintenance/application/maintenance_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/data/maintenance_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/parts_request.dart';

/// "Parts Request" — a technician asks for a part against the order they're
/// working. Returns the created request, or null if cancelled.
Future<PartsRequest?> showPartsRequestDialog(BuildContext context, {required String token, required int workOrderId}) {
  return showDialog<PartsRequest>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.6),
    builder: (_) => PartsRequestDialog(token: token, workOrderId: workOrderId),
  );
}

class PartsRequestDialog extends ConsumerStatefulWidget {
  const PartsRequestDialog({super.key, required this.token, required this.workOrderId});

  final String token;
  final int workOrderId;

  @override
  ConsumerState<PartsRequestDialog> createState() => _PartsRequestDialogState();
}

class _PartsRequestDialogState extends ConsumerState<PartsRequestDialog> {
  final _partController = TextEditingController();
  final _quantityController = TextEditingController(text: '1');
  final _noteController = TextEditingController();
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _partController.dispose();
    _quantityController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final partName = _partController.text.trim();
    if (partName.isEmpty) {
      setState(() => _error = 'Name the part you need.');
      return;
    }
    final quantity = int.tryParse(_quantityController.text.trim()) ?? 1;

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final request = await ref
          .read(maintenanceActionsProvider(widget.token))
          .createPartsRequest(
            widget.workOrderId,
            partName: partName,
            quantity: quantity < 1 ? 1 : quantity,
            note: _noteController.text.trim().isEmpty ? null : _noteController.text.trim(),
          );
      if (mounted) Navigator.of(context).pop(request);
    } on MaintenanceException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Could not submit this request.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
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
                  'Request Parts',
                  style: AppTypography.style(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 16),
                _field(controller: _partController, label: 'Part needed, e.g. "Compressor capacitor"'),
                const SizedBox(height: 12),
                _field(controller: _quantityController, label: 'Quantity', keyboardType: TextInputType.number),
                const SizedBox(height: 12),
                _field(controller: _noteController, label: 'Note (optional)', maxLines: 3),
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
                                    'Submit',
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
}
