import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/kitchen_navigation.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_nav_rail.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_scaffold.dart';
import 'package:retirodelrocioapp/features/staff_intercom/presentation/widgets/staff_intercom_body.dart';

/// The kitchen tablet's Intercom screen — the shared staff directory
/// (voice-call Reception, Bar & Lounge, Housekeeping or Security) inside
/// the same frosted shell every other kitchen screen uses.
class KitchenIntercomScreen extends ConsumerStatefulWidget {
  const KitchenIntercomScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<KitchenIntercomScreen> createState() =>
      _KitchenIntercomScreenState();
}

class _KitchenIntercomScreenState extends ConsumerState<KitchenIntercomScreen> {
  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) KitchenNavigation.afterLogout(context);
  }

  @override
  Widget build(BuildContext context) {
    return KitchenScaffold(
      session: widget.session,
      active: KitchenNavItem.intercom,
      onNav: (item) => KitchenNavigation.select(
        context,
        widget.session,
        item,
        current: KitchenNavItem.intercom,
      ),
      onLogout: _logout,
      title: 'Intercom',
      body: StaffIntercomBody(session: widget.session),
    );
  }
}
