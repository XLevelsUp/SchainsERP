<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesRecordAccess;
use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orders.
 *
 * Business rule (agreed 2026-11-12): heads and employees see and manage every
 * order; customers and retailers only orders where they are the customer.
 */
class OrderController extends Controller
{
    use ScopesRecordAccess;

    private const STATUSES = 'PENDING,PROCESSING,COMPLETED,CANCELLED';

    private function isStaff(Request $request): bool
    {
        $role = strtoupper((string) optional($request->user()->role)->role);

        return in_array($role, ['HEAD', 'EMPLOYEE'], true);
    }

    /** Non-staff may only touch orders where they are the customer. */
    private function ownsOrDenied(Request $request, $customerId): ?JsonResponse
    {
        if ($this->isStaff($request) || (int) $customerId === $this->actingUserId($request)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'You can only access your own orders.',
        ], 403);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:' . self::STATUSES,
            'customer_id' => 'nullable|integer',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = OrderDetail::query();

        if ($this->isStaff($request)) {
            if (!empty($validated['customer_id'])) {
                $query->where('customer_id', $validated['customer_id']);
            }
        } else {
            if (!empty($validated['customer_id']) && ($denied = $this->ownsOrDenied($request, $validated['customer_id']))) {
                return $denied;
            }
            $query->where('customer_id', $this->actingUserId($request));
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['from_date'])) {
            $query->whereDate('added_at', '>=', $validated['from_date']);
        }
        if (!empty($validated['to_date'])) {
            $query->whereDate('added_at', '<=', $validated['to_date']);
        }

        $orders = $query
            ->orderByDesc('added_at')
            ->orderByDesc('order_id')
            ->paginate($validated['per_page'] ?? 50);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // `remarks` / `due_date` used to be accepted here too, but
        // order_details has no such columns, so sending either was a 500.
        $validated = $request->validate([
            'customer_id' => 'required|exists:user_details,user_id',
            'item_id' => 'required|exists:items,item_id',
            'status' => 'required|in:' . self::STATUSES,
            'grams' => 'nullable|numeric',
        ]);

        if ($denied = $this->ownsOrDenied($request, $validated['customer_id'])) {
            return $denied;
        }

        $order = OrderDetail::create($validated);

        return response()->json([
            'success' => true,
            'data' => $order->fresh(),
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $order = OrderDetail::findOrFail($id);

        if ($denied = $this->ownsOrDenied($request, $order->customer_id)) {
            return $denied;
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $order = OrderDetail::findOrFail($id);

        if ($denied = $this->ownsOrDenied($request, $order->customer_id)) {
            return $denied;
        }

        $validated = $request->validate([
            'customer_id' => 'sometimes|exists:user_details,user_id',
            'item_id' => 'sometimes|exists:items,item_id',
            'status' => 'sometimes|in:' . self::STATUSES,
            'grams' => 'nullable|numeric',
        ]);

        if (array_key_exists('customer_id', $validated) && ($denied = $this->ownsOrDenied($request, $validated['customer_id']))) {
            return $denied;
        }

        $order->update($validated);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $order = OrderDetail::findOrFail($id);

        if ($denied = $this->ownsOrDenied($request, $order->customer_id)) {
            return $denied;
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully',
        ]);
    }
}
