<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');
        if ($contact instanceof Contact) {
            Gate::authorize('update', $contact);
        }

        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['country', 'tax_code', 'sdi_code', 'vat_number'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => mb_strtoupper(trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['customer', 'supplier', 'both'])],
            'entity_type' => ['required', Rule::in(['company', 'person'])],
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'tax_code' => ['nullable', 'string', 'max:32'],
            'pec' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'sdi_code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{7}$/'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Inserisci il nome o la ragione sociale.',
            'type.in' => 'Scegli cliente, fornitore o entrambi.',
            'entity_type.in' => 'Scegli azienda o persona fisica.',
            '*.required' => 'Completa il campo :attribute.',
            '*.string' => 'Il campo :attribute deve contenere testo.',
            '*.max' => 'Il campo :attribute può contenere al massimo :max caratteri.',
            '*.email' => 'Inserisci un indirizzo email valido per :attribute.',
            'country.regex' => 'Usa un codice paese di due lettere, ad esempio IT.',
            'sdi_code.regex' => 'Il codice SDI deve contenere 7 lettere o numeri.',
        ];
    }
}
