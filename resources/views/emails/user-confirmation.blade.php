<x-mail::message>
# Спасибо за обращение, {{ $contact['name'] }}!

Мы получили вашу заявку и скоро свяжемся с вами по телефону {{ $contact['phone'] }} или email.

**Ваш комментарий:**

{{ $contact['comment'] }}

@if ($ai['used'] && $ai['suggested_reply'])
---

{{ $ai['suggested_reply'] }}
@endif

Это автоматическое подтверждение, отвечать на него не нужно.

С уважением,<br>
{{ config('app.name') }}
</x-mail::message>
