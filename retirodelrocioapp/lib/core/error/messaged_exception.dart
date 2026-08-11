/// An exception that carries a ready, user-facing [message].
///
/// The tablet's repositories throw these so shared UI (like the SOS alert
/// overlay) can surface the server's own words — "No connection", "Session
/// expired" — rather than a blanket failure, without depending on any one
/// feature's exception type.
abstract interface class MessagedException implements Exception {
  String get message;
}
