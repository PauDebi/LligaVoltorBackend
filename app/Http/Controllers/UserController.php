<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $user->update($request->only(['name', 'nickname']));

        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    public function uploadProfilePicture(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif',
            ]);

            $user = $request->user();
            $path = $request->file('image')->store('profile_pictures', 'public');
            $url = asset("storage/{$path}");
            $user->update(['image_url' => $url]);

            return response()->json(['message' => 'Profile picture uploaded successfully', 'user' => $user]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error uploading profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
