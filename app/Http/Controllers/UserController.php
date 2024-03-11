<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserLoginRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function register(UserRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        var_dump($data);
        if (User::where('name', $data['name'])->count() > 0) {
            throw new HttpResponseException(response([
                "errors" => [
                    "name" => [
                        "name already registered"
                    ]
                ]
            ], 400));
        }

        $user = new User();

        // Check for null pointer reference error in case the file is not present
        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');

            try {
                // Upload file avatar ke Cloudinary
                $cloudinaryUpload = Cloudinary::upload($avatarFile->getRealPath(), [
                    'folder' => 'teka_apps',
                    'public_id' => 'image_' . time(),
                    'overwrite' => true,
                ]);

                // Menyimpan URL avatar yang diunggah dalam array $data
                $data['avatar'] = $cloudinaryUpload->getSecurePath();
            } catch (\Throwable $e) {
                report($e);

                throw new HttpResponseException(
                    response(['message' => 'There was an error uploading the file'], 500)
                );
            }
        }

        if ($request->hasFile('purchasedAvatars')) {
            $data['purchasedAvatars'] = [];

            foreach ($request->file('purchasedAvatars') as $file) {
                try {
                    $cloudinaryUpload = Cloudinary::upload($file->getRealPath(), [
                        'folder' => 'teka_apps',
                        'public_id' => 'image_' . time(),
                        'overwrite' => true,
                    ]);

                    // Menyimpan URL avatar yang diunggah dalam array $purchasedAvatarsUrls
                    $data['purchasedAvatars'][]['avatar'] = $cloudinaryUpload->getSecurePath();
                } catch (\Throwable $e) {
                    report($e);

                    throw new HttpResponseException(
                        response(['message' => 'There was an error uploading the file'], 500)
                    );
                }
            }
        }



        $user->fill($data);

        try {
            $user->save();
        } catch (\Throwable $e) {
            report($e);

            throw new HttpResponseException(
                response(['message' => 'Error saving data: ' . $e->getMessage()], 500)
            );
        }


        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(UserLoginRequest $request): UserResource
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            throw new HttpResponseException(response([
                "errors" => [
                    "message" => [
                        "email not found!"
                    ]
                ]
            ], 401));
        }

        $user->token = Str::uuid()->toString();
        $user->save();

        return new UserResource($user);
    }

    public function get(Request $request): UserResource
    {
        $user = Auth::user();
        return new UserResource($user);
    }

    public function update(UserUpdateRequest $request): UserResource
    {
        $data = $request->validated();
        $user = Auth::user();

        if (!$user instanceof User) {
            throw new HttpResponseException(response(['message' => 'User not found'], 404));
        }

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');

            try {
                // Upload file avatar to Cloudinary
                $cloudinaryUpload = Cloudinary::upload($avatarFile->getRealPath(), [
                    'folder' => 'teka_apps',
                    'public_id' => 'image_' . time(),
                    'overwrite' => true,
                ]);

                $user->avatar = $cloudinaryUpload->getSecurePath();
            } catch (\Throwable $e) {
                report($e);

                throw new HttpResponseException(
                    response(['message' => 'There was an error uploading the avatar file'], 500)
                );
            }
        }

        try {
            $user->save();
        } catch (\Throwable $e) {
            report($e);

            throw new HttpResponseException(
                response(['message' => 'Error saving data: ' . $e->getMessage()], 500)
            );
        }

        return new UserResource($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            throw new HttpResponseException(response(['message' => 'User not found'], 404));
        }

        $user->token = null;

        try {
            $user->save();
        } catch (\Throwable $e) {
            report($e);

            throw new HttpResponseException(
                response(['message' => 'Error saving data: ' . $e->getMessage()], 500)
            );
        }

        return response()->json([
            "data" => true
        ])->setStatusCode(200);
    }
}
