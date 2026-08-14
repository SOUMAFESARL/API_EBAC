<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\DTOs\Api\V1\Auth\ConnexionDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Auth\ConnexionResource;
use App\Http\Resources\Api\V1\UtilisateurResource;
use App\Models\ConnexionDeuxFacteurs;
use App\Models\User;
use App\Notifications\CodeOtpConnexionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthentificationController extends Controller
{
    public function motDePasseOublie(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::broker()->sendResetLink($donnees);

        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation a été envoyé.',
        ]);
    }

    public function reinitialiserMotDePasse(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
        ]);

        $statut = Password::broker()->reset(
            $donnees,
            function (User $utilisateur, string $motDePasse): void {
                $utilisateur->forceFill([
                    'password' => $motDePasse,
                    'tentatives_echouees' => 0,
                ])->save();

                $utilisateur->tokens()->delete();
            },
        );

        if ($statut !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['Le lien de réinitialisation est invalide ou a expiré.'],
            ]);
        }

        return response()->json([
            'message' => 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.',
        ]);
    }

    public function connexion(Request $request): JsonResponse
    {
        $identifiants = ConnexionDTO::fromArray($request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'nom_appareil' => ['sometimes', 'string', 'max:100'],
        ]));

        $utilisateur = User::query()
            ->with('role')
            ->where('email', $identifiants->email)
            ->first();

        if (! $utilisateur || ! Hash::check($identifiants->password, $utilisateur->password)) {
            if ($utilisateur) {
                $utilisateur->increment('tentatives_echouees');
            }

            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        if (! $utilisateur->is_active || $utilisateur->statut !== 'Actif') {
            return response()->json([
                'message' => 'Ce compte est inactif ou ne peut pas se connecter.',
            ], 403);
        }

        $code = (string) random_int(100000, 999999);

        $utilisateur->connexionsDeuxFacteurs()
            ->whereNull('valide_le')
            ->whereNull('deleted_at')
            ->update(['reussi' => false, 'deleted_at' => now()]);

        $tentative = $utilisateur->connexionsDeuxFacteurs()->create([
            'code_otp_hash' => Hash::make($code),
            'canal' => 'Email',
            'envoye_le' => now(),
            'adresse_ip' => $request->ip(),
            'nom_appareil' => $identifiants->nomAppareil,
            'tentatives' => 0,
        ]);

        try {
            $utilisateur->notifyNow(new CodeOtpConnexionNotification($code));
        } catch (\Throwable $exception) {
            $tentative->delete();
            report($exception);

            return response()->json([
                'message' => "Le code OTP n'a pas pu être envoyé. Veuillez réessayer.",
            ], 503);
        }

        return response()->json([
            'message' => 'Un code OTP a été envoyé à votre adresse e-mail.',
            'otp_requis' => true,
            'id_tentative' => $tentative->getKey(),
            'expire_dans' => 600,
        ], 202);
    }

    public function confirmerOtp(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'id_tentative' => ['required', 'integer'],
            'code_otp' => ['required', 'digits:6'],
        ]);

        $tentative = ConnexionDeuxFacteurs::query()
            ->with('compte.role')
            ->find($donnees['id_tentative']);

        if (! $tentative || $tentative->valide_le || $tentative->reussi === false) {
            throw ValidationException::withMessages([
                'code_otp' => ['Cette demande de connexion est invalide ou a déjà été utilisée.'],
            ]);
        }

        if ($tentative->envoye_le->lt(now()->subMinutes(10))) {
            $tentative->forceFill(['reussi' => false])->save();

            throw ValidationException::withMessages([
                'code_otp' => ['Le code OTP a expiré. Veuillez recommencer la connexion.'],
            ]);
        }

        if ($tentative->tentatives >= 5 || ! Hash::check($donnees['code_otp'], $tentative->code_otp_hash)) {
            $tentative->increment('tentatives');

            if ($tentative->fresh()->tentatives >= 5) {
                $tentative->forceFill(['reussi' => false])->save();
            }

            throw ValidationException::withMessages([
                'code_otp' => ['Le code OTP est incorrect.'],
            ]);
        }

        $utilisateur = $tentative->compte;

        if (! $utilisateur || ! $utilisateur->is_active || $utilisateur->statut !== 'Actif') {
            return response()->json([
                'message' => 'Ce compte est inactif ou ne peut pas se connecter.',
            ], 403);
        }

        $tentative->forceFill([
            'valide_le' => now(),
            'reussi' => true,
        ])->save();

        $utilisateur->forceFill([
            'tentatives_echouees' => 0,
            'derniere_connexion' => now(),
        ])->save();

        $jeton = $utilisateur->createToken($tentative->nom_appareil)->plainTextToken;

        return ConnexionResource::make([
            'token' => $jeton,
            'utilisateur' => $utilisateur->fresh()->load('role'),
        ])->response();
    }

    public function profil(Request $request): JsonResponse
    {
        return response()->json([
            'utilisateur' => UtilisateurResource::make($request->user()?->load('role')),
        ]);
    }

    public function deconnexion(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    public function deconnexionGlobale(Request $request): JsonResponse
    {
        $request->user()?->tokens()->delete();

        return response()->json([
            'message' => 'Déconnexion effectuée sur tous les appareils.',
        ]);
    }
}
