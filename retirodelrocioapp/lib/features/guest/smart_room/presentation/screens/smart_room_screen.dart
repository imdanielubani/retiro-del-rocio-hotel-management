import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/presentation/screens/air_conditioning_screen.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/presentation/screens/curtains_screen.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/presentation/screens/lights_screen.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/presentation/screens/room_scenes_screen.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/presentation/screens/television_screen.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/presentation/widgets/smart_room_control_card.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/screens/sos_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// 10.1 — Smart Room (Figma 417:20 / 419:253): in-room device controls —
/// lights, curtains, air conditioning, television and room scenes, plus the
/// Do Not Disturb / Make Up Room toggles. Each control opens its own page
/// (populated once the Tuya integration lands); only Do Not Disturb and Make
/// Up Room are simple in-card toggles, kept in this screen's own state the
/// same way the Dining cart is.
class SmartRoomScreen extends ConsumerStatefulWidget {
  const SmartRoomScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<SmartRoomScreen> createState() => _SmartRoomScreenState();
}

class _SmartRoomScreenState extends ConsumerState<SmartRoomScreen> {
  bool _doNotDisturb = false;
  bool _makeUpRoom = false;

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

  void _openLights() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            LightsScreen(device: widget.device, status: widget.status),
      ),
    );
  }

  void _openCurtains() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            CurtainsScreen(device: widget.device, status: widget.status),
      ),
    );
  }

  void _openAc() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            AirConditioningScreen(device: widget.device, status: widget.status),
      ),
    );
  }

  void _openTv() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            TelevisionScreen(device: widget.device, status: widget.status),
      ),
    );
  }

  void _openScenes() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            RoomScenesScreen(device: widget.device, status: widget.status),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final status = widget.status;
    final weather = ref.watch(weatherProvider).value;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
          const ColoredBox(color: Color.fromARGB(230, 0, 0, 0)),
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
                    weather: weather,
                    onNotifications: _openNotifications,
                    onProfile: () {},
                    onEmergency: _openEmergency,
                    hasUnreadNotifications:
                        ref.watch(guestUnreadNotificationsProvider(_token)) > 0,
                  ),
                  const SizedBox(height: 20),
                  _header(status),
                  const SizedBox(height: 20),
                  Text(
                    'CONTROLS',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.4),
                      fontSize: 12,
                      letterSpacing: 1.2,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Expanded(
                    child: SingleChildScrollView(
                      child: Wrap(
                        spacing: 17,
                        runSpacing: 17,
                        children: [
                          SmartRoomControlCard(
                            icon: Icons.lightbulb_rounded,
                            title: 'Lights',
                            subtitle: 'Tap to control',
                            onTap: _openLights,
                          ),
                          SmartRoomControlCard(
                            icon: Icons.curtains_rounded,
                            title: 'Curtains',
                            subtitle: 'Tap to control',
                            onTap: _openCurtains,
                          ),
                          SmartRoomControlCard(
                            icon: Icons.air_rounded,
                            title: 'Air Conditioning',
                            subtitle: 'Tap to control',
                            onTap: _openAc,
                          ),
                          SmartRoomControlCard(
                            icon: Icons.tv_rounded,
                            title: 'Television',
                            subtitle: 'Tap to control',
                            onTap: _openTv,
                          ),
                          SmartRoomControlCard(
                            icon: Icons.auto_awesome_rounded,
                            title: 'Room Scenes',
                            subtitle: 'Tap to control',
                            onTap: _openScenes,
                          ),
                          SmartRoomControlCard(
                            icon: Icons.notifications_off_rounded,
                            title: 'Do Not Disturb',
                            subtitle: _doNotDisturb ? 'On' : 'Off',
                            active: _doNotDisturb,
                            trailingSwitch: _doNotDisturb,
                            onTap: () =>
                                setState(() => _doNotDisturb = !_doNotDisturb),
                          ),
                          SmartRoomControlCard(
                            icon: Icons.cleaning_services_rounded,
                            title: 'Make Up Room',
                            subtitle: 'Request housekeeping',
                            active: _makeUpRoom,
                            trailingSwitch: _makeUpRoom,
                            onTap: () =>
                                setState(() => _makeUpRoom = !_makeUpRoom),
                          ),
                        ],
                      ),
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

  Widget _header(RoomStatus status) {
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
                'Smart Room',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                _subtitle(status),
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  String _subtitle(RoomStatus status) {
    final suite = status.suiteName ?? 'Suite';
    final room = status.roomNumber ?? widget.device.roomNumber;
    return room != null ? '$suite - Room $room' : suite;
  }
}
