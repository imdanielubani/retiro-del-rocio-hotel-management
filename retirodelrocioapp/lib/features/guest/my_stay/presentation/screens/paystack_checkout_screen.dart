import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:webview_flutter/webview_flutter.dart';

/// Opens Paystack's hosted checkout in a WebView and resolves to `true` once the
/// charge completes — detected when Paystack redirects to [callbackUrl] — or
/// `false` if the guest backs out. The reference is verified server-side after
/// this returns, so a spoofed redirect can't fake a payment.
Future<bool> showPaystackCheckout(
  BuildContext context, {
  required String authorizationUrl,
  required String callbackUrl,
}) async {
  final paid = await Navigator.of(context).push<bool>(
    MaterialPageRoute(
      fullscreenDialog: true,
      builder: (_) => _PaystackCheckoutScreen(
        authorizationUrl: authorizationUrl,
        callbackUrl: callbackUrl,
      ),
    ),
  );
  return paid ?? false;
}

class _PaystackCheckoutScreen extends StatefulWidget {
  const _PaystackCheckoutScreen({
    required this.authorizationUrl,
    required this.callbackUrl,
  });

  final String authorizationUrl;
  final String callbackUrl;

  @override
  State<_PaystackCheckoutScreen> createState() =>
      _PaystackCheckoutScreenState();
}

class _PaystackCheckoutScreenState extends State<_PaystackCheckoutScreen> {
  late final WebViewController _controller;
  bool _loading = true;
  bool _closed = false;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(AppColors.background)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) {
            if (mounted) setState(() => _loading = true);
          },
          onPageFinished: (_) {
            if (mounted) setState(() => _loading = false);
          },
          onNavigationRequest: (request) {
            // Paystack sends the browser to our callback the moment the charge
            // finishes — treat that as "paid" and hand back to verify.
            if (request.url.startsWith(widget.callbackUrl)) {
              _finish(true);
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.authorizationUrl));
  }

  void _finish(bool paid) {
    if (_closed) return;
    _closed = true;
    Navigator.of(context).pop(paid);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.background,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.close_rounded, color: Colors.white),
          onPressed: () => _finish(false),
        ),
        title: Text(
          'Secure Payment',
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_loading)
            const Center(
              child: CircularProgressIndicator(color: AppColors.gold),
            ),
        ],
      ),
    );
  }
}
