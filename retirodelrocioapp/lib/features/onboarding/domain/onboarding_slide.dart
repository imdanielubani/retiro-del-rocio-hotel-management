import 'package:flutter/foundation.dart';

/// A single onboarding slide's copy (Figma nodes 62:1820 → 66:2629).
@immutable
class OnboardingSlide {
  const OnboardingSlide({
    required this.eyebrow,
    required this.titleTop,
    required this.titleBottom,
    required this.description,
  });

  /// Small gold label, e.g. "WELCOME TO".
  final String eyebrow;

  /// First headline line (white), e.g. "Retiro Del".
  final String titleTop;

  /// Second headline line (gold), e.g. "Rocio".
  final String titleBottom;

  final String description;

  static const List<OnboardingSlide> all = [
    OnboardingSlide(
      eyebrow: 'WELCOME TO',
      titleTop: 'Retiro Del',
      titleBottom: 'Rocio',
      description:
          'Where luxury meets serenity. Experience world-class hospitality '
          'crafted to perfection.',
    ),
    OnboardingSlide(
      eyebrow: 'SMART LIVING',
      titleTop: 'Your room,',
      titleBottom: 'Your Rules.',
      description:
          'Control every element of your suite — lights, climate, curtains, '
          'and entertainment — from one elegant interface.',
    ),
    OnboardingSlide(
      eyebrow: 'FINE DINING',
      titleTop: 'Taste the',
      titleBottom: 'Extraordinary.',
      description:
          'From in-room dining to The Golden Fork Restaurant, our chefs craft '
          'unforgettable culinary experiences.',
    ),
    OnboardingSlide(
      eyebrow: 'TOTAL WELLNESS',
      titleTop: 'Restore &',
      titleBottom: 'Rejuvenate.',
      description:
          'Discover our Sanctuary Spa, Rooftop Pool, and Fitness Centre — '
          'your personal retreat within a retreat.',
    ),
  ];
}
