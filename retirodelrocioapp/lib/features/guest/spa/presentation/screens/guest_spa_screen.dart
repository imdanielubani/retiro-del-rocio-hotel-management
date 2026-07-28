import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/screens/paystack_checkout_screen.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/spa/application/spa_providers.dart';
import 'package:retirodelrocioapp/features/guest/spa/domain/spa_service.dart';
import 'package:retirodelrocioapp/features/guest/spa/presentation/screens/guest_spa_appointments_screen.dart';
import 'package:retirodelrocioapp/features/guest/spa/presentation/widgets/spa_dialogs.dart';
import 'package:retirodelrocioapp/features/guest/spa/presentation/widgets/spa_service_card.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// 2.8 — Spa & Wellness (Figma 162:4569 → 332:3082). Browse the spa's real
/// service catalogue — grouped by the admin's real categories — pick a time
/// and how to pay — charged straight to the room, or paid directly via
/// Paystack — then confirm.
class GuestSpaScreen extends ConsumerStatefulWidget {
  const GuestSpaScreen({super.key, required this.device, required this.status});

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<GuestSpaScreen> createState() => _GuestSpaScreenState();
}

class _GuestSpaScreenState extends ConsumerState<GuestSpaScreen> {
  String? _selectedServiceSlug;
  String? _selectedTime;
  SpaPaymentMethod? _paymentMethod;

  /// null = the "All" chip — every category shown.
  int? _selectedCategoryId;

  String get _token => widget.device.token;

  void _selectService(SpaService service) {
    setState(() => _selectedServiceSlug = service.slug);
  }

  void _selectTime(String time) {
    setState(() => _selectedTime = time);
  }

  void _selectPayment(SpaPaymentMethod method) {
    setState(() => _paymentMethod = method);
  }

  void _selectCategory(int? categoryId) {
    setState(() => _selectedCategoryId = categoryId);
  }

  Future<void> _openPaymentSummary(SpaService service) async {
    final method = _paymentMethod;
    final time = _selectedTime;
    if (method == null || time == null) return;

    final naira = NumberFormat('#,###');
    final subtotal = service.price;
    final vat = (subtotal * 0.075).round();
    final total = subtotal + vat;
    final buttonLabel = method == SpaPaymentMethod.room
        ? 'BOOK NOW'
        : 'PAY NOW';

    final confirmation = await showSpaPaymentDialog(
      context,
      serviceLabel: service.name,
      guests: 1,
      subtotalLabel: 'NGN ${naira.format(subtotal)}',
      vatLabel: 'NGN ${naira.format(vat)}',
      totalLabel: 'NGN ${naira.format(total)}',
      buttonLabel: buttonLabel,
      onConfirm: () => method == SpaPaymentMethod.room
          ? _bookToRoom(service, time)
          : _payWithPaystack(service, time),
    );

    if (confirmation == null || !mounted) return;

    await showSpaBookingConfirmedDialog(context, confirmation: confirmation);
    if (mounted) {
      setState(() {
        _selectedServiceSlug = null;
        _selectedTime = null;
        _paymentMethod = null;
      });
      ref.invalidate(guestNotificationsProvider(_token));
    }
  }

  Future<SpaBookingConfirmation?> _bookToRoom(SpaService service, String time) {
    return ref.read(spaActionsProvider(_token)).bookToRoom(service.slug, time);
  }

  Future<SpaBookingConfirmation?> _payWithPaystack(
    SpaService service,
    String time,
  ) async {
    final quote = await ref
        .read(spaActionsProvider(_token))
        .initializePaystack(service.slug, time);
    if (!mounted) return null;

    final paid = await showPaystackCheckout(
      context,
      authorizationUrl: quote.authorizationUrl,
      callbackUrl: quote.callbackUrl,
    );
    if (!paid) return null;

    return ref
        .read(spaActionsProvider(_token))
        .confirmPaystack(quote.reference);
  }

  @override
  Widget build(BuildContext context) {
    final catalogAsync = ref.watch(spaCatalogProvider(_token));
    final catalog = catalogAsync.value ?? SpaCatalog.empty;
    final weather = ref.watch(weatherProvider).value;
    final guest = widget.status.guest;

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
                    suiteName: widget.status.suiteName ?? 'Suite',
                    roomNumber:
                        widget.status.roomNumber ??
                        widget.device.roomNumber ??
                        '—',
                    guestName: guest?.name ?? 'Guest',
                    weather: weather,
                    onNotifications: () {},
                    onProfile: () {},
                    hasUnreadNotifications:
                        ref.watch(guestUnreadNotificationsProvider(_token)) > 0,
                  ),
                  const SizedBox(height: 20),
                  _header(),
                  const SizedBox(height: 19),
                  if (catalog.categories.isNotEmpty) ...[
                    _categoryChips(catalog.categories),
                    const SizedBox(height: 19),
                  ],
                  Expanded(
                    child: catalogAsync.when(
                      data: (data) => _content(data),
                      loading: () => catalog.services.isNotEmpty
                          ? _content(catalog)
                          : const Center(
                              child: CircularProgressIndicator(
                                color: AppColors.gold,
                              ),
                            ),
                      error: (_, _) => catalog.services.isNotEmpty
                          ? _content(catalog)
                          : _errorState(),
                    ),
                  ),
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
        Material(
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
        ),
        const SizedBox(width: 15),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Spa & Wellness',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                'Retiro Del Rocio Sanctuary Spa • Open 9:00 AM – 9:00 PM',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        _viewAppointmentsButton(),
      ],
    );
  }

  Widget _viewAppointmentsButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => GuestSpaAppointmentsScreen(
              device: widget.device,
              status: widget.status,
            ),
          ),
        ),
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.12),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.event_note_rounded,
                size: 15,
                color: Colors.white.withValues(alpha: 0.8),
              ),
              const SizedBox(width: 8),
              Text(
                'View All Appointments',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.8),
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _categoryChips(List<SpaCategory> categories) {
    return SizedBox(
      height: 33,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          _categoryChip(
            label: 'All',
            selected: _selectedCategoryId == null,
            onTap: () => _selectCategory(null),
          ),
          for (final category in categories) ...[
            const SizedBox(width: 8),
            _categoryChip(
              label: category.name,
              selected: _selectedCategoryId == category.id,
              onTap: () => _selectCategory(category.id),
            ),
          ],
        ],
      ),
    );
  }

  Widget _categoryChip({
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return Material(
      color: selected ? AppColors.gold : Colors.white.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.5)
                  : Colors.white.withValues(alpha: 0.2),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected
                  ? const Color(0xFF0A0F1E)
                  : Colors.white.withValues(alpha: 0.6),
              fontSize: 13,
              fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
            ),
          ),
        ),
      ),
    );
  }

  Widget _content(SpaCatalog catalog) {
    final visibleServices = _selectedCategoryId == null
        ? catalog.services
        : catalog.services
              .where((s) => s.categoryId == _selectedCategoryId)
              .toList();
    final selectedService = catalog.services
        .where((s) => s.slug == _selectedServiceSlug)
        .firstOrNull;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: visibleServices.isEmpty
              ? _emptyState()
              : SingleChildScrollView(child: _servicesGrid(visibleServices)),
        ),
        const SizedBox(width: 24),
        SizedBox(width: 290, child: _sidebar(catalog, selectedService)),
      ],
    );
  }

  Widget _servicesGrid(List<SpaService> services) {
    return Wrap(
      spacing: 14,
      runSpacing: 24,
      children: [
        for (final service in services)
          SizedBox(
            width: 450,
            child: SpaServiceCard(
              service: service,
              selected: service.slug == _selectedServiceSlug,
              onTap: () => _selectService(service),
            ),
          ),
      ],
    );
  }

  Widget _sidebar(SpaCatalog catalog, SpaService? selectedService) {
    final canBook =
        selectedService != null &&
        _selectedTime != null &&
        _paymentMethod != null;

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _timesPanel(catalog.availableTimes),
          const SizedBox(height: 15),
          _paymentOption(
            method: SpaPaymentMethod.room,
            icon: Icons.hotel_rounded,
            title: 'Charge to Room',
            subtitle: 'Add to your room bill',
          ),
          const SizedBox(height: 15),
          _paymentOption(
            method: SpaPaymentMethod.paystack,
            icon: Icons.credit_card_rounded,
            title: 'Paystack',
            subtitle: 'African payment gateway',
          ),
          const SizedBox(height: 20),
          _ctaButton(canBook, selectedService),
        ],
      ),
    );
  }

  Widget _timesPanel(List<String> times) {
    return Container(
      padding: const EdgeInsets.all(16),
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
            children: [
              Icon(
                Icons.calendar_today_rounded,
                size: 12,
                color: Colors.white.withValues(alpha: 0.35),
              ),
              const SizedBox(width: 8),
              Text(
                'AVAILABLE TIMES',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.35),
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.4,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (times.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Text(
                'No times available today.',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 12,
                ),
              ),
            )
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final time in times)
                  SizedBox(width: 123, child: _timeChip(time)),
              ],
            ),
        ],
      ),
    );
  }

  Widget _timeChip(String time) {
    final selected = time == _selectedTime;
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.12)
          : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: () => _selectTime(time),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 9),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.2)
                  : Colors.white.withValues(alpha: 0.07),
              width: 0.8,
            ),
          ),
          child: Text(
            time,
            style: AppTypography.style(
              color: selected
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: 0.5),
              fontSize: 12,
            ),
          ),
        ),
      ),
    );
  }

  Widget _paymentOption({
    required SpaPaymentMethod method,
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    final selected = method == _paymentMethod;
    final tint = selected ? AppColors.gold : Colors.white;

    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.12)
          : Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: () => _selectPayment(method),
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(17),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.08),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 36,
                height: 36,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: selected
                      ? AppColors.gold.withValues(alpha: 0.13)
                      : Colors.white.withValues(alpha: 0.13),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, size: 18, color: tint),
              ),
              const SizedBox(height: 8),
              Text(
                title,
                style: AppTypography.style(
                  color: tint,
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _ctaButton(bool canBook, SpaService? selectedService) {
    if (!canBook || selectedService == null) {
      return Container(
        height: 54,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.06),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: Colors.white.withValues(alpha: 0.08),
            width: 0.8,
          ),
        ),
        child: Text(
          'Select a Service & Time',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.25),
            fontSize: 14,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    final label = _paymentMethod == SpaPaymentMethod.room
        ? 'BOOK NOW'
        : 'PAY NOW';

    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: () => _openPaymentSummary(selectedService),
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: 54,
          alignment: Alignment.center,
          child: Text(
            label,
            style: AppTypography.style(
              color: Colors.black,
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }

  Widget _emptyState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.spa_outlined,
            size: 32,
            color: Colors.white.withValues(alpha: 0.3),
          ),
          const SizedBox(height: 12),
          Text(
            'No spa services are available right now.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  Widget _errorState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_rounded,
            size: 30,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          const SizedBox(height: 12),
          Text(
            'Could not load spa services.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.6),
              fontSize: 15,
            ),
          ),
          const SizedBox(height: 16),
          Material(
            color: AppColors.gold,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              onTap: () => ref.invalidate(spaCatalogProvider(_token)),
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 24,
                  vertical: 12,
                ),
                child: Text(
                  'Retry',
                  style: AppTypography.style(
                    color: AppColors.onGold,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
