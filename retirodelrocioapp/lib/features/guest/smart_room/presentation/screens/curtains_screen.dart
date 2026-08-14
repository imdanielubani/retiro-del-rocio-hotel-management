import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/presentation/widgets/smart_room_control_page.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// Curtains control page — blank shell, awaiting the Tuya integration.
class CurtainsScreen extends StatelessWidget {
  const CurtainsScreen({super.key, required this.device, required this.status});

  final ProvisionedDevice device;
  final RoomStatus status;

  @override
  Widget build(BuildContext context) =>
      SmartRoomControlPage(device: device, status: status, title: 'Curtains');
}
