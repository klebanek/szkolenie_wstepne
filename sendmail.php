<?php
/**
 * ELMAR - Szkolenie HACCP
 * Backend do wysyłki powiadomień o ukończeniu szkolenia z pełnym raportem
 * 
 * @author INOVIT - Krzysztof Klebaniuk
 * @version 2.0
 */

// Nagłówki CORS i JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Obsługa OPTIONS request (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Tylko POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Metoda niedozwolona. Użyj POST.'
    ]);
    exit();
}

// Odbierz dane JSON z JavaScript
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Walidacja danych
if (!isset($data['firstName']) || !isset($data['lastName']) || !isset($data['trainingDate'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Brak wymaganych danych (firstName, lastName, trainingDate)'
    ]);
    exit();
}

// Sanityzacja danych podstawowych
$firstName = htmlspecialchars(trim($data['firstName']), ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars(trim($data['lastName']), ENT_QUOTES, 'UTF-8');
$trainingDate = htmlspecialchars(trim($data['trainingDate']), ENT_QUOTES, 'UTF-8');

// Dane dodatkowe z testu
$score = isset($data['score']) ? (int)$data['score'] : 0;
$total = isset($data['total']) ? (int)$data['total'] : 12;
$duration = isset($data['duration']) ? (int)$data['duration'] : 0;
$wrong = isset($data['wrong']) ? $data['wrong'] : [];
$trainingEndTime = isset($data['trainingEndTime']) ? $data['trainingEndTime'] : date('c');

// Formatowanie dat
$dateObj = new DateTime($trainingDate);
$formattedDate = $dateObj->format('d.m.Y');

$endDateTime = new DateTime($trainingEndTime);
$endDateTimeFormatted = $endDateTime->format('d.m.Y H:i:s');

// Formatowanie czasu trwania
$minutes = floor($duration / 60);
$seconds = $duration % 60;
$durationStr = sprintf("%02d:%02d", $minutes, $seconds);

// Procent
$percentage = $total > 0 ? round(($score/$total)*100) : 0;

// Przygotuj listę błędnych odpowiedzi (HTML)
$wrongListHtml = "";
if (empty($wrong)) {
    $wrongListHtml = "<li style='color: green;'>✅ BRAK - wszystkie odpowiedzi poprawne!</li>";
} else {
    foreach($wrong as $item) {
        $nr = isset($item['nr']) ? $item['nr'] : '?';
        $user = isset($item['user']) ? htmlspecialchars($item['user'], ENT_QUOTES, 'UTF-8') : 'brak';
        $correct = isset($item['correct']) ? htmlspecialchars($item['correct'], ENT_QUOTES, 'UTF-8') : '?';
        $wrongListHtml .= "<li>❌ <strong>Pytanie $nr:</strong> użytkownik wybrał '<strong>$user</strong>', poprawna odpowiedź to '<strong>$correct</strong>'</li>";
    }
}

// Przygotuj listę błędnych odpowiedzi (Plain Text)
$wrongListPlain = "";
if (empty($wrong)) {
    $wrongListPlain = "- BRAK - wszystkie odpowiedzi poprawne!\n";
} else {
    foreach($wrong as $item) {
        $nr = isset($item['nr']) ? $item['nr'] : '?';
        $user = isset($item['user']) ? htmlspecialchars($item['user'], ENT_QUOTES, 'UTF-8') : 'brak';
        $correct = isset($item['correct']) ? htmlspecialchars($item['correct'], ENT_QUOTES, 'UTF-8') : '?';
        $wrongListPlain .= "  - Pytanie $nr: użytkownik wybrał '$user', poprawna odpowiedź to '$correct'\n";
    }
}

// Konfiguracja emaila
$to = "kontakt@inovit.com.pl";
$subject = "Szkolenie dla nowego pracownika - $firstName $lastName";

// Treść emaila (HTML)
$messageHtml = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #00FFFF 0%, #0066FF 50%, #8A2BE2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border: 1px solid #ddd;
        }
        .info-box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #00FFFF;
            border-radius: 5px;
        }
        .info-row {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: bold;
            color: #0066FF;
            display: inline-block;
            width: 180px;
        }
        .footer {
            background: #333;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 10px 10px;
            font-size: 12px;
        }
        .success-badge {
            background: #d4edda;
            border: 2px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            color: #155724;
            font-size: 18px;
        }
        .errors-section {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .errors-section h4 {
            margin-top: 0;
            color: #856404;
        }
        .errors-section ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .errors-section li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🐟 ELMAR</h1>
        <h2>Raport Ukończenia Szkolenia Wstępnego</h2>
    </div>

    <div class='content'>
        <p><strong>Witaj Krzysztof!</strong></p>

        <p>Nowy pracownik pomyślnie ukończył szkolenie wstępne z bezpieczeństwa żywności w przetwórstwie ryb.</p>

        <div class='success-badge'>
            ✅ SZKOLENIE ZALICZONE
        </div>

        <div class='info-box'>
            <h3>📋 Dane pracownika:</h3>
            <div class='info-row'>
                <span class='info-label'>Imię i nazwisko:</span>
                <span>$firstName $lastName</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>Data rozpoczęcia:</span>
                <span>$formattedDate</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>Data i godzina zakończenia:</span>
                <span>$endDateTimeFormatted</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>Czas trwania:</span>
                <span>$durationStr min</span>
            </div>
        </div>

        <div class='info-box'>
            <h3>📊 Wynik testu końcowego:</h3>
            <div class='info-row'>
                <span class='info-label'>Liczba poprawnych odpowiedzi:</span>
                <span style='font-weight: bold; color: #28a745;'>$score/$total</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>Procent poprawnych:</span>
                <span style='font-weight: bold; color: #28a745;'>$percentage%</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>Status:</span>
                <span style='color: green; font-weight: bold;'>✅ ZALICZONE</span>
            </div>
        </div>

        <div class='errors-section'>
            <h4>📝 Szczegóły odpowiedzi:</h4>
            <ul>
                $wrongListHtml
            </ul>
        </div>

        <h3>📚 Zakres szkolenia:</h3>
        <ul>
            <li>Podstawa prawna szkolenia (Rozp. WE 852/2004)</li>
            <li>Zasady przechowywania ryb (temperatura 4°C)</li>
            <li>Wymogi zdrowotne pracowników</li>
            <li>Higiena rąk i odzież ochronna</li>
            <li>Czystość stanowiska pracy</li>
            <li>Zabronione praktyki w produkcji</li>
        </ul>

        <p style='margin-top: 30px;'>
            <strong>System szkoleniowy:</strong> 
            <a href='https://inovit.com.pl'>INOVIT Training System</a>
        </p>
    </div>

    <div class='footer'>
        <strong>P.H.U. ELMAR Lesiuk</strong><br>
        Zakład Przetwórstwa Ryb<br>
        ul. Droga Wojskowa 39, 21-500 Biała Podlaska<br>
        <br>
        🌐 <a href='https://inovit.com.pl' style='color: #00FFFF;'>www.inovit.com.pl</a> | 
        📧 kontakt@inovit.com.pl | 
        📞 +48 575-757-638
    </div>
</body>
</html>
";

// Treść emaila (plain text - fallback)
$messagePlain = "
========================================
ELMAR - RAPORT UKOŃCZENIA SZKOLENIA
========================================

Witaj Krzysztof!

Nowy pracownik pomyślnie ukończył szkolenie wstępne z bezpieczeństwa żywności w przetwórstwie ryb.

STATUS: SZKOLENIE ZALICZONE ✅

DANE PRACOWNIKA:
- Imię i nazwisko: $firstName $lastName
- Data rozpoczęcia: $formattedDate
- Data i godzina zakończenia: $endDateTimeFormatted
- Czas trwania: $durationStr min

WYNIK TESTU KOŃCOWEGO:
- Liczba poprawnych odpowiedzi: $score/$total
- Procent poprawnych: $percentage%
- Status: ZALICZONE

SZCZEGÓŁY ODPOWIEDZI:
$wrongListPlain

ZAKRES SZKOLENIA:
- Podstawa prawna szkolenia (Rozp. WE 852/2004)
- Zasady przechowywania ryb (temperatura 4°C)
- Wymogi zdrowotne pracowników
- Higiena rąk i odzież ochronna
- Czystość stanowiska pracy
- Zabronione praktyki w produkcji

========================================
P.H.U. ELMAR Lesiuk
Zakład Przetwórstwa Ryb
ul. Droga Wojskowa 39, 21-500 Biała Podlaska

www.inovit.com.pl | kontakt@inovit.com.pl | +48 575-757-638
========================================
";

// Separator dla multipart
$separator = md5(time());

// Nagłówki emaila
$headers = "From: ELMAR Szkolenia <szkolenia@inovit.com.pl>\r\n";
$headers .= "Reply-To: kontakt@inovit.com.pl\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"$separator\"\r\n";

// Budowa wiadomości multipart
$message = "--$separator\r\n";
$message .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
$message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$message .= $messagePlain . "\r\n";
$message .= "--$separator\r\n";
$message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
$message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$message .= $messageHtml . "\r\n";
$message .= "--$separator--";

// Wysyłka emaila
$mailSent = mail($to, $subject, $message, $headers);

// Logowanie do pliku
if ($mailSent) {
    $logEntry = date('Y-m-d H:i:s') . " | SUCCESS | $firstName $lastName | Wynik: $score/$total ($percentage%) | Czas: $durationStr\n";
    file_put_contents('training-logs.txt', $logEntry, FILE_APPEND);
}

// Odpowiedź JSON
if ($mailSent) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Email wysłany pomyślnie'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Błąd wysyłania emaila'
    ]);
}
?>