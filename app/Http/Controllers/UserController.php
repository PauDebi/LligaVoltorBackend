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
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $user = $request->user();
        $relativePath = $request->file('image')->store('profile_pictures', 'public');
        $path = asset('storage/' . $relativePath);
        $user->update(['image_url' => $path]);

        return response()->json(['message' => 'Profile picture uploaded successfully', 'user' => $user]);
    }
}
