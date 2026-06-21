<x-mail::message>
# Новая заявка с формы обратной связи

**Имя:** {{ $contact['name'] }}
**Телефон:** {{ $contact['phone'] }}
**Email:** {{ $contact['email'] }}

**Комментарий:**

{{ $contact['comment'] }}

@if ($ai['used'])
---

### AI-анализ (Claude)

- **Тональность:** {{ $ai['sentiment'] }}
- **Категория обращения:** {{ $ai['category'] }}
@if ($ai['suggested_reply'])

**Черновик ответа:**

{{ $ai['suggested_reply'] }}
@endif
@else
---

_AI-анализ недоступен ({{ $ai['error'] ?? 'причина неизвестна' }}), заявка обработана без него._
@endif

<x-mail::button :url="'mailto:'.$contact['email']">
Ответить клиенту
</x-mail::button>

Письмо сформировано автоматически сервисом {{ config('app.name') }}.
</x-mail::message>
