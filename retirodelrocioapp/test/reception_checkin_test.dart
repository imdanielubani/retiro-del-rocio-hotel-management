import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_checkin.dart';

void main() {
  group('RoomOptions.fromJson', () {
    test('parses the assigned unit and same-type alternatives', () {
      final options = RoomOptions.fromJson({
        'assigned': {'id': 5, 'number': '205', 'room_name': 'Brisa Residence', 'price_label': 'NGN 150,000/night', 'has_tablet': true},
        'available': [
          {'id': 6, 'number': '202', 'room_name': 'Brisa Residence', 'price_label': 'NGN 150,000/night', 'has_tablet': false},
          {'id': 7, 'number': '204', 'room_name': 'Brisa Residence', 'price_label': 'NGN 150,000/night', 'has_tablet': true},
        ],
      });

      expect(options.assigned!.number, '205');
      expect(options.assigned!.hasTablet, isTrue);
      expect(options.available, hasLength(2));
      expect(options.available.first.priceLabel, 'NGN 150,000/night');
      expect(options.available.first.hasTablet, isFalse);
      expect(options.available.last.hasTablet, isTrue);
    });

    test('tolerates a null assignment and empty alternatives', () {
      final options = RoomOptions.fromJson(const {'assigned': null, 'available': []});
      expect(options.assigned, isNull);
      expect(options.available, isEmpty);
    });

    test('defaults hasTablet to false when the flag is absent', () {
      final option = RoomOption.fromJson(const {'id': 9, 'number': '301', 'room_name': 'Suite'});
      expect(option.hasTablet, isFalse);
    });
  });

  group('CheckinConfirmation.fromJson', () {
    test('parses the completion summary', () {
      final c = CheckinConfirmation.fromJson({
        'confirmation': 'RC-2507-137',
        'guest_name': 'Fatima Al-Rashid',
        'room_number': '205',
        'room_label': 'Brisa Residence · Room 205',
        'check_in_time': '14:36',
        'document_label': 'International Passport',
        'document_number': '784-1985-1234567-1',
        'document_url': 'https://res.cloudinary.com/demo/image/upload/x.jpg',
      });

      expect(c.confirmation, 'RC-2507-137');
      expect(c.guestName, 'Fatima Al-Rashid');
      expect(c.roomNumber, '205');
      expect(c.checkInTime, '14:36');
      expect(c.documentLabel, 'International Passport');
      expect(c.documentNumber, '784-1985-1234567-1');
      expect(c.documentUrl, contains('res.cloudinary.com'));
    });

    test('degrades gracefully on a sparse payload', () {
      final c = CheckinConfirmation.fromJson(const {'confirmation': 'RC-1', 'guest_name': 'Guest'});
      expect(c.confirmation, 'RC-1');
      expect(c.roomNumber, isNull);
      expect(c.documentUrl, isNull);
    });
  });
}
