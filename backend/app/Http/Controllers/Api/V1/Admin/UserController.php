<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\QrCode;
use App\Models\Area;
use App\Enums\RoleEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('areas')
            ->when($request->role, fn($q, $v) => $q->where('role', $v))
            ->when($request->search, fn($q, $v) => $q->where(fn($q2) =>
                $q2->where('name', 'like', "%{$v}%")
                    ->orWhere('email', 'like', "%{$v}%")
                    ->orWhere('employee_id', 'like', "%{$v}%")
            ))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'nullable|email|unique:users,email',
            'employee_id' => 'nullable|string|max:30|unique:users,employee_id',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:' . implode(',', array_column(RoleEnum::cases(), 'value')),
            'password' => 'required|string|min:8',
            'area_ids' => 'sometimes|array',
            'area_ids.*' => 'exists:areas,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        if ($request->has('area_ids')) {
            $user->areas()->sync($validated['area_ids']);
        }

        return response()->json([
            'message' => 'User berhasil ditambahkan',
            'data' => $user->load('areas'),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:users,username,' . $user->id,
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'employee_id' => 'nullable|string|max:30|unique:users,employee_id,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:' . implode(',', array_column(RoleEnum::cases(), 'value')),
            'is_active' => 'sometimes|boolean',
            'password' => 'sometimes|string|min:8',
            'area_ids' => 'sometimes|array',
            'area_ids.*' => 'exists:areas,id',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        if ($request->has('area_ids')) {
            $user->areas()->sync($validated['area_ids']);
        }

        return response()->json([
            'message' => 'User berhasil diperbarui',
            'data' => $user->load('areas'),
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot delete yourself'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Reset device_id for a user so they can login on a new device.
     */
    public function resetDevice(User $user)
    {
        $user->device_id = null;
        $user->save();

        return response()->json([
            'message' => "Device untuk user {$user->name} berhasil di-reset. User sekarang dapat login di perangkat baru."
        ]);
    }
}
