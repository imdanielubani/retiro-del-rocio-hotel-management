import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/data/reception_repository.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_checkin.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_overview.dart';

const Color _green = Color(0xFF22C55E);

/// Opens the reception check-in flow for [booking]. Returns true if the guest
/// was checked in.
Future<bool?> showReceptionCheckInDialog(
  BuildContext context, {
  required StaffSession session,
  required ReceptionBooking booking,
}) {
  return showDialog<bool>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.7),
    builder: (_) => _CheckInDialog(session: session, booking: booking),
  );
}

enum _Step { identity, room, complete }

/// The three-step Check In modal (Figma 346:15439 → 349:16154): Verify Identity,
/// Room Assignment, Complete.
class _CheckInDialog extends ConsumerStatefulWidget {
  const _CheckInDialog({required this.session, required this.booking});

  final StaffSession session;
  final ReceptionBooking booking;

  @override
  ConsumerState<_CheckInDialog> createState() => _CheckInDialogState();
}

class _CheckInDialogState extends ConsumerState<_CheckInDialog> {
  _Step _step = _Step.identity;

  // Step 1 — identity.
  String _docType = 'passport';
  final _numberController = TextEditingController();
  XFile? _document;
  bool _verified = false;

  // Step 2 — room.
  RoomOptions? _rooms;
  int? _selectedRoomId;
  bool _loadingRooms = false;

  // Shared.
  bool _busy = false;
  String? _error;
  CheckinConfirmation? _confirmation;

  String get _token => widget.session.token;

  @override
  void dispose() {
    _numberController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    // The tallest the dialog may be: the screen minus the keyboard inset and the
    // 24px inset padding top and bottom. Cap at 720 so it stays a modal on big
    // tablets. Header, stepper, body and error scroll together; only the footer
    // is pinned, so the flow never overflows when the keyboard is up.
    final maxHeight =
        (media.size.height - media.viewInsets.bottom - 48).clamp(280.0, 720.0);

    return Dialog(
      backgroundColor: const Color(0xFF161616),
      insetPadding: const EdgeInsets.all(24),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: 640, maxHeight: maxHeight),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Flexible(
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _header(),
                      const SizedBox(height: 22),
                      _stepper(),
                      const SizedBox(height: 22),
                      _body(),
                      if (_error != null) ...[
                        const SizedBox(height: 12),
                        Text(
                          _error!,
                          style: AppTypography.style(color: const Color(0xFFEF4444), fontSize: 13),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),
              _footer(),
            ],
          ),
        ),
      ),
    );
  }

  // --- Header ---------------------------------------------------------------

  Widget _header() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Check In',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${widget.booking.guestName} · ${widget.booking.reference}',
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

  Widget _stepper() {
    return Row(
      children: [
        _stepDot(1, 'Verify Identity', _Step.identity),
        _stepConnector(),
        _stepDot(2, 'Room Assignment', _Step.room),
        _stepConnector(),
        _stepDot(3, 'Complete', _Step.complete),
      ],
    );
  }

  Widget _stepDot(int n, String label, _Step step) {
    final index = _Step.values.indexOf(step);
    final current = _Step.values.indexOf(_step);
    final done = index < current;
    final active = index == current;

    return Column(
      children: [
        Container(
          width: 30,
          height: 30,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: done
                ? _green
                : active
                    ? AppColors.gold
                    : Colors.white.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: done
              ? const Icon(Icons.check_rounded, size: 16, color: Colors.white)
              : Text(
                  '$n',
                  style: AppTypography.style(
                    color: active ? Colors.black : Colors.white.withValues(alpha: 0.5),
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
        ),
        const SizedBox(height: 6),
        Text(
          label,
          style: AppTypography.style(
            color: active ? Colors.white : Colors.white.withValues(alpha: 0.4),
            fontSize: 11,
            fontWeight: active ? FontWeight.w600 : FontWeight.w400,
          ),
        ),
      ],
    );
  }

  Widget _stepConnector() => Expanded(
        child: Container(
          height: 1,
          margin: const EdgeInsets.only(bottom: 20),
          color: Colors.white.withValues(alpha: 0.1),
        ),
      );

  // --- Body -----------------------------------------------------------------

  Widget _body() => switch (_step) {
        _Step.identity => _identityStep(),
        _Step.room => _roomStep(),
        _Step.complete => _completeStep(),
      };

  Widget _identityStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _bookingCard(),
        const SizedBox(height: 20),
        _label('DOCUMENT TYPE'),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(child: _docToggle('International Passport', 'passport')),
            const SizedBox(width: 12),
            Expanded(child: _docToggle('NIN', 'nin')),
          ],
        ),
        const SizedBox(height: 16),
        _label(_docType == 'passport' ? 'PASSPORT NUMBER' : 'NIN NUMBER'),
        const SizedBox(height: 8),
        _numberField(),
        const SizedBox(height: 16),
        _label(_docType == 'passport' ? 'PASSPORT PHOTO' : 'NIN PHOTO'),
        const SizedBox(height: 8),
        _uploadZone(),
        const SizedBox(height: 20),
        _verifyButton(),
      ],
    );
  }

  Widget _bookingCard() {
    final initials = widget.booking.guestName.trim().isEmpty
        ? 'G'
        : widget.booking.guestName.trim().split(RegExp(r'\s+')).take(2).map((p) => p[0]).join().toUpperCase();

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            alignment: Alignment.center,
            decoration: const BoxDecoration(color: AppColors.gold, shape: BoxShape.circle),
            child: Text(
              initials,
              style: AppTypography.style(color: Colors.black, fontSize: 13, fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.booking.guestName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 2),
                Text(
                  widget.booking.roomLabel,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Text(
            widget.booking.reference,
            style: AppTypography.style(
              color: AppColors.gold,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _docToggle(String label, String value) {
    final selected = _docType == value;
    return Material(
      color: selected ? AppColors.gold.withValues(alpha: 0.15) : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: _busy ? null : () => setState(() => _docType = value),
        borderRadius: BorderRadius.circular(10),
        child: Container(
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: selected ? AppColors.gold.withValues(alpha: 0.6) : Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.7),
              fontSize: 13,
              fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
            ),
          ),
        ),
      ),
    );
  }

  Widget _numberField() {
    return TextField(
      controller: _numberController,
      enabled: !_busy,
      onChanged: (_) => setState(() {}),
      cursorColor: AppColors.gold,
      style: AppTypography.style(color: Colors.white, fontSize: 14),
      decoration: InputDecoration(
        isDense: true,
        filled: true,
        fillColor: Colors.white.withValues(alpha: 0.05),
        hintText: _docType == 'passport' ? 'Enter passport number' : 'Enter NIN number',
        hintStyle: AppTypography.style(color: Colors.white.withValues(alpha: 0.35), fontSize: 14),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: _fieldBorder(0.1),
        enabledBorder: _fieldBorder(0.1),
        focusedBorder: _fieldBorder(0.5, AppColors.gold),
        disabledBorder: _fieldBorder(0.1),
      ),
    );
  }

  OutlineInputBorder _fieldBorder(double alpha, [Color? color]) => OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: (color ?? Colors.white).withValues(alpha: alpha), width: 0.8),
      );

  Widget _uploadZone() {
    final picked = _document != null;
    return Material(
      color: Colors.white.withValues(alpha: 0.03),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: _busy ? null : _pickDocument,
        borderRadius: BorderRadius.circular(12),
        child: DottedBorderBox(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 22, horizontal: 16),
            child: Column(
              children: [
                Icon(
                  picked ? Icons.check_circle_rounded : Icons.image_outlined,
                  size: 24,
                  color: picked ? _green : Colors.white.withValues(alpha: 0.4),
                ),
                const SizedBox(height: 8),
                Text(
                  picked ? _document!.name : 'Tap to snap or upload document',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(
                    color: picked ? Colors.white : Colors.white.withValues(alpha: 0.6),
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  picked ? 'Tap to replace' : 'Photo · PDF · Scan',
                  style: AppTypography.style(color: Colors.white.withValues(alpha: 0.3), fontSize: 11),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _verifyButton() {
    final canVerify = _numberController.text.trim().isNotEmpty || _document != null;

    if (_verified) {
      return Container(
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: _green.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: _green.withValues(alpha: 0.4), width: 0.8),
        ),
        child: Text(
          'Identity Verified',
          style: AppTypography.style(color: _green, fontSize: 14, fontWeight: FontWeight.w600),
        ),
      );
    }

    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: (canVerify && !_busy) ? () => setState(() => _verified = true) : null,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: Colors.white.withValues(alpha: 0.12), width: 0.8),
          ),
          child: Text(
            'Mark as Verified',
            style: AppTypography.style(
              color: canVerify ? Colors.white : Colors.white.withValues(alpha: 0.35),
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  Widget _roomStep() {
    if (_loadingRooms) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 40),
        child: Center(child: CircularProgressIndicator(color: AppColors.gold)),
      );
    }
    final rooms = _rooms;
    if (rooms == null) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 40),
        child: Center(
          child: Text(
            'Could not load rooms.',
            style: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 14),
          ),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.04),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.gold.withValues(alpha: 0.25), width: 0.8),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Assigned Room',
                style: AppTypography.style(color: Colors.white.withValues(alpha: 0.4), fontSize: 12),
              ),
              const SizedBox(height: 6),
              Text(
                rooms.assigned?.number ?? '—',
                style: AppTypography.style(color: AppColors.gold, fontSize: 26, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 2),
              Text(
                rooms.assigned?.roomName ?? widget.booking.roomLabel,
                style: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 13),
              ),
              if (rooms.assigned?.hasTablet ?? false) ...[
                const SizedBox(height: 12),
                _tabletChip(),
              ],
            ],
          ),
        ),
        if (rooms.available.isNotEmpty) ...[
          const SizedBox(height: 18),
          _label('AVAILABLE ROOMS'),
          const SizedBox(height: 10),
          for (final option in rooms.available) ...[
            _roomRadio(option),
            const SizedBox(height: 10),
          ],
        ],
      ],
    );
  }

  Widget _roomRadio(RoomOption option) {
    final selected = _selectedRoomId == option.id;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: _busy ? null : () => setState(() => _selectedRoomId = selected ? _rooms?.assigned?.id : option.id),
        borderRadius: BorderRadius.circular(10),
        child: Row(
          children: [
            Container(
              width: 18,
              height: 18,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.3),
                  width: 2,
                ),
              ),
              child: selected
                  ? Center(
                      child: Container(
                        width: 8,
                        height: 8,
                        decoration: const BoxDecoration(color: AppColors.gold, shape: BoxShape.circle),
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
                    'Room ${option.number} · ${option.roomName}',
                    style: AppTypography.style(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                  if ((option.priceLabel ?? '').isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      option.priceLabel!,
                      style: AppTypography.style(color: Colors.white.withValues(alpha: 0.4), fontSize: 12),
                    ),
                  ],
                ],
              ),
            ),
            if (option.hasTablet) ...[
              const SizedBox(width: 8),
              _tabletChip(),
            ],
          ],
        ),
      ),
    );
  }

  /// The in-room tablet mark — same meaning as the admin dashboard's: the
  /// guest's details appear on this room's device the moment they check in.
  Widget _tabletChip() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: _green.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: _green.withValues(alpha: 0.3), width: 0.8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.tablet_mac_rounded, size: 12, color: _green),
          const SizedBox(width: 5),
          Text(
            'In-room tablet',
            style: AppTypography.style(color: _green, fontSize: 11, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }

  Widget _completeStep() {
    final c = _confirmation;
    return Column(
      children: [
        const SizedBox(height: 8),
        Container(
          width: 76,
          height: 76,
          decoration: BoxDecoration(color: _green.withValues(alpha: 0.15), shape: BoxShape.circle),
          child: const Icon(Icons.check_circle_rounded, size: 44, color: _green),
        ),
        const SizedBox(height: 18),
        Text(
          'Check-In Complete',
          style: AppTypography.style(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 20),
        if (c != null)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.04),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
            ),
            child: Column(
              children: [
                _summaryRow('Confirmation', c.confirmation),
                _summaryRow('Guest', c.guestName),
                if ((c.roomNumber ?? '').isNotEmpty) _summaryRow('Room', c.roomNumber!),
                if ((c.checkInTime ?? '').isNotEmpty) _summaryRow('Check-In Time', c.checkInTime!),
                if ((c.documentLabel ?? '').isNotEmpty)
                  _summaryRow(c.documentLabel!, c.documentNumber ?? '—', last: true),
              ],
            ),
          ),
      ],
    );
  }

  Widget _summaryRow(String label, String value, {bool last = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 11),
      decoration: BoxDecoration(
        border: last
            ? null
            : Border(bottom: BorderSide(color: Colors.white.withValues(alpha: 0.06), width: 0.8)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: AppTypography.style(color: Colors.white.withValues(alpha: 0.45), fontSize: 12),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.style(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }

  // --- Footer ---------------------------------------------------------------

  Widget _footer() {
    return switch (_step) {
      _Step.identity => Align(
          alignment: Alignment.centerRight,
          child: _primaryButton('Next', enabled: _verified, onTap: _goToRooms),
        ),
      _Step.room => Row(
          children: [
            _secondaryButton('Back', () => setState(() => _step = _Step.identity)),
            const Spacer(),
            _primaryButton('Next', enabled: !_busy, busy: _busy, onTap: _submit),
          ],
        ),
      _Step.complete => Center(
          child: _primaryButton('Close', enabled: true, onTap: () => Navigator.of(context).pop(true)),
        ),
    };
  }

  Widget _primaryButton(String label, {required bool enabled, bool busy = false, required VoidCallback onTap}) {
    return Material(
      color: enabled ? AppColors.gold : Colors.white.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: (enabled && !busy) ? onTap : null,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 12),
          alignment: Alignment.center,
          child: busy
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
              : Text(
                  label,
                  style: AppTypography.style(
                    color: enabled ? Colors.black : Colors.white.withValues(alpha: 0.4),
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
        ),
      ),
    );
  }

  Widget _secondaryButton(String label, VoidCallback onTap) {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: _busy ? null : onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          child: Text(
            label,
            style: AppTypography.style(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
          ),
        ),
      ),
    );
  }

  Widget _label(String text) => Text(
        text,
        style: AppTypography.style(
          color: Colors.white.withValues(alpha: 0.4),
          fontSize: 11,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.8,
        ),
      );

  // --- Actions --------------------------------------------------------------

  Future<void> _pickDocument() async {
    final source = await showModalBottomSheet<ImageSource>(
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
              leading: const Icon(Icons.photo_camera_rounded, color: AppColors.gold),
              title: Text('Take a photo', style: AppTypography.style(color: Colors.white, fontSize: 15)),
              onTap: () => Navigator.of(context).pop(ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded, color: AppColors.gold),
              title: Text('Choose from gallery', style: AppTypography.style(color: Colors.white, fontSize: 15)),
              onTap: () => Navigator.of(context).pop(ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;

    try {
      final picked = await ImagePicker().pickImage(source: source, imageQuality: 80);
      if (picked != null && mounted) {
        setState(() => _document = picked);
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not open the camera or gallery.');
    }
  }

  Future<void> _goToRooms() async {
    setState(() {
      _step = _Step.room;
      _loadingRooms = true;
      _error = null;
    });
    try {
      final rooms = await ref.read(receptionActionsProvider(_token)).roomOptions(widget.booking.id);
      if (!mounted) return;
      setState(() {
        _rooms = rooms;
        _selectedRoomId = rooms.assigned?.id;
        _loadingRooms = false;
      });
    } on ReceptionException catch (e) {
      if (!mounted) return;
      setState(() {
        _loadingRooms = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingRooms = false;
        _error = 'Could not load the rooms.';
      });
    }
  }

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final confirmation = await ref.read(receptionActionsProvider(_token)).checkIn(
            widget.booking.id,
            documentType: _docType,
            documentNumber: _numberController.text.trim(),
            documentPath: _document?.path,
            documentName: _document?.name,
            roomUnitId: _selectedRoomId,
          );
      if (!mounted) return;
      setState(() {
        _confirmation = confirmation;
        _step = _Step.complete;
        _busy = false;
      });
    } on ReceptionException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = 'Could not complete the check-in.';
      });
    }
  }
}

/// A dashed rounded border for the upload dropzone.
class DottedBorderBox extends StatelessWidget {
  const DottedBorderBox({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _DashedBorderPainter(),
      child: child,
    );
  }
}

class _DashedBorderPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withValues(alpha: 0.18)
      ..strokeWidth = 1
      ..style = PaintingStyle.stroke;

    final rrect = RRect.fromRectAndRadius(
      Offset.zero & size,
      const Radius.circular(12),
    );
    final path = Path()..addRRect(rrect);

    const dash = 6.0;
    const gap = 4.0;
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        canvas.drawPath(
          metric.extractPath(distance, distance + dash),
          paint,
        );
        distance += dash + gap;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
