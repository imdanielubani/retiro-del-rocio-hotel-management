import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/guest/visitor_pass/application/visitor_pass_providers.dart';
import 'package:retirodelrocioapp/features/guest/visitor_pass/data/visitor_pass_repository.dart';
import 'package:retirodelrocioapp/features/guest/visitor_pass/domain/visitor_pass.dart';
import 'package:retirodelrocioapp/features/guest/visitor_pass/presentation/widgets/visitor_pass_card.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

const Color _codeGreen = Color(0xFF00FF00);

/// Height the two-pane body stops shrinking at and starts scrolling instead.
/// Roughly the fixed chrome (top bar, heading, padding) plus enough of a pane to
/// still be worth looking at — the keyboard is what pushes below this.
const double _minBodyHeight = 440;

/// Which panel the left column is showing.
enum _Panel { intro, form, success }

/// 2.7 — Visitor Pass (Figma 267:2721, 273:1181, 276:1383).
///
/// The checked-in guest invites a visitor by name (+ optional email / WhatsApp);
/// the server mints a unique 6-digit entry code the visitor quotes at the gate,
/// where security verifies them. One screen, three left-panel states — how it
/// works, the invite form, and the generated pass — beside the live visitor
/// history, which is read from the server so a security verify/deny reflects here
/// on its own.
class VisitorPassScreen extends ConsumerStatefulWidget {
  const VisitorPassScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<VisitorPassScreen> createState() => _VisitorPassScreenState();
}

class _VisitorPassScreenState extends ConsumerState<VisitorPassScreen> {
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();

  _Panel _panel = _Panel.intro;
  bool _busy = false;
  VisitorPass? _generated;

  ProvisionedDevice get device => widget.device;
  RoomStatus get status => widget.status;

  String get _guestName => status.guest?.name ?? 'Guest';

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  void _openForm() => setState(() => _panel = _Panel.form);

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GuestNotificationScreen(device: device, status: status),
      ),
    );
  }

  void _reset() {
    _nameController.clear();
    _emailController.clear();
    _phoneController.clear();
    setState(() {
      _generated = null;
      _panel = _Panel.intro;
    });
  }

  Future<void> _generate() async {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      _showError('Please enter the visitor’s name.');
      return;
    }

    setState(() => _busy = true);
    try {
      final pass = await ref
          .read(visitorPassActionsProvider(device.token))
          .create(
            name: name,
            email: _emailController.text.trim(),
            phone: _phoneController.text.trim(),
          );
      if (!mounted) return;
      setState(() {
        _generated = pass;
        _panel = _Panel.success;
      });
      unawaited(_awaitOnlineCode(pass));
    } on VisitorPassException catch (error) {
      _showError(error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// The one-time gate (TTLock) code is pushed to the locks just after the pass
  /// is committed, so it lands a beat after the success card appears. Watch for
  /// it briefly and swap it in, instead of leaving the guest reading out the
  /// backup code for a visitor who could have let themselves in.
  Future<void> _awaitOnlineCode(VisitorPass pass) async {
    if (pass.hasOnlineCode) return;

    final repo = ref.read(visitorPassRepositoryProvider);
    for (final seconds in const [2, 3, 5, 8]) {
      await Future<void>.delayed(Duration(seconds: seconds));
      // The guest moved on, or issued another pass — stop watching.
      if (!mounted || _generated?.id != pass.id) return;

      final passes = await repo.list(device.token);
      if (!mounted || _generated?.id != pass.id) return;

      for (final fresh in passes) {
        if (fresh.id == pass.id && fresh.hasOnlineCode) {
          setState(() => _generated = fresh);
          ref.invalidate(visitorPassesProvider(device.token));
          return;
        }
      }
    }
  }

  void _copyCode() {
    final code = _generated?.code;
    if (code == null) return;
    Clipboard.setData(ClipboardData(text: code));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: _codeGreen,
        duration: const Duration(seconds: 2),
        content: Text(
          'Entry code copied.',
          style: AppTypography.style(
            color: Colors.black,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }

  void _showError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFFDC2626),
        duration: const Duration(seconds: 5),
        content: Text(
          message,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final passes =
        ref.watch(visitorPassesProvider(device.token)).value ?? const [];

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
          const ColoredBox(color: Color.fromARGB(243, 0, 0, 0)),
          SafeArea(
            // The top bar and the page heading are a fixed height, so when the
            // keyboard opens on the invite form there is less room than the two
            // panes need and the column overflows its last few pixels. Give the
            // body a floor and let it scroll below that instead of overflowing:
            // with no keyboard the box is exactly the viewport, so nothing about
            // the layout changes.
            child: LayoutBuilder(
              builder: (context, constraints) {
                final height = math.max(constraints.maxHeight, _minBodyHeight);

                return SingleChildScrollView(
                  child: SizedBox(
                    height: height,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(25, 24, 25, 24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          GuestTopBar(
                            suiteName: status.suiteName ?? 'Suite',
                            roomNumber:
                                status.roomNumber ?? device.roomNumber ?? '—',
                            guestName: _guestName,
                            weather: ref.watch(weatherProvider).value,
                            onNotifications: _openNotifications,
                            onProfile: () {},
                            hasUnreadNotifications:
                                ref.watch(
                                  guestUnreadNotificationsProvider(
                                    widget.device.token,
                                  ),
                                ) >
                                0,
                          ),
                          const SizedBox(height: 22),
                          _headerRow(),
                          const SizedBox(height: 20),
                          Expanded(
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // Left: the active panel (how it works / form /
                                // success).
                                Expanded(
                                  flex: 52,
                                  child: SingleChildScrollView(
                                    child: _leftPanel(),
                                  ),
                                ),
                                const SizedBox(width: 28),
                                // Right: the live visitor history.
                                Expanded(flex: 48, child: _history(passes)),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  // --- Header ---------------------------------------------------------------

  Widget _headerRow() {
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
                'Visitor Pass',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Invite guests — they receive a unique entry code',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 16),
        _inviteButton(),
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

  Widget _inviteButton() {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: _openForm,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 11),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.person_add_alt_1_rounded,
                size: 18,
                color: Colors.black,
              ),
              const SizedBox(width: 8),
              Text(
                'Invite Guest',
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

  // --- Left panel -----------------------------------------------------------

  Widget _leftPanel() => switch (_panel) {
    _Panel.intro => _howItWorks(),
    _Panel.form => _form(),
    _Panel.success => _success(),
  };

  /// Default state — a quiet explainer (Figma 267:2525).
  Widget _howItWorks() {
    return Container(
      padding: const EdgeInsets.all(20.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.15),
          width: 0.8,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Icon(
              Icons.tag_rounded,
              size: 18,
              color: AppColors.gold.withValues(alpha: 0.9),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'How it works',
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Invite a visitor by entering their details. A unique 6-digit '
                  'code is generated and sent to security. Your visitor quotes '
                  'the code at the entrance to be verified.',
                  style: AppTypography.style(
                    color: Colors.white.withValues(alpha: 0.4),
                    fontSize: 12,
                    height: 1.6,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// Invite form — name, email, WhatsApp → generate (Figma 273:756).
  Widget _form() {
    return Container(
      padding: const EdgeInsets.all(24.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.2),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'New Visitor Pass',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 17,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          _field(
            label: 'VISITOR NAME',
            controller: _nameController,
            hint: 'Full name',
            icon: Icons.contacts_outlined,
            textInputAction: TextInputAction.next,
          ),
          const SizedBox(height: 14),
          _field(
            label: 'VISITOR EMAIL',
            controller: _emailController,
            hint: 'visitor@gmail.com',
            icon: Icons.mail_outline_rounded,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.next,
          ),
          const SizedBox(height: 14),
          _field(
            label: 'WHATSAPP NUMBER',
            controller: _phoneController,
            hint: '+234 50 000 0000',
            icon: Icons.phone_enabled_outlined,
            keyboardType: TextInputType.phone,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => _generate(),
          ),
          const SizedBox(height: 20),
          _primaryButton(
            label: 'Generate Entry Code',
            busy: _busy,
            onTap: _generate,
          ),
        ],
      ),
    );
  }

  Widget _field({
    required String label,
    required TextEditingController controller,
    required String hint,
    IconData? icon,
    TextInputType? keyboardType,
    TextInputAction? textInputAction,
    ValueChanged<String>? onSubmitted,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 11,
            fontWeight: FontWeight.w500,
            letterSpacing: 0.88,
          ),
        ),
        const SizedBox(height: 8),
        Container(
          height: 46,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.07),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.12),
              width: 0.8,
            ),
          ),
          child: Row(
            children: [
              if (icon != null) ...[
                const SizedBox(width: 14),
                Icon(
                  icon,
                  size: 14,
                  color: Colors.white.withValues(alpha: 0.5),
                ),
              ],
              Expanded(
                child: TextField(
                  controller: controller,
                  keyboardType: keyboardType,
                  textInputAction: textInputAction,
                  onSubmitted: onSubmitted,
                  cursorColor: AppColors.gold,
                  style: AppTypography.style(color: Colors.white, fontSize: 14),
                  decoration: InputDecoration(
                    isCollapsed: true,
                    border: InputBorder.none,
                    contentPadding: EdgeInsets.symmetric(
                      horizontal: icon != null ? 10 : 16,
                      vertical: 14,
                    ),
                    hintText: hint,
                    hintStyle: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.5),
                      fontSize: 14,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  /// Generated pass — the green confirmation with the entry code.
  Widget _success() {
    final pass = _generated!;

    // The Figma success card (276:1917): a green-bordered panel with its content
    // centred at a fixed ~301 width.
    const width = 301.0;

    return Container(
      padding: const EdgeInsets.all(24.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: _codeGreen.withValues(alpha: 0.35),
          width: 0.8,
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Icon(Icons.check_circle_outline_rounded, size: 36, color: _codeGreen),
          const SizedBox(height: 10),
          Text(
            'Pass Generated!',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            _guestName,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(width: width, child: _codePanel(pass)),
          const SizedBox(height: 16),
          SizedBox(width: width, child: _detailsBlock(pass)),
          const SizedBox(height: 16),
          SizedBox(width: width, child: _copyButton()),
          const SizedBox(height: 8),
          SizedBox(width: width, child: _doneButton()),
        ],
      ),
    );
  }

  Widget _codePanel(VisitorPass pass) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 20.8, horizontal: 32.8),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: _codeGreen.withValues(alpha: 0.4),
          width: 0.8,
        ),
      ),
      child: Column(
        children: [
          Text(
            'ENTRY CODE',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.35),
              fontSize: 10,
              letterSpacing: 2,
            ),
          ),
          const SizedBox(height: 8),
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              pass.code,
              style: AppTypography.style(
                color: _codeGreen,
                fontSize: 42,
                fontWeight: FontWeight.w700,
                letterSpacing: 10,
              ).copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Share this code with your visitor',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.3),
              fontSize: 11,
            ),
          ),
        ],
      ),
    );
  }

  /// The Pass No. / Email / WhatsApp rows (Figma 276:1942). When a live TTLock
  /// code exists, the manual backup code is appended as a further row.
  Widget _detailsBlock(VisitorPass pass) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        _detailRow('Pass No.', pass.reference),
        if ((pass.visitorEmail ?? '').isNotEmpty)
          _detailRow('Email', pass.visitorEmail!),
        if ((pass.visitorPhone ?? '').isNotEmpty)
          _detailRow('WhatsApp', pass.visitorPhone!),
        if (pass.hasOnlineCode && (pass.offlineCode ?? '').isNotEmpty)
          _detailRow('Backup code', pass.offlineCode!),
      ],
    );
  }

  Widget _detailRow(String label, String value) {
    return Container(
      padding: const EdgeInsets.only(top: 6, bottom: 6.8),
      decoration: BoxDecoration(
        border: Border(
          bottom: BorderSide(
            color: Colors.white.withValues(alpha: 0.06),
            width: 0.8,
          ),
        ),
      ),
      child: Row(
        children: [
          Text(
            label,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.35),
              fontSize: 12,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.right,
              style: AppTypography.style(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _copyButton() {
    return Material(
      color: _codeGreen.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: _copyCode,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 11),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: _codeGreen.withValues(alpha: 0.3),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.copy_rounded, size: 14, color: _codeGreen),
              const SizedBox(width: 8),
              Text(
                'Copy Code',
                style: AppTypography.style(
                  color: _codeGreen,
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

  Widget _doneButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: _reset,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 11),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.2),
              width: 0.8,
            ),
          ),
          child: Text(
            'Done',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 14,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ),
    );
  }

  Widget _primaryButton({
    required String label,
    required bool busy,
    required VoidCallback onTap,
  }) {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: busy ? null : onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: 48,
          alignment: Alignment.center,
          child: busy
              ? const SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.black,
                  ),
                )
              : Text(
                  label,
                  style: AppTypography.style(
                    color: Colors.black,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
        ),
      ),
    );
  }

  // --- Right: visitor history -----------------------------------------------

  Widget _history(List<VisitorPass> passes) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 12),
          child: Text(
            'VISITOR HISTORY',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 12,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.1,
            ),
          ),
        ),
        Expanded(
          child: passes.isEmpty
              ? _emptyHistory()
              : ListView.separated(
                  padding: const EdgeInsets.only(bottom: 12),
                  itemCount: passes.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (_, i) => VisitorPassCard(pass: passes[i]),
                ),
        ),
      ],
    );
  }

  Widget _emptyHistory() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 24),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.08),
          width: 0.8,
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.groups_outlined,
            size: 34,
            color: Colors.white.withValues(alpha: 0.25),
          ),
          const SizedBox(height: 14),
          Text(
            'No visitors yet',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.6),
              fontSize: 15,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Invite a guest and their entry code will appear here.',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.35),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }
}
