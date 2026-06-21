<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-\(\)]{7,20}$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'comment' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'name.min' => 'Имя слишком короткое.',
            'phone.required' => 'Укажите телефон.',
            'phone.regex' => 'Некорректный формат телефона.',
            'email.required' => 'Укажите email.',
            'email.email' => 'Некорректный email.',
            'comment.required' => 'Добавьте комментарий к заявке.',
            'comment.min' => 'Комментарий слишком короткий.',
            'comment.max' => 'Комментарий слишком длинный (макс. 2000 символов).',
        ];
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(new JsonResponse([
            'success' => false,
            'message' => 'Ошибка валидации данных.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
