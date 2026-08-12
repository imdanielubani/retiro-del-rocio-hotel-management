import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/data/reception_repository.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_pickup.dart';

/// Opens the assign-driver flow for [pickup]. Returns true if the assignment
/// changed.
Future<bool?> showReceptionAssignDriverDialog(
  BuildContext context, {
  required StaffSession session,
  required PickupBooking pickup,
}) {
  return showDialog<bool>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.7),
    builder: (_) => _AssignDriverDialog(session: session, pickup: pickup),
  );
}

class _AssignDriverDialog extends ConsumerStatefulWidget {
  const _AssignDriverDialog({required this.session, required this.pickup});

  final StaffSession session;
  final PickupBooking pickup;

  @override
  ConsumerState<_AssignDriverDialog> createState() =>
      _AssignDriverDialogState();
}

class _AssignDriverDialogState extends ConsumerState<_AssignDriverDialog> {
  int? _selectedId;
  bool _busy = false;
  String? _error;

  String get _token => widget.session.token;

  @override
  void initState() {
    super.initState();
    _selectedId = widget.pickup.driver?.id;
  }

  Future<void> _save() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref
          .read(receptionActionsProvider(_token))
          .assignDriver(widget.pickup.id, _selectedId);
      if (mounted) Navigator.of(context).pop(true);
    } on ReceptionException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted)
        setState(() => _error = 'Something went wrong. Please try again.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final maxHeight = (media.size.height - media.viewInsets.bottom - 48).clamp(
      280.0,
      680.0,
    );
    final driversAsync = ref.watch(receptionDriversProvider(_token));

    return Dialog(
      backgroundColor: const Color(0xFF161616),
      insetPadding: const EdgeInsets.all(24),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: 520, maxHeight: maxHeight),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _header(),
              const SizedBox(height: 18),
              Flexible(
                child: driversAsync.when(
                  loading: () => const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: Center(
                      child: CircularProgressIndicator(color: AppColors.gold),
                    ),
                  ),
                  error: (_, _) => _emptyRoster('Could not load drivers.'),
                  data: (drivers) => _roster(drivers),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: AppTypography.style(
                    color: const Color(0xFFEF4444),
                    fontSize: 13,
                  ),
                ),
              ],
              const SizedBox(height: 20),
              _footer(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _header() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Assign Driver',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${widget.pickup.guestName} · ${widget.pickup.reference}',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.5),
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
        Material(
          color: Colors.white.withValues(alpha: 0.08),
          shape: const CircleBorder(),
          child: InkWell(
            onTap: _busy ? null : () => Navigator.of(context).pop(false),
            customBorder: const CircleBorder(),
            child: const SizedBox(
              width: 34,
              height: 34,
              child: Icon(Icons.close_rounded, size: 18, color: Colors.white),
            ),
          ),
        ),
      ],
    );
  }

  Widget _roster(List<PickupDriver> drivers) {
    // Keep the currently-assigned driver visible even if they're now off the
    // available roster, so the selection still shows.
    final current = widget.pickup.driver;
    final list = [...drivers];
    if (current != null && !list.any((d) => d.id == current.id)) {
      list.insert(0, current);
    }

    if (list.isEmpty) {
      return _emptyRoster(
        'No available drivers. Add one from Admin → Vehicle Pickups → Drivers.',
      );
    }

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        _unassignTile(),
        const SizedBox(height: 10),
        for (final d in list) ...[_driverTile(d), const SizedBox(height: 10)],
      ],
    );
  }

  Widget _unassignTile() {
    final selected = _selectedId == null;
    return _tile(
      selected: selected,
      onTap: () => setState(() => _selectedId = null),
      title: 'Unassigned',
      subtitle: 'No driver on this pickup',
    );
  }

  Widget _driverTile(PickupDriver d) {
    final selected = _selectedId == d.id;
    final subtitle = [
      d.vehicleDetails,
      d.phone,
    ].where((s) => (s ?? '').isNotEmpty).join(' · ');
    return _tile(
      selected: selected,
      onTap: () => setState(() => _selectedId = d.id),
      title: d.name,
      subtitle: subtitle.isEmpty ? 'No contact details' : subtitle,
    );
  }

  Widget _tile({
    required bool selected,
    required VoidCallback onTap,
    required String title,
    required String subtitle,
  }) {
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.1)
          : Colors.white.withValues(alpha: 0.03),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: _busy ? null : onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.08),
              width: 0.8,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 18,
                height: 18,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: selected
                        ? AppColors.gold
                        : Colors.white.withValues(alpha: 0.3),
                    width: 2,
                  ),
                ),
                child: selected
                    ? Center(
                        child: Container(
                          width: 8,
                          height: 8,
                          decoration: const BoxDecoration(
                            color: AppColors.gold,
                            shape: BoxShape.circle,
                          ),
                        ),
                      )
                    : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.4),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _emptyRoster(String message) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 30),
      child: Center(
        child: Text(
          message,
          textAlign: TextAlign.center,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.5),
            fontSize: 13,
          ),
        ),
      ),
    );
  }

  Widget _footer() {
    return Row(
      children: [
        Expanded(
          child: TextButton(
            onPressed: _busy ? null : () => Navigator.of(context).pop(false),
            child: Text(
              'Cancel',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.7),
                fontSize: 14,
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: FilledButton(
            onPressed: _busy ? null : _save,
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.gold,
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            child: _busy
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.black,
                    ),
                  )
                : Text(
                    'Save',
                    style: AppTypography.style(
                      color: Colors.black,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
          ),
        ),
      ],
    );
  }
}
