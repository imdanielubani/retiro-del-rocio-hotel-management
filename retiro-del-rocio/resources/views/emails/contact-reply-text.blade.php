Hi {{ $contact->first_name }},

{{ $reply }}

--
Your original message:
{{ $contact->message ?: 'No message provided.' }}

--
Retiro Del Rocio · Jos, Plateau State · {{ cms('contact.phone') }}
