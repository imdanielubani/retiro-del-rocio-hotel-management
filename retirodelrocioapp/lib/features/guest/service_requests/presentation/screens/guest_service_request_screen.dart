import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/application/service_request_providers.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/data/service_request_repository.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/domain/guest_service_request.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/presentation/widgets/service_request_card.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

const Color _confirmGreen = Color(0xFF00FF00);
const Color _housekeepingGold = AppColors.gold;
const Color _maintenanceBlue = Color(0xFF3B82F6);

/// Height the two-pane body stops shrinking at and starts scrolling instead —
/// same rule as the Visitor Pass screen this one is built alongside.
const double _minBodyHeight = 440;

/// Which panel the left column is showing.
enum _Panel { category, housekeeping, maintenance, success }

/// Service Request — the guest picks Housekeeping (towels, amenities, DND,
/// make-up room) or Maintenance (report a fault), fills a short form, and the
/// request lands straight on the relevant staff tablet's own board. One
/// screen, several left-panel states, beside the live request history —
/// mirrors the Visitor Pass screen's shape exactly.
class GuestServiceRequestScreen extends ConsumerStatefulWidget {
  const GuestServiceRequestScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<GuestServiceRequestScreen> createState() => _GuestServiceRequestScreenState();
}

class _GuestServiceRequestScreenState extends ConsumerState<GuestServiceRequestScreen> {
  final _notesController = TextEditingController();
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();

  _Panel _panel = _Panel.category;
  bool _busy = false;
  HousekeepingRequestType _selectedType = HousekeepingRequestType.towels;
  MaintenancePriority _selectedPriority = MaintenancePriority.medium;
  GuestServiceRequest? _submitted;

  ProvisionedDevice get device => widget.device;
  RoomStatus get status => widget.status;

  @override
  void dispose() {
    _notesController.dispose();
    _titleController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GuestNotificationScreen(device: device, status: status),
      ),
    );
  }

  void _pickHousekeeping() => setState(() => _panel = _Panel.housekeeping);

  void _pickMaintenance() => setState(() => _panel = _Panel.maintenance);

  void _reset() {
    _notesController.clear();
    _titleController.clear();
    _descriptionController.clear();
    setState(() {
      _selectedType = HousekeepingRequestType.towels;
      _selectedPriority = MaintenancePriority.medium;
      _submitted = null;
      _panel = _Panel.category;
    });
  }

  Future<void> _submitHousekeeping() async {
    setState(() => _busy = true);
    try {
      final request = await ref
          .read(serviceRequestActionsProvider(device.token))
          .createHousekeeping(type: _selectedType.value, notes: _notesController.text.trim());
      if (!mounted) return;
      setState(() {
        _submitted = request;
        _panel = _Panel.success;
      });
    } on ServiceRequestException catch (error) {
      _showError(error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _submitMaintenance() async {
    final title = _titleController.text.trim();
    if (title.isEmpty) {
      _showError('Please describe the fault in a few words.');
      return;
    }

    setState(() => _busy = true);
    try {
      final request = await ref
          .read(serviceRequestActionsProvider(device.token))
          .createMaintenance(
            title: title,
            description: _descriptionController.text.trim(),
            priority: _selectedPriority.value,
          );
      if (!mounted) return;
      setState(() {
        _submitted = request;
        _panel = _Panel.success;
      });
    } on ServiceRequestException catch (error) {
      _showError(error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFFDC2626),
        duration: const Duration(seconds: 5),
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final requests = ref.watch(guestServiceRequestsProvider(device.token)).value ?? const [];

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
          const ColoredBox(color: Color.fromARGB(243, 0, 0, 0)),
          SafeArea(
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
                            roomNumber: status.roomNumber ?? device.roomNumber ?? '—',
                            guestName: status.guest?.name ?? 'Guest',
                            weather: ref.watch(weatherProvider).value,
                            onNotifications: _openNotifications,
                            onProfile: () {},
                            hasUnreadNotifications:
                                ref.watch(guestUnreadNotificationsProvider(device.token)) > 0,
                          ),
                          const SizedBox(height: 22),
                          _headerRow(),
                          const SizedBox(height: 20),
                          Expanded(
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  flex: 52,
                                  child: SingleChildScrollView(child: _leftPanel()),
                                ),
                                const SizedBox(width: 28),
                                Expanded(flex: 48, child: _history(requests)),
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

  // --- Header -----------------------------------------------------------

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
                'Service Request',
                style: AppTypography.style(color: Colors.white, fontSize: 36, fontWeight: FontWeight.w700, height: 1.15),
              ),
              const SizedBox(height: 4),
              Text(
                'Housekeeping asks and maintenance faults, in one place',
                style: AppTypography.style(color: Colors.white.withValues(alpha: 0.4), fontSize: 15),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _backButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      shape: CircleBorder(side: BorderSide(color: Colors.white.withValues(alpha: 0.1), width: 0.8)),
      child: InkWell(
        onTap: () {
          if (_panel != _Panel.category) {
            _reset();
            return;
          }
          Navigator.of(context).maybePop();
        },
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 40,
          height: 40,
          child: Icon(Icons.arrow_back_rounded, size: 18, color: Colors.white.withValues(alpha: 0.8)),
        ),
      ),
    );
  }

  // --- Left panel ---------------------------------------------------------

  Widget _leftPanel() => switch (_panel) {
    _Panel.category => _categoryPicker(),
    _Panel.housekeeping => _housekeepingForm(),
    _Panel.maintenance => _maintenanceForm(),
    _Panel.success => _success(),
  };

  Widget _categoryPicker() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _categoryCard(
          color: _housekeepingGold,
          icon: Icons.cleaning_services_rounded,
          title: 'Housekeeping',
          subtitle: 'Towels, amenities, Do Not Disturb, or a make-up-room visit',
          onTap: _pickHousekeeping,
        ),
        const SizedBox(height: 16),
        _categoryCard(
          color: _maintenanceBlue,
          icon: Icons.build_rounded,
          title: 'Maintenance',
          subtitle: 'Report something broken or not working in your room',
          onTap: _pickMaintenance,
        ),
      ],
    );
  }

  Widget _categoryCard({
    required Color color,
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.all(20.8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: color.withValues(alpha: 0.25), width: 0.8),
          ),
          child: Row(
            children: [
              Container(
                width: 52,
                height: 52,
                alignment: Alignment.center,
                decoration: BoxDecoration(color: color.withValues(alpha: 0.14), shape: BoxShape.circle),
                child: Icon(icon, size: 24, color: color),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      title,
                      style: AppTypography.style(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: AppTypography.style(color: Colors.white.withValues(alpha: 0.45), fontSize: 12, height: 1.4),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: Colors.white.withValues(alpha: 0.3)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _housekeepingForm() {
    return _formShell(
      title: 'Housekeeping Request',
      accent: _housekeepingGold,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'WHAT DO YOU NEED',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 11,
              fontWeight: FontWeight.w500,
              letterSpacing: 0.88,
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final type in HousekeepingRequestType.values)
                _typeChip(type),
            ],
          ),
          const SizedBox(height: 16),
          _textField(
            label: 'NOTES (OPTIONAL)',
            controller: _notesController,
            hint: 'Anything else housekeeping should know',
            maxLines: 3,
          ),
          const SizedBox(height: 20),
          _primaryButton(label: 'Send Request', accent: _housekeepingGold, busy: _busy, onTap: _submitHousekeeping),
        ],
      ),
    );
  }

  Widget _typeChip(HousekeepingRequestType type) {
    final selected = _selectedType == type;
    return Material(
      color: selected ? _housekeepingGold.withValues(alpha: 0.16) : Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _selectedType = type),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected ? _housekeepingGold.withValues(alpha: 0.5) : Colors.white.withValues(alpha: 0.12),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(type.icon, size: 14, color: selected ? _housekeepingGold : Colors.white.withValues(alpha: 0.6)),
              const SizedBox(width: 6),
              Text(
                type.label,
                style: AppTypography.style(
                  color: selected ? _housekeepingGold : Colors.white.withValues(alpha: 0.7),
                  fontSize: 13,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _maintenanceForm() {
    return _formShell(
      title: 'Report a Fault',
      accent: _maintenanceBlue,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _textField(label: "WHAT'S WRONG", controller: _titleController, hint: 'e.g. AC not cooling'),
          const SizedBox(height: 14),
          _textField(
            label: 'DETAILS (OPTIONAL)',
            controller: _descriptionController,
            hint: 'Anything that will help maintenance fix it faster',
            maxLines: 3,
          ),
          const SizedBox(height: 16),
          Text(
            'HOW URGENT',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 11,
              fontWeight: FontWeight.w500,
              letterSpacing: 0.88,
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [for (final p in MaintenancePriority.values) _priorityChip(p)],
          ),
          const SizedBox(height: 20),
          _primaryButton(label: 'Send Request', accent: _maintenanceBlue, busy: _busy, onTap: _submitMaintenance),
        ],
      ),
    );
  }

  Widget _priorityChip(MaintenancePriority priority) {
    final selected = _selectedPriority == priority;
    return Material(
      color: selected ? _maintenanceBlue.withValues(alpha: 0.16) : Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => setState(() => _selectedPriority = priority),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected ? _maintenanceBlue.withValues(alpha: 0.5) : Colors.white.withValues(alpha: 0.12),
              width: 0.8,
            ),
          ),
          child: Text(
            priority.label,
            style: AppTypography.style(
              color: selected ? _maintenanceBlue : Colors.white.withValues(alpha: 0.7),
              fontSize: 13,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
            ),
          ),
        ),
      ),
    );
  }

  Widget _formShell({required String title, required Color accent, required Widget child}) {
    return Container(
      padding: const EdgeInsets.all(24.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: accent.withValues(alpha: 0.2), width: 0.8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: AppTypography.style(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }

  Widget _textField({
    required String label,
    required TextEditingController controller,
    required String hint,
    int maxLines = 1,
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
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.07),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white.withValues(alpha: 0.12), width: 0.8),
          ),
          child: TextField(
            controller: controller,
            maxLines: maxLines,
            cursorColor: AppColors.gold,
            style: AppTypography.style(color: Colors.white, fontSize: 14),
            decoration: InputDecoration(
              isCollapsed: true,
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              hintText: hint,
              hintStyle: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 14),
            ),
          ),
        ),
      ],
    );
  }

  Widget _primaryButton({
    required String label,
    required Color accent,
    required bool busy,
    required VoidCallback onTap,
  }) {
    return Material(
      color: accent,
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
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black),
                )
              : Text(
                  label,
                  style: AppTypography.style(color: Colors.black, fontSize: 14, fontWeight: FontWeight.w600),
                ),
        ),
      ),
    );
  }

  Widget _success() {
    final request = _submitted!;
    const width = 301.0;

    return Container(
      padding: const EdgeInsets.all(24.8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _confirmGreen.withValues(alpha: 0.35), width: 0.8),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Icon(Icons.check_circle_outline_rounded, size: 36, color: _confirmGreen),
          const SizedBox(height: 10),
          Text(
            'Request Sent!',
            style: AppTypography.style(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          Text(
            request.title,
            textAlign: TextAlign.center,
            style: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 13),
          ),
          const SizedBox(height: 20),
          SizedBox(
            width: width,
            child: Text(
              request.isHousekeeping
                  ? 'Housekeeping has been notified and will attend to your room shortly.'
                  : 'Maintenance has been notified and will look into it shortly.',
              textAlign: TextAlign.center,
              style: AppTypography.style(color: Colors.white.withValues(alpha: 0.5), fontSize: 13, height: 1.5),
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(width: width, child: _doneButton()),
        ],
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
            border: Border.all(color: Colors.white.withValues(alpha: 0.2), width: 0.8),
          ),
          child: Text(
            'Done',
            style: AppTypography.style(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500),
          ),
        ),
      ),
    );
  }

  // --- Right: request history ----------------------------------------------

  Widget _history(List<GuestServiceRequest> requests) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 12),
          child: Text(
            'REQUEST HISTORY',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 12,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.1,
            ),
          ),
        ),
        Expanded(
          child: requests.isEmpty
              ? _emptyHistory()
              : ListView.separated(
                  padding: const EdgeInsets.only(bottom: 12),
                  itemCount: requests.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (_, i) => ServiceRequestCard(request: requests[i]),
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
        border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 0.8),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.support_agent_rounded, size: 34, color: Colors.white.withValues(alpha: 0.25)),
          const SizedBox(height: 14),
          Text(
            'No requests yet',
            style: AppTypography.style(color: Colors.white.withValues(alpha: 0.6), fontSize: 15, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 6),
          Text(
            'Ask housekeeping or report a fault and it will show up here.',
            textAlign: TextAlign.center,
            style: AppTypography.style(color: Colors.white.withValues(alpha: 0.35), fontSize: 13),
          ),
        ],
      ),
    );
  }
}
