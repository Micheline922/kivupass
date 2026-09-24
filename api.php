<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
startAppSession();

try {
    $pdo = db();
    foreach ([
        'logo LONGTEXT NULL',
    ] as $column) {
        try { $pdo->exec('ALTER TABLE companies ADD COLUMN ' . $column); } catch (Throwable $ignored) {}
    }
    foreach ([
        'acceptance_logo LONGTEXT NULL', 'acceptance_message TEXT NULL', 'ticket_code VARCHAR(80) NULL',
        'rejection_reason TEXT NULL', 'checked_in TINYINT(1) NOT NULL DEFAULT 0',
        'accepted_at DATETIME NULL'
    ] as $column) {
        try { $pdo->exec('ALTER TABLE reservations ADD COLUMN ' . $column); } catch (Throwable $ignored) {}
    }
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $body = requestBody();

    if ($method === 'POST' && $action === 'boat-login') {
        $companyName = trim((string)($body['companyName'] ?? ''));
        $boatName = trim((string)($body['boatName'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $stmt = $pdo->prepare('SELECT id, company_name, fleet, approved FROM companies WHERE company_name = ? LIMIT 1');
        $stmt->execute([$companyName]);
        $company = $stmt->fetch();
        $boat = null;
        foreach (json_decode((string)($company['fleet'] ?? '[]'), true) ?: [] as $candidate) {
            if (($candidate['name'] ?? '') === $boatName) {
                $boat = $candidate;
                break;
            }
        }
        if (!$company || !(bool)$company['approved'] || !$boat || empty($boat['passwordHash']) || !password_verify($password, $boat['passwordHash'])) {
            jsonResponse(['error' => 'Compagnie, bateau ou mot de passe incorrect.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['armateur'] = [
            'companyId' => (int)$company['id'],
            'companyName' => $company['company_name'],
            'boatName' => $boatName,
        ];
        unset($_SESSION['super_admin']);
        jsonResponse(['success' => true, 'companyName' => $company['company_name'], 'boatName' => $boatName]);
    }

    if ($method === 'POST' && $action === 'super-admin-login') {
        if (!password_verify((string)($body['password'] ?? ''), SUPER_ADMIN_PASSWORD_HASH)) {
            jsonResponse(['error' => 'Mot de passe super-admin incorrect.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['super_admin'] = true;
        unset($_SESSION['armateur']);
        jsonResponse(['success' => true]);
    }

    if ($method === 'POST' && $action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        jsonResponse(['success' => true]);
    }

    if ($method === 'GET' && $action === 'companies') {
        $companies = $pdo->query('SELECT id, company_name, payments, required_docs, fleet, logo, approved FROM companies ORDER BY id DESC')->fetchAll();
        jsonResponse(array_map('companyFromRow', $companies));
    }

    if ($method === 'GET' && $action === 'reservations') {
        $armateur = requireArmateurSession();
        $stmt = $pdo->prepare('SELECT * FROM reservations WHERE compagnie = ? AND bateau = ? ORDER BY id DESC');
        $stmt->execute([$armateur['companyName'], $armateur['boatName']]);
        jsonResponse(array_map('reservationFromRow', $stmt->fetchAll()));
    }

    if ($method === 'POST' && $action === 'companies') {
        if (trim((string)($body['companyName'] ?? '')) === '') {
            jsonResponse(['error' => 'Le nom de la compagnie est requis.'], 422);
        }
        validatePaymentNumbers($body['payments'] ?? []);
        $fleet = array_values($body['fleet'] ?? []);
        foreach ($fleet as &$boat) {
            if (trim((string)($boat['name'] ?? '')) === '' || strlen((string)($boat['password'] ?? '')) < 8) {
                jsonResponse(['error' => 'Chaque bateau doit avoir un mot de passe d’au moins 8 caractères.'], 422);
            }
            if (empty($boat['levels']) || !is_array($boat['levels'])) {
                jsonResponse(['error' => 'Chaque bateau doit avoir au moins une classe tarifaire.'], 422);
            }
            foreach ($boat['levels'] as $level) {
                if (trim((string)($level['label'] ?? '')) === '' || !is_numeric($level['price'] ?? null) || (int)($level['seats'] ?? 0) < 1) {
                    jsonResponse(['error' => 'Chaque classe doit avoir un nom, un prix et au moins une place.'], 422);
                }
            }
            $boat['passwordHash'] = password_hash((string)$boat['password'], PASSWORD_DEFAULT);
            unset($boat['password']);
        }
        unset($boat);
        $stmt = $pdo->prepare('INSERT INTO companies (company_name, payments, required_docs, fleet, logo, approved) VALUES (?, ?, ?, ?, ?, 0)');
        $stmt->execute([
            trim((string)$body['companyName']),
            json_encode($body['payments'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode(array_values($body['requiredDocs'] ?? []), JSON_UNESCAPED_UNICODE),
            json_encode($fleet, JSON_UNESCAPED_UNICODE),
            $body['logo'] ?? null,
        ]);
        jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()], 201);
    }

    if ($method === 'PATCH' && $action === 'companies') {
        $companyName = trim((string)($body['companyName'] ?? ''));
        if ($companyName === '') {
            jsonResponse(['error' => 'Le nom de la compagnie est requis.'], 422);
        }
        validatePaymentNumbers($body['payments'] ?? []);

        $existingStmt = $pdo->prepare('SELECT fleet FROM companies WHERE company_name = ? LIMIT 1');
        $existingStmt->execute([$companyName]);
        $existingFleet = json_decode((string)$existingStmt->fetchColumn(), true) ?: [];
        $existingHashes = [];
        foreach ($existingFleet as $existingBoat) {
            if (!empty($existingBoat['name']) && !empty($existingBoat['passwordHash'])) {
                $existingHashes[trim((string)$existingBoat['name'])] = $existingBoat['passwordHash'];
            }
        }

        $fleet = array_values($body['fleet'] ?? []);
        foreach ($fleet as &$boat) {
            $boatName = trim((string)($boat['name'] ?? ''));
            $plainPassword = (string)($boat['password'] ?? '');
            $passwordHash = (string)($boat['passwordHash'] ?? ($existingHashes[$boatName] ?? ''));
            if ($boatName === '' || ($plainPassword === '' && $passwordHash === '')) {
                jsonResponse(['error' => 'Chaque bateau doit avoir un mot de passe d’au moins 8 caractères.'], 422);
            }
            if ($plainPassword !== '' && strlen($plainPassword) < 8) {
                jsonResponse(['error' => 'Chaque bateau doit avoir un mot de passe d’au moins 8 caractères.'], 422);
            }
            if (empty($boat['levels']) || !is_array($boat['levels'])) {
                jsonResponse(['error' => 'Chaque bateau doit avoir au moins une classe tarifaire.'], 422);
            }
            foreach ($boat['levels'] as $level) {
                if (trim((string)($level['label'] ?? '')) === '' || !is_numeric($level['price'] ?? null) || (int)($level['seats'] ?? 0) < 1) {
                    jsonResponse(['error' => 'Chaque classe doit avoir un nom, un prix et au moins une place.'], 422);
                }
            }
            $boat['passwordHash'] = $plainPassword !== '' ? password_hash($plainPassword, PASSWORD_DEFAULT) : $passwordHash;
            unset($boat['password']);
        }
        unset($boat);

        $stmt = $pdo->prepare('UPDATE companies SET payments = ?, required_docs = ?, fleet = ?, logo = ? WHERE company_name = ?');
        $stmt->execute([
            json_encode($body['payments'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode(array_values($body['requiredDocs'] ?? []), JSON_UNESCAPED_UNICODE),
            json_encode($fleet, JSON_UNESCAPED_UNICODE),
            $body['logo'] ?? null,
            $companyName,
        ]);
        jsonResponse(['success' => $stmt->rowCount() > 0]);
    }

    if ($method === 'POST' && $action === 'reservations') {
        $required = ['client', 'email', 'whatsapp', 'compagnie', 'bateau', 'niveau', 'prix'];
        foreach ($required as $field) {
            if (!isset($body[$field]) || trim((string)$body[$field]) === '') {
                jsonResponse(['error' => 'Champ requis manquant: ' . $field], 422);
            }
        }

        $jourVoyage = trim((string)($body['jourVoyage'] ?? $body['date'] ?? ''));
        if ($jourVoyage === '') {
            jsonResponse(['error' => 'La date de voyage est obligatoire.'], 422);
        }

        $pdo->beginTransaction();
        $companyStmt = $pdo->prepare('SELECT id, fleet FROM companies WHERE company_name = ? FOR UPDATE');
        $companyStmt->execute([$body['compagnie']]);
        $company = $companyStmt->fetch();
        if (!$company) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Compagnie introuvable.'], 404);
        }

        $fleet = json_decode($company['fleet'], true) ?: [];
        $boatFound = false;
        $levelFound = false;
        $levelAvailable = false;
        $requestedBoat = trim((string)$body['bateau']);
        $requestedLevel = trim((string)$body['niveau']);
        foreach ($fleet as &$boat) {
            if (trim((string)($boat['name'] ?? '')) !== $requestedBoat) {
                continue;
            }
            $boatFound = true;
            foreach ($boat['levels'] ?? [] as &$level) {
                if (trim((string)($level['label'] ?? '')) !== $requestedLevel) {
                    continue;
                }
                $levelAvailable = true;
                if ((int)($level['seats'] ?? 0) > 0) {
                    $levelFound = true;
                }
            }
        }
        unset($boat, $level);
        if (!$levelFound) {
            $pdo->rollBack();
            $error = !$boatFound
                ? 'Le bateau sélectionné est introuvable.'
                : (!$levelAvailable ? 'La classe sélectionnée est introuvable pour ce bateau.' : 'La classe sélectionnée est complète.');
            jsonResponse(['error' => $error], 409);
        }

        $insert = $pdo->prepare('INSERT INTO reservations (client, email, whatsapp, telephone, compagnie, bateau, niveau, prix, reservation_date, reservation_time, statut, docs_soumis, pay_img) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $body['client'], $body['email'], $body['whatsapp'] ?? '', $body['telephone'] ?? '', $body['compagnie'],
            $body['bateau'], $body['niveau'], $body['prix'], $jourVoyage,
            $body['heure'] ?? date('H:i:s'), 'En attente de validation', json_encode($body['docsSoumis'] ?? [], JSON_UNESCAPED_UNICODE), $body['payImg'] ?? null,
        ]);
        $pdo->commit();
        jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()], 201);
    }

    if ($method === 'PATCH' && $action === 'reservation-status') {
        $armateur = requireArmateurSession();
        $status = trim((string)($body['statut'] ?? ''));
        if (!in_array($status, ['Rejeté'], true)) {
            jsonResponse(['error' => 'Utilisez le formulaire d’acceptation pour valider une réservation.'], 422);
        }
        $stmt = $pdo->prepare('UPDATE reservations SET statut = ? WHERE id = ? AND compagnie = ? AND bateau = ?');
        $stmt->execute([$status, (int)($body['id'] ?? 0), $armateur['companyName'], $armateur['boatName']]);
        jsonResponse(['success' => $stmt->rowCount() > 0]);
    }

    if ($method === 'POST' && $action === 'accept-reservation') {
        $armateur = requireArmateurSession();
        $logo = trim((string)($body['acceptanceLogo'] ?? ''));
        $message = trim((string)($body['message'] ?? ''));
        if ($logo === '' || $message === '') {
            jsonResponse(['error' => 'Le logo et le message d’acceptation sont obligatoires.'], 422);
        }

        $pdo->beginTransaction();
        $reservationId = (int)($body['id'] ?? 0);
        $reservationStmt = $pdo->prepare('SELECT id, compagnie, bateau, niveau FROM reservations WHERE id = ? AND compagnie = ? AND bateau = ? AND statut = ? LIMIT 1 FOR UPDATE');
        $reservationStmt->execute([$reservationId, $armateur['companyName'], $armateur['boatName'], 'En attente de validation']);
        $reservation = $reservationStmt->fetch();
        if (!$reservation) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Réservation introuvable ou déjà traitée.'], 404);
        }

        $companyStmt = $pdo->prepare('SELECT fleet FROM companies WHERE company_name = ? FOR UPDATE');
        $companyStmt->execute([$armateur['companyName']]);
        $company = $companyStmt->fetch();
        if (!$company) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Compagnie introuvable.'], 404);
        }

        $fleet = json_decode((string)$company['fleet'], true) ?: [];
        $seatReduced = false;
        $availableSeats = null;
        foreach ($fleet as &$boat) {
            if (trim((string)($boat['name'] ?? '')) !== $armateur['boatName']) {
                continue;
            }
            foreach ($boat['levels'] ?? [] as &$level) {
                if (trim((string)($level['label'] ?? '')) !== trim((string)$reservation['niveau'])) {
                    continue;
                }
                if ((int)($level['seats'] ?? 0) <= 0) {
                    $pdo->rollBack();
                    jsonResponse(['error' => 'Places non disponibles dans cette classe pour le bateau sélectionné.'], 409);
                }
                $level['seats'] = (int)$level['seats'] - 1;
                $availableSeats = $level['seats'];
                $seatReduced = true;
                break 2;
            }
        }
        unset($boat, $level);

        if (!$seatReduced) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Classe ou bateau non trouvé pour la réduction des places.'], 404);
        }

        $ticketCode = 'KVP-' . strtoupper(bin2hex(random_bytes(4)));
        $updateCompany = $pdo->prepare('UPDATE companies SET fleet = ? WHERE company_name = ?');
        $updateCompany->execute([json_encode($fleet, JSON_UNESCAPED_UNICODE), $armateur['companyName']]);

        $stmt = $pdo->prepare('UPDATE reservations SET statut = ?, acceptance_logo = ?, acceptance_message = ?, ticket_code = ?, rejection_reason = NULL, accepted_at = NOW() WHERE id = ? AND compagnie = ? AND bateau = ? AND statut = ?');
        $stmt->execute(['Validé', $logo, $message, $ticketCode, $reservationId, $armateur['companyName'], $armateur['boatName'], 'En attente de validation']);
        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            jsonResponse(['error' => 'La réservation a déjà été traitée.'], 409);
        }
        $pdo->commit();
        jsonResponse([
            'success' => true,
            'ticketCode' => $ticketCode,
            'message' => $message,
            'availableSeats' => $availableSeats,
        ]);
    }

    if ($method === 'PATCH' && $action === 'reservation-checkin') {
        $armateur = requireArmateurSession();
        $stmt = $pdo->prepare('UPDATE reservations SET checked_in = 1, statut = ? WHERE id = ? AND compagnie = ? AND bateau = ? AND statut = ?');
        $stmt->execute(['Arrivé', (int)($body['id'] ?? 0), $armateur['companyName'], $armateur['boatName'], 'Validé']);
        jsonResponse(['success' => $stmt->rowCount() > 0]);
    }

    if ($method === 'PATCH' && $action === 'company-status') {
        requireSuperAdminSession();
        $stmt = $pdo->prepare('UPDATE companies SET approved = ? WHERE id = ?');
        $stmt->execute([(int)!empty($body['approved']), (int)($body['id'] ?? 0)]);
        jsonResponse(['success' => $stmt->rowCount() > 0]);
    }

    if ($method === 'DELETE' && $action === 'company') {
        requireSuperAdminSession();
        $stmt = $pdo->prepare('DELETE FROM companies WHERE id = ?');
        $stmt->execute([(int)($_GET['id'] ?? 0)]);
        jsonResponse(['success' => $stmt->rowCount() > 0]);
    }

    jsonResponse(['error' => 'Action API inconnue.'], 404);
} catch (Throwable $error) {
    jsonResponse(['error' => 'Erreur serveur: ' . $error->getMessage()], 500);
}

function companyFromRow(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'companyName' => $row['company_name'],
        'payments' => json_decode($row['payments'], true) ?: [],
        'requiredDocs' => json_decode($row['required_docs'], true) ?: [],
        'fleet' => publicFleet(json_decode($row['fleet'], true) ?: []),
        'logo' => $row['logo'] ?? null,
        'approved' => (bool)$row['approved'],
    ];
}

function validatePaymentNumbers(array $payments): void
{
    foreach ($payments as $provider => $number) {
        $number = trim((string)$number);
        if ($number !== '' && !preg_match('/^\d+$/', $number)) {
            jsonResponse(['error' => 'Le numéro Mobile Money "' . $provider . '" doit contenir uniquement des chiffres.'], 422);
        }
    }
}

function publicFleet(array $fleet): array
{
    return array_map(static function (array $boat): array {
        unset($boat['password'], $boat['passwordHash']);
        return $boat;
    }, $fleet);
}

function reservationFromRow(array $row): array
{
    return [
        'id' => (int)$row['id'], 'client' => $row['client'], 'email' => $row['email'],
        'whatsapp' => $row['whatsapp'], 'telephone' => $row['telephone'], 'compagnie' => $row['compagnie'],
        'bateau' => $row['bateau'], 'niveau' => $row['niveau'], 'prix' => $row['prix'],
        'date' => $row['reservation_date'], 'heure' => $row['reservation_time'], 'statut' => $row['statut'],
        'docsSoumis' => json_decode($row['docs_soumis'], true) ?: [], 'payImg' => $row['pay_img'],
        'acceptanceLogo' => $row['acceptance_logo'] ?? null, 'ticketCode' => $row['ticket_code'] ?? null,
        'acceptanceMessage' => $row['acceptance_message'] ?? null,
        'rejectionReason' => $row['rejection_reason'] ?? null, 'checkedIn' => (bool)($row['checked_in'] ?? false),
    ];
}
