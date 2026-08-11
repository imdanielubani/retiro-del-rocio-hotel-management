import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_widgets.dart';

/// The claimant's name/contact when handing a found item back — both
/// optional, since a housekeeper may just be recording that it left the
/// property without the front desk having taken a formal claim.
class LostFoundClaim {
  const LostFoundClaim({this.name, this.contact});

  final String? name;
  final String? contact;
}

/// "Mark Returned" — capture who the item was handed back to, if known.
/// Returns a [LostFoundClaim] on confirm, or null if cancelled.
Future<LostFoundClaim?> showClaimLostFoundItemDialog(BuildContext context, {required String itemDescription}) {
  return showDialog<LostFoundClaim>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.6),
    builder: (_) => _ClaimDialog(itemDescription: itemDescription),
  );
}

class _ClaimDialog extends StatefulWidget {
  const _ClaimDialog({required this.itemDescription});

  final String itemDescription;

  @override
  State<_ClaimDialog> createState() => _ClaimDialogState();
}

class _ClaimDialogState extends State<_ClaimDialog> {
  final _nameController = TextEditingController();
  final _contactController = TextEditingController();

  @override
  void dispose() {
    _nameController.dispose();
    _contactController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: const Color(0xFF141414),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 380),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Mark Returned',
                style: AppTypography.style(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 4),
              Text(
                widget.itemDescription,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 13),
              ),
              const SizedBox(height: 16),
              _field(controller: _nameController, label: 'Returned to (optional)'),
              const SizedBox(height: 12),
              _field(controller: _contactController, label: 'Contact — phone or email (optional)'),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: TextButton(
                      onPressed: () => Navigator.of(context).pop(),
                      child: Text('Cancel', style: AppTypography.style(color: Colors.white70, fontSize: 14)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Material(
                      color: kHkGreen,
                      borderRadius: BorderRadius.circular(12),
                      child: InkWell(
                        onTap: () => Navigator.of(context).pop(
                          LostFoundClaim(
                            name: _nameController.text.trim().isEmpty ? null : _nameController.text.trim(),
                            contact: _contactController.text.trim().isEmpty ? null : _contactController.text.trim(),
                          ),
                        ),
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          height: 44,
                          alignment: Alignment.center,
                          child: Text(
                            'Confirm Returned',
                            style: AppTypography.style(color: Colors.black, fontSize: 14, fontWeight: FontWeight.w700),
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
    );
  }

  Widget _field({required TextEditingController controller, required String label}) {
    return TextField(
      controller: controller,
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
