<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$name = isset($input['name']) ? trim($input['name']) : '';
$email = isset($input['email']) ? filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL) : '';
$phone_number = isset($input['phone_number']) ? trim($input['phone_number']) : '';
$user_type = isset($input['user_type']) ? trim($input['user_type']) : 'individual';

if (empty($name) || !$email || empty($phone_number)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'يرجى تقديم اسم وبريد الكتروني ورقم هاتف صالحين.']);
    exit();
}

if (!in_array($user_type, ['individual', 'broker', 'company'])) {
    $user_type = 'individual';
}

try {
    $db = DB::connect();
    
    // Check if user already exists
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update information if changed
        $updateStmt = $db->prepare("UPDATE users SET name = ?, phone_number = ?, user_type = ? WHERE id = ?");
        $updateStmt->execute([$name, $phone_number, $user_type, $user['id']]);
        $userId = $user['id'];
        $avatar = $user['avatar'];
    } else {
        // Create new user
        $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=10B981&color=fff';
        $insertStmt = $db->prepare("INSERT INTO users (name, email, phone_number, user_type, avatar) VALUES (?, ?, ?, ?, ?)");
        $insertStmt->execute([$name, $email, $phone_number, $user_type, $avatar]);
        $userId = $db->lastInsertId();
    }

    // Set PHP Session for the user (WebView session storage)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['success'] = 'تم تسجيل الدخول بنجاح عبر التطبيق!';

    echo json_encode([
        'success' => true,
        'message' => 'User authenticated successfully',
        'data' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'phone_number' => $phone_number,
            'user_type' => $user_type,
            'avatar' => $avatar
        ]
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
