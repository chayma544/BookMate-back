<?php
// jwt.php - JWT Configuration File

// Required for JWT token generation/validation
require_once __DIR__.'/vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Set headers (consistent with your db.php)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// 1. SECRET KEY CONFIGURATION
// ==============================================
// In production, store this securely (environment variable/secret manager)
define('JWT_SECRET_KEY', 'azertyuiop^$qsdfghjklmùwxcvbn,;:');

// 2. TOKEN EXPIRATION SETTINGS
// ==============================================
define('JWT_EXPIRE_ACCESS', 3600); // 1 hour in seconds
define('JWT_EXPIRE_REFRESH', 86400); // 24 hours for refresh tokens

// 3. JWT TOKEN GENERATION
// ==============================================
/**
 * Generates a JWT token
 * @param int $userId - The user's database ID
 * @param string $email - User's email
 * @param string $type - 'access' or 'refresh'
 * @return string - The generated JWT token
 */
function generateJWT($userId, $email, $type = 'access') {
    $issuedAt = time();
    $expirationTime = $issuedAt + 
        ($type === 'access' ? JWT_EXPIRE_ACCESS : JWT_EXPIRE_REFRESH);
    
    $payload = [
        'iat'  => $issuedAt,         // Issued at
        'exp'  => $expirationTime,   // Expiration time
        'sub'  => $userId,           // Subject (user ID)
        'email' => $email,            // User email
        'type' => $type              // Token type
    ];
    
    return JWT::encode($payload, JWT_SECRET_KEY, 'HS256');
}

// 4. JWT TOKEN VALIDATION
// ==============================================
/**
 * Validates a JWT token
 * @param string $jwt - The JWT token to validate
 * @return object|false - Decoded token data or false if invalid
 */
function validateJWT($jwt) {
    try {
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        // Log error for debugging (remove in production)
        error_log("JWT Validation Error: " . $e->getMessage());
        return false;
    }
}

// 5. REFRESH TOKEN HANDLING
// ==============================================
/**
 * Refreshes an access token using a refresh token
 * @param string $refreshToken - Valid refresh token
 * @return array|false - New tokens or false if invalid
 */
function refreshTokens($refreshToken) {
    $decoded = validateJWT($refreshToken);
    
    if (!$decoded || $decoded->type !== 'refresh') {
        return false;
    }
    
    return [
        'access_token' => generateJWT($decoded->sub, $decoded->email, 'access'),
        'refresh_token' => generateJWT($decoded->sub, $decoded->email, 'refresh')
    ];
}

// 6. HELPER FUNCTION TO GET JWT FROM HEADERS
// ==============================================
/**
 * Extracts JWT from Authorization header
 * @return string|null - The JWT token or null if not found
 */
function getBearerToken() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        }
    }
    
    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        return $matches[1];
    }
    
    return null;
}
?>