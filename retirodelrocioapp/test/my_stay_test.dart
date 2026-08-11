import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/domain/guest_stay.dart';

/// The My Stay payload parsing — the reservation, guests, summary, inclusions and
/// the extension details returned after extending.
void main() {
  group('GuestStay.fromJson', () {
    test('parses the full reservation payload', () {
      final stay = GuestStay.fromJson({
        'reservation': {
          'room_name': 'Alba Suite',
          'unit_label': 'Room 101',
          'image_url': 'https://example.test/room.jpg',
          'check_in': '2026-07-09T14:00:00+01:00',
          'check_out': '2026-07-14T12:00:00+01:00',
          'nights': 5,
          'rate_per_night': 7500,
          'rate_label': 'NGN 7,500/night',
        },
        'guests': {'primary_name': 'Daniel Ubani', 'party_size': 3},
        'visitors': [
          {
            'name': 'Maria Lopez',
            'initials': 'ML',
            'status': 'inside',
            'status_label': 'Inside',
          },
          {
            'name': 'John Doe',
            'initials': 'JD',
            'status': 'pending',
            'status_label': 'Invited',
          },
        ],
        'summary': {
          'lines': [
            {
              'label': 'Room Rate',
              'sub': 'NGN 7,500 × 5',
              'amount_label': 'NGN 37,500',
            },
          ],
          'total_label': 'NGN 37,500',
        },
        'current_bill_label': 'NGN 37,500',
        'inclusions': [
          {'title': 'High-Speed WiFi', 'value': 'Included'},
          {'title': 'Daily Breakfast', 'value': 'Included'},
        ],
      });

      expect(stay.reservation.roomName, 'Alba Suite');
      expect(stay.reservation.unitLabel, 'Room 101');
      expect(stay.reservation.nights, 5);
      expect(stay.reservation.ratePerNight, 7500);
      expect(stay.reservation.checkIn, isNotNull);
      expect(stay.guests.primaryName, 'Daniel Ubani');
      expect(stay.guests.partySize, 3);
      expect(stay.visitors.length, 2);
      expect(stay.visitors.first.name, 'Maria Lopez');
      expect(stay.visitors.first.initials, 'ML');
      expect(stay.visitors.first.status, 'inside');
      expect(stay.visitors.first.statusLabel, 'Inside');
      expect(stay.summaryLines.single.amountLabel, 'NGN 37,500');
      expect(stay.summaryTotalLabel, 'NGN 37,500');
      expect(stay.currentBillLabel, 'NGN 37,500');
      expect(stay.inclusions.length, 2);
      expect(stay.inclusions.first.title, 'High-Speed WiFi');
      // No extension on a plain My Stay fetch.
      expect(stay.extension, isNull);
    });

    test('parses the extension block returned after extending', () {
      final stay = GuestStay.fromJson({
        'reservation': {
          'room_name': 'Alba Suite',
          'nights': 7,
          'rate_per_night': 7500,
        },
        'guests': {'primary_name': 'Daniel Ubani', 'party_size': 1},
        'summary': {'lines': [], 'total_label': 'NGN 52,500'},
        'current_bill_label': 'NGN 52,500',
        'inclusions': [],
        'extension': {
          'additional_nights': 2,
          'additional_cost': 15000,
          'additional_cost_label': 'NGN 15,000',
          'new_check_out': '2026-07-16T12:00:00+01:00',
          'new_check_out_label': 'July 16, 2026',
        },
      });

      expect(stay.extension, isNotNull);
      expect(stay.extension!.additionalNights, 2);
      expect(stay.extension!.additionalCost, 15000);
      expect(stay.extension!.newCheckOutLabel, 'July 16, 2026');
      expect(stay.extension!.newCheckOut, isNotNull);
    });

    test('a malformed payload degrades to safe defaults', () {
      final stay = GuestStay.fromJson(const {});
      expect(stay.reservation.roomName, 'Your Room');
      expect(stay.reservation.nights, 0);
      expect(stay.guests.partySize, 1);
      expect(stay.visitors, isEmpty);
      expect(stay.summaryLines, isEmpty);
      expect(stay.inclusions, isEmpty);
      expect(stay.extension, isNull);
    });
  });

  group('ExtensionQuote.fromJson', () {
    test('parses the priced Paystack quote', () {
      final quote = ExtensionQuote.fromJson({
        'authorization_url': 'https://checkout.paystack.com/abc123',
        'callback_url': 'https://retirodelrocio.ng/tablet/extend-return',
        'reference': 'EXT-12-ABCDEFGHIJ',
        'additional_nights': 2,
        'subtotal': 15000,
        'subtotal_label': 'NGN 15,000',
        'vat': 1125,
        'vat_label': 'NGN 1,125',
        'total': 16125,
        'total_label': 'NGN 16,125',
      });

      expect(quote.authorizationUrl, 'https://checkout.paystack.com/abc123');
      expect(
        quote.callbackUrl,
        'https://retirodelrocio.ng/tablet/extend-return',
      );
      expect(quote.reference, 'EXT-12-ABCDEFGHIJ');
      expect(quote.additionalNights, 2);
      expect(quote.subtotalLabel, 'NGN 15,000');
      expect(quote.vatLabel, 'NGN 1,125');
      expect(quote.totalLabel, 'NGN 16,125');
    });
  });
}
