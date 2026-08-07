import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/intercom/application/guest_intercom_call_providers.dart';
import 'package:retirodelrocioapp/features/guest/intercom/domain/intercom_department.dart';
import 'package:retirodelrocioapp/features/intercom_call/data/intercom_call_repository.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// 2.9 — Intercom (Figma 335:4684 / 335:5021). A voice-call directory to the
/// hotel's departments plus a general "Room Call" to the front desk.
/// Reception is the only station with a receiving screen built, so its card
/// (and the header's "Room Call" button) place a real, ringing call; the
/// other three departments have no receiving tablet yet, so their "Call"
/// stays the same confirmation-toast placeholder as before.
class GuestIntercomScreen extends ConsumerStatefulWidget {
  const GuestIntercomScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<GuestIntercomScreen> createState() =>
      _GuestIntercomScreenState();
}

class _GuestIntercomScreenState extends ConsumerState<GuestIntercomScreen> {
  static const _availableColor = Color(0xFF34D399);

  bool _placingCall = false;

  String get _token => widget.device.token;

  void _call(String label) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: const Color(0xFF14532D),
        content: Text(
          'Calling $label — someone will be with you shortly.',
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  Future<void> _callReception() async {
    if (_placingCall) return;
    setState(() => _placingCall = true);
    try {
      await ref.read(guestIntercomCallProvider(_token).notifier).place();
    } on IntercomCallException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) {
        _toast('Something went wrong. Please try again.', error: true);
      }
    } finally {
      if (mounted) setState(() => _placingCall = false);
    }
  }

  void _toast(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: error
            ? const Color(0xFF7F1D1D)
            : const Color(0xFF14532D),
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final status = widget.status;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
          const ColoredBox(color: Color.fromARGB(243, 0, 0, 0)),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(25, 24, 25, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GuestTopBar(
                    suiteName: status.suiteName ?? 'Suite',
                    roomNumber:
                        status.roomNumber ?? widget.device.roomNumber ?? '—',
                    guestName: status.guest?.name ?? 'Guest',
                    weather: ref.watch(weatherProvider).value,
                    onNotifications: () {},
                    onProfile: () {},
                  ),
                  const SizedBox(height: 20),
                  _header(),
                  const SizedBox(height: 24),
                  Expanded(child: _grid()),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _header() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        _backButton(),
        const SizedBox(width: 15),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Intercom',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                'Voice call with hotel department',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 16),
        _roomCallButton(),
      ],
    );
  }

  Widget _backButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      shape: CircleBorder(
        side: BorderSide(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: InkWell(
        onTap: () => Navigator.of(context).maybePop(),
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 40,
          height: 40,
          child: Icon(
            Icons.arrow_back_rounded,
            size: 18,
            color: Colors.white.withValues(alpha: 0.8),
          ),
        ),
      ),
    );
  }

  Widget _roomCallButton() {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: _placingCall ? null : _callReception,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.add_rounded, size: 18, color: Colors.black),
              const SizedBox(width: 8),
              Text(
                'Room Call',
                style: AppTypography.style(
                  color: Colors.black,
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _grid() {
    return GridView.builder(
      padding: EdgeInsets.zero,
      itemCount: IntercomDepartments.all.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: 17,
        crossAxisSpacing: 17,
        childAspectRatio: 393 / 228,
      ),
      itemBuilder: (_, i) => _departmentCard(IntercomDepartments.all[i]),
    );
  }

  Widget _departmentCard(IntercomDepartment department) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.09),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(department.icon, size: 20, color: AppColors.gold),
              ),
              _availablePill(),
            ],
          ),
          const SizedBox(height: 19),
          Text(
            department.label,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            department.tagline,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 12,
            ),
          ),
          const Spacer(),
          _callButton(department),
        ],
      ),
    );
  }

  Widget _availablePill() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: _availableColor.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: const BoxDecoration(
              color: _availableColor,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 6),
          Text(
            'available',
            style: AppTypography.style(color: _availableColor, fontSize: 10),
          ),
        ],
      ),
    );
  }

  Widget _callButton(IntercomDepartment department) {
    final isReception = department.id == IntercomDepartments.reception.id;

    return Material(
      color: _availableColor.withValues(alpha: 0.13),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: _placingCall
            ? null
            : isReception
            ? _callReception
            : () => _call(department.label),
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: 46,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: _availableColor.withValues(alpha: 0.25),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.call_rounded, size: 16, color: _availableColor),
              const SizedBox(width: 8),
              Text(
                'Call',
                style: AppTypography.style(
                  color: _availableColor,
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
