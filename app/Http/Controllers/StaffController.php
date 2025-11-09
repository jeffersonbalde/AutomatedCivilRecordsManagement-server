<?php
// app/Http/Controllers/StaffController.php
namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user() instanceof \App\Models\Staff) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $staff = Staff::with('creator')->get()->map(function ($staff) {
            return $this->formatStaffResponse($staff);
        });
        
        return response()->json($staff);
    }

    public function store(Request $request)
    {
        if ($request->user() instanceof \App\Models\Staff) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:staff',
            'full_name' => 'required|string|max:100',
            'contact_number' => 'nullable|string|max:11|regex:/^09\d{9}$/',
            'address' => 'nullable|string|max:500',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ], [
            'contact_number.regex' => 'The contact number must be a valid Philippine mobile number (09XXXXXXXXX).',
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['created_by'] = $request->user()->id;

        // Handle avatar upload - STORE ONLY FILENAME
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            // Store: 1762566427_690ea11b86a5e.jpg (NO "avatar_" prefix, NO "avatars/" folder)
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('avatars', $filename, 'public');
            
            // Store ONLY the filename
            $validated['avatar'] = $filename;
        }

        $staff = Staff::create($validated);

        return response()->json([
            'message' => 'Staff created successfully',
            'staff' => $this->formatStaffResponse($staff->load('creator'))
        ], 201);
    }

    public function show(Request $request, $id)
    {
        if ($request->user() instanceof \App\Models\Staff) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $staff = Staff::with('creator')->findOrFail($id);
        return response()->json($this->formatStaffResponse($staff));
    }

// In StaffController.php - update method
public function update(Request $request, $id)
{
    if ($request->user() instanceof \App\Models\Staff) {
        return response()->json(['error' => 'Unauthenticated'], 403);
    }

    $staff = Staff::findOrFail($id);

    $validated = $request->validate([
        'email' => 'sometimes|email|unique:staff,email,' . $id,
        'full_name' => 'sometimes|string|max:100',
        'contact_number' => 'nullable|string|max:11|regex:/^09\d{9}$/',
        'address' => 'nullable|string|max:500',
        'is_active' => 'sometimes|boolean',
        'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        'remove_avatar' => 'nullable|string'
    ], [
        'contact_number.regex' => 'The contact number must be a valid Philippine mobile number (09XXXXXXXXX).',
    ]);

    Log::info('🔄 Staff update request received:', [
        'has_remove_avatar' => $request->has('remove_avatar'),
        'remove_avatar_value' => $request->remove_avatar,
        'has_avatar_file' => $request->hasFile('avatar'),
        'original_avatar' => $staff->avatar
    ]);

    // Handle avatar removal - only if explicitly requested
    if ($request->has('remove_avatar') && $request->remove_avatar === 'true') {
        Log::info('🗑️ Removing avatar as requested');
        if ($staff->avatar) {
            Storage::disk('public')->delete('avatars/' . $staff->avatar);
        }
        $validated['avatar'] = null;
    }
    // Handle new avatar upload
    else if ($request->hasFile('avatar')) {
        Log::info('📸 New avatar uploaded');
        // Delete old avatar if exists
        if ($staff->avatar) {
            Storage::disk('public')->delete('avatars/' . $staff->avatar);
        }
        
        $file = $request->file('avatar');
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('avatars', $filename, 'public');
        
        $validated['avatar'] = $filename;
    }
    // 🔥 FIX: If no avatar changes, preserve the existing avatar by not modifying the field
    else {
        Log::info('🔍 No avatar changes - preserving existing avatar');
        unset($validated['avatar']); // This is crucial - don't modify avatar field
    }

    // Update password if provided
    if ($request->has('password') && $request->password) {
        $request->validate([
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'
        ], [
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',
        ]);
        $validated['password'] = Hash::make($request->password);
    }

    Log::info('💾 Updating staff with data:', $validated);
    $staff->update($validated);

    Log::info('✅ Staff updated successfully:', [
        'staff_id' => $staff->id,
        'new_avatar' => $staff->avatar
    ]);

    return response()->json([
        'message' => 'Staff updated successfully',
        'staff' => $this->formatStaffResponse($staff->load('creator'))
    ]);
}
    public function deactivate(Request $request, $id)
    {
        if ($request->user() instanceof \App\Models\Staff) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $staff = Staff::findOrFail($id);
        
        if ($staff->id === $request->user()->id) {
            return response()->json([
                'error' => 'You cannot modify your own account status'
            ], 403);
        }

        $request->validate([
            'deactivate_reason' => 'required|string|max:500'
        ]);

        $staff->update([
            'is_active' => false,
            'deactivate_reason' => $request->deactivate_reason,
            'deactivated_at' => now(),
            'deactivated_by' => $request->user()->id
        ]);

        return response()->json([
            'message' => 'Staff account deactivated successfully',
            'staff' => $this->formatStaffResponse($staff->load('creator'))
        ]);
    }

    public function reactivate(Request $request, $id)
    {
        if ($request->user() instanceof \App\Models\Staff) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $staff = Staff::findOrFail($id);

        $staff->update([
            'is_active' => true,
            'deactivate_reason' => null,
            'deactivated_at' => null,
            'deactivated_by' => null
        ]);

        return response()->json([
            'message' => 'Staff account reactivated successfully',
            'staff' => $this->formatStaffResponse($staff->load('creator'))
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user() instanceof \App\Models\Staff) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $staff = Staff::findOrFail($id);

        if ($staff->id === $request->user()->id) {
            return response()->json([
                'error' => 'You cannot delete your own account'
            ], 403);
        }

        // Delete avatar if exists
        if ($staff->avatar) {
            Storage::disk('public')->delete('avatars/' . $staff->avatar);
        }

        $staff->delete();

        return response()->json([
            'message' => 'Staff account deleted successfully'
        ]);
    }

    public function statistics(Request $request)
    {
        if ($request->user() instanceof \App\Models\Staff) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $totalStaff = Staff::count();
        $activeStaff = Staff::where('is_active', true)->count();
        $inactiveStaff = Staff::where('is_active', false)->count();
        $recentStaff = Staff::where('created_at', '>=', now()->subDays(30))->count();

        return response()->json([
            'total_staff' => $totalStaff,
            'active_staff' => $activeStaff,
            'inactive_staff' => $inactiveStaff,
            'recent_staff' => $recentStaff
        ]);
    }

    private function formatStaffResponse($staff)
    {
        $avatarUrl = null;
        
        if ($staff->avatar) {
            // Construct full URL: http://localhost:8000/storage/avatars/1762566427_690ea11b86a5e.jpg
            $avatarUrl = url('storage/avatars/' . $staff->avatar);
        }

        return [
            'id' => $staff->id,
            'email' => $staff->email,
            'full_name' => $staff->full_name,
            'contact_number' => $staff->contact_number,
            'address' => $staff->address,
            'avatar' => $staff->avatar, // This will be: 1762566427_690ea11b86a5e.jpg
            'avatar_url' => $avatarUrl, // This will be: http://localhost:8000/storage/avatars/1762566427_690ea11b86a5e.jpg
            'is_active' => $staff->is_active,
            'created_at' => $staff->created_at,
            'updated_at' => $staff->updated_at,
            'last_login_at' => $staff->last_login_at,
            'deactivate_reason' => $staff->deactivate_reason,
            'deactivated_at' => $staff->deactivated_at,
            'creator' => $staff->creator,
        ];
    }


    public function testUpdate(Request $request, $id)
{
    Log::info('🧪 TEST UPDATE - Request received:', [
        'staff_id' => $id,
        'user_id' => $request->user()->id,
        'data' => $request->all()
    ]);

    if ($request->user() instanceof \App\Models\Staff) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $staff = Staff::findOrFail($id);

    $validated = $request->validate([
        'email' => 'sometimes|email|unique:staff,email,' . $id,
        'full_name' => 'sometimes|string|max:100',
        'contact_number' => 'nullable|string|max:11|regex:/^09\d{9}$/',
        'address' => 'nullable|string|max:500',
        'is_active' => 'sometimes|boolean',
    ]);

    Log::info('🧪 TEST UPDATE - Validated data:', $validated);

    $staff->update($validated);

    Log::info('🧪 TEST UPDATE - Staff updated successfully:', ['staff_id' => $staff->id]);

    return response()->json([
        'message' => 'Staff updated successfully (TEST)',
        'staff' => $this->formatStaffResponse($staff->load('creator'))
    ]);
}

// Add this method for testing create
public function testCreate(Request $request)
{
    Log::info('🧪 TEST CREATE - Request received:', [
        'user_id' => $request->user()->id,
        'data' => $request->all()
    ]);

    if ($request->user() instanceof \App\Models\Staff) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'email' => 'required|email|unique:staff',
        'full_name' => 'required|string|max:100',
        'contact_number' => 'nullable|string|max:11|regex:/^09\d{9}$/',
        'address' => 'nullable|string|max:500',
        'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
    ]);

    $validated['password'] = Hash::make($validated['password']);
    $validated['created_by'] = $request->user()->id;

    $staff = Staff::create($validated);

    Log::info('🧪 TEST CREATE - Staff created successfully:', ['staff_id' => $staff->id]);

    return response()->json([
        'message' => 'Staff created successfully (TEST)',
        'staff' => $this->formatStaffResponse($staff->load('creator'))
    ], 201);
}
}