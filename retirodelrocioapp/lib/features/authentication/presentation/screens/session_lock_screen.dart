import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/reauth_panel.dart';

/// Full-screen lock shown after inactivity. The staffer re-enters their
/// PIN (or password) to resume, or logs out entirely.
class SessionLockScreen extends ConsumerWidget {
  const SessionLockScreen({
    super.key,
    required this.session,
    required this.onUnlocked,
  });

  final StaffSession session;
  final VoidCallback onUnlocked;

  Future<void> _logout(BuildContext context, WidgetRef ref) async {
    await ref.read(authControllerProvider.notifier).logout();
    if (context.mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // The lock is an overlay above the child's Scaffold, so it must bring its
    // own Material ancestor — the password field, icon buttons and "Log out"
    // TextButton all require one. `type: transparency` keeps the blur visible.
    return Material(
      type: MaterialType.transparency,
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
        child: ColoredBox(
          color: Colors.black.withValues(alpha: 0.7),
          child: Center(
            child: SingleChildScrollView(
              child: Container(
                width: 420,
                padding: const EdgeInsets.symmetric(
                  horizontal: 40,
                  vertical: 36,
                ),
                decoration: BoxDecoration(
                  color: const Color(0xFF1A1712),
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.12),
                    width: 0.8,
                  ),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Center(
                      child: Container(
                        width: 64,
                        height: 64,
                        decoration: BoxDecoration(
                          color: AppColors.gold.withValues(alpha: 0.15),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.lock_rounded,
                          color: AppColors.gold,
                          size: 28,
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Session Locked',
                      textAlign: TextAlign.center,
                      style: AppTypography.style(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Signed in as ${session.name}\nEnter your PIN to continue',
                      textAlign: TextAlign.center,
                      style: AppTypography.style(
                        color: Colors.white.withValues(alpha: 0.55),
                        fontSize: 14,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 24),
                    ReauthPanel(onSuccess: onUnlocked, actionLabel: 'Unlock'),
                    const SizedBox(height: 14),
                    Center(
                      child: TextButton(
                        onPressed: () => _logout(context, ref),
                        child: Text(
                          'Not you? Log out',
                          style: AppTypography.style(
                            color: Colors.white.withValues(alpha: 0.5),
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
