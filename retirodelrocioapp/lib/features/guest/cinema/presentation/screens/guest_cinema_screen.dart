import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/cinema/application/cinema_providers.dart';
import 'package:retirodelrocioapp/features/guest/cinema/domain/cinema_service.dart';
import 'package:retirodelrocioapp/features/guest/cinema/presentation/screens/guest_cinema_bookings_screen.dart';
import 'package:retirodelrocioapp/features/guest/cinema/presentation/widgets/cinema_dialogs.dart';
import 'package:retirodelrocioapp/features/guest/cinema/presentation/widgets/movie_card.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/screens/paystack_checkout_screen.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/screens/sos_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// Cinema — browse the real movie catalogue, pick a date/showtime, book one
/// of the two private rooms and any snacks, then pay — charged straight to
/// the room, or paid directly via Paystack. Follows the same layout and
/// booking pattern as Spa & Wellness.
class GuestCinemaScreen extends ConsumerStatefulWidget {
  const GuestCinemaScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<GuestCinemaScreen> createState() => _GuestCinemaScreenState();
}

class _GuestCinemaScreenState extends ConsumerState<GuestCinemaScreen> {
  String? _selectedMovieSlug;
  DateTime? _selectedDate;
  String? _selectedTime;
  String? _selectedRoom;
  int _guests = 1;

  /// Snack id → quantity picked.
  final Map<int, int> _snackQty = {};

  CinemaPaymentMethod? _paymentMethod;

  static final _dateKeyFormat = DateFormat('yyyy-MM-dd');

  String get _token => widget.device.token;

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GuestNotificationScreen(
          device: widget.device,
          status: widget.status,
        ),
      ),
    );
  }

  void _openEmergency() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SosScreen(device: widget.device, status: widget.status),
      ),
    );
  }

  void _selectMovie(Movie movie) {
    setState(() {
      _selectedMovieSlug = movie.slug;
      // Showtimes and room availability are movie-specific — start over.
      _selectedTime = null;
      _selectedRoom = null;
    });
  }

  void _selectDate(DateTime date) {
    setState(() {
      _selectedDate = date;
      _selectedRoom = null;
    });
  }

  void _selectTime(String time) {
    setState(() {
      _selectedTime = time;
      _selectedRoom = null;
    });
  }

  void _selectRoom(String room) {
    setState(() => _selectedRoom = room);
  }

  void _selectPayment(CinemaPaymentMethod method) {
    setState(() => _paymentMethod = method);
  }

  bool _isSameDate(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;

  List<SnackSelection> _snackSelections(List<CinemaSnack> snacks) {
    return snacks
        .where((s) => (_snackQty[s.id] ?? 0) > 0)
        .map((s) => SnackSelection(snack: s, qty: _snackQty[s.id]!))
        .toList();
  }

  Map<String, dynamic> _buildPayload(Movie movie, List<SnackSelection> snacks) {
    return {
      'movie_slug': movie.slug,
      'date': _dateKeyFormat.format(_selectedDate!),
      'time': _selectedTime,
      'room': _selectedRoom,
      'guests': _guests,
      if (snacks.isNotEmpty) 'snacks': snacks.map((s) => s.toJson()).toList(),
    };
  }

  Future<void> _openPaymentSummary(
    Movie movie,
    List<SnackSelection> snacks,
  ) async {
    final method = _paymentMethod;
    if (method == null ||
        _selectedDate == null ||
        _selectedTime == null ||
        _selectedRoom == null) {
      return;
    }

    final naira = NumberFormat('#,###');
    final snacksTotal = snacks.fold<int>(0, (sum, s) => sum + s.total);
    final subtotal = movie.roomPrice + snacksTotal;
    final vat = (subtotal * 0.075).round();
    final total = subtotal + vat;
    final buttonLabel = method == CinemaPaymentMethod.room
        ? 'BOOK NOW'
        : 'PAY NOW';
    final payload = _buildPayload(movie, snacks);

    final confirmation = await showCinemaPaymentDialog(
      context,
      movieLabel: movie.title,
      roomLabel: _selectedRoom!,
      guests: _guests,
      subtotalLabel: 'NGN ${naira.format(subtotal)}',
      vatLabel: 'NGN ${naira.format(vat)}',
      totalLabel: 'NGN ${naira.format(total)}',
      buttonLabel: buttonLabel,
      onConfirm: () => method == CinemaPaymentMethod.room
          ? _bookToRoom(payload)
          : _payWithPaystack(payload),
    );

    if (confirmation == null || !mounted) return;

    await showCinemaBookingConfirmedDialog(context, confirmation: confirmation);
    if (mounted) {
      setState(() {
        _selectedMovieSlug = null;
        _selectedDate = null;
        _selectedTime = null;
        _selectedRoom = null;
        _guests = 1;
        _snackQty.clear();
        _paymentMethod = null;
      });
      ref.invalidate(guestNotificationsProvider(_token));
    }
  }

  Future<CinemaBookingConfirmation?> _bookToRoom(Map<String, dynamic> payload) {
    return ref.read(cinemaActionsProvider(_token)).bookToRoom(payload);
  }

  Future<CinemaBookingConfirmation?> _payWithPaystack(
    Map<String, dynamic> payload,
  ) async {
    final quote = await ref
        .read(cinemaActionsProvider(_token))
        .initializePaystack(payload);
    if (!mounted) return null;

    final paid = await showPaystackCheckout(
      context,
      authorizationUrl: quote.authorizationUrl,
      callbackUrl: quote.callbackUrl,
    );
    if (!paid) return null;

    return ref
        .read(cinemaActionsProvider(_token))
        .confirmPaystack(quote.reference);
  }

  @override
  Widget build(BuildContext context) {
    final catalogAsync = ref.watch(cinemaCatalogProvider(_token));
    final catalog = catalogAsync.value ?? CinemaCatalog.empty;
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
                    onNotifications: _openNotifications,
                    onProfile: () {},
                    onEmergency: _openEmergency,
                    hasUnreadNotifications:
                        ref.watch(guestUnreadNotificationsProvider(_token)) > 0,
                  ),
                  const SizedBox(height: 20),
                  _header(),
                  const SizedBox(height: 19),
                  Expanded(
                    child: catalogAsync.when(
                      data: (data) => _content(data),
                      loading: () => catalog.movies.isNotEmpty
                          ? _content(catalog)
                          : const Center(
                              child: CircularProgressIndicator(
                                color: AppColors.gold,
                              ),
                            ),
                      error: (_, _) => catalog.movies.isNotEmpty
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
                'Cinema',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                'Retiro Del Rocio Private Cinema • Book Your Own Room',
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        _viewBookingsButton(),
      ],
    );
  }

  Widget _viewBookingsButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => GuestCinemaBookingsScreen(
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
                'My Bookings',
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

  Widget _content(CinemaCatalog catalog) {
    final selectedMovie = catalog.movies
        .where((m) => m.slug == _selectedMovieSlug)
        .firstOrNull;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: catalog.movies.isEmpty
              ? _emptyState()
              : SingleChildScrollView(child: _moviesGrid(catalog.movies)),
        ),
        const SizedBox(width: 24),
        SizedBox(width: 340, child: _sidebar(catalog, selectedMovie)),
      ],
    );
  }

  Widget _moviesGrid(List<Movie> movies) {
    return Wrap(
      spacing: 20,
      runSpacing: 20,
      children: [
        for (final movie in movies)
          SizedBox(
            width: 200,
            child: MovieCard(
              movie: movie,
              selected: movie.slug == _selectedMovieSlug,
              onTap: () => _selectMovie(movie),
            ),
          ),
      ],
    );
  }

  Widget _sidebar(CinemaCatalog catalog, Movie? selectedMovie) {
    final snacks = _snackSelections(catalog.snacks);
    final canBook =
        selectedMovie != null &&
        _selectedDate != null &&
        _selectedTime != null &&
        _selectedRoom != null &&
        _paymentMethod != null;

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _datePanel(),
          const SizedBox(height: 15),
          if (selectedMovie != null) ...[
            _timePanel(selectedMovie.showtimes),
            const SizedBox(height: 15),
          ],
          if (selectedMovie != null &&
              _selectedDate != null &&
              _selectedTime != null) ...[
            _roomPanel(catalog.rooms, catalog.seatsPerRoom, selectedMovie),
            const SizedBox(height: 15),
          ],
          _guestsPanel(catalog.seatsPerRoom),
          const SizedBox(height: 15),
          if (catalog.snacks.isNotEmpty) ...[
            _snacksPanel(catalog.snacks),
            const SizedBox(height: 15),
          ],
          _paymentOption(
            method: CinemaPaymentMethod.room,
            icon: Icons.hotel_rounded,
            title: 'Charge to Room',
            subtitle: 'Add to your room bill',
          ),
          const SizedBox(height: 15),
          _paymentOption(
            method: CinemaPaymentMethod.paystack,
            icon: Icons.credit_card_rounded,
            title: 'Paystack',
            subtitle: 'African payment gateway',
          ),
          const SizedBox(height: 20),
          _ctaButton(canBook, selectedMovie, snacks),
        ],
      ),
    );
  }

  BoxDecoration _panelDecoration() => BoxDecoration(
    color: Colors.white.withValues(alpha: 0.06),
    borderRadius: BorderRadius.circular(24),
    border: Border.all(color: Colors.white.withValues(alpha: 0.1), width: 0.8),
  );

  Widget _panelLabel(String text) => Text(
    text,
    style: AppTypography.style(
      color: Colors.white.withValues(alpha: 0.35),
      fontSize: 10,
      fontWeight: FontWeight.w700,
      letterSpacing: 1.4,
    ),
  );

  Widget _datePanel() {
    final dates = List.generate(
      7,
      (i) => DateTime.now().add(Duration(days: i)),
    );

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _panelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _panelLabel('DATE'),
          const SizedBox(height: 10),
          SizedBox(
            height: 56,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: dates.length,
              separatorBuilder: (_, _) => const SizedBox(width: 7),
              itemBuilder: (_, i) => _dateChip(dates[i]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _dateChip(DateTime date) {
    final selected = _selectedDate != null && _isSameDate(_selectedDate!, date);
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.12)
          : Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: () => _selectDate(date),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          width: 52,
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
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                DateFormat('EEE').format(date).toUpperCase(),
                style: AppTypography.style(
                  color: selected
                      ? AppColors.gold
                      : Colors.white.withValues(alpha: 0.5),
                  fontSize: 10,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                DateFormat('d').format(date),
                style: AppTypography.style(
                  color: selected ? AppColors.gold : Colors.white,
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _timePanel(List<String> showtimes) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _panelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _panelLabel('SHOWTIME'),
          const SizedBox(height: 10),
          if (showtimes.isEmpty)
            Text(
              'No showtimes set for this movie.',
              style: AppTypography.style(
                color: Colors.white.withValues(alpha: 0.4),
                fontSize: 12,
              ),
            )
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final time in showtimes)
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

  /// Booking is by whole private room, not an individual seat — same as the
  /// website. Each room card still shows its own little seat map (a "screen"
  /// plus [seatsPerRoom] seat icons) so the guest sees exactly what "Room 1"
  /// / "Room 2" means, matching the website's room picker.
  Widget _roomPanel(List<String> rooms, int seatsPerRoom, Movie movie) {
    final takenAsync = ref.watch(
      cinemaRoomAvailabilityProvider((
        deviceToken: _token,
        movieSlug: movie.slug,
        date: _dateKeyFormat.format(_selectedDate!),
        time: _selectedTime!,
      )),
    );
    final taken = takenAsync.value ?? const <String>[];

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _panelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _panelLabel('PRIVATE ROOM'),
          const SizedBox(height: 4),
          Text(
            'Book a private room — $seatsPerRoom seats all to yourself.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 11,
            ),
          ),
          const SizedBox(height: 12),
          for (var i = 0; i < rooms.length; i++) ...[
            _roomCard(
              rooms[i],
              taken.contains(rooms[i]),
              seatsPerRoom,
              movie.roomPriceLabel,
            ),
            if (i != rooms.length - 1) const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }

  Widget _roomCard(
    String room,
    bool isTaken,
    int seatsPerRoom,
    String priceLabel,
  ) {
    final selected = room == _selectedRoom;
    final seatColor = isTaken
        ? Colors.white.withValues(alpha: 0.15)
        : (selected ? AppColors.gold : Colors.white.withValues(alpha: 0.4));

    return Material(
      color: isTaken
          ? Colors.white.withValues(alpha: 0.02)
          : (selected
                ? AppColors.gold.withValues(alpha: 0.1)
                : Colors.white.withValues(alpha: 0.04)),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: isTaken ? null : () => _selectRoom(room),
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: isTaken
                  ? Colors.white.withValues(alpha: 0.07)
                  : (selected
                        ? AppColors.gold.withValues(alpha: 0.4)
                        : Colors.white.withValues(alpha: 0.1)),
              width: selected ? 1.4 : 0.8,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      room,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  if (isTaken)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        'Booked',
                        style: AppTypography.style(
                          color: Colors.white.withValues(alpha: 0.6),
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    )
                  else if (selected)
                    Container(
                      width: 20,
                      height: 20,
                      alignment: Alignment.center,
                      decoration: const BoxDecoration(
                        color: AppColors.gold,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.check_rounded,
                        size: 13,
                        color: Colors.black,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 10),
              // Screen + private seats — purely a visual seat map. Booking
              // is by the whole room, not an individual seat within it.
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Column(
                  children: [
                    Container(
                      height: 3,
                      width: 70,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(2),
                        gradient: LinearGradient(
                          colors: [
                            Colors.transparent,
                            seatColor.withValues(alpha: 0.7),
                            Colors.transparent,
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        for (var i = 0; i < seatsPerRoom; i++) ...[
                          Icon(
                            Icons.event_seat_rounded,
                            size: 20,
                            color: seatColor,
                          ),
                          if (i != seatsPerRoom - 1) const SizedBox(width: 6),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '$seatsPerRoom private seats',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.4),
                        fontSize: 11,
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    priceLabel,
                    style: AppTypography.style(
                      color: AppColors.gold,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
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

  Widget _guestsPanel(int seatsPerRoom) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _panelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _panelLabel('GUESTS'),
          const SizedBox(height: 10),
          Row(
            children: [
              Text(
                '$_guests guest${_guests == 1 ? '' : 's'}',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const Spacer(),
              _stepperButton(
                Icons.remove_rounded,
                onTap: _guests > 1 ? () => setState(() => _guests--) : null,
              ),
              const SizedBox(width: 10),
              _stepperButton(
                Icons.add_rounded,
                onTap: _guests < seatsPerRoom
                    ? () => setState(() => _guests++)
                    : null,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _stepperButton(IconData icon, {required VoidCallback? onTap}) {
    final enabled = onTap != null;
    return Material(
      color: enabled
          ? Colors.white.withValues(alpha: 0.1)
          : Colors.white.withValues(alpha: 0.03),
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 28,
          height: 28,
          child: Icon(
            icon,
            size: 14,
            color: enabled ? Colors.white : Colors.white.withValues(alpha: 0.2),
          ),
        ),
      ),
    );
  }

  Widget _snacksPanel(List<CinemaSnack> snacks) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _panelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _panelLabel('SNACKS (OPTIONAL)'),
          const SizedBox(height: 12),
          for (var i = 0; i < snacks.length; i++) ...[
            _snackRow(snacks[i]),
            if (i != snacks.length - 1) const SizedBox(height: 12),
          ],
        ],
      ),
    );
  }

  Widget _snackRow(CinemaSnack snack) {
    final qty = _snackQty[snack.id] ?? 0;
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                snack.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                snack.priceLabel,
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
        _stepperButton(
          Icons.remove_rounded,
          onTap: qty > 0
              ? () => setState(() => _snackQty[snack.id] = qty - 1)
              : null,
        ),
        SizedBox(
          width: 24,
          child: Text(
            '$qty',
            textAlign: TextAlign.center,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        _stepperButton(
          Icons.add_rounded,
          onTap: () => setState(() => _snackQty[snack.id] = qty + 1),
        ),
      ],
    );
  }

  Widget _paymentOption({
    required CinemaPaymentMethod method,
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

  Widget _ctaButton(
    bool canBook,
    Movie? selectedMovie,
    List<SnackSelection> snacks,
  ) {
    if (!canBook || selectedMovie == null) {
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
          'Select a Movie, Date & Room',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.25),
            fontSize: 14,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    final label = _paymentMethod == CinemaPaymentMethod.room
        ? 'BOOK NOW'
        : 'PAY NOW';

    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: () => _openPaymentSummary(selectedMovie, snacks),
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
            Icons.movie_outlined,
            size: 32,
            color: Colors.white.withValues(alpha: 0.3),
          ),
          const SizedBox(height: 12),
          Text(
            'No movies are showing right now.',
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
            'Could not load the cinema.',
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
              onTap: () => ref.invalidate(cinemaCatalogProvider(_token)),
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
