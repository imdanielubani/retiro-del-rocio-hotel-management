import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:retirodelrocioapp/app/app_router.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/device_setup/presentation/screens/scan_qr_screen.dart';
import 'package:retirodelrocioapp/features/device_setup/presentation/widgets/enter_code_dialog.dart';
import 'package:retirodelrocioapp/features/device_setup/presentation/widgets/pairing_success_dialog.dart';

/// 0.5 — Device Setup (Figma node 67:2720).
///
/// The tablet's "awaiting activation" screen: pair by scanning the QR issued
/// at reception, or by typing the short setup code.
class DeviceSetupScreen extends StatelessWidget {
  const DeviceSetupScreen({super.key});

  Future<void> _scanQr(BuildContext context) async {
    final device = await Navigator.of(context).push<ProvisionedDevice>(
      MaterialPageRoute(builder: (_) => const ScanQrScreen()),
    );
    if (device != null && context.mounted) _onPaired(context, device);
  }

  Future<void> _enterCode(BuildContext context) async {
    final device = await showDialog<ProvisionedDevice>(
      context: context,
      barrierDismissible: true,
      barrierColor: Colors.transparent,
      builder: (_) => const EnterCodeDialog(),
    );
    if (device != null && context.mounted) _onPaired(context, device);
  }

  Future<void> _onPaired(BuildContext context, ProvisionedDevice device) async {
    await showPairingSuccessDialog(context, device);
    if (!context.mounted) return;
    context.go(Routes.welcome, extra: device);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // Static room image (no video here) per the Figma design.
          Image.asset('assets/images/12375.jpg', fit: BoxFit.cover),
          const ColoredBox(color: AppColors.scrim),
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset(
                  'assets/icons/Rociologosetup.png',
                  width: 152,
                  height: 152,
                  fit: BoxFit.contain,
                ),
                const SizedBox(height: 6),
                Text(
                  'This tablet is awaiting activation',
                  textAlign: TextAlign.center,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 15),
                SizedBox(
                  width: 640,
                  child: Text(
                    'Please scan the QR code provided at check-in, or enter the '
                    'setup code from the reception team to pair this device to a suite.',
                    textAlign: TextAlign.center,
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.7),
                      fontSize: 16,
                      height: 1.35,
                    ),
                  ),
                ),
                const SizedBox(height: 42),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _SetupCard(
                      icon: Icons.qr_code_2_rounded,
                      label: 'Scan QR Code',
                      onTap: () => _scanQr(context),
                    ),
                    const SizedBox(width: 34),
                    _SetupCard(
                      icon: Icons.keyboard_alt_outlined,
                      label: 'Enter Setup Code',
                      onTap: () => _enterCode(context),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 40,
            child: Text(
              'RETIRO DEL ROCIO SYSTEM v1.0',
              textAlign: TextAlign.center,
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.3),
                fontSize: 11,
                letterSpacing: 1.65,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SetupCard extends StatelessWidget {
  const _SetupCard({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(25),
        child: Container(
          width: 262,
          padding: const EdgeInsets.symmetric(horizontal: 33, vertical: 33),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(25),
            border: Border.all(color: Colors.white.withValues(alpha: 0.85)),
          ),
          child: Column(
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.12),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, size: 32, color: AppColors.gold),
              ),
              const SizedBox(height: 28),
              Text(
                label,
                textAlign: TextAlign.center,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
