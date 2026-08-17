import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/guest_top_bar.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/application/smart_room_providers.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/data/smart_room_repository.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/domain/smart_scene.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/screens/sos_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// Admin-assigned icon keys mapped to a Material icon — a fixed vocabulary,
/// the same idea as the capability `type` -> control-shape mapping in
/// `smart_room_control_page.dart`. An unrecognized or missing key falls back
/// to a generic scene icon rather than failing.
const Map<String, IconData> _sceneIcons = {
  'sunny': Icons.wb_sunny_rounded,
  'wb_sunny': Icons.wb_sunny_rounded,
  'moon': Icons.nightlight_round,
  'nightlight': Icons.nightlight_round,
  'bedtime': Icons.bedtime_rounded,
  'sleep': Icons.bedtime_rounded,
  'relax': Icons.spa_rounded,
  'spa': Icons.spa_rounded,
  'welcome': Icons.emoji_people_rounded,
  'movie': Icons.movie_rounded,
  'party': Icons.celebration_rounded,
  'celebration': Icons.celebration_rounded,
  'home': Icons.home_rounded,
  'exit': Icons.exit_to_app_rounded,
  'checkout': Icons.exit_to_app_rounded,
  'clean': Icons.cleaning_services_rounded,
  'light': Icons.lightbulb_rounded,
  'reading': Icons.menu_book_rounded,
};

IconData _iconFor(SmartScene scene) =>
    _sceneIcons[scene.icon?.toLowerCase()] ?? Icons.auto_awesome_rounded;

/// Room Scenes — every scene available for this room (category templates plus
/// room-specific overrides, already merged server-side), each a one-tap
/// "Activate" that fires a preset group of device commands. Never claims
/// success before the POST returns 2xx; a 207 partial failure lists exactly
/// which devices didn't run rather than pretending it all worked.
class RoomScenesScreen extends ConsumerStatefulWidget {
  const RoomScenesScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  ConsumerState<RoomScenesScreen> createState() => _RoomScenesScreenState();
}

class _RoomScenesScreenState extends ConsumerState<RoomScenesScreen> {
  /// Scene id currently activating — disables its card and shows a spinner
  /// in place of the Activate button.
  int? _activatingSceneId;

  ProvisionedDevice get device => widget.device;
  RoomStatus get status => widget.status;
  String get _token => device.token;

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GuestNotificationScreen(device: device, status: status),
      ),
    );
  }

  void _openEmergency() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SosScreen(device: device, status: status),
      ),
    );
  }

  Future<void> _activate(SmartScene scene) async {
    setState(() => _activatingSceneId = scene.id);
    try {
      final result = await ref
          .read(smartRoomActionsProvider(device))
          .activateScene(scene.id);
      if (!mounted) return;
      setState(() => _activatingSceneId = null);

      if (result.isPartialFailure) {
        final failedList = result.failedDevices.isNotEmpty
            ? result.failedDevices.join(', ')
            : 'some devices';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              result.message ?? 'Some devices did not respond: $failedList',
            ),
            backgroundColor: const Color(0xFFB45309),
          ),
        );
        return;
      }

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('${scene.name} activated.')));
    } catch (error) {
      if (!mounted) return;
      setState(() => _activatingSceneId = null);
      final message = error is SmartRoomException
          ? error.message
          : 'Could not activate this scene.';
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final weather = ref.watch(weatherProvider).value;
    final scenesAsync = ref.watch(smartScenesProvider(device));
    final scenes = scenesAsync.value ?? const <SmartScene>[];

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
                    roomNumber: status.roomNumber ?? device.roomNumber ?? '—',
                    guestName: status.guest?.name ?? 'Guest',
                    weather: weather,
                    onNotifications: _openNotifications,
                    onProfile: () {},
                    onEmergency: _openEmergency,
                    hasUnreadNotifications:
                        ref.watch(guestUnreadNotificationsProvider(_token)) > 0,
                  ),
                  const SizedBox(height: 20),
                  _header(),
                  const SizedBox(height: 20),
                  Expanded(child: _body(scenesAsync, scenes)),
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
        Text(
          'Room Scenes',
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 36,
            fontWeight: FontWeight.w700,
            height: 1.15,
          ),
        ),
      ],
    );
  }

  Widget _body(
    AsyncValue<List<SmartScene>> scenesAsync,
    List<SmartScene> scenes,
  ) {
    return scenesAsync.when(
      data: (_) => _sceneGrid(scenes),
      loading: () => scenes.isNotEmpty
          ? _sceneGrid(scenes)
          : const Center(
              child: CircularProgressIndicator(color: AppColors.gold),
            ),
      error: (_, _) => scenes.isNotEmpty ? _sceneGrid(scenes) : _errorState(),
    );
  }

  Widget _sceneGrid(List<SmartScene> scenes) {
    if (scenes.isEmpty) {
      return Center(
        child: Text(
          'No scenes are set up for this room yet.',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 14,
          ),
        ),
      );
    }

    return SingleChildScrollView(
      child: Wrap(
        spacing: 17,
        runSpacing: 17,
        children: [for (final scene in scenes) _sceneCard(scene)],
      ),
    );
  }

  Widget _sceneCard(SmartScene scene) {
    final activating = _activatingSceneId == scene.id;
    final busy = _activatingSceneId != null;

    return Container(
      width: 232,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.12),
          width: 0.8,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(_iconFor(scene), size: 22, color: AppColors.gold),
          ),
          const SizedBox(height: 16),
          Text(
            scene.name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 14),
          _activateButton(scene, activating: activating, disabled: busy),
        ],
      ),
    );
  }

  Widget _activateButton(
    SmartScene scene, {
    required bool activating,
    required bool disabled,
  }) {
    return Material(
      color: activating
          ? AppColors.gold.withValues(alpha: 0.5)
          : AppColors.gold,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: disabled ? null : () => _activate(scene),
        borderRadius: BorderRadius.circular(12),
        child: Container(
          height: 40,
          alignment: Alignment.center,
          child: activating
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.onGold,
                  ),
                )
              : Text(
                  'Activate',
                  style: AppTypography.style(
                    color: AppColors.onGold,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
        ),
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
            'Could not load room scenes.',
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
              onTap: () => ref.invalidate(smartScenesProvider(device)),
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
