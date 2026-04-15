<?php
/**
 * Webhook GitHub — Déploiement automatique sur push
 *
 * Configuration :
 * 1. Sur GitHub → Settings → Webhooks → Add webhook
 *    - Payload URL : https://gestion-commandes.bougerolle.ovh/webhook.php
 *    - Content type : application/json
 *    - Secret : <le même que WEBHOOK_SECRET ci-dessous>
 *    - Events : Just the push event
 *
 * 2. Configurer le secret dans .env.local :
 *    WEBHOOK_SECRET=mon-secret-tres-long
 *
 * 3. S'assurer que www-data peut exécuter deploy.sh :
 *    sudo visudo -f /etc/sudoers.d/gestion_commandes
 *    www-data ALL=(ALL) NOPASSWD: /var/www/gestion_commandes/deploy.sh
 */

// --- Configuration ---
$appDir = dirname(__DIR__);
$logFile = $appDir . '/var/log/webhook.log';
$deployScript = $appDir . '/deploy.sh';
$branch = 'master';

// Charger le secret depuis .env.local
$secret = null;
$envFile = $appDir . '/.env.local';
if (file_exists($envFile)) {
    foreach (file($envFile) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'WEBHOOK_SECRET=')) {
            $secret = trim(substr($line, strlen('WEBHOOK_SECRET=')), '"\'');
        }
    }
}

// Fallback: variable d'environnement
if (!$secret) {
    $secret = $_ENV['WEBHOOK_SECRET'] ?? getenv('WEBHOOK_SECRET') ?: null;
}

// --- Fonctions utilitaires ---
function webhookLog(string $msg, string $logFile): void
{
    $date = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$date] $msg\n", FILE_APPEND);
}

function respond(int $code, string $msg, string $logFile): never
{
    webhookLog("HTTP $code — $msg", $logFile);
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['status' => $code, 'message' => $msg]);
    exit;
}

// --- Vérifications ---

// Méthode POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Method Not Allowed', $logFile);
}

// Lire le body
$payload = file_get_contents('php://input');
if (!$payload) {
    respond(400, 'Empty payload', $logFile);
}

// Vérifier la signature GitHub (si un secret est configuré)
if ($secret) {
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

    if (!hash_equals($expected, $signature)) {
        respond(403, 'Invalid signature', $logFile);
    }
}

// Décoder le JSON
$data = json_decode($payload, true);
if (!$data) {
    respond(400, 'Invalid JSON', $logFile);
}

// Vérifier que c'est un push sur la bonne branche
$ref = $data['ref'] ?? '';
if ($ref !== "refs/heads/$branch") {
    respond(200, "Ignored: push to $ref (not $branch)", $logFile);
}

// --- Déployer ---
webhookLog("Push détecté sur $branch par " . ($data['pusher']['name'] ?? 'unknown'), $logFile);
webhookLog("Commit: " . ($data['head_commit']['message'] ?? 'n/a'), $logFile);

// Lancer le déploiement en arrière-plan
$cmd = sprintf(
    'cd %s && bash deploy.sh >> %s 2>&1 &',
    escapeshellarg($appDir),
    escapeshellarg($logFile)
);

exec($cmd, $output, $returnCode);

if ($returnCode === 0) {
    respond(200, 'Deployment triggered', $logFile);
} else {
    respond(500, 'Deployment failed to start', $logFile);
}
