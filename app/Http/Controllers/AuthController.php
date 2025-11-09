<?php
namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = null;
        $guard = null;

        // First try to find as Admin by email
        $user = Admin::where('email', $request->email)->first();
        if ($user) {
            $guard = 'admin';
        } else {
            // If not admin, try as Staff by email
            $user = Staff::where('email', $request->email)->first();
            
            if ($user) {
                // Check if staff account is active
                if (!$user->is_active) {
                    throw ValidationException::withMessages([
                        'email' => ['Your account has been deactivated. Please contact an administrator.'],
                    ]);
                }
                $guard = 'staff';
            }
        }

        // Check if user was found
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if password is correct
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Update last login for staff
        if ($user instanceof Staff) {
            $user->update(['last_login_at' => now()]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'user_type' => $guard
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function getAuthenticatedUser(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'user_type' => $this->getUserType($request->user())
        ]);
    }

    private function getUserType($user)
    {
        if ($user instanceof Admin) {
            return 'admin';
        } elseif ($user instanceof Staff) {
            return 'staff';
        }
        
        return null;
    }


        /**
     * Change admin password
     */
    public function changeAdminPassword(Request $request)
    {
        // Ensure only admins can access this
        if (!$request->user() instanceof Admin) {
            return response()->json([
                'message' => 'Unauthorized. Only administrators can change passwords.'
            ], 403);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
            'new_password_confirmation' => 'required|string'
        ]);

        $user = $request->user();

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.']
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Password updated successfully.'
        ]);
    }

    /**
     * Update user profile (for both admin and staff)
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        // Define validation rules based on user type
        $rules = [
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:' . ($user instanceof Admin ? 'admins' : 'staff') . ',email,' . $user->id,
        ];

        // Add username validation if it's being updated
        if ($request->has('username')) {
            $rules['username'] = 'sometimes|string|unique:' . ($user instanceof Admin ? 'admins' : 'staff') . ',username,' . $user->id;
        }

        $validated = $request->validate($rules);

        // Update user profile
        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user
        ]);
    }

    /**
     * Change password for any authenticated user
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
            'new_password_confirmation' => 'required|string'
        ]);

        $user = $request->user();

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.']
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Password updated successfully.'
        ]);
    }
}