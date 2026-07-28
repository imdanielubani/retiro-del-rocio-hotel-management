import 'package:flutter/material.dart';

/// The root [ScaffoldMessengerState], so code with no [BuildContext] of its
/// own — e.g. a realtime signal handler living in a provider — can still show
/// a toast, regardless of which screen the guest currently has open.
final rootScaffoldMessengerKey = GlobalKey<ScaffoldMessengerState>();
