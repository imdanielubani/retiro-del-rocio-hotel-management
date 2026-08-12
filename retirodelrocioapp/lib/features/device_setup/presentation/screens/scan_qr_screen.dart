import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/data/provisioning_repository.dart';

/// Scan QR Code — camera pairing (derived from the Device Setup design).
///
/// Reads the provisioning QR issued at reception (JSON: device_code +
/// provision_token), binds the tablet, and pops with the ProvisionedDevice.
class ScanQrScreen extends StatefulWidget {
  const ScanQrScreen({super.key, this.repository});

  final ProvisioningRepository? repository;

  @override
  State<ScanQrScreen> createState() => _ScanQrScreenState();
}

class _ScanQrScreenState extends State<ScanQrScreen> {
  late final ProvisioningRepository _repository =
      widget.repository ?? ProvisioningRepository();
  final MobileScannerController _scanner = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );

  bool _handling = false;
  String? _error;

  @override
  void dispose() {
    _scanner.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_handling) return;
    final raw = capture.barcodes.firstOrNull?.rawValue;
    if (raw == null || raw.isEmpty) return;

    setState(() {
      _handling = true;
      _error = null;
    });
    await _scanner.stop();

    try {
      final payload = jsonDecode(raw);
      if (payload is! Map) throw ProvisioningException('Unrecognised QR code.');
      final device = await _repository.provisionWithQrPayload(
        payload.cast<String, dynamic>(),
      );
      if (mounted) Navigator.of(context).pop(device);
    } catch (error) {
      final message = error is ProvisioningException
          ? error.message
          : 'This QR code is not a valid setup code.';
      if (!mounted) return;
      setState(() {
        _handling = false;
        _error = message;
      });
      await _scanner.start();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(controller: _scanner, onDetect: _onDetect),
          const ColoredBox(color: Color(0x99000000)),
          _content(),
          Positioned(
            top: 40,
            left: 32,
            child: _BackButton(onTap: () => Navigator.of(context).maybePop()),
          ),
        ],
      ),
    );
  }

  Widget _content() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            'Scan Setup QR Code',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 26,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            'Point the camera at the QR code from reception',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.7),
              fontSize: 16,
            ),
          ),
          const SizedBox(height: 34),
          _viewport(),
          const SizedBox(height: 26),
          SizedBox(
            height: 24,
            child: _handling
                ? Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.gold,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Text(
                        'Pairing…',
                        style: AppTypography.style(
                          color: Colors.white,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  )
                : _error != null
                ? Text(
                    _error!,
                    style: AppTypography.style(
                      color: const Color(0xFFF87171),
                      fontSize: 14,
                    ),
                  )
                : null,
          ),
        ],
      ),
    );
  }

  Widget _viewport() {
    return Container(
      width: 300,
      height: 300,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: AppColors.gold, width: 3),
      ),
    );
  }
}

class _BackButton extends StatelessWidget {
  const _BackButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.12),
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: const SizedBox(
          width: 44,
          height: 44,
          child: Icon(Icons.arrow_back_rounded, color: Colors.white),
        ),
      ),
    );
  }
}
