<?php
// Start session securely
session_start();
session_regenerate_id(true);

// Configuration
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'production');
define('UPLOAD_DIR', realpath(__DIR__ . '/../uploads/') . '/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('RATE_LIMIT', 100); // Requests per minute

// Create secure upload directory
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    file_put_contents(UPLOAD_DIR . '.htaccess', "Deny from all");
}

// Security headers
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// CORS for Angular frontend
header("Access-Control-Allow-Origin: https://localhost:4200");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Database connection
require_once __DIR__ . '/../config/db.php';

/**
 * Generate CSRF token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate and handle file upload
 * @return string The filename of the uploaded file
 */
function handleFileUpload(array $file): string {
    // Error check
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error: ' . $file['error']);
    }

    // Size validation
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException('File exceeds 2MB limit');
    }

    // MIME type validation
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_MIME_TYPES)) {
        throw new RuntimeException('Invalid file type');
    }

    // Extension validation
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        throw new RuntimeException('Invalid file extension');
    }

    // Generate secure filename
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = UPLOAD_DIR . $filename;

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to save file');
    }

    return $filename;
}

/**
 * Apply rate limiting
 */
function applyRateLimiting(string $key): void {
    $currentMinute = (int)(time() / 60);
    $rateLimitKey = 'rate_' . $key . '_' . $currentMinute;

    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = 0;
    }

    if (++$_SESSION[$rateLimitKey] > RATE_LIMIT) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests']);
        exit();
    }
}

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication check
$method = $_SERVER['REQUEST_METHOD'];
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_role = $_SESSION['role'] ?? 'user';

if ($method !== 'GET' && !$current_user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Apply rate limiting for authenticated users
if ($current_user_id) {
    applyRateLimiting('user_' . $current_user_id);
}

// Main API Handler
try {
    switch ($method) {
        case 'GET':
            // Get single book
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT * FROM livre WHERE book_id = ?");
                $stmt->execute([$_GET['id']]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$book) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Book not found']);
                    break;
                }

                // Add image URL if exists
                if (!empty($book['image_path'])) {
                    $book['image_url'] = "/books/image/" . basename($book['image_path']);
                }

                echo json_encode(['data' => $book]);
                break;
            }
            
            // Search books
            if (isset($_GET['search'])) {
                $search = '%' . sanitizeInput($_GET['search']) . '%';
                $stmt = $pdo->prepare("
                    SELECT * FROM livre 
                    WHERE (title LIKE ? OR author_name LIKE ? OR genre LIKE ?)
                    AND availability = 1
                    " . ($current_user_id ? "AND user_id != ?" : "") . "
                    LIMIT 50
                ");

                $params = [$search, $search, $search];
                if ($current_user_id) {
                    $params[] = $current_user_id;
                }

                $stmt->execute($params);
                $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Add image URLs
                foreach ($books as &$book) {
                    if (!empty($book['image_path'])) {
                        $book['image_url'] = "/books/image/" . basename($book['image_path']);
                    }
                }

                echo json_encode(['data' => $books]);
                break;
            }
            
            // Get all books
            $query = "SELECT * FROM livre";
            $params = [];
            
            if ($current_user_role !== 'admin') {
                $query .= " WHERE availability = 1";
                if ($current_user_id) {
                    $query .= " AND user_id != ?";
                    $params[] = $current_user_id;
                }
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($books as &$book) {
                if (!empty($book['image_path'])) {
                    $book['image_url'] = "/books/image/" . basename($book['image_path']);
                }
            }

            echo json_encode(['data' => $books]);
            break;

        case 'POST':
            // Verify CSRF token
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
            if (!verifyCsrfToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                break;
            }

            // Get and validate input
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isJson = strpos($contentType, 'application/json') !== false;
            
            if ($isJson) {
                $requestData = json_decode(file_get_contents('php://input'), true);
                $imageData = null;
            } else {
                $requestData = $_POST;
                $imageData = $_FILES['image'] ?? null;
            }

            $requestData = sanitizeInput($requestData);

            // Validate required fields
            $required = ['title', 'author_name'];
            foreach ($required as $field) {
                if (empty($requestData[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    break 2;
                }
            }

            // Start transaction
            $pdo->beginTransaction();

            try {
                // Check for duplicate
                $stmt = $pdo->prepare("
                    SELECT 1 FROM livre 
                    WHERE title = ? AND author_name = ? AND user_id = ?
                ");
                $stmt->execute([
                    $requestData['title'],
                    $requestData['author_name'],
                    $current_user_id
                ]);

                if ($stmt->rowCount() > 0) {
                    throw new RuntimeException('Book already exists');
                }

                // Handle image upload
                $imagePath = null;
                if ($imageData && $imageData['error'] === UPLOAD_ERR_OK) {
                    $filename = handleFileUpload($imageData);
                    $imagePath = 'uploads/' . $filename;
                }

                // Insert book
                $stmt = $pdo->prepare("
                    INSERT INTO livre (
                        title, author_name, language, genre, 
                        release_date, status, dateAjout, 
                        availability, user_id, image_path
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
                ");

                $stmt->execute([
                    $requestData['title'],
                    $requestData['author_name'],
                    $requestData['language'] ?? 'Unknown',
                    $requestData['genre'] ?? 'Other',
                    $requestData['release_date'] ?? null,
                    $requestData['status'] ?? 'good',
                    $requestData['availability'] ?? 1,
                    $current_user_id,
                    $imagePath
                ]);

                $bookId = $pdo->lastInsertId();
                $pdo->commit();

                // Return created book
                $stmt = $pdo->prepare("SELECT * FROM livre WHERE book_id = ?");
                $stmt->execute([$bookId]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!empty($book['image_path'])) {
                    $book['image_url'] = "/books/image/" . basename($book['image_path']);
                }

                http_response_code(201);
                echo json_encode([
                    'data' => $book,
                    'message' => 'Book created successfully'
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                if (isset($filename)) {
                    @unlink(UPLOAD_DIR . $filename);
                }
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        case 'PUT':
            // Verify CSRF token
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!verifyCsrfToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                break;
            }

            $requestData = json_decode(file_get_contents('php://input'), true);
            $requestData = sanitizeInput($requestData);

            if (empty($requestData['book_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID required']);
                break;
            }

            $pdo->beginTransaction();

            try {
                // Verify ownership
                $stmt = $pdo->prepare("
                    SELECT image_path FROM livre 
                    WHERE book_id = ? AND user_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$requestData['book_id'], $current_user_id]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$current) {
                    throw new RuntimeException('Book not found or access denied');
                }

                // Build update
                $updates = [];
                $params = [];
                $allowedFields = [
                    'title', 'author_name', 'language', 'genre',
                    'release_date', 'status', 'availability'
                ];

                foreach ($allowedFields as $field) {
                    if (array_key_exists($field, $requestData)) {
                        $updates[] = "$field = ?";
                        $params[] = $requestData[$field];
                    }
                }

                if (empty($updates)) {
                    throw new RuntimeException('No fields to update');
                }

                $params[] = $requestData['book_id'];

                // Execute update
                $query = "UPDATE livre SET " . implode(', ', $updates) . " WHERE book_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);

                $pdo->commit();
                echo json_encode(['message' => 'Book updated successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        case 'DELETE':
            // Verify CSRF token
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!verifyCsrfToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                break;
            }

            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID required']);
                break;
            }

            $pdo->beginTransaction();

            try {
                // Verify ownership and get image path
                $stmt = $pdo->prepare("
                    SELECT image_path FROM livre 
                    WHERE book_id = ? AND user_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$_GET['id'], $current_user_id]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$book) {
                    throw new RuntimeException('Book not found or access denied');
                }

                // Delete book
                $stmt = $pdo->prepare("DELETE FROM livre WHERE book_id = ?");
                $stmt->execute([$_GET['id']]);

                // Delete image if exists
                if (!empty($book['image_path'])) {
                    $imageFile = UPLOAD_DIR . basename($book['image_path']);
                    if (file_exists($imageFile)) {
                        unlink($imageFile);
                    }
                }

                $pdo->commit();
                echo json_encode(['message' => 'Book deleted successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Server Error: " . $e->getMessage());
    echo json_encode(['error' => 'Server error']);
}
?>