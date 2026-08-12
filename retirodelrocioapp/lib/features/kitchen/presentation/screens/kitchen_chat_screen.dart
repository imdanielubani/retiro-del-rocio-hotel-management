import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/kitchen_navigation.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_nav_rail.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_scaffold.dart';
import 'package:retirodelrocioapp/features/staff_chat/presentation/widgets/staff_chat_body.dart';

/// The kitchen tablet's Chat screen — the shared Staff Chat body (talk to
/// Reception, Bar & Lounge, Housekeeping, Security, or the Admin dashboard)
/// inside the same frosted shell every other kitchen screen uses.
class KitchenChatScreen extends ConsumerStatefulWidget {
  const KitchenChatScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<KitchenChatScreen> createState() => _KitchenChatScreenState();
}

class _KitchenChatScreenState extends ConsumerState<KitchenChatScreen> {
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
      active: KitchenNavItem.chat,
      onNav: (item) => KitchenNavigation.select(
        context,
        widget.session,
        item,
        current: KitchenNavItem.chat,
      ),
      onLogout: _logout,
      title: 'Chat',
      body: StaffChatBody(session: widget.session),
    );
  }
}
