<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de votre mot de passe EBAC</title>
</head>
<body style="margin:0;padding:0;background:#f3f6f9;color:#243447;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">Votre lien sécurisé de réinitialisation EBAC.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3f6f9;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:620px;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(20,62,86,.10);">
                    <tr>
                        <td align="center" style="padding:34px 40px;background:#075985;background-image:linear-gradient(135deg,#075985,#0e7490);">
                            <div style="display:inline-block;padding:8px 14px;border:1px solid rgba(255,255,255,.4);border-radius:999px;color:#fff;font-size:13px;font-weight:700;letter-spacing:2px;">EBAC</div>
                            <h1 style="margin:20px 0 8px;color:#fff;font-size:27px;line-height:1.25;">Mot de passe oublié ?</h1>
                            <p style="margin:0;color:#cffafe;font-size:15px;line-height:1.6;">Créez un nouveau mot de passe en toute sécurité.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 40px 20px;">
                            <p style="margin:0 0 16px;font-size:18px;line-height:1.6;">Bonjour <strong>{{ $nomComplet }}</strong>,</p>
                            <p style="margin:0;color:#526476;font-size:15px;line-height:1.7;">Nous avons reçu une demande de réinitialisation du mot de passe associé à votre compte EBAC.</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:8px 40px 30px;">
                            <a href="{{ $urlReinitialisation }}" style="display:inline-block;padding:14px 28px;background:#ea580c;border-radius:9px;color:#fff;font-size:16px;font-weight:700;text-decoration:none;">Réinitialiser mon mot de passe</a>
                        </td>
                    </tr>
                    <tr>

                        <td style="padding:0 40px 34px;">
                            <div style="padding:15px 17px;background:#fff7ed;border-left:4px solid #f97316;border-radius:6px;color:#9a3412;font-size:14px;line-height:1.6;">
                                Ce lien expirera dans <strong>{{ $expiration }} minutes</strong>. Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet e-mail.
                            </div>
                            <p style="margin:22px 0 0;color:#64748b;font-size:12px;line-height:1.6;word-break:break-all;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br><a href="{{ $urlReinitialisation }}" style="color:#0369a1;">{{ $urlReinitialisation }}</a></p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:24px 40px;background:#f8fafc;border-top:1px solid #e2e8f0;">
                            <p style="margin:0 0 6px;color:#334155;font-size:14px;font-weight:700;">L’équipe EBAC</p>
                            <p style="margin:0;color:#94a3b8;font-size:12px;">Ceci est un message automatique, merci de ne pas y répondre.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
