<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhoneBookResource;
use App\Models\PhoneBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The phone book (2026-11-24, the legacy Phone Books screen): its own list
 * of contacts, not the users table (see the create_phone_book_table
 * migration).
 *
 *  - Admin console (routes/admin.php): list, create, edit, delete.
 *  - App (routes/api.php): list and show, read-only, for users with the
 *    "Phone book shown" permission.
 *
 * A contact is a name, a phone number (exactly 10 digits, unique in the
 * phone book), a role and an active status. Nothing here changes a user.
 */
class PhoneBookController extends Controller
{
    private const SORTS = ['name', 'phone_no', 'is_active', 'id'];

    private const MESSAGES = [
        'phone_no.regex' => 'Phone number must be exactly 10 digits.',
        'phone_no.unique' => 'This phone number is already in the phone book.',
        'role_id.exists' => 'Pick a role from the list.',
    ];

    /**
     * GET — filters: search (name or phone), name, phone_no, role_id,
     * is_active; sort (a column, "-" for descending); per_page (max 200),
     * or all=1 for every contact on one page.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PhoneBook::with(['role', 'user:user_id,profile_image']);

            if (filled($search = trim((string) $request->input('search')))) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                        ->orWhere('phone_no', 'LIKE', "%{$search}%");
                });
            }
            if (filled($name = trim((string) $request->input('name')))) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($name) . '%']);
            }
            if (filled($phone = trim((string) $request->input('phone_no')))) {
                $query->where('phone_no', 'LIKE', "%{$phone}%");
            }
            if ($request->filled('role_id')) {
                $query->where('role_id', $request->integer('role_id'));
            }
            if ($request->filled('is_active')) {
                $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
            }

            $sort = (string) $request->input('sort', 'name');
            $column = ltrim($sort, '-');
            $query->orderBy(in_array($column, self::SORTS, true) ? $column : 'name', str_starts_with($sort, '-') ? 'desc' : 'asc')
                ->orderBy('id');

            $perPage = $request->boolean('all')
                ? max(1, (clone $query)->count())
                : min(200, max(1, $request->integer('per_page', 50)));

            $contacts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Phone book retrieved successfully.',
                'data' => [
                    'current_page' => $contacts->currentPage(),
                    'data' => PhoneBookResource::collection($contacts->items()),
                    'total' => $contacts->total(),
                    'per_page' => $contacts->perPage(),
                    'last_page' => $contacts->lastPage(),
                    'next_page_url' => $contacts->nextPageUrl(),
                    'prev_page_url' => $contacts->previousPageUrl(),
                ],
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to retrieve phone book.'], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $contact = PhoneBook::with(['role', 'user:user_id,profile_image'])->find($id);
        if (!$contact) {
            return response()->json(['success' => false, 'message' => 'Contact not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Contact retrieved.', 'data' => new PhoneBookResource($contact)], 200);
    }

    /** POST (admin console). */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules(), self::MESSAGES);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        try {
            $contact = PhoneBook::create($this->data($validator->validated()) + ['is_active' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Contact created successfully.',
                'data' => new PhoneBookResource($contact->load(['role', 'user:user_id,profile_image'])),
            ], 201);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to create contact.'], 500);
        }
    }

    /** PUT (admin console). */
    public function update(Request $request, $id): JsonResponse
    {
        $contact = PhoneBook::find($id);
        if (!$contact) {
            return response()->json(['success' => false, 'message' => 'Contact not found.'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($contact), self::MESSAGES);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        try {
            $contact->update($this->data($validator->validated()));

            return response()->json([
                'success' => true,
                'message' => 'Contact updated successfully.',
                'data' => new PhoneBookResource($contact->fresh(['role', 'user:user_id,profile_image'])),
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to update contact.'], 500);
        }
    }

    /** DELETE (admin console): removes the contact only, never a user. */
    public function destroy($id): JsonResponse
    {
        $contact = PhoneBook::find($id);
        if (!$contact) {
            return response()->json(['success' => false, 'message' => 'Contact not found.'], 404);
        }

        try {
            $contact->delete();

            return response()->json(['success' => true, 'message' => 'Contact deleted successfully.', 'data' => ['id' => (int) $id]], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to delete contact.'], 500);
        }
    }

    private function rules(?PhoneBook $contact = null): array
    {
        $required = $contact ? 'sometimes|required' : 'required';
        $ignore = $contact ? ",{$contact->id},id" : '';

        return [
            'name' => "{$required}|string|max:55",
            'phone_no' => "{$required}|string|regex:/^[0-9]{10}$/|unique:phone_book,phone_no{$ignore}",
            'role_id' => "{$required}|integer|exists:roles,id",
            'is_active' => 'sometimes|boolean',
        ];
    }

    private function data(array $validated): array
    {
        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim($validated['name']);
        }
        if (array_key_exists('is_active', $validated)) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        return $validated;
    }

    private function invalid($validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }
}
