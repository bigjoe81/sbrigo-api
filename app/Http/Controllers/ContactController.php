<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveContactRequest;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['customer', 'supplier', 'both'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = $request->user()->contacts();
        $type = $request->input('type');
        if ($type) {
            $query->whereIn('type', $type === 'both' ? ['both'] : [$type, 'both']);
        }
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(email) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(vat_number) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
            });
        }

        return response()->json($query->orderBy('name')->orderBy('id')->paginate(20));
    }

    public function store(SaveContactRequest $request): JsonResponse
    {
        $contact = $request->user()->contacts()->create($request->validated());

        return response()->json(['data' => $contact], 201);
    }

    public function show(Contact $contact): JsonResponse
    {
        Gate::authorize('view', $contact);

        return response()->json(['data' => $contact]);
    }

    public function update(SaveContactRequest $request, Contact $contact): JsonResponse
    {
        $contact->update($request->validated());

        return response()->json(['data' => $contact->fresh()]);
    }

    public function destroy(Contact $contact): Response
    {
        Gate::authorize('delete', $contact);
        $contact->delete();

        return response()->noContent();
    }
}
