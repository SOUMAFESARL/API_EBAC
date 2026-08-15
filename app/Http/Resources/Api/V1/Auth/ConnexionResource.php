<?php

namespace App\Http\Resources\Api\V1\Auth;

use App\Http\Resources\Api\V1\UtilisateurResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnexionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => 'Connexion réussie.',
            'token' => $this->resource['token'],
            'token_type' => 'Bearer',
            'otp_requis' => $this->resource['otp_requis'] ?? false,
            'redirect_to' => '/dashboard/index',
            'utilisateur' => UtilisateurResource::make($this->resource['utilisateur']),
        ];
    }
}
