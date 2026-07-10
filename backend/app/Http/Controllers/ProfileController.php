<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UploadProfilePhotoRequest;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Kullanıcı bilgileri başarıyla getirildi.',
            'data' => new UserResource($request->user()),
            ], 200);
    }
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();
        return response()->json([
            'success' => true,
            'message' => 'Profil başarıyla güncellendi.',
            'data' => new UserResource($request->user()),
            ], 200);
    }
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        
        $user->password = Hash::make($request->password);
        $user->save();
         return response()->json([
            'success' => true,
            'message' => 'Şifre başarıyla değiştirildi.',
            ], 200);
    }
    public function uploadPhoto(UploadProfilePhotoRequest $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
            }
            $path = $request->file('photo')->store('profile-photos', 'public');
            $user->profile_photo = $path;
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Profile photo uploaded successfully.',
                'data' => [
                    'profile_photo' => asset('storage/' . $path),
                     ],
                     ], 200);
    }
}
