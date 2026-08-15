<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code de confirmation EBAC</title>
</head>

<body style="margin:0; padding:0; background-color:#eef3f9; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#eef3f9;">
    <tr>
        <td align="center" style="padding:36px 16px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(15, 56, 120, 0.12);">
                <tr>
                    <td align="center" style="padding:28px 32px 22px; background:#ffffff; border-bottom:4px solid #123b8f;">
                        <img src="{{ $message->embed(public_path('images/logo-ebac.jpg')) }}" width="120" height="120" alt="Logo EBAC" style="display:block; width:120px; height:120px; max-width:100%; border:0; border-radius:50%; object-fit:cover; outline:none; box-shadow:none;">
                        <div style="margin-top:14px; font-size:20px; line-height:28px; font-weight:700; color:#123b8f;">
                            ÉCOLE BIBLIQUE DE L'ALLIANCE CHRÉTIENNE
                        </div>
                        <div style="margin-top:4px; font-size:13px; letter-spacing:2px; color:#64748b;">E.B.A.C.</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:34px 40px 12px;">
                        <h1 style="margin:0 0 18px; font-size:24px; line-height:32px; color:#102a56;">
                            Confirmation de votre connexion
                        </h1>
                        <p style="margin:0 0 14px; font-size:16px; line-height:25px;">
                            Bonjour <strong>{{ $nomComplet }}</strong>,
                        </p>
                        <p style="margin:0; font-size:16px; line-height:25px; color:#475569;">
                            Utilisez le code ci-dessous pour confirmer votre connexion à la plateforme EBAC.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:22px 40px;">
                        <div style="display:inline-block; min-width:250px; padding:20px 28px; background:#123b8f; border-radius:12px; color:#ffffff; font-size:34px; line-height:42px; font-weight:800; letter-spacing:10px; text-align:center; box-shadow:0 6px 16px rgba(18, 59, 143, 0.25);">
                            {{ $code }}
                        </div>
                        <p style="margin:16px 0 0; font-size:14px; line-height:22px; color:#64748b;">
                            Ce code expire dans <strong style="color:#123b8f;">{{ $dureeValiditeMinutes }} minutes</strong>.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:10px 40px 34px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#fff7ed; border-left:4px solid #f59e0b; border-radius:8px;">
                            <tr>
                                <td style="padding:15px 18px; font-size:14px; line-height:22px; color:#92400e;">
                                    <strong>Sécurité :</strong> si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail et ne communiquez ce code à personne.
                                </td>
                            </tr>
                        </table>
                        <div style="padding-top:24px; text-align:center;">
                            <a href="https://ebac.ci" target="_blank" style="display:inline-block; padding:13px 26px; background:#123b8f; border-radius:8px; color:#ffffff; font-size:15px; line-height:20px; font-weight:700; text-decoration:none;">
                                Accéder à EBAC
                            </a>
                            <p style="margin:10px 0 0; font-size:13px; line-height:20px; color:#64748b;">
                                Adresse de connexion :
                                <a href="https://ebac.ci" target="_blank" style="color:#123b8f; font-weight:700; text-decoration:none;">ebac.ci</a>
                            </p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:22px 30px; background:#102a56; color:#dbeafe;">
                        <div style="font-size:15px; line-height:22px; font-weight:700; color:#ffffff;">École Biblique de l'Alliance Chrétienne</div>
                        <div style="margin-top:4px; font-size:13px; line-height:20px;">Équipe EBAC</div>
                        <div style="margin-top:7px; font-size:13px; line-height:20px;">
                            <a href="https://ebac.ci" target="_blank" style="color:#ffffff; font-weight:700; text-decoration:none;">www.ebac.ci</a>
                        </div>
                        <div style="margin-top:8px; font-size:12px; line-height:18px; color:#bfdbfe;">E-mail automatique — merci de ne pas répondre.</div>
                    </td>
                </tr>
            </table>
            <div style="max-width:600px; padding:18px 20px 0; font-size:12px; line-height:18px; color:#94a3b8; text-align:center;">
                © {{ date('Y') }} EBAC. Tous droits réservés.
            </div>
        </td>
    </tr>
</table>
</body>
</html>
