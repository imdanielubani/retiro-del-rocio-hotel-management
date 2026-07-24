import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/security/application/security_providers.dart';
import 'package:retirodelrocioapp/features/security/data/security_repository.dart';
import 'package:retirodelrocioapp/features/security/domain/visitor_pass_record.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_nav_rail.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/security_top_bar.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/visitor_code_keypad.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/visitor_verification_row.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

const Color _green = Color(0xFF22C55E);

/// Which way the verify panel reads.
enum _Verify { entering, verifying, notFound, matched, granted, denied }

const Color _deniedRed = Color(0xFFEF4444);

/// How the visitor list is narrowed.
enum _ListFilter { all, pending, verified }

/// Visitor Verification (Figma 229:13150 → 237:14868).
///
/// The security officer's gate screen: today's visitor passes on the left, a
/// numeric keypad on the right. The officer enters the 6-digit code the visitor
/// quotes; a live match reveals the visitor's details and a Grant Access button
/// that logs the entry. The list follows the same 20-second poll as the rest of
/// the security tablet, so a pass a guest just issued appears on its own.
class VisitorVerificationScreen extends ConsumerStatefulWidget {
  const VisitorVerificationScreen({
    super.key,
    required this.session,
    this.initialCode,
  });

  final StaffSession session;

  /// Pre-loaded when the officer arrives here by tapping "Verify Pass" on a
  /// dashboard request: the visitor is already standing at the gate and their
  /// code is already known, so re-keying six digits off the card would only
  /// invite a typo. Left null when they walk up to the keypad cold.
  final String? initialCode;

  @override
  ConsumerState<VisitorVerificationScreen> createState() =>
      _VisitorVerificationScreenState();
}

class _VisitorVerificationScreenState
    extends ConsumerState<VisitorVerificationScreen> {
  String _code = '';
  _Verify _state = _Verify.entering;
  VisitorPassRecord? _match;
  String _error = '';
  bool _granting = false;

  int? _expandedId;
  _ListFilter _filter = _ListFilter.all;

  String get _token => widget.session.token;

  @override
  void initState() {
    super.initState();

    // Arrived from a dashboard request — show the code already filled in and
    // look it up, so the officer lands on the visitor's details ready to grant.
    final code = widget.initialCode;
    if (code != null && code.length == 6) {
      _code = code;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _verify(code);
      });
    }
  }

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) Navigator.of(context).popUntil((r) => r.isFirst);
  }

  void _onNav(SecurityNavItem item) {
    switch (item) {
      case SecurityNavItem.verifiedPass:
        break; // already here
      case SecurityNavItem.dashboard:
        Navigator.of(context).pop();
      case SecurityNavItem.incidentResponse:
        // The dashboard beneath owns Incident Response; drop back to it and let
        // the officer pick it there rather than stacking a second security tree.
        Navigator.of(context).pop();
      case SecurityNavItem.chat:
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => const ComingSoonScreen(title: 'Chat'),
          ),
        );
    }
  }

  // --- Keypad ---------------------------------------------------------------

  void _onDigit(String d) {
    // A digit after a rejection starts a fresh code.
    if (_state == _Verify.notFound) {
      setState(() {
        _code = d;
        _state = _Verify.entering;
        _error = '';
      });
      return;
    }
    if (_code.length >= 6) return;

    final next = _code + d;
    setState(() => _code = next);
    if (next.length == 6) _verify(next);
  }

  void _onBackspace() {
    if (_state == _Verify.notFound) {
      setState(() {
        _code = '';
        _state = _Verify.entering;
        _error = '';
      });
      return;
    }
    if (_code.isEmpty) return;
    setState(() => _code = _code.substring(0, _code.length - 1));
  }

  Future<void> _verify(String code) async {
    setState(() => _state = _Verify.verifying);
    try {
      final match = await ref
          .read(securityActionsProvider(_token))
          .verifyVisitor(code);
      if (!mounted) return;
      setState(() {
        _match = match;
        _state = _Verify.matched;
      });
    } on SecurityException catch (e) {
      if (!mounted) return;
      setState(() {
        _state = _Verify.notFound;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _state = _Verify.notFound;
        _error = 'Could not verify the code.';
      });
    }
  }

  Future<void> _grant() async {
    final match = _match;
    if (match == null) return;
    setState(() => _granting = true);
    try {
      await ref.read(securityActionsProvider(_token)).grantVisitor(match.id);
      if (!mounted) return;
      setState(() => _state = _Verify.granted);
    } on SecurityException catch (e) {
      _showFailure(e.message);
    } catch (_) {
      _showFailure('Could not grant access.');
    } finally {
      if (mounted) setState(() => _granting = false);
    }
  }

  Future<void> _deny() async {
    final match = _match;
    if (match == null) return;
    setState(() => _granting = true);
    try {
      await ref.read(securityActionsProvider(_token)).denyVisitor(match.id);
      if (!mounted) return;
      setState(() => _state = _Verify.denied);
    } on SecurityException catch (e) {
      _showFailure(e.message);
    } catch (_) {
      _showFailure('Could not deny access.');
    } finally {
      if (mounted) setState(() => _granting = false);
    }
  }

  void _reset() {
    setState(() {
      _code = '';
      _match = null;
      _error = '';
      _state = _Verify.entering;
    });
  }

  void _showFailure(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF7F1D1D),
        behavior: SnackBarBehavior.floating,
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(securityRealtimeProvider(_token));
    final passes =
        ref.watch(securityVisitorsProvider(_token)).value ?? const [];
    final overview = ref.watch(securityOverviewProvider(_token)).value;
    final weather = ref.watch(weatherProvider).value;

    return SessionGuard(
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
            const ColoredBox(color: Color(0xF2000000)),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    SecurityNavRail(
                      active: SecurityNavItem.verifiedPass,
                      onSelect: _onNav,
                      onLogout: _logout,
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          SecurityTopBar(
                            officerName: widget.session.name,
                            officerRole:
                                overview?.officerRole ?? 'Security Office',
                            weather: weather,
                            hasAlert: (overview?.activeIncidents ?? 0) > 0,
                          ),
                          const SizedBox(height: 20),
                          _header(),
                          const SizedBox(height: 20),
                          Expanded(
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                // Left list — the Figma column is ~394 of ~1158.
                                Expanded(flex: 35, child: _listColumn(passes)),
                                // Right: the keypad is a fixed 340-wide block,
                                // centred and top-aligned, exactly as the Figma
                                // frame (x=733, width=340).
                                Expanded(
                                  flex: 65,
                                  child: Align(
                                    alignment: Alignment.topCenter,
                                    child: SizedBox(
                                      width: 340,
                                      child: _verifyPanel(),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Security',
          style: AppTypography.style(color: AppColors.gold, fontSize: 12),
        ),
        const SizedBox(height: 3),
        Text(
          'Visitor Verification',
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 36,
            fontWeight: FontWeight.w700,
            height: 1.1,
          ),
        ),
      ],
    );
  }

  // --- Left: today's visitors -----------------------------------------------

  Widget _listColumn(List<VisitorPassRecord> passes) {
    final pending = passes.where((p) => p.isPending).length;
    final visible = switch (_filter) {
      _ListFilter.all => passes,
      _ListFilter.pending => passes.where((p) => p.isPending).toList(),
      _ListFilter.verified => passes.where((p) => p.isVerified).toList(),
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'Visitors Today',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.85),
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${passes.length} pass${passes.length == 1 ? '' : 'es'} · $pending pending',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.35),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            _filterButton(),
          ],
        ),
        const SizedBox(height: 16),
        Expanded(
          child: visible.isEmpty
              ? _emptyList()
              : ListView.separated(
                  padding: const EdgeInsets.only(bottom: 16),
                  itemCount: visible.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (_, i) {
                    final pass = visible[i];
                    return VisitorVerificationRow(
                      pass: pass,
                      expanded: _expandedId == pass.id,
                      onTap: () => setState(
                        () => _expandedId = _expandedId == pass.id
                            ? null
                            : pass.id,
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _filterButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(100),
      child: InkWell(
        onTap: _pickFilter,
        borderRadius: BorderRadius.circular(100),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(100),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.12),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.tune_rounded,
                size: 14,
                color: Colors.white.withValues(alpha: 0.7),
              ),
              const SizedBox(width: 8),
              Text(
                switch (_filter) {
                  _ListFilter.all => 'Filter',
                  _ListFilter.pending => 'Pending',
                  _ListFilter.verified => 'Verified',
                },
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

  Future<void> _pickFilter() async {
    const items = <(_ListFilter, String)>[
      (_ListFilter.all, 'All visitors'),
      (_ListFilter.pending, 'Pending'),
      (_ListFilter.verified, 'Verified'),
    ];
    final selected = await showMenu<_ListFilter>(
      context: context,
      color: const Color(0xFF141414),
      position: const RelativeRect.fromLTRB(400, 170, 40, 0),
      items: [
        for (final (value, label) in items)
          PopupMenuItem<_ListFilter>(
            value: value,
            child: Row(
              children: [
                Icon(
                  _filter == value
                      ? Icons.check_rounded
                      : Icons.circle_outlined,
                  size: 16,
                  color: _filter == value
                      ? AppColors.gold
                      : Colors.white.withValues(alpha: 0.3),
                ),
                const SizedBox(width: 10),
                Text(
                  label,
                  style: AppTypography.style(color: Colors.white, fontSize: 14),
                ),
              ],
            ),
          ),
      ],
    );
    if (selected != null && mounted) setState(() => _filter = selected);
  }

  Widget _emptyList() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.groups_outlined,
            size: 30,
            color: Colors.white.withValues(alpha: 0.25),
          ),
          const SizedBox(height: 12),
          Text(
            _filter == _ListFilter.all
                ? 'No visitors invited today.'
                : 'No ${_filter == _ListFilter.pending ? 'pending' : 'verified'} visitors.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.5),
              fontSize: 15,
            ),
          ),
        ],
      ),
    );
  }

  // --- Right: the verify panel ----------------------------------------------

  Widget _verifyPanel() {
    final boxStyle = switch (_state) {
      _Verify.entering => CodeBoxStyle.neutral,
      _Verify.notFound || _Verify.denied => CodeBoxStyle.error,
      _Verify.verifying ||
      _Verify.matched ||
      _Verify.granted => CodeBoxStyle.success,
    };

    return SingleChildScrollView(
      child: Column(
        children: [
          const SizedBox(height: 8),
          Text(
            'Enter Visitor Code',
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 28,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Ask the visitor for their 6-digit entry code',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 26),
          VisitorCodeBoxes(code: _code, style: boxStyle),
          const SizedBox(height: 18),
          _panelBody(),
        ],
      ),
    );
  }

  Widget _panelBody() {
    switch (_state) {
      case _Verify.notFound:
        return Column(
          children: [
            Text(
              _error.isNotEmpty ? _error : 'Code not recognised',
              style: AppTypography.style(
                color: const Color(0xFFFF4D4D),
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 18),
            _keypad(enabled: true),
          ],
        );
      case _Verify.verifying:
        return Column(
          children: [
            const SizedBox(
              width: 26,
              height: 26,
              child: CircularProgressIndicator(strokeWidth: 2.5, color: _green),
            ),
            const SizedBox(height: 20),
            _keypad(enabled: false),
          ],
        );
      case _Verify.matched:
        return _matchCard(_match!);
      case _Verify.granted:
        return _grantedCard();
      case _Verify.denied:
        return _deniedCard();
      case _Verify.entering:
        return _keypad(enabled: true);
    }
  }

  Widget _keypad({required bool enabled}) {
    return VisitorNumericPad(
      onDigit: _onDigit,
      onBackspace: _onBackspace,
      enabled: enabled,
    );
  }

  Widget _matchCard(VisitorPassRecord pass) {
    return Column(
      children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.04),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: _green.withValues(alpha: 0.4),
              width: 0.8,
            ),
          ),
          child: Column(
            children: [
              Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: _green.withValues(alpha: 0.15),
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      pass.initials,
                      style: AppTypography.style(
                        color: _green,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          pass.visitorName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTypography.style(
                            color: Colors.white,
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          _visitingLine(pass),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTypography.style(
                            color: Colors.white.withValues(alpha: 0.45),
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Icon(Icons.check_circle_rounded, size: 24, color: _green),
                ],
              ),
              const SizedBox(height: 16),
              Divider(color: Colors.white.withValues(alpha: 0.08), height: 1),
              const SizedBox(height: 12),
              _matchRow('Pass', pass.reference),
              if ((pass.submittedLabel ?? '').isNotEmpty) ...[
                const SizedBox(height: 9),
                _matchRow('Submitted', pass.submittedLabel!),
              ],
              if ((pass.email ?? '').isNotEmpty) ...[
                const SizedBox(height: 9),
                _matchRow('Email', pass.email!),
              ],
              if ((pass.phone ?? '').isNotEmpty) ...[
                const SizedBox(height: 9),
                _matchRow('WhatsApp', pass.phone!),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),
        _grantButton(),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(child: _denyButton()),
            const SizedBox(width: 10),
            Expanded(child: _secondaryButton('Cancel', _reset)),
          ],
        ),
      ],
    );
  }

  Widget _denyButton() {
    return Material(
      color: _deniedRed.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: _granting ? null : _deny,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: 48,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: _deniedRed.withValues(alpha: 0.4),
              width: 0.8,
            ),
          ),
          child: Text(
            'Deny',
            style: AppTypography.style(
              color: _deniedRed,
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }

  Widget _deniedCard() {
    return Column(
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 22, horizontal: 20),
          decoration: BoxDecoration(
            color: _deniedRed.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: _deniedRed.withValues(alpha: 0.4),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.block_rounded, size: 22, color: _deniedRed),
              const SizedBox(width: 10),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'Access Denied',
                    style: AppTypography.style(
                      color: _deniedRed,
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text(
                    'Visitor turned away',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.45),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _primaryButton('Verify Another', _reset),
      ],
    );
  }

  String _visitingLine(VisitorPassRecord pass) {
    final room = (pass.roomNumber ?? '').isNotEmpty
        ? 'Room ${pass.roomNumber}'
        : null;
    final host = pass.hostName;
    return [
      if (room != null) 'Visiting $room',
      if ((host ?? '').isNotEmpty) host,
    ].whereType<String>().join('  ·  ');
  }

  /// Label left, value hard against the right edge.
  ///
  /// A `Spacer` and a `Flexible` both carry flex 1, so they split the leftover
  /// width in half: the value was being ellipsized with space to spare, and the
  /// rows sat at different right edges because the gap moved with the value.
  /// `Expanded` on the value alone lines every row up.
  Widget _matchRow(String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 12,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.right,
            style: AppTypography.style(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }

  Widget _grantedCard() {
    return Column(
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 22, horizontal: 20),
          decoration: BoxDecoration(
            color: _green.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: _green.withValues(alpha: 0.4),
              width: 0.8,
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.check_circle_rounded, size: 22, color: _green),
              const SizedBox(width: 10),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'Access Granted',
                    style: AppTypography.style(
                      color: _green,
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text(
                    'Entry logged successfully',
                    style: AppTypography.style(
                      color: Colors.white.withValues(alpha: 0.45),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _primaryButton('Verify Another', _reset),
      ],
    );
  }

  Widget _grantButton() {
    return Material(
      color: _green,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: _granting ? null : _grant,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: 52,
          width: double.infinity,
          alignment: Alignment.center,
          child: _granting
              ? const SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.black,
                  ),
                )
              : Text(
                  'Grant Access',
                  style: AppTypography.style(
                    color: Colors.black,
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
        ),
      ),
    );
  }

  Widget _primaryButton(String label, VoidCallback onTap) {
    return Material(
      color: _green.withValues(alpha: 0.15),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: 50,
          width: double.infinity,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: _green.withValues(alpha: 0.4),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: _green,
              fontSize: 15,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }

  Widget _secondaryButton(String label, VoidCallback onTap) {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: 48,
          width: double.infinity,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.12),
              width: 0.8,
            ),
          ),
          child: Text(
            label,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.7),
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }
}
