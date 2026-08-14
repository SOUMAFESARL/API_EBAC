<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Création de votre compte EBAC</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f6f9; color: #243447; font-family: Arial, Helvetica, sans-serif;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0;">
        Votre compte EBAC est prêt. Découvrez vos informations de connexion.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f3f6f9;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 620px; background-color: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 30px rgba(20, 62, 86, 0.10);">
                    <tr>
                        <td style="padding: 34px 40px; background-color: #075985; background-image: linear-gradient(135deg, #075985 0%, #0e7490 100%); text-align: center;">
                            <div style="display: inline-block; padding: 8px 14px; border: 1px solid rgba(255,255,255,.4); border-radius: 999px; color: #ffffff; font-size: 13px; font-weight: 700; letter-spacing: 2px;">EBAC</div>
                            <h1 style="margin: 20px 0 8px; color: #ffffff; font-size: 28px; line-height: 1.25;">Bienvenue sur la plateforme</h1>
                            <p style="margin: 0; color: #cffafe; font-size: 16px; line-height: 1.6;">Votre compte a été créé avec succès.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 36px 40px 16px;">
                            <p style="margin: 0 0 16px; font-size: 18px; line-height: 1.6;">Bonjour <strong>{{ $nomComplet }}</strong>,</p>
                            <p style="margin: 0; color: #526476; font-size: 15px; line-height: 1.7;">
                                Nous sommes heureux de vous informer que votre accès à la plateforme EBAC est maintenant disponible. Voici vos informations de connexion :
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 12px 40px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px;">
                                <tr>
                                    <td style="padding: 22px 24px;">
                                        <p style="margin: 0 0 6px; color: #64748b; font-size: 12px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase;">Adresse e-mail</p>
                                        <p style="margin: 0 0 18px; color: #0f172a; font-size: 16px; line-height: 1.5; word-break: break-word;">{{ $email }}</p>

                                        <p style="margin: 0 0 6px; color: #64748b; font-size: 12px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase;">Rôle attribué</p>
                                        <p style="margin: 0 0 18px; color: #075985; font-size: 16px; font-weight: 700; line-height: 1.5;">{{ $role }}</p>

                                        <p style="margin: 0 0 6px; color: #64748b; font-size: 12px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase;">Mot de passe temporaire</p>
                                        <p style="margin: 0; padding: 12px 16px; background-color: #ffffff; border: 1px dashed #38bdf8; border-radius: 8px; color: #0f172a; font-family: Consolas, Monaco, monospace; font-size: 18px; font-weight: 700; letter-spacing: 1px; line-height: 1.4; word-break: break-all;">{{ $motDePasseTemporaire }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 4px 40px 30px;">
                            <a href="{{ $urlConnexion }}" style="display: inline-block; padding: 14px 30px; background-color: #ea580c; border-radius: 9px; color: #ffffff; font-size: 16px; font-weight: 700; line-height: 1.2; text-decoration: none;">Se connecter</a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 36px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #fff7ed; border-left: 4px solid #f97316; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 14px 16px; color: #9a3412; font-size: 14px; line-height: 1.6;">
                                        <strong>Conseil de sécurité :</strong> changez ce mot de passe temporaire dès votre première connexion et ne le partagez avec personne.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 40px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center;">
                            <p style="margin: 0 0 6px; color: #334155; font-size: 14px; font-weight: 700;">L’équipe EBAC</p>
                            <p style="margin: 0; color: #94a3b8; font-size: 12px; line-height: 1.5;">Ceci est un message automatique, merci de ne pas y répondre.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
