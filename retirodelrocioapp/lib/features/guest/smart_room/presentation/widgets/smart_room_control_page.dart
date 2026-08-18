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
import 'package:retirodelrocioapp/features/guest/smart_room/domain/smart_device.dart';
import 'package:retirodelrocioapp/features/guest/sos/presentation/screens/sos_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// Shared control page for every Smart Room device category (Lights,
/// Curtains, Air Conditioning, Television) — the top bar and back/title
/// header, plus a capability-driven control list for every device of
/// [deviceType] in this room.
///
/// Nothing here switches on a device's `type`; it only ever switches on each
/// capability's own `type` (`bool` -> toggle, `int` -> slider, `enum` ->
/// segmented control). A new Tuya product that reuses the same normalized
/// capability keys therefore renders correctly with zero Flutter changes.
///
/// On tap/drag the affected control's value flips immediately (optimistic),
/// the command is sent, and the page reconciles once [smartDevicesProvider]
/// refetches — a failure reverts the optimistic value and shows the server's
/// own error message; success is never implied before the POST actually
/// returns 2xx.
class SmartRoomControlPage extends ConsumerStatefulWidget {
  const SmartRoomControlPage({
    super.key,
    required this.device,
    required this.status,
    required this.title,
    this.deviceType,
  });

  final ProvisionedDevice device;
  final RoomStatus status;
  final String title;

  /// `light` | `ac` | `curtain` | `tv` — narrows [smartDevicesProvider] to
  /// just this category. Left null keeps the page a bare header-only shell
  /// (Room Scenes renders its own scene list instead of this generic device
  /// control view).
  final String? deviceType;

  @override
  ConsumerState<SmartRoomControlPage> createState() =>
      _SmartRoomControlPageState();
}

class _SmartRoomControlPageState extends ConsumerState<SmartRoomControlPage> {
  /// Optimistic capability values, keyed by device id then capability key —
  /// painted immediately on tap/drag, before the command call resolves.
  /// Cleared the moment that command succeeds so the next server-backed
  /// fetch (not this guess) becomes the source of truth.
  final Map<int, Map<String, dynamic>> _optimistic = {};

  /// `deviceId:capabilityKey` currently in flight — blocks a second tap on
  /// the same control until the first command resolves.
  final Set<String> _pending = {};

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

  dynamic _valueFor(SmartDevice smartDevice, String capabilityKey) {
    final override = _optimistic[smartDevice.id];
    if (override != null && override.containsKey(capabilityKey)) {
      return override[capabilityKey];
    }
    return smartDevice.valueOf(capabilityKey);
  }

  bool _isPending(int deviceId, String capabilityKey) =>
      _pending.contains('$deviceId:$capabilityKey');

  /// Flips [capabilityKey] to [value] immediately, sends the command, and
  /// either drops the optimistic override (on success — the invalidated
  /// provider's refetch takes over) or restores [previousValue] and surfaces
  /// the server's error (on failure).
  Future<void> _sendCommand(
    SmartDevice smartDevice,
    String capabilityKey,
    dynamic value, {
    required dynamic previousValue,
  }) async {
    final pendingKey = '${smartDevice.id}:$capabilityKey';
    setState(() {
      _optimistic.putIfAbsent(smartDevice.id, () => {})[capabilityKey] = value;
      _pending.add(pendingKey);
    });

    try {
      await ref
          .read(smartRoomActionsProvider(device))
          .sendCommand(smartDevice.id, capabilityKey, value);
      if (!mounted) return;
      setState(() {
        _optimistic[smartDevice.id]?.remove(capabilityKey);
        _pending.remove(pendingKey);
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _optimistic.putIfAbsent(smartDevice.id, () => {})[capabilityKey] =
            previousValue;
        _pending.remove(pendingKey);
      });
      final message = error is SmartRoomException
          ? error.message
          : 'Could not send that command.';
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(smartRoomRealtimeProvider(device));
    final weather = ref.watch(weatherProvider).value;
    final devicesAsync = widget.deviceType != null
        ? ref.watch(smartDevicesProvider(device))
        : const AsyncValue<List<SmartDevice>>.data(<SmartDevice>[]);

    final allDevices = devicesAsync.value ?? const <SmartDevice>[];
    final devices = widget.deviceType == null
        ? const <SmartDevice>[]
        : allDevices.where((d) => d.type == widget.deviceType).toList();

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
                  if (widget.deviceType != null) ...[
                    const SizedBox(height: 20),
                    Expanded(child: _body(devicesAsync, devices)),
                  ],
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
          widget.title,
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
    AsyncValue<List<SmartDevice>> devicesAsync,
    List<SmartDevice> devices,
  ) {
    return devicesAsync.when(
      data: (_) => _deviceList(devices),
      loading: () => devices.isNotEmpty
          ? _deviceList(devices)
          : const Center(
              child: CircularProgressIndicator(color: AppColors.gold),
            ),
      error: (_, _) =>
          devices.isNotEmpty ? _deviceList(devices) : _errorState(),
    );
  }

  Widget _deviceList(List<SmartDevice> devices) {
    if (devices.isEmpty) {
      return Center(
        child: Text(
          'No ${widget.title.toLowerCase()} devices in this room.',
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 14,
          ),
        ),
      );
    }

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final smartDevice in devices) ...[
            _deviceCard(smartDevice),
            const SizedBox(height: 16),
          ],
        ],
      ),
    );
  }

  Widget _deviceCard(SmartDevice smartDevice) {
    final online = smartDevice.isOnline;
    return Container(
      padding: const EdgeInsets.all(20),
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
              Expanded(
                child: Text(
                  smartDevice.name,
                  style: AppTypography.style(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              _statusBadge(online),
            ],
          ),
          const SizedBox(height: 16),
          for (final spec in smartDevice.capabilities.values) ...[
            _control(smartDevice, spec, enabled: online),
            const SizedBox(height: 14),
          ],
        ],
      ),
    );
  }

  Widget _statusBadge(bool online) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: (online ? AppColors.success : Colors.white).withValues(
          alpha: 0.12,
        ),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        online ? 'Online' : 'Offline',
        style: AppTypography.style(
          color: online
              ? AppColors.success
              : Colors.white.withValues(alpha: 0.5),
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _control(
    SmartDevice smartDevice,
    SmartCapabilitySpec spec, {
    required bool enabled,
  }) {
    if (spec.isBool) return _boolControl(smartDevice, spec, enabled: enabled);
    if (spec.isInt) return _intControl(smartDevice, spec, enabled: enabled);
    if (spec.isEnum) return _enumControl(smartDevice, spec, enabled: enabled);
    return const SizedBox.shrink();
  }

  Widget _boolControl(
    SmartDevice smartDevice,
    SmartCapabilitySpec spec, {
    required bool enabled,
  }) {
    final value = _valueFor(smartDevice, spec.key) == true;
    final pending = _isPending(smartDevice.id, spec.key);

    return Row(
      children: [
        Expanded(
          child: Text(
            _labelFor(spec.key),
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.85),
              fontSize: 14,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
        if (pending) ...[
          const SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(width: 10),
        ],
        Switch(
          value: value,
          activeThumbColor: AppColors.gold,
          onChanged: enabled && !pending
              ? (next) => _sendCommand(
                  smartDevice,
                  spec.key,
                  next,
                  previousValue: value,
                )
              : null,
        ),
      ],
    );
  }

  Widget _intControl(
    SmartDevice smartDevice,
    SmartCapabilitySpec spec, {
    required bool enabled,
  }) {
    final min = (spec.min ?? 0).toDouble();
    final max = (spec.max ?? 100).toDouble();
    final raw = _valueFor(smartDevice, spec.key);
    final current = (raw is num ? raw.toDouble() : min).clamp(min, max);
    final pending = _isPending(smartDevice.id, spec.key);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                _labelFor(spec.key),
                style: AppTypography.style(
                  color: Colors.white.withValues(alpha: 0.85),
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
            Text(
              current.round().toString(),
              style: AppTypography.style(
                color: AppColors.gold,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        SliderTheme(
          data: SliderTheme.of(context).copyWith(
            activeTrackColor: AppColors.gold,
            thumbColor: AppColors.gold,
            inactiveTrackColor: Colors.white.withValues(alpha: 0.15),
          ),
          child: Slider(
            value: current,
            min: min,
            max: max,
            onChanged: enabled && !pending
                ? (next) => setState(() {
                    _optimistic.putIfAbsent(
                      smartDevice.id,
                      () => {},
                    )[spec.key] = next
                        .round();
                  })
                : null,
            onChangeEnd: enabled && !pending
                ? (next) => _sendCommand(
                    smartDevice,
                    spec.key,
                    next.round(),
                    previousValue: raw,
                  )
                : null,
          ),
        ),
      ],
    );
  }

  Widget _enumControl(
    SmartDevice smartDevice,
    SmartCapabilitySpec spec, {
    required bool enabled,
  }) {
    final values = spec.values ?? const <String>[];
    final current = _valueFor(smartDevice, spec.key) as String?;
    final pending = _isPending(smartDevice.id, spec.key);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          _labelFor(spec.key),
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.85),
            fontSize: 14,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final option in values)
              _enumChip(
                label: _labelFor(option),
                selected: option == current,
                enabled: enabled && !pending,
                onTap: () => _sendCommand(
                  smartDevice,
                  spec.key,
                  option,
                  previousValue: current,
                ),
              ),
          ],
        ),
      ],
    );
  }

  Widget _enumChip({
    required String label,
    required bool selected,
    required bool enabled,
    required VoidCallback onTap,
  }) {
    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.16)
          : Colors.white.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: enabled ? onTap : null,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.gold.withValues(alpha: 0.4)
                  : Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: selected
                  ? AppColors.gold
                  : Colors.white.withValues(alpha: enabled ? 0.7 : 0.3),
              fontSize: 13,
              fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
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
            'Could not load devices.',
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
              onTap: () => ref.invalidate(smartDevicesProvider(device)),
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

  /// Generic label formatter for a capability key or enum value — no
  /// per-device-type or per-capability hard-coding, just underscore -> Title
  /// Case (`fan_speed` -> "Fan Speed", `cold` -> "Cold").
  String _labelFor(String raw) => raw
      .split('_')
      .where((w) => w.isNotEmpty)
      .map((w) => w[0].toUpperCase() + w.substring(1))
      .join(' ');
}
