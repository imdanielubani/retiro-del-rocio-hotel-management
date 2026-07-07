<?php

/*
|--------------------------------------------------------------------------
| Website CMS — page & field schema
|--------------------------------------------------------------------------
| Single source of truth for every editable area of the public website.
| Content is grouped into "pages" (the cards on Admin → Website CMS). Each
| page has a category (core | system), section "chips", and a flat list of
| fields. The per-page editor (Admin\Cms\Edit) renders fields from here, and
| the public blades read values via cms()/cms_image()/cms_array() using the
| `default` below — so unset keys fall back to the original copy.
|
| Field types: text | textarea | image | repeater
| A dot-safe binding `name` is derived for each field automatically.
*/

$pages = [
    'landing' => [
        'label' => 'Landing Page',
        'category' => 'core',
        'chips' => ['Hero', 'Stillness', 'Offers', 'Member', 'Explore Jos', 'Wellness'],
        'preview' => '/',
        'fields' => [
            ['key' => 'home.hero_image', 'label' => 'Hero image', 'type' => 'image', 'default' => 'images/image 1.jpg'],

            ['key' => 'home.stillness_title', 'label' => '“Stillness” heading', 'type' => 'text', 'default' => 'Where stillness finds you'],
            ['key' => 'home.stillness_text', 'label' => '“Stillness” paragraph', 'type' => 'textarea', 'default' => 'Retiro Del Rocio blends modern hospitality with intentional living. From intelligent room experiences and personalized comfort to curated wellness spaces and attentive service, every part of your journey is designed to feel effortless.'],

            ['key' => 'home.offers_heading', 'label' => 'Offers section heading', 'type' => 'text', 'default' => 'Explore our exclusive offers'],

            ['key' => 'home.destination_title', 'label' => '“Destination” heading', 'type' => 'text', 'default' => 'More than a destination'],
            ['key' => 'home.destination_text', 'label' => '“Destination” paragraph', 'type' => 'textarea', 'default' => 'Surrounded by calming architecture and refined hospitality, every experience is thoughtfully crafted to help you slow down and reconnect. From personalized room experiences and wellness-centered spaces to seamless service and quiet luxury, every detail is designed to make your stay feel effortless.'],
            ['key' => 'home.destination_image', 'label' => '“Destination” image', 'type' => 'image', 'default' => 'images/image 5.png'],

            ['key' => 'home.member_title', 'label' => '“Member” heading', 'type' => 'text', 'default' => 'Become a member of Retiro Del Rocio'],
            ['key' => 'home.member_text', 'label' => '“Member” paragraph', 'type' => 'textarea', 'default' => 'Get exclusive discounts on services, experiences, and curated destinations across Jos and beyond. Enjoy member-only perks designed to help you explore more for less.'],
            ['key' => 'home.member_image', 'label' => '“Member” image', 'type' => 'image', 'default' => 'images/image 47.jpg'],
            ['key' => 'home.member_cta_label', 'label' => '“Member” button text', 'type' => 'text', 'default' => 'Subscribe'],
            ['key' => 'home.member_cta_url', 'label' => '“Member” button link', 'type' => 'text', 'default' => '#'],
            ['key' => 'home.member_link_label', 'label' => '“Member” secondary link text', 'type' => 'text', 'default' => 'Contact Us'],
            ['key' => 'home.member_link_url', 'label' => '“Member” secondary link', 'type' => 'text', 'default' => '/contact-us'],

            ['key' => 'home.jos_title', 'label' => '“Explore Jos” heading', 'type' => 'text', 'default' => 'Beyond the stay Explore Jos City'],
            ['key' => 'home.jos_text', 'label' => '“Explore Jos” paragraph', 'type' => 'textarea', 'default' => 'Experience the beauty and calm that make Jos truly unforgettable. From breathtaking rock landscapes and cool weather to peaceful nature trails and rich local culture, every moment invites discovery. Whether you seek adventure, relaxation, or quiet reflection, Jos offers a refreshing escape where nature, serenity, and memorable experiences come together beautifully.'],
            ['key' => 'home.jos_image_1', 'label' => '“Explore Jos” image 1', 'type' => 'image', 'default' => 'images/IMG_2625 1.jpg'],
            ['key' => 'home.jos_image_2', 'label' => '“Explore Jos” image 2', 'type' => 'image', 'default' => 'images/IMG_2620 2.jpg'],
            ['key' => 'home.jos_image_3', 'label' => '“Explore Jos” image 3', 'type' => 'image', 'default' => 'images/IMG_2627 2.jpg'],

            ['key' => 'home.wellness_title', 'label' => '“Wellness lifestyle” heading', 'type' => 'text', 'default' => 'Explore our wellness lifestyle'],
            ['key' => 'home.wellness_image', 'label' => '“Wellness lifestyle” image', 'type' => 'image', 'default' => 'images/image 14.jpg'],
            ['key' => 'home.wellness_cta_label', 'label' => '“Wellness” button text', 'type' => 'text', 'default' => 'Explore'],
            ['key' => 'home.wellness_cta_url', 'label' => '“Wellness” button link', 'type' => 'text', 'default' => '#'],

            ['key' => 'home.train_title', 'label' => '“Train. Recover.” heading', 'type' => 'text', 'default' => 'Train. Recover. Recharge.'],
            ['key' => 'home.train_text', 'label' => '“Train. Recover.” paragraph', 'type' => 'textarea', 'default' => 'Stay active and restore your balance in a space designed for movement, wellness, and recovery. Whether you’re maintaining your routine, starting your day with energy, or unwinding after a long one, our fitness and wellness experience is designed to help you feel refreshed, focused, and recharged throughout your stay.'],
            ['key' => 'home.train_image', 'label' => '“Train. Recover.” image', 'type' => 'image', 'default' => 'images/image 13.jpg'],
        ],
    ],

    'spa' => [
        'label' => 'Spa & Wellness',
        'category' => 'core',
        'chips' => ['Hero', 'Services', 'Discover', 'Why Us'],
        'preview' => '/spa-wellness',
        'fields' => [
            ['key' => 'spa.hero_image', 'label' => 'Hero image', 'type' => 'image', 'default' => 'images/spabg.jpg'],
            ['key' => 'spa.hero_title', 'label' => 'Hero heading', 'type' => 'text', 'default' => 'Rejuvenate Mind, Body & Soul'],
            ['key' => 'spa.hero_text', 'label' => 'Hero subtext', 'type' => 'textarea', 'default' => 'Escape the demands of everyday life and immerse yourself in a world of relaxation.'],
            ['key' => 'spa.hero_cta_label', 'label' => 'Hero button text', 'type' => 'text', 'default' => 'Book Session'],
            ['key' => 'spa.hero_cta_url', 'label' => 'Hero button link', 'type' => 'text', 'default' => '/contact-us'],

            ['key' => 'spa.services_title', 'label' => 'Services heading', 'type' => 'text', 'default' => 'Explore our Spa Services'],
            ['key' => 'spa.services_text', 'label' => 'Services subtext', 'type' => 'textarea', 'default' => 'From therapeutic massages to revitalizing skincare treatments, our wellness experiences are tailored to help you unwind, recharge, and feel your absolute best.'],

            ['key' => 'spa.service_1_image', 'label' => 'Service 1 image', 'type' => 'image', 'default' => 'images/skincare.png'],
            ['key' => 'spa.service_1_title', 'label' => 'Service 1 title', 'type' => 'text', 'default' => 'Skin Care'],
            ['key' => 'spa.service_2_image', 'label' => 'Service 2 image', 'type' => 'image', 'default' => 'images/manicure.png'],
            ['key' => 'spa.service_2_title', 'label' => 'Service 2 title', 'type' => 'text', 'default' => 'Manicure & Pedicure'],
            ['key' => 'spa.service_3_image', 'label' => 'Service 3 image', 'type' => 'image', 'default' => 'images/sauna.jpg'],
            ['key' => 'spa.service_3_title', 'label' => 'Service 3 title', 'type' => 'text', 'default' => 'Sauna Baths'],
            ['key' => 'spa.service_4_image', 'label' => 'Service 4 image', 'type' => 'image', 'default' => 'images/massage.png'],
            ['key' => 'spa.service_4_title', 'label' => 'Service 4 title', 'type' => 'text', 'default' => 'Massage'],

            ['key' => 'spa.discover_image', 'label' => 'Discover band image', 'type' => 'image', 'default' => 'images/image 1.jpg'],
            ['key' => 'spa.discover_title', 'label' => 'Discover heading', 'type' => 'text', 'default' => 'Discover a Healthier Way to Relax'],
            ['key' => 'spa.discover_text', 'label' => 'Discover paragraph', 'type' => 'textarea', 'default' => 'Wellness is more than a treatment—it’s a lifestyle. Experience holistic care that nurtures your body, mind, and spirit.'],

            ['key' => 'spa.why_title', 'label' => '“Why us” heading', 'type' => 'text', 'default' => 'WHY Retiro Del Rocio'],
            ['key' => 'spa.features', 'label' => '“Why us” features', 'type' => 'repeater', 'item' => ['title' => 'Title', 'text' => 'Description'], 'default' => [
                ['title' => 'Premium Products', 'text' => 'We use carefully selected, high-quality products to ensure exceptional results and comfort.'],
                ['title' => 'Certified Professionals', 'text' => 'Our experienced wellness specialists are dedicated to delivering personalized care and outstanding service.'],
                ['title' => 'Personalized Treatments', 'text' => 'Every guest receives tailored recommendations and treatments designed around their unique needs.'],
                ['title' => 'Serene Environment', 'text' => 'Enjoy a peaceful atmosphere thoughtfully designed to help you relax, recharge, and reconnect.'],
            ]],
        ],
    ],

    'navigation' => [
        'label' => 'Navigation Menu',
        'category' => 'system',
        'chips' => ['Logo', 'Desktop Nav', 'Mobile Nav', 'Links'],
        'preview' => '/',
        'fields' => [
            ['key' => 'nav.logo', 'label' => 'Navbar logo', 'type' => 'image', 'default' => 'images/Hotel Logo 1.png'],
            ['key' => 'nav.links', 'label' => 'Menu links', 'type' => 'repeater', 'item' => ['label' => 'Label', 'url' => 'Link (URL)'], 'default' => [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'Rooms & Apartment', 'url' => '/rooms-apartment'],
                ['label' => 'Experience', 'url' => '/experience'],
                ['label' => 'Gym', 'url' => '/gym'],
                ['label' => 'Cinema', 'url' => '/cinema'],
                ['label' => 'Restaurant', 'url' => '/restaurant'],
                ['label' => 'Spa/Wellness', 'url' => '/spa-wellness'],
            ]],
            ['key' => 'nav.cta_label', 'label' => 'Button text', 'type' => 'text', 'default' => 'Get in touch'],
            ['key' => 'nav.cta_url', 'label' => 'Button link', 'type' => 'text', 'default' => '/contact-us'],
        ],
    ],

    'footer' => [
        'label' => 'Footer Editor',
        'category' => 'system',
        'chips' => ['Brand', 'Links', 'Contact', 'Legal'],
        'preview' => '/',
        'fields' => [
            ['key' => 'footer.logo', 'label' => 'Footer logo', 'type' => 'image', 'default' => 'images/Logo footer.png'],
            ['key' => 'footer.brand_text', 'label' => 'Brand description', 'type' => 'textarea', 'default' => 'Experience the elegance of stay at Retiro Del Rocio, where luxury meets world-class comfort in every detail.'],
            ['key' => 'footer.links', 'label' => 'Helpful links', 'type' => 'repeater', 'item' => ['label' => 'Label', 'url' => 'Link (URL)'], 'default' => [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'About Us', 'url' => '#'],
                ['label' => 'Rooms & Apartments', 'url' => '/rooms-apartment'],
            ]],
            ['key' => 'footer.email', 'label' => 'Contact email', 'type' => 'text', 'default' => 'hello@retirodelrocio.com'],
            ['key' => 'footer.address', 'label' => 'Address', 'type' => 'textarea', 'default' => 'No. 1, Off Liberty Boulevard, Millionaire Quarters, Jos, Plateau State'],
            ['key' => 'footer.phone', 'label' => 'Phone number', 'type' => 'text', 'default' => '(+234) 7012623680'],
            ['key' => 'footer.copyright', 'label' => 'Copyright line', 'type' => 'text', 'default' => '2026 Retiro Del Rocio. All Rights Reserved.'],
            ['key' => 'footer.policy_links', 'label' => 'Policy links (bottom bar)', 'type' => 'repeater', 'item' => ['label' => 'Label', 'url' => 'Link (URL)'], 'default' => [
                ['label' => 'Privacy Policy', 'url' => '/privacy-policy'],
                ['label' => 'Terms of Service', 'url' => '/terms-of-service'],
                ['label' => 'Booking Policy', 'url' => '/booking-policy'],
            ]],
        ],
    ],

    'pickup' => [
        'label' => 'Vehicle Pickup',
        'category' => 'service',
        'chips' => ['Service', 'Locations', 'Vehicles'],
        'preview' => '/rooms-apartment',
        'fields' => [
            ['key' => 'pickup.title', 'label' => 'Popup title', 'type' => 'text', 'default' => 'Vehicle Pickup Service'],
            ['key' => 'pickup.heading', 'label' => 'Feature heading', 'type' => 'text', 'default' => 'Premium Chauffeur Experience'],
            ['key' => 'pickup.text', 'label' => 'Feature paragraph', 'type' => 'textarea', 'default' => 'Combine luxury accommodation with premium transportation services and enjoy special packages designed to enhance your stay from the moment you arrive.'],
            ['key' => 'pickup.image_1', 'label' => 'Popup image (left)', 'type' => 'image', 'default' => 'images/airportpickup image popup1.jpg'],
            ['key' => 'pickup.image_2', 'label' => 'Popup image (right)', 'type' => 'image', 'default' => 'images/airportpickup image popup2.jpg'],
            ['key' => 'pickup.locations', 'label' => 'Pickup locations', 'type' => 'repeater', 'item' => ['name' => 'Location name'], 'default' => [
                ['name' => 'Airport Pickup'],
                ['name' => 'Valgee'],
                ['name' => 'Nengee'],
                ['name' => 'Plateau Riders'],
            ]],
        ],
    ],

    'checkout' => [
        'label' => 'Checkout',
        'category' => 'service',
        'chips' => ['Booking Summary', 'Payment', 'Confirmation'],
        'preview' => '/rooms-apartment',
        'fields' => [
            ['key' => 'checkout.summary_title', 'label' => 'Summary heading', 'type' => 'text', 'default' => 'Summary'],
            ['key' => 'checkout.edit_label', 'label' => '“Edit selection” link text', 'type' => 'text', 'default' => 'Edit selection'],
            ['key' => 'checkout.guest_title', 'label' => 'Guest section heading', 'type' => 'text', 'default' => "Who's Checking in?"],
            ['key' => 'checkout.secure_note', 'label' => 'Secure-payment note', 'type' => 'textarea', 'default' => 'Card details are entered securely in the Paystack window.'],
            ['key' => 'checkout.pay_label', 'label' => 'Pay button text', 'type' => 'text', 'default' => 'Make reservation'],
        ],
    ],

    'spareservation' => [
        'label' => 'Spa Reservation',
        'category' => 'service',
        'chips' => ['Popup', 'Summary'],
        'preview' => '/spa-wellness',
        'fields' => [
            ['key' => 'spares.title', 'label' => 'Popup heading', 'type' => 'text', 'default' => 'Reservation'],
            ['key' => 'spares.intro', 'label' => 'Popup intro text', 'type' => 'textarea', 'default' => 'Choose your spa services, the number of guests and a preferred date & time. We’ll confirm your reservation after a secure payment.'],
            ['key' => 'spares.service_label', 'label' => '“Select service” label', 'type' => 'text', 'default' => 'Select Spa Service'],
            ['key' => 'spares.special_label', 'label' => '“Special request” label', 'type' => 'text', 'default' => 'Special Request'],
            ['key' => 'spares.summary_title', 'label' => 'Reservation Summary heading', 'type' => 'text', 'default' => 'Reservation Summary'],
            ['key' => 'spares.summary_text', 'label' => 'Reservation Summary text', 'type' => 'textarea', 'default' => 'Review your selected services and reservation details below, then complete your secure payment to confirm your booking.'],
            ['key' => 'spares.cta_label', 'label' => 'Popup button text', 'type' => 'text', 'default' => 'Complete Reservation'],
        ],
    ],

    'spacheckout' => [
        'label' => 'Spa Checkout',
        'category' => 'service',
        'chips' => ['Checkout', 'Payment', 'Success'],
        'preview' => '/spa-wellness',
        'fields' => [
            // Checkout page
            ['key' => 'spacheckout.checkout_heading', 'label' => 'Checkout heading', 'type' => 'text', 'default' => 'Complete your spa reservation securely in under 2 minutes.'],
            ['key' => 'spacheckout.customer_title', 'label' => 'Customer section heading', 'type' => 'text', 'default' => 'Customer Details'],
            ['key' => 'spacheckout.cancellation_title', 'label' => 'Cancellation policy heading', 'type' => 'text', 'default' => 'Cancellation Policy'],
            ['key' => 'spacheckout.cancellation_text', 'label' => 'Cancellation policy text', 'type' => 'textarea', 'default' => 'Reschedule or cancel up to 24 hours before your appointment for a full refund. Within 24 hours, the convenience fee is non-refundable.'],
            ['key' => 'spacheckout.secure_note', 'label' => 'Secure-payment note', 'type' => 'textarea', 'default' => 'Card details are entered securely in the Paystack window.'],
            ['key' => 'spacheckout.pay_label', 'label' => 'Pay button text', 'type' => 'text', 'default' => 'Make reservation'],

            // Success page
            ['key' => 'spacheckout.success_title', 'label' => 'Success heading', 'type' => 'text', 'default' => 'Reservation Successful!'],
            ['key' => 'spacheckout.success_text', 'label' => 'Success message', 'type' => 'textarea', 'default' => 'Your spa reservation is confirmed.'],
        ],
    ],

    'contact' => [
        'label' => 'Contact Page',
        'category' => 'core',
        'chips' => ['Form', 'Enquiries', 'FAQs'],
        'preview' => '/contact-us',
        'fields' => [
            ['key' => 'contact.form_title', 'label' => 'Form heading', 'type' => 'text', 'default' => 'Get in Touch'],
            ['key' => 'contact.form_subtitle', 'label' => 'Form subheading', 'type' => 'text', 'default' => 'You can reach us anytime'],

            ['key' => 'contact.enquiry_eyebrow', 'label' => 'Enquiries eyebrow', 'type' => 'text', 'default' => 'Let’s Start a Conversation'],
            ['key' => 'contact.enquiry_title', 'label' => 'Enquiries heading', 'type' => 'text', 'default' => 'General Enquires'],
            ['key' => 'contact.enquiry_text', 'label' => 'Enquiries paragraph', 'type' => 'textarea', 'default' => 'For services inquiries, project discussions, or partnerships opportunities, please reach out using the contact details or form. A member of our team will get back to you shortly.'],

            ['key' => 'contact.address', 'label' => 'Address', 'type' => 'textarea', 'default' => 'No. 1, Off Liberty Boulevard, Millionaire Quarters, Jos, Plateau State'],
            ['key' => 'contact.email', 'label' => 'Contact email', 'type' => 'text', 'default' => 'hello@retirodelrocio.com'],
            ['key' => 'contact.phone', 'label' => 'Phone number', 'type' => 'text', 'default' => '(+234) 7012623680'],
            ['key' => 'contact.phone_note', 'label' => 'Phone note', 'type' => 'text', 'default' => 'Get in touch with us. Speak to one of our business reps'],
            ['key' => 'contact.hours', 'label' => 'Opening hours', 'type' => 'text', 'default' => 'Mon - Fri  from 9am - 4pm'],

            ['key' => 'contact.facebook', 'label' => 'Facebook URL', 'type' => 'text', 'default' => '#'],
            ['key' => 'contact.x', 'label' => 'X (Twitter) URL', 'type' => 'text', 'default' => '#'],
            ['key' => 'contact.instagram', 'label' => 'Instagram URL', 'type' => 'text', 'default' => '#'],
            ['key' => 'contact.linkedin', 'label' => 'LinkedIn URL', 'type' => 'text', 'default' => '#'],

            ['key' => 'contact.faqs', 'label' => 'Frequently asked questions', 'type' => 'repeater', 'item' => ['q' => 'Question', 'a' => 'Answer'], 'default' => [
                ['q' => 'Can I change my room booking?', 'a' => 'Yes. You can adjust or reschedule your booking from your confirmation email or by contacting our team — changes are subject to availability and our cancellation policy.'],
                ['q' => 'Can I modify my selection after making reservations?', 'a' => 'Absolutely. Reach out to our reservations team before your stay and we will update your selection where possible.'],
                ['q' => 'Can I modify my seat selection after booking a ticket?', 'a' => 'Cinema and experience seats can be changed up to a few hours before the session, depending on availability.'],
                ['q' => 'Is my payment information secure on Retiro Del Rocio?', 'a' => 'Yes. All payments are processed over encrypted, secure channels and we never store your full card details.'],
                ['q' => 'What if I have trouble booking tickets?', 'a' => 'Our support team is available Mon–Fri, 9am–4pm. Call or email us and we will help you complete your booking.'],
            ]],
        ],
    ],

    'experience' => [
        'label' => 'Experience Page',
        'category' => 'core',
        'chips' => ['Hero', 'Explore Jos', 'Calm & Adventure', 'Member', 'Trending'],
        'preview' => '/experience',
        'fields' => [
            // Hero
            ['key' => 'experience.hero_image', 'label' => 'Hero image', 'type' => 'image', 'default' => 'images/experience.jpg'],

            // Beyond Your Stay, Explore Jos City
            ['key' => 'experience.jos_image', 'label' => '“Explore Jos” image', 'type' => 'image', 'default' => 'images/ilovejoscity.jpg'],
            ['key' => 'experience.jos_title', 'label' => '“Explore Jos” heading', 'type' => 'text', 'default' => 'Beyond Your Stay, Explore Jos City'],
            ['key' => 'experience.jos_text', 'label' => '“Explore Jos” paragraph', 'type' => 'textarea', 'default' => 'Discover the beauty and calm that make Jos truly unforgettable. From breathtaking landscapes and cultural landmarks to serene outdoor escapes, every moment outside the hotel becomes part of your experience.'],

            // Experience Calm & Adventure
            ['key' => 'experience.calm_title', 'label' => '“Calm & Adventure” heading', 'type' => 'text', 'default' => 'Experience Calm & Adventure'],
            ['key' => 'experience.calm_text', 'label' => '“Calm & Adventure” paragraph', 'type' => 'textarea', 'default' => 'Reconnect with nature, calm your mind, and enjoy experiences designed for relaxation, adventure, and well-being across Jos.'],
            ['key' => 'experience.calm_image_1', 'label' => '“Calm & Adventure” image (left)', 'type' => 'image', 'default' => 'images/2.jpg'],
            ['key' => 'experience.calm_image_2', 'label' => '“Calm & Adventure” image (right)', 'type' => 'image', 'default' => 'images/1.jpg'],

            // Become a member
            ['key' => 'experience.member_image', 'label' => '“Member” image', 'type' => 'image', 'default' => 'images/image 474.jpg'],
            ['key' => 'experience.member_title', 'label' => '“Member” heading', 'type' => 'text', 'default' => 'Become a member of Retiro Del Rocio'],
            ['key' => 'experience.member_text', 'label' => '“Member” paragraph', 'type' => 'textarea', 'default' => 'Get exclusive discounts on services, experiences, and curated destinations across Jos and beyond. Enjoy member-only perks designed to help you explore more for less.'],
            ['key' => 'experience.member_cta_label', 'label' => '“Member” button text', 'type' => 'text', 'default' => 'Subscribe'],
            ['key' => 'experience.member_cta_url', 'label' => '“Member” button link', 'type' => 'text', 'default' => '/contact-us'],
            ['key' => 'experience.member_link_label', 'label' => '“Member” secondary link text', 'type' => 'text', 'default' => 'Contact Us'],
            ['key' => 'experience.member_link_url', 'label' => '“Member” secondary link', 'type' => 'text', 'default' => '/contact-us'],

            // Trending Destination
            ['key' => 'experience.trending_title', 'label' => '“Trending” heading', 'type' => 'text', 'default' => 'Trending Destination'],
            ['key' => 'experience.trending_text', 'label' => '“Trending” paragraph', 'type' => 'textarea', 'default' => 'Discover the most visited and talked-about spots in Jos, from scenic nature escapes to cultural landmarks and relaxing outdoor experiences.'],

            ['key' => 'experience.dest_1_image', 'label' => 'Destination 1 image', 'type' => 'image', 'default' => 'images/Joswild.jpg'],
            ['key' => 'experience.dest_1_title', 'label' => 'Destination 1 title', 'type' => 'text', 'default' => 'Jos Wildlife Park'],
            ['key' => 'experience.dest_1_text', 'label' => 'Destination 1 text', 'type' => 'textarea', 'default' => 'A major conservation area featuring a wide variety of animals, ideal for picnics and safari views.'],

            ['key' => 'experience.dest_2_image', 'label' => 'Destination 2 image', 'type' => 'image', 'default' => 'images/kurra.jpg'],
            ['key' => 'experience.dest_2_title', 'label' => 'Destination 2 title', 'type' => 'text', 'default' => 'Kurra Falls'],
            ['key' => 'experience.dest_2_text', 'label' => 'Destination 2 text', 'type' => 'textarea', 'default' => 'A beautiful area known for its waterfalls, lakes, and electric power generation, often used for leisure.'],

            ['key' => 'experience.dest_3_image', 'label' => 'Destination 3 image', 'type' => 'image', 'default' => 'images/sheres.jpg'],
            ['key' => 'experience.dest_3_title', 'label' => 'Destination 3 title', 'type' => 'text', 'default' => 'Shere Hills'],
            ['key' => 'experience.dest_3_text', 'label' => 'Destination 3 text', 'type' => 'textarea', 'default' => 'A range of high, rocky hills popular for hiking, climbing, and offering panoramic views of the city.'],

            ['key' => 'experience.dest_4_image', 'label' => 'Destination 4 image', 'type' => 'image', 'default' => 'images/ten.jpg'],
            ['key' => 'experience.dest_4_title', 'label' => 'Destination 4 title', 'type' => 'text', 'default' => 'Ten Commandments Monument'],
            ['key' => 'experience.dest_4_text', 'label' => 'Destination 4 text', 'type' => 'textarea', 'default' => 'A significant, often visited landmark located in Dwei Du, Rayfield.'],

            ['key' => 'experience.dest_5_image', 'label' => 'Destination 5 image', 'type' => 'image', 'default' => 'images/jsmuseum.jpg'],
            ['key' => 'experience.dest_5_title', 'label' => 'Destination 5 title', 'type' => 'text', 'default' => 'Jos Museum Complex'],
            ['key' => 'experience.dest_5_text', 'label' => 'Destination 5 text', 'type' => 'textarea', 'default' => 'The Museum sits at the foot of a tree-covered granite mountain named Coronation Hill. Pre-historic Nigeria is deeply rooted in Jos, as seen in its Nok Art.'],

            ['key' => 'experience.dest_6_image', 'label' => 'Destination 6 image', 'type' => 'image', 'default' => 'images/solomon.jpg'],
            ['key' => 'experience.dest_6_title', 'label' => 'Destination 6 title', 'type' => 'text', 'default' => 'Solomon Lar Amusement Park'],
            ['key' => 'experience.dest_6_text', 'label' => 'Destination 6 text', 'type' => 'textarea', 'default' => 'A centrally located park for leisure and recreation, offering a relaxed outdoor escape in the heart of the city.'],
        ],
    ],
    'privacy' => [
        'label' => 'Privacy Policy',
        'category' => 'system',
        'chips' => ['Header', 'Sections'],
        'preview' => '/privacy-policy',
        'fields' => [
            ['key' => 'privacy.title', 'label' => 'Page title', 'type' => 'text', 'default' => 'Privacy Policy'],
            ['key' => 'privacy.updated', 'label' => 'Last updated line', 'type' => 'text', 'default' => 'Last Updated: 12th April, 2026.'],
            ['key' => 'privacy.intro', 'label' => 'Intro paragraph', 'type' => 'textarea', 'default' => 'At Retiro Del Rocio (“we”, “our”, or “us”), your privacy matters to us. This Privacy Policy explains how we collect, use, share, and protect your information when you book with us or use our website and services. By using Retiro Del Rocio, you agree to the terms outlined in this policy.'],
            ['key' => 'privacy.sections', 'label' => 'Policy sections', 'type' => 'repeater', 'item' => ['title' => 'Section heading', 'text' => 'Section content'], 'default' => [
                ['title' => 'Information We Collect', 'text' => "We collect information you give us and information gathered automatically when you use our website and services.\n\nPersonal Information\n• Full name\n• Email address and phone number\n• Billing and payment details (processed securely by our payment partner)\n• Booking details such as stay dates, room or apartment, number of guests, and special requests\n\nBooking & Transaction Information\n• Reservation, spa, dining, and vehicle pickup requests\n• Payment and receipt history\n\nDevice & Usage Information\n• Device type, browser, and operating system\n• IP address and approximate location\n• Pages visited and how you interact with our site"],
                ['title' => 'How We Use Your Information', 'text' => "We use your information to:\n• Process and manage your reservations and payments\n• Confirm bookings and send updates, receipts, and reminders\n• Provide guest support and respond to your enquiries\n• Personalise and improve your experience on our website\n• Detect, prevent, and address fraud or security issues\n• Comply with legal, tax, and regulatory obligations"],
                ['title' => 'How We Share Your Information', 'text' => "We do not sell your personal information. We share it only with:\n• Trusted service providers who help us operate, such as our payment processor (Paystack), email, and hosting providers\n• Professional advisers and authorities where required by law\n• Partners you ask us to engage, such as a vehicle pickup service you book\n\nEvery partner is required to protect your data and use it only for the agreed purpose."],
                ['title' => 'Payments & Security', 'text' => "Payments are processed through secure, encrypted channels by our payment partner, and we never store your full card details on our servers. We apply administrative, technical, and physical safeguards to protect your information. However, no method of transmission over the internet is completely secure, and we cannot guarantee absolute security."],
                ['title' => 'Cookies & Tracking Technologies', 'text' => "We use cookies and similar technologies to keep the site working, remember your preferences, and understand how it is used. You can control or disable cookies through your browser settings, though some features of the site may not work as intended without them."],
                ['title' => 'Data Retention', 'text' => "We keep your information only for as long as necessary to provide our services, meet legal and accounting requirements, and resolve any disputes. When it is no longer needed, we securely delete or anonymise it."],
                ['title' => 'Your Rights & Choices', 'text' => "Depending on your location, you may have the right to access, correct, update, or delete your personal information, object to certain processing, or withdraw your consent. To exercise any of these rights, please contact us using the details below and we will respond in line with applicable law."],
                ["title" => "Children's Privacy", 'text' => "Our services are intended for guests aged 18 and above. We do not knowingly collect personal information from children. If you believe a child has provided us with information, please contact us so we can remove it."],
                ['title' => 'Changes to This Policy', 'text' => "We may update this Privacy Policy from time to time. When we do, we will revise the “Last Updated” date above and, where appropriate, notify you. Your continued use of our services means you accept the updated policy."],
                ['title' => 'Contact Us', 'text' => "If you have any questions about this Privacy Policy or how we handle your information, please contact us:\n\nRetiro Del Rocio\nNo. 1, Off Liberty Boulevard, Millionaire Quarters, Jos, Plateau State\nEmail: hello@retirodelrocio.com\nPhone: (+234) 7012623680"],
            ]],
        ],
    ],
    'terms' => [
        'label' => 'Terms of Service',
        'category' => 'system',
        'chips' => ['Header', 'Sections'],
        'preview' => '/terms-of-service',
        'fields' => [
            ['key' => 'terms.title', 'label' => 'Page title', 'type' => 'text', 'default' => 'Terms of Service'],
            ['key' => 'terms.updated', 'label' => 'Last updated line', 'type' => 'text', 'default' => 'Last Updated: 12th April, 2026.'],
            ['key' => 'terms.intro', 'label' => 'Intro paragraph', 'type' => 'textarea', 'default' => 'These Terms of Service (“Terms”) govern your access to and use of the Retiro Del Rocio website, bookings, and services. Please read them carefully before making a reservation. By using our services, you agree to these Terms.'],
            ['key' => 'terms.sections', 'label' => 'Sections', 'type' => 'repeater', 'item' => ['title' => 'Section heading', 'text' => 'Section content'], 'default' => [
                ['title' => 'Acceptance of Terms', 'text' => 'By accessing or using the Retiro Del Rocio website and services, or by making a reservation, you agree to be bound by these Terms. If you do not agree, please do not use our services.'],
                ['title' => 'Bookings & Reservations', 'text' => 'A reservation is confirmed once payment is received and you receive a confirmation email with your booking reference. You are responsible for providing accurate guest details. We reserve the right to decline or cancel a booking where information is inaccurate or where we suspect fraud.'],
                ['title' => 'Pricing & Payment', 'text' => 'All prices are quoted in Nigerian Naira (₦) and include applicable taxes and fees unless stated otherwise. Payment is processed securely through our payment partner at the time of booking. We may update prices at any time, but confirmed bookings are honoured at the price paid.'],
                ['title' => 'Check-in & Check-out', 'text' => 'Standard check-in is from 2:00 PM and check-out is by 12:00 noon. Early check-in or late check-out may be available on request and could attract additional charges. A valid means of identification is required at check-in.'],
                ['title' => 'Guest Conduct & House Rules', 'text' => 'Guests are expected to treat the property, staff, and other guests with respect. Smoking is permitted only in designated areas. Damage to the property, disruptive behaviour, or breach of house rules may result in additional charges or termination of your stay without refund.'],
                ['title' => 'Cancellations & Refunds', 'text' => 'Cancellations, modifications, and refunds are governed by our Booking Policy. Please review it before completing your reservation.'],
                ['title' => 'Use of the Website', 'text' => 'You agree to use our website lawfully and not to interfere with its operation, attempt unauthorised access, or use it to transmit harmful or unlawful content.'],
                ['title' => 'Limitation of Liability', 'text' => 'To the extent permitted by law, Retiro Del Rocio is not liable for indirect or consequential losses, or for loss of personal belongings. Our total liability in connection with your stay is limited to the amount paid for your booking.'],
                ['title' => 'Intellectual Property', 'text' => 'All content on this website — including text, images, logos, and designs — belongs to Retiro Del Rocio or its licensors and may not be copied or reused without our permission.'],
                ['title' => 'Changes to These Terms', 'text' => 'We may update these Terms from time to time. The latest version will always be available on this page, and your continued use of our services constitutes acceptance of the changes.'],
                ['title' => 'Contact Us', 'text' => "If you have any questions about these Terms, please contact us:\n\nRetiro Del Rocio\nNo. 1, Off Liberty Boulevard, Millionaire Quarters, Jos, Plateau State\nEmail: hello@retirodelrocio.com\nPhone: (+234) 7012623680"],
            ]],
        ],
    ],

    'booking' => [
        'label' => 'Booking Policy',
        'category' => 'system',
        'chips' => ['Header', 'Sections'],
        'preview' => '/booking-policy',
        'fields' => [
            ['key' => 'booking.title', 'label' => 'Page title', 'type' => 'text', 'default' => 'Booking Policy'],
            ['key' => 'booking.updated', 'label' => 'Last updated line', 'type' => 'text', 'default' => 'Last Updated: 12th April, 2026.'],
            ['key' => 'booking.intro', 'label' => 'Intro paragraph', 'type' => 'textarea', 'default' => 'This Booking Policy explains how reservations, payments, check-in and check-out, cancellations, and refunds work at Retiro Del Rocio. It forms part of our Terms of Service and applies to every booking made with us.'],
            ['key' => 'booking.sections', 'label' => 'Sections', 'type' => 'repeater', 'item' => ['title' => 'Section heading', 'text' => 'Section content'], 'default' => [
                ['title' => 'Making a Reservation', 'text' => 'Reservations can be made through our website. Your booking is confirmed only after successful payment and receipt of a confirmation email containing your booking reference.'],
                ['title' => 'Payment', 'text' => 'Full payment is required at the time of booking and is processed securely via our payment partner. We accept card, bank, and transfer payments, and we never store your full card details.'],
                ['title' => 'Check-in & Check-out', 'text' => 'Check-in is from 2:00 PM and check-out is by 12:00 noon. Please present a valid ID and your booking reference at check-in. Early check-in and late check-out are subject to availability and may incur a fee.'],
                ['title' => 'Cancellations & Modifications', 'text' => 'To cancel or modify a booking, contact us as early as possible with your booking reference. Modifications are subject to availability and any difference in rate. The refund that applies depends on how far in advance you cancel (see Refunds below).'],
                ['title' => 'Refunds', 'text' => "Refunds on cancellation are as follows:\n• 7 days or more before check-in: full refund, less any payment-processing fee\n• 3–6 days before check-in: 50% refund\n• Within 48 hours of check-in: non-refundable\n\nApproved refunds are processed to your original payment method within 5–10 business days."],
                ['title' => 'No-Shows & Late Arrivals', 'text' => 'If you do not arrive on your check-in date and have not notified us, the booking is treated as a no-show and is non-refundable. Please contact us in advance if you expect to arrive late.'],
                ['title' => 'Spa, Dining & Vehicle Pickup', 'text' => 'Add-on services such as spa sessions and vehicle pickups are confirmed and paid for at the time of booking. Spa sessions can be rescheduled up to 24 hours before the appointment, subject to availability. Please provide accurate arrival details for vehicle pickups.'],
                ['title' => 'Group & Extended Stays', 'text' => 'Special terms may apply to group bookings and extended stays, including deposit and cancellation requirements. Please contact us directly to arrange these.'],
                ['title' => 'Contact Us', 'text' => "For help with a booking, please contact us:\n\nRetiro Del Rocio\nNo. 1, Off Liberty Boulevard, Millionaire Quarters, Jos, Plateau State\nEmail: hello@retirodelrocio.com\nPhone: (+234) 7012623680"],
            ]],
        ],
    ],
    'gym' => [
        'label' => 'Gym & Fitness',
        'category' => 'core',
        'chips' => ['Hero', 'Elevate', 'Plans', 'What We Offer'],
        'preview' => '/gym',
        'fields' => [
            // Hero
            ['key' => 'gym.hero_image', 'label' => 'Hero image', 'type' => 'image', 'default' => 'images/image 131.jpg'],
            ['key' => 'gym.hero_title', 'label' => 'Hero heading', 'type' => 'text', 'default' => 'Wellness that fits your lifestyle'],
            ['key' => 'gym.hero_text', 'label' => 'Hero subtext', 'type' => 'textarea', 'default' => 'Achieve balance, strength, and vitality with personalized wellness experiences designed around your goals. From fitness programs to holistic recovery, we help you become your best self.'],
            ['key' => 'gym.subscribe_label', 'label' => 'Subscribe button text', 'type' => 'text', 'default' => 'Subscribe'],
            ['key' => 'gym.renew_label', 'label' => 'Renew button text', 'type' => 'text', 'default' => 'Renew Subscription'],

            // Elevate band
            ['key' => 'gym.elevate_title', 'label' => '“Elevate” heading', 'type' => 'text', 'default' => 'Elevate Your Mind, Body & Spirit'],
            ['key' => 'gym.elevate_text', 'label' => '“Elevate” paragraph', 'type' => 'textarea', 'default' => 'Whether you’re beginning your fitness journey or refining an established routine, our expert-led programs, modern facilities, and supportive environment provide everything you need to succeed.'],
            ['key' => 'gym.banner_image', 'label' => 'Wide banner image', 'type' => 'image', 'default' => 'images/IMG_2649 1.jpg'],

            // Plans heading (plans themselves are managed in Admin → Gym → Plans)
            ['key' => 'gym.plans_subtitle', 'label' => 'Plans — small heading', 'type' => 'text', 'default' => 'Our Fitness Plans'],
            ['key' => 'gym.plans_title', 'label' => 'Plans — heading', 'type' => 'text', 'default' => 'Exclusive plans just for you'],

            // What we offer
            ['key' => 'gym.offer_image', 'label' => '“What we offer” image', 'type' => 'image', 'default' => 'images/image 161.jpg'],
            ['key' => 'gym.offer_subtitle', 'label' => '“What we offer” small heading', 'type' => 'text', 'default' => 'What we offer'],
            ['key' => 'gym.offer_title', 'label' => '“What we offer” heading', 'type' => 'text', 'default' => 'Programs Designed for Every Fitness Level'],
            ['key' => 'gym.offer_1_title', 'label' => 'Offer 1 title', 'type' => 'text', 'default' => 'Yoga & Flexibility'],
            ['key' => 'gym.offer_1_text', 'label' => 'Offer 1 text', 'type' => 'textarea', 'default' => 'Improve mobility, balance, and calm with guided yoga and stretching sessions.'],
            ['key' => 'gym.offer_2_title', 'label' => 'Offer 2 title', 'type' => 'text', 'default' => 'Personal Trainer'],
            ['key' => 'gym.offer_2_text', 'label' => 'Offer 2 text', 'type' => 'textarea', 'default' => 'Train one-on-one with certified coaches who tailor every session to your goals.'],
            ['key' => 'gym.offer_3_title', 'label' => 'Offer 3 title', 'type' => 'text', 'default' => 'Weight Loss'],
            ['key' => 'gym.offer_3_text', 'label' => 'Offer 3 text', 'type' => 'textarea', 'default' => 'Structured programs and accountability to help you burn fat and build lasting habits.'],
            ['key' => 'gym.offer_4_title', 'label' => 'Offer 4 title', 'type' => 'text', 'default' => 'Cardio Training'],
            ['key' => 'gym.offer_4_text', 'label' => 'Offer 4 text', 'type' => 'textarea', 'default' => 'Boost stamina and heart health with high-energy cardio workouts for every level.'],
        ],
    ],

    'restaurant' => [
        'label' => 'Restaurant',
        'category' => 'core',
        'chips' => ['Hero', 'Opening Hours', 'Signature Dishes', 'Culinary', 'Reservation'],
        'preview' => '/restaurant',
        'fields' => [
            // Hero
            ['key' => 'restaurant.hero_image', 'label' => 'Hero image', 'type' => 'image', 'default' => 'images/image 383.jpg'],
            ['key' => 'restaurant.hero_title', 'label' => 'Hero heading', 'type' => 'text', 'default' => 'Experience Fine Dining'],
            ['key' => 'restaurant.hero_text', 'label' => 'Hero subtext', 'type' => 'textarea', 'default' => 'Discover a dining experience where exceptional cuisine, elegant ambiance, and attentive service come together. From breakfast to late-night dining, every meal is crafted to delight your senses.'],
            ['key' => 'restaurant.reserve_label', 'label' => 'Reserve button text', 'type' => 'text', 'default' => 'Reserve Table'],

            // Opening hours
            ['key' => 'restaurant.hours_title', 'label' => 'Opening hours heading', 'type' => 'text', 'default' => 'Opening Hours'],
            ['key' => 'restaurant.hours_text', 'label' => 'Opening hours subtext', 'type' => 'textarea', 'default' => 'Whether you’re starting your day with breakfast, enjoying a leisurely lunch, or indulging in an elegant dinner, we’re ready to serve you.'],
            ['key' => 'restaurant.breakfast_hours', 'label' => 'Breakfast hours', 'type' => 'text', 'default' => '7:00AM - 10:30AM'],
            ['key' => 'restaurant.lunch_hours', 'label' => 'Lunch hours', 'type' => 'text', 'default' => '12:30PM - 4:00PM'],
            ['key' => 'restaurant.dinner_hours', 'label' => 'Dinner hours', 'type' => 'text', 'default' => '6:00PM - 10:30PM'],

            // Signature dishes
            ['key' => 'restaurant.dishes_title', 'label' => 'Signature dishes heading', 'type' => 'text', 'default' => 'Explore our Signature Dishes'],
            ['key' => 'restaurant.dishes_text', 'label' => 'Signature dishes subtext', 'type' => 'textarea', 'default' => 'Prepared with the finest ingredients and inspired by both local and international flavours, our signature dishes offer an unforgettable culinary journey.'],
            ['key' => 'restaurant.dish_1_image', 'label' => 'Dish 1 image', 'type' => 'image', 'default' => 'images/image 40.jpg'],
            ['key' => 'restaurant.dish_1_title', 'label' => 'Dish 1 title', 'type' => 'text', 'default' => 'Herb Rusted Curry'],
            ['key' => 'restaurant.dish_1_text', 'label' => 'Dish 1 text', 'type' => 'textarea', 'default' => 'Slow-cooked curry with aromatic herbs and spices, served with steamed rice.'],
            ['key' => 'restaurant.dish_2_image', 'label' => 'Dish 2 image', 'type' => 'image', 'default' => 'images/image 403.jpg'],
            ['key' => 'restaurant.dish_2_title', 'label' => 'Dish 2 title', 'type' => 'text', 'default' => 'Grilled Seafood Platter'],
            ['key' => 'restaurant.dish_2_text', 'label' => 'Dish 2 text', 'type' => 'textarea', 'default' => 'A selection of the freshest seafood, char-grilled to perfection with citrus butter.'],
            ['key' => 'restaurant.dish_3_image', 'label' => 'Dish 3 image', 'type' => 'image', 'default' => 'images/image 4044.jpg'],
            ['key' => 'restaurant.dish_3_title', 'label' => 'Dish 3 title', 'type' => 'text', 'default' => 'Signature Beef Steak'],
            ['key' => 'restaurant.dish_3_text', 'label' => 'Dish 3 text', 'type' => 'textarea', 'default' => 'Tender prime cut grilled to your liking, paired with seasonal vegetables.'],

            // Culinary excellence
            ['key' => 'restaurant.culinary_title', 'label' => 'Culinary heading', 'type' => 'text', 'default' => 'Explore our Culinary Excellence'],
            ['key' => 'restaurant.culinary_text', 'label' => 'Culinary text', 'type' => 'textarea', 'default' => 'We create experiences that go beyond great food. Enjoy a warm atmosphere, exceptional hospitality, and moments worth sharing with family, friends, and colleagues.'],
            ['key' => 'restaurant.culinary_image', 'label' => 'Culinary image', 'type' => 'image', 'default' => 'images/image 411.jpg'],

            // More than a dining destination
            ['key' => 'restaurant.dining_title', 'label' => '“More than dining” heading', 'type' => 'text', 'default' => 'More Than a Dining Destination'],
            ['key' => 'restaurant.dining_text', 'label' => '“More than dining” text', 'type' => 'textarea', 'default' => 'Experience a vibrant blend of flavors, atmosphere, and hospitality. Whether you’re joining us for a casual lunch, a romantic dinner, or celebratory drinks, every visit promises something memorable.'],
            ['key' => 'restaurant.dining_image', 'label' => '“More than dining” image', 'type' => 'image', 'default' => 'images/image 431.jpg'],

        ],
    ],

    'restaurantreservation' => [
        'label' => 'Restaurant Reservation',
        'category' => 'service',
        'chips' => ['Popup', 'Summary'],
        'preview' => '/restaurant',
        'fields' => [
            ['key' => 'restaurant.reservation_title', 'label' => 'Popup heading', 'type' => 'text', 'default' => 'Reservation'],
            ['key' => 'restaurant.reservation_text', 'label' => 'Popup intro text', 'type' => 'textarea', 'default' => 'Whether you’re joining us for a casual lunch, a romantic dinner, or celebratory drinks, every visit promises something memorable.'],
            ['key' => 'restaurant.summary_text', 'label' => 'Reservation Summary description', 'type' => 'textarea', 'default' => 'Please review your reservation details below before continuing to payment. A small refundable fee secures your table and is returned when you honour your reservation.'],
        ],
    ],

    'restaurantcheckout' => [
        'label' => 'Restaurant Checkout',
        'category' => 'service',
        'chips' => ['Checkout', 'Payment', 'Success'],
        'preview' => '/restaurant',
        'fields' => [
            // Checkout
            ['key' => 'restaurant.reservation_fee', 'label' => 'Refundable reservation fee (₦)', 'type' => 'text', 'default' => '10000'],
            ['key' => 'restaurant.cancellation_policy', 'label' => 'Cancellation policy', 'type' => 'textarea', 'default' => 'Your reservation fee is fully refundable when you honour your reservation or cancel at least 24 hours in advance. No-shows and late cancellations may forfeit the fee.'],

            // Success
            ['key' => 'restaurant.success_title', 'label' => 'Success heading', 'type' => 'text', 'default' => 'Reservation Successful!'],
            ['key' => 'restaurant.success_text', 'label' => 'Success message', 'type' => 'textarea', 'default' => 'Your reservation is confirmed.'],
        ],
    ],

    'cinema' => [
        'label' => 'Cinema',
        'category' => 'core',
        'chips' => ['Offer', 'Now Showing', 'Logos', 'Top 10', 'Weekend', 'Coming Soon'],
        'preview' => '/cinema',
        'fields' => [
            ['key' => 'cinema.offer_text', 'label' => 'Offer ticker text', 'type' => 'text', 'default' => 'Special Offer: Buy 2 tickets, get 1 FREE! Valid this weekend only.'],
            ['key' => 'cinema.offer_terms', 'label' => 'Offer ticker — small print', 'type' => 'text', 'default' => 'Terms & Conditions Apply.'],

            ['key' => 'cinema.nowshowing_title', 'label' => 'Now Showing heading', 'type' => 'text', 'default' => 'Now Showing With Special Offers'],
            ['key' => 'cinema.nowshowing_text', 'label' => 'Now Showing subtext', 'type' => 'textarea', 'default' => 'Catch the latest blockbusters on the big screen with exclusive weekday and weekend offers made just for you.'],

            ['key' => 'cinema.top10_title', 'label' => 'Top 10 heading', 'type' => 'text', 'default' => 'Top 10 Movies This Week'],
            ['key' => 'cinema.top10_text', 'label' => 'Top 10 subtext', 'type' => 'text', 'default' => 'Top movies showing this week in our cinema'],

            ['key' => 'cinema.weekend_title', 'label' => 'Weekend band heading', 'type' => 'text', 'default' => 'Book Tickets To Your Favourite Movie This Weekend'],
            ['key' => 'cinema.weekend_text', 'label' => 'Weekend band text', 'type' => 'textarea', 'default' => 'Reserve your seats in advance and enjoy a premium cinema experience with the people you love.'],
            ['key' => 'cinema.weekend_button', 'label' => 'Weekend band — button text', 'type' => 'text', 'default' => 'Book Ticket'],
            ['key' => 'cinema.weekend_url', 'label' => 'Weekend band — button link (URL or movie page)', 'type' => 'text', 'default' => '/cinema/movies'],
            ['key' => 'cinema.weekend_image', 'label' => 'Weekend band image', 'type' => 'image', 'default' => 'images/Background Image.png'],

            ['key' => 'cinema.comingsoon_title', 'label' => 'Coming Soon heading', 'type' => 'text', 'default' => 'Coming Soon'],
            ['key' => 'cinema.comingsoon_text', 'label' => 'Coming Soon subtext', 'type' => 'text', 'default' => 'Get ready for the movies arriving soon at our cinema'],

            ['key' => 'cinema.platforms', 'label' => 'Platform & rating logos (scrolling marquee)', 'type' => 'repeater', 'item' => ['name' => 'Name', 'image' => 'Logo image'], 'image_cols' => ['image'], 'default' => [
                ['name' => 'Netflix', 'image' => 'images/Netflix svg.png'],
                ['name' => 'Disney+', 'image' => 'images/Disney svg.png'],
                ['name' => 'Prime Video', 'image' => 'images/Peime video.png'],
                ['name' => 'IMDb', 'image' => 'images/IMDb svg.png'],
                ['name' => 'Rotten Tomatoes', 'image' => 'images/Rotten Tomatoes svg.png'],
            ]],
        ],
    ],

    'cinemamovie' => [
        'label' => 'Cinema Movie Details',
        'category' => 'service',
        'chips' => ['Detail', 'Tickets', 'Snacks'],
        'preview' => '/cinema',
        'fields' => [
            ['key' => 'cinemamovie.summary_label', 'label' => '“Summary” label', 'type' => 'text', 'default' => 'Summary'],
            ['key' => 'cinemamovie.trailer_label', 'label' => '“Watch Trailer” label', 'type' => 'text', 'default' => 'Watch Trailer'],
            ['key' => 'cinemamovie.date_label', 'label' => '“Date” label', 'type' => 'text', 'default' => 'Date'],
            ['key' => 'cinemamovie.time_label', 'label' => '“Time” label', 'type' => 'text', 'default' => 'Time'],
            ['key' => 'cinemamovie.seats_label', 'label' => '“Tickets & Seats” label', 'type' => 'text', 'default' => 'Choose Tickets & Seats'],
            ['key' => 'cinemamovie.snacks_label', 'label' => '“Select Snacks” label', 'type' => 'text', 'default' => 'Select Snacks'],
            ['key' => 'cinemamovie.order_label', 'label' => '“Order Details” label', 'type' => 'text', 'default' => 'Order Details'],
            ['key' => 'cinemamovie.book_label', 'label' => 'Book button text', 'type' => 'text', 'default' => 'Complete Booking'],
        ],
    ],

    'cinemacheckout' => [
        'label' => 'Cinema Checkout',
        'category' => 'service',
        'chips' => ['Checkout', 'Payment', 'Success'],
        'preview' => '/cinema',
        'fields' => [
            ['key' => 'cinemacheckout.cancellation_policy', 'label' => 'Cancellation policy', 'type' => 'textarea', 'default' => 'Tickets are refundable up to 2 hours before showtime. After that, tickets and snacks are non-refundable. A refund, when due, is processed within 24 hours.'],
            ['key' => 'cinemacheckout.pay_label', 'label' => 'Pay button text', 'type' => 'text', 'default' => 'Complete Booking'],
            ['key' => 'cinemacheckout.success_title', 'label' => 'Success heading', 'type' => 'text', 'default' => 'Payment Successful!'],
            ['key' => 'cinemacheckout.success_text', 'label' => 'Success message', 'type' => 'textarea', 'default' => 'Your ticket has been successfully purchased.'],
        ],
    ],
];

// Derive a dot-safe binding name for each field + a flat defaults map.
$defaults = [];
foreach ($pages as $pageKey => &$page) {
    foreach ($page['fields'] as &$field) {
        $field['name'] = str_replace('.', '__', $field['key']);
        $defaults[$field['key']] = $field['default'] ?? null;
    }
    unset($field);
}
unset($page);

return [
    'pages' => $pages,
    'defaults' => $defaults,
];
