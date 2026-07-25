import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_pickup.dart';

void main() {
  group('PickupBooking.fromJson', () {
    test('parses a fully assigned pickup with its driver', () {
      final p = PickupBooking.fromJson({
        'id': 12,
        'reference': 'TR-1012',
        'guest_name': 'Fatima Al-Rashid',
        'guest_phone': '+2348012345678',
        'vehicle': 'Toyota Sienna',
        'passengers_label': '2 passengers',
        'from': 'Yakubu Gowon Airport (JOS)',
        'to': 'Hotel Del Retiro',
        'number_label': 'Flight Number',
        'flight_number': 'WB402',
        'pickup_date': 'Jul 26, 2026',
        'pickup_time': '14:30',
        'amount_label': '₦25,000',
        'pickup_status': 'assigned',
        'pickup_status_label': 'Driver Assigned',
        'driver': {
          'id': 3,
          'name': 'Musa Bello',
          'phone': '+2348090000000',
          'vehicle_details': 'Toyota Sienna · ABC-123-XY',
          'status_label': 'Available',
        },
      });

      expect(p.reference, 'TR-1012');
      expect(p.isAssigned, isTrue);
      expect(p.driver, isNotNull);
      expect(p.driver!.name, 'Musa Bello');
      expect(p.driver!.vehicleDetails, contains('ABC-123-XY'));
    });

    test('an unassigned pickup has no driver', () {
      final p = PickupBooking.fromJson(const {
        'id': 1,
        'reference': 'TR-1001',
        'guest_name': 'Guest',
        'vehicle': 'Sedan',
        'pickup_status': 'unassigned',
        'pickup_status_label': 'Awaiting Driver',
        'driver': null,
      });

      expect(p.isUnassigned, isTrue);
      expect(p.isAssigned, isFalse);
      expect(p.driver, isNull);
    });
  });

  group('PickupDriver.fromJson', () {
    test('parses a roster entry', () {
      final d = PickupDriver.fromJson(const {
        'id': 7,
        'name': 'Ade Johnson',
        'phone': '+2348011112222',
        'vehicle_details': 'Hyundai · XYZ-1',
        'status_label': 'Available',
      });

      expect(d.id, 7);
      expect(d.name, 'Ade Johnson');
      expect(d.statusLabel, 'Available');
    });
  });
}
