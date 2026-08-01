import 'package:flutter/material.dart';

/// A guest-facing service tile on the home dashboard (Figma 84:3429).
@immutable
class GuestService {
  const GuestService({
    required this.id,
    required this.title,
    required this.tagline,
    required this.icon,
    this.iconAsset,
  });

  final String id;
  final String title;

  /// Sub-label under the title, e.g. "Order Anytime".
  final String tagline;

  /// Vector icon, used wherever [iconAsset] is absent.
  final IconData icon;

  /// Bundled artwork for the tile's gold badge.
  final String? iconAsset;
}

/// The "Quick Services" grid: 11 tiles in three rows of four, four and three,
/// in the order the design lays them out.
abstract final class GuestServices {
  const GuestServices._();

  static const dining = GuestService(
    id: 'dining',
    title: 'Dining',
    tagline: 'Order Anytime',
    icon: Icons.restaurant_rounded,
    iconAsset: 'assets/icons/dinning.png',
  );

  static const smartRoom = GuestService(
    id: 'smart-room',
    title: 'Smart Room',
    tagline: 'Control Everything',
    icon: Icons.settings_remote_rounded,
    iconAsset: 'assets/icons/smartroom.png',
  );

  static const spa = GuestService(
    id: 'spa',
    title: 'Spa & Wellness',
    tagline: 'Book a Session',
    icon: Icons.spa_rounded,
    iconAsset: 'assets/icons/spa.png',
  );

  static const cinema = GuestService(
    id: 'cinema',
    title: 'Cinema',
    tagline: 'Now Showing',
    icon: Icons.movie_rounded,
    iconAsset: 'assets/icons/Cinema.png',
  );

  static const gym = GuestService(
    id: 'gym',
    title: 'Gym & Fitness',
    tagline: 'Stay Active',
    icon: Icons.fitness_center_rounded,
    iconAsset: 'assets/icons/gym.png',
  );

  static const visitorPass = GuestService(
    id: 'visitor-pass',
    title: 'Visitor Pass',
    tagline: 'Invite Guests',
    icon: Icons.qr_code_2_rounded,
    iconAsset: 'assets/icons/qrcode.png',
  );

  static const bills = GuestService(
    id: 'bills',
    title: 'My Bills',
    tagline: 'View Charges',
    icon: Icons.receipt_long_rounded,
    iconAsset: 'assets/icons/bills.png',
  );

  static const chat = GuestService(
    id: 'chat',
    title: 'Chat',
    tagline: 'Concierge 24/7',
    icon: Icons.chat_bubble_outline_rounded,
    iconAsset: 'assets/icons/chat.png',
  );

  static const intercom = GuestService(
    id: 'intercom',
    title: 'Intercom',
    tagline: 'Call Reception',
    icon: Icons.call_rounded,
    iconAsset: 'assets/icons/phone.png',
  );

  static const myStay = GuestService(
    id: 'my-stay',
    title: 'My Stay',
    tagline: 'Booking & Extras',
    icon: Icons.event_note_rounded,
  );

  static const hotelInfo = GuestService(
    id: 'hotel-info',
    title: 'Hotel Information',
    tagline: 'Explore the Hotel',
    icon: Icons.info_outline_rounded,
  );

  static const serviceRequest = GuestService(
    id: 'service-request',
    title: 'Service Request',
    tagline: 'Housekeeping & Maintenance',
    icon: Icons.support_agent_rounded,
  );

  /// Row 1 · Row 2 · Row 3, exactly as the design orders them.
  static const List<List<GuestService>> grid = [
    [dining, smartRoom, spa, cinema],
    [gym, visitorPass, bills, chat],
    [intercom, myStay, hotelInfo, serviceRequest],
  ];
}
