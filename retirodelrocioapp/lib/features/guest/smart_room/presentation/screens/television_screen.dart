import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/presentation/widgets/smart_room_control_page.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// Television control page — blank shell, awaiting the Tuya integration.
class TelevisionScreen extends StatelessWidget {
  const TelevisionScreen({
    super.key,
    required this.device,
    required this.status,
  });

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  Widget build(BuildContext context) =>
      SmartRoomControlPage(device: device, status: status, title: 'Television');
}
