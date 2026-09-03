<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vos accès étudiants EBAC</title>
</head>
<body style="margin:0;padding:0;background:#eef3f9;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eef3f9;">
<tr><td align="center" style="padding:36px 16px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(15,56,120,.12);">
    <tr><td align="center" style="padding:28px 32px 22px;border-bottom:4px solid #15803d;">
        <img src="{{ $message->embed(public_path('images/logo-ebac.jpg')) }}" width="120" height="120" alt="Logo EBAC" style="display:block;width:120px;height:120px;border-radius:50%;object-fit:cover;">
        <div style="margin-top:14px;font-size:20px;line-height:28px;font-weight:700;color:#123b8f;">ÉCOLE BIBLIQUE DE L'ALLIANCE CHRÉTIENNE</div>
        <div style="margin-top:4px;font-size:13px;letter-spacing:2px;color:#64748b;">ESPACE ÉTUDIANT</div>
    </td></tr>
    <tr><td style="padding:34px 40px 14px;">
        <h1 style="margin:0 0 18px;font-size:24px;line-height:32px;color:#166534;">Votre compte étudiant est prêt</h1>
        <p style="margin:0 0 14px;font-size:16px;line-height:25px;">Bonjour <strong>{{ $nomComplet }}</strong>,</p>
        <p style="margin:0;font-size:16px;line-height:25px;color:#475569;">Votre préinscription a été validée. Vous pouvez maintenant accéder à votre espace étudiant EBAC.</p>
    </td></tr>
    <tr><td style="padding:16px 40px 24px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f0fdf4;border-radius:12px;">
        <tr><td style="padding:22px 24px;">
            <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">Matricule étudiant</div>
            <div style="margin-top:6px;font-size:18px;font-weight:700;color:#166534;">{{ $matricule }}</div>
            @if ($numeroDossier)
            <div style="margin-top:18px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">Numéro du dossier</div>
            <div style="margin-top:6px;font-size:16px;font-weight:700;color:#166534;">{{ $numeroDossier }}</div>
            @endif
            @if ($statutDossier)
            <div style="margin-top:18px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">Statut du dossier</div>
            <div style="margin-top:6px;font-size:16px;font-weight:700;color:#102a56;">{{ $statutDossier }}</div>
            @endif
            <div style="margin-top:18px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">Adresse de connexion</div>
            <div style="margin-top:6px;font-size:16px;color:#102a56;word-break:break-word;">{{ $email }}</div>
            @if ($anneeAcademique)
            <div style="margin-top:18px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">Année académique</div>
            <div style="margin-top:6px;font-size:16px;color:#102a56;">{{ $anneeAcademique }}</div>
            @endif
            @if ($eglise)
            <div style="margin-top:18px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">Église</div>
            <div style="margin-top:6px;font-size:16px;color:#102a56;">{{ $eglise }}</div>
            @endif
        </td></tr></table>
    </td></tr>
    <tr><td align="center" style="padding:0 40px 24px;">
        <div style="margin-bottom:8px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">Mot de passe temporaire</div>
        <div style="display:inline-block;min-width:260px;padding:18px 24px;background:#166534;border-radius:12px;color:#fff;font-family:Consolas,Monaco,monospace;font-size:20px;font-weight:800;letter-spacing:2px;word-break:break-all;">{{ $motDePasseTemporaire }}</div>
    </td></tr>
    <tr><td style="padding:0 40px 28px;font-size:14px;line-height:22px;color:#92400e;">Changez ce mot de passe dès votre première connexion et ne le communiquez à personne.</td></tr>
    <tr><td align="center" style="padding:0 40px 34px;">
        <a href="{{ $urlConnexion }}" target="_blank" style="display:inline-block;padding:13px 28px;background:#166534;border-radius:8px;color:#fff;font-size:16px;font-weight:700;text-decoration:none;">Accéder à mon espace étudiant</a>
    </td></tr>
    <tr><td align="center" style="padding:22px 30px;background:#102a56;color:#dbeafe;font-size:13px;">L'équipe académique EBAC · E-mail automatique</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
