<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // Endpoint per la registrazione (signup)
    public function signup(Request $request)
    {
        // Validazione dei dati di input
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users',
            'password'              => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Creazione dell'utente
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Creazione del token JWT per l'utente appena creato
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'user'  => $user,
            'token' => $token
        ], 201);
    }

    // Endpoint per il login che rilascia il token JWT
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Credenziali non valide'], 401);
        }

        return response()->json([
            'token' => $token
        ]);
    }

    // Endpoint protetto che ritorna i dati dell'utente autenticato
    public function me()
    {
        return response()->json(auth()->user());
    }
}

