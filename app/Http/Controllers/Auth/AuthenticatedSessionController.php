<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();

        $user = $request->user();

        return response()->json([
            'status' => true,
            'user' => $user,
            'token' => $user->createToken('api_token')->plainTextToken
        ], 201);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->json([
            'status' => true,
            'message' => 'User loged out'
        ], 201);
    }
}
