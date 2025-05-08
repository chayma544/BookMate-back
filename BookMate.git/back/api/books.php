<?php

/**
 * Book API with Role-Based Access Control
 * 
 * Features:
 * - Session-based authentication
 * - Role-based permissions (admin/user)
 * - Rate limiting
 * - Input sanitization
 * - CORS support
 * - Image handling
 */

// Start session to track logged-in users
session_start();

// Configuration

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB

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
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Database connection
require_once __DIR__ . '/../config/db.php';

/**
 * Sanitize input data to prevent XSS attacks
 * @param mixed $data Input data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Normalize book data structure for consistent API responses
 * @param array $book Book data from database
 * @return array Normalized book data
 */
function normalizeBook(array $book): array {
    return [
        'id' => $book['book_id'],
        'title' => $book['title'],
        'author' => $book['author_name'],
        'language' => $book['language'],
        'genre' => $book['genre'],
        'releaseDate' => $book['release_date'],
        'status' => $book['status'],
        'coverImage' => $book['URL'],
        'availability' => (bool) $book['availability'],
        'userId' => $book['user_id']
    ];
}

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication Check - All endpoints require authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Please log in']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

/**
 * Rate Limiting (100 requests per minute per user)
 */


// Get user role from database
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$current_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    $isAdmin = ($user['role'] === 'admin');
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error while checking user role']);
    exit();
}

/**
 * Image Request Handler - Public endpoint (doesn't require auth)
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['image'])) {
    $imagePath = realpath(UPLOAD_DIR . sanitizeInput($_GET['image']));
    
    // Security check to prevent directory traversal
    if (strpos($imagePath, realpath(UPLOAD_DIR)) !== 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
    
    if (file_exists($imagePath)) {
        $mimeType = mime_content_type($imagePath);
        header('Content-Type: ' . $mimeType);
        readfile($imagePath);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
    }
    exit();
}

// Main request handler
try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            /**
             * GET Endpoints:
             * - Get user's own books (for "My Library")
             * - Get specific book details
             * - Search books
             * - Get discoverable books (not owned by user)
             */
            
            if (isset($_GET['own_books']) && $_GET['own_books'] === 'true') {
                // Get user's own books
                if ($isAdmin) {
                    $stmt = $pdo->query("SELECT * FROM livre");
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM livre WHERE user_id = ?");
                    $stmt->execute([$current_user_id]);
                }
                $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(array_map('normalizeBook', $books));
            } 
            elseif (isset($_GET['book_id'])) {
                // Get specific book details
                $stmt = $pdo->prepare("SELECT * FROM livre WHERE book_id = ?");
                $stmt->execute([$_GET['book_id']]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$book) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Book not found']);
                    break;
                }
                
                // Check permissions
                if (!$isAdmin && $book['user_id'] != $current_user_id && !$book['availability']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied']);
                    break;
                }
                
                echo json_encode(normalizeBook($book));
            }
            elseif (isset($_GET['title']) || isset($_GET['genre']) || isset($_GET['author'])) {
                // Search books with filters
                $title = isset($_GET['title']) ? "%".sanitizeInput($_GET['title'])."%" : "%";
                $genre = isset($_GET['genre']) ? sanitizeInput($_GET['genre']) : "%";
                $author = isset($_GET['author']) ? "%".sanitizeInput($_GET['author'])."%" : "%";

                if ($isAdmin) {
                    $query = "SELECT * FROM livre WHERE (title LIKE :title AND author_name LIKE :author AND genre LIKE :genre)";
                } else {
                    $query = "SELECT * FROM livre WHERE (title LIKE :title AND author_name LIKE :author AND genre LIKE :genre AND (user_id != :user_id AND availability = 1))";
                }

                $stmt = $pdo->prepare($query);
                $params = [
                    ':title' => $title,
                    ':author' => $author,
                    ':genre' => $genre
                ];
                
                if (!$isAdmin) {
                    $params[':user_id'] = $current_user_id;
                }

                $stmt->execute($params);
                $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(array_map('normalizeBook', $books));
            } 
            else {
                // Default case - Get discoverable books (not owned by user)
                if ($isAdmin) {
                    $stmt = $pdo->query("SELECT * FROM livre");
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM livre WHERE user_id != ? AND availability = 1");
                    $stmt->execute([$current_user_id]);
                }
                $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(array_map('normalizeBook', $books));
            }
            break;

        case 'POST':
            /**
             * Add a new book
             * Accessible to both admin and regular users
             */
            $requestData = json_decode(file_get_contents("php://input"), true);

            // Validate required fields
            if (empty($requestData['title']) || empty($requestData['authorName']) || empty($requestData['coverImage'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Title and author name are required']);
                break;
            }

            // Prepare book data
            $title = sanitizeInput($requestData['title']);
            $authorName = sanitizeInput($requestData['authorName']);
            $availability = isset($requestData['availability']) ? (int) $requestData['availability'] : 1;
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO livre 
                    (title, author_name, language, genre, release_date, status, URL, dateAjout, availability, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
                ");

                $stmt->execute([
                    $title,
                    $authorName,
                    sanitizeInput($requestData['language'] ?? 'Unknown'),
                    sanitizeInput($requestData['genre'] ?? 'Other'),
                    sanitizeInput($requestData['releaseDate'] ?? null),
                    sanitizeInput($requestData['status'] ?? 'good'),
                    sanitizeInput($requestData['coverImage'] ),
                    $availability,
                    $current_user_id
                ]);

                // Return the created book
                $bookId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("SELECT * FROM livre WHERE book_id = ?");
                $stmt->execute([$bookId]);
                $newBook = $stmt->fetch(PDO::FETCH_ASSOC);

                http_response_code(201);
                echo json_encode([
                    'book' => normalizeBook($newBook),
                    'message' => 'Book added successfully'
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to add book', 'details' => $e->getMessage()]);
            }
            break;

        case 'PUT':
            /**
             * Update an existing book
             * Users can only update their own books
             * Admins can update any book
             */
            $requestData = sanitizeInput(json_decode(file_get_contents("php://input"), true));
            
            if (empty($requestData['book_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID is required']);
                break;
            }

            // Verify book exists and check ownership
            $stmt = $pdo->prepare("SELECT user_id FROM livre WHERE book_id = ?");
            $stmt->execute([$requestData['book_id']]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$book) {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found']);
                break;
            }
            
            // Check permissions
            if (!$isAdmin && $book['user_id'] != $current_user_id) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only modify your own books']);
                break;
            }

            // Prepare update fields
            $updateFields = [];
            $params = [];
            
            $allowedFields = [
                'title', 'author_name', 'language', 'genre',
                'release_date', 'status', 'availability', 'URL'
            ];
        
            foreach ($allowedFields as $field) {
                if (isset($requestData[$field])) {
                    $updateFields[] = "`$field` = ?";
                    $params[] = sanitizeInput($requestData[$field]);
                }
            }
        
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields provided for update']);
                break;
            }
        
            $params[] = $requestData['book_id'];
        
            // Execute update
            try {
                $query = "UPDATE livre SET " . implode(', ', $updateFields) . " WHERE book_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
            
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['message' => 'Book updated successfully']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'No changes made']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update book', 'details' => $e->getMessage()]);
            }
            break;

        case 'DELETE':
            /**
             * Delete a book
             * Users can only delete their own books
             * Admins can delete any book
             */
            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID is required']);
                break;
            }

            $bookId = sanitizeInput($_GET['id']);

            // Verify book exists and check ownership
            $stmt = $pdo->prepare("SELECT user_id, URL FROM livre WHERE book_id = ?");
            $stmt->execute([$bookId]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$book) {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found']);
                break;
            }
            
            // Check permissions
            if (!$isAdmin && $book['user_id'] != $current_user_id) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only delete your own books']);
                break;
            }

            // Delete book
            try {
                $stmt = $pdo->prepare("DELETE FROM livre WHERE book_id = ?");
                $stmt->execute([$bookId]);
                
                if ($stmt->rowCount() > 0) {
                    // Delete associated image if exists
                    if (!empty($book['URL'])) {
                        $imagePath = realpath(UPLOAD_DIR . basename($book['URL']));
                        if (file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                    }
                    
                    echo json_encode(['message' => 'Book deleted successfully']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Book not found']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete book', 'details' => $e->getMessage()]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
    ]);
}
?>
