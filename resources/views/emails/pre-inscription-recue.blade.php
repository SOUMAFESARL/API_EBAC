<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Pré-inscription EBAC</title></head>
<body style="margin:0;padding:0;background:#eef3f9;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center" style="padding:36px 16px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(15,56,120,.12);">
    <tr><td align="center" style="padding:28px 32px 22px;border-bottom:4px solid #123b8f;">
        <img src="{{ $message->embed(public_path('images/logo-ebac.jpg')) }}" width="120" height="120" alt="Logo EBAC" style="display:block;width:120px;height:120px;border:0;border-radius:50%;object-fit:cover;outline:none;box-shadow:none;">
        <div style="margin-top:14px;font-size:22px;font-weight:700;color:#123b8f;">ÉCOLE BIBLIQUE DE L'ALLIANCE CHRÉTIENNE</div>
        <div style="margin-top:5px;font-size:13px;letter-spacing:2px;color:#64748b;">E.B.A.C.</div>
    </td></tr>
    <tr><td style="padding:34px 40px;">
        <h1 style="margin:0 0 20px;font-size:23px;color:#102a56;">Demande de pré-inscription reçue</h1>
        <p style="font-size:16px;line-height:25px;">Bonjour <strong>{{ $nomComplet }}</strong>,</p>
        <p style="font-size:16px;line-height:25px;color:#475569;">Vous avez effectué une demande de pré-inscription auprès de l’EBAC. Nous vous confirmons que votre dossier a bien été reçu et qu’il est actuellement en cours d’analyse.</p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0;background:#f5f8fc;border-radius:10px;"><tr><td style="padding:20px 24px;">
            <div style="font-size:13px;color:#64748b;">Numéro de dossier</div><div style="font-size:17px;font-weight:700;color:#123b8f;">{{ $numeroDossier }}</div>
        </td></tr></table>
        <p style="font-size:16px;line-height:25px;color:#475569;">Nous vous invitons à patienter pendant le traitement. Vous serez contacté(e) dès qu’une décision ou une information complémentaire sera disponible.</p>
        <p style="margin-bottom:0;font-size:14px;line-height:22px;color:#64748b;">Ceci est un message automatique, merci de ne pas y répondre.</p>
    </td></tr>
    <tr><td align="center" style="padding:22px;background:#102a56;color:#dbeafe;">L’équipe EBAC</td></tr>
</table>
</td></tr></table>
</body>
</html>
