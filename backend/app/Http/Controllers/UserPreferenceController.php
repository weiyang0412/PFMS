<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserPreferenceController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'profile_type' => ['required', Rule::in(['student', 'general'])],
            'preferred_period' => ['required', Rule::in(['monthly', 'semester'])],
            'semester_start_month' => ['nullable', 'integer', 'between:1,12'],
            'semester_length_months' => ['nullable', 'integer', 'between:1,12'],
        ]);

        if ($validated['profile_type'] === 'general' && $validated['preferred_period'] === 'semester') {
            return response()->json([
                'message' => 'General profile supports monthly period only.',
                'errors' => [
                    'preferred_period' => ['General profile supports monthly period only.'],
                ],
            ], 422);
        }

        $user = $request->user();
        if (array_key_exists('name', $validated)) {
            $user->name = trim((string) $validated['name']);
        }
        $user->profile_type = $validated['profile_type'];
        $user->preferred_period = $validated['preferred_period'];
        $user->semester_start_month = (int) ($validated['semester_start_month'] ?? $user->semester_start_month ?? 1);
        $user->semester_length_months = (int) ($validated['semester_length_months'] ?? $user->semester_length_months ?? 6);
        $user->onboarding_completed_at = now();
        $user->save();

        return response()->json($user->fresh());
    }
}
