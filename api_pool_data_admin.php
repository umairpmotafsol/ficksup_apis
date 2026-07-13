<?php
// ─────────────────────────────────────────────────────────────────────────────
// api_pool_data_admin.php
// Admin APIs for Pool Types, Shapes, Models & Gel Coats
// Images are handled as real file uploads (multipart/form-data), NOT as a
// path/string field. Upload field name expected: "image"
//
// Endpoints (all under .../admin/...):
//
// GET    /admin/pool-types
// GET    /admin/pool-types/{id}
// POST   /admin/pool-types            (multipart/form-data, field: image)
// POST   /admin/pool-types/{id}       (update - multipart/form-data)
// DELETE /admin/pool-types/{id}
//
// Same pattern for: pool-shapes, pool-models, gel-coats
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config.php';

// Absolute path on disk where uploaded files are stored
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Relative path stored in DB / returned in API responses
// (your front-end / public API already prefixes this with the CDN base URL)
define('UPLOAD_URL_PREFIX', 'uploads/');

define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB

$method = $_SERVER['REQUEST_METHOD'];
$requestPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $requestPath);

// Find "admin" in the URL
$adminIndex = array_search('admin', $segments);
if ($adminIndex === false) {
    jsonResponse(false, [], 'Admin endpoint not found', 404);
}

$resource = $segments[$adminIndex + 1] ?? '';
$id = $segments[$adminIndex + 2] ?? null;

switch ($resource) {

    case 'pool-types':
        if ($id !== null) {
            handlePoolTypeByIdAdmin($method, $id);
        } else {
            handlePoolTypesAdmin($method);
        }
        break;

    case 'pool-shapes':
        if ($id !== null) {
            handlePoolShapeByIdAdmin($method, $id);
        } else {
            handlePoolShapesAdmin($method);
        }
        break;

    case 'pool-models':
        if ($id !== null) {
            handlePoolModelByIdAdmin($method, $id);
        } else {
            handlePoolModelsAdmin($method);
        }
        break;

    case 'gel-coats':
        if ($id !== null) {
            handleGelCoatByIdAdmin($method, $id);
        } else {
            handleGelCoatsAdmin($method);
        }
        break;

    default:
        jsonResponse(false, [], 'Admin endpoint not found', 404);
}

// ─────────────────────────────────────────────────────────────────────────────
// Upload helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Handles a single uploaded file from $_FILES.
 * Returns the relative path to store in DB, or null if no file was sent.
 * Throws Exception on validation failure.
 */
function handleUploadedImage(string $fieldName = 'image'): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error (code ' . $file['error'] . ')');
    }

    if ($file['size'] > MAX_IMAGE_SIZE) {
        throw new Exception('Image exceeds max allowed size of 5MB');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        throw new Exception('Invalid image type: ' . $mimeType);
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $ext = $extMap[$mimeType];

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to save uploaded file');
    }

    return UPLOAD_URL_PREFIX . $filename;
}

/**
 * Deletes a previously stored asset (by its relative path) from disk, if it
 * lives inside our uploads folder. Safe no-op if file doesn't exist.
 */
function deleteStoredAsset(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    // Only ever delete files we manage inside uploads/
    if (strpos($relativePath, UPLOAD_URL_PREFIX) !== 0) {
        return;
    }

    $fullPath = __DIR__ . '/' . $relativePath;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

/**
 * Reads a non-file text field from either $_POST (multipart) — works for
 * both create and "update via POST" requests.
 */
function field(string $key, $default = null)
{
    return isset($_POST[$key]) && $_POST[$key] !== '' ? $_POST[$key] : $default;
}

// ─────────────────────────────────────────────────────────────────────────────
// Pool Types
// ─────────────────────────────────────────────────────────────────────────────

function handlePoolTypesAdmin(string $method)
{
    global $pdo;

    switch ($method) {

        case 'GET':
            try {
                $stmt = $pdo->prepare("
                    SELECT id, label, asset_path, description, is_active
                    FROM pool_types
                    ORDER BY id ASC
                ");
                $stmt->execute();
                jsonResponse(true, $stmt->fetchAll(), 'Pool types fetched successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'POST':
            try {
                $label = field('label');
                $description = field('description');

                if (!$label) {
                    jsonResponse(false, [], 'label is required', 400);
                }

                $assetPath = handleUploadedImage('image');

                $stmt = $pdo->prepare("
                    INSERT INTO pool_types (label, asset_path, description, is_active)
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute([$label, $assetPath, $description]);

                jsonResponse(true, ['id' => (int)$pdo->lastInsertId()], 'Pool type created successfully', 201);
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        default:
            jsonResponse(false, [], 'Method not allowed', 405);
    }
}

function handlePoolTypeByIdAdmin(string $method, string $id)
{
    global $pdo;

    $id = (int)$id;

    switch ($method) {

        case 'GET':
            try {
                $stmt = $pdo->prepare("SELECT id, label, asset_path, description, is_active FROM pool_types WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();

                if (!$row) {
                    jsonResponse(false, [], 'Pool type not found', 404);
                }

                jsonResponse(true, $row, 'Pool type fetched successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'POST': // update
            try {
                $stmt = $pdo->prepare("SELECT asset_path FROM pool_types WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    jsonResponse(false, [], 'Pool type not found', 404);
                }

                $label = field('label');
                $description = field('description');
                $isActive = field('is_active');

                $newAssetPath = handleUploadedImage('image');

                $fields = [];
                $params = [];

                if ($label !== null) { $fields[] = 'label = ?'; $params[] = $label; }
                if ($description !== null) { $fields[] = 'description = ?'; $params[] = $description; }
                if ($isActive !== null) { $fields[] = 'is_active = ?'; $params[] = (int)$isActive; }

                if ($newAssetPath !== null) {
                    $fields[] = 'asset_path = ?';
                    $params[] = $newAssetPath;
                }

                if (empty($fields)) {
                    jsonResponse(false, [], 'No fields to update', 400);
                }

                $params[] = $id;

                $stmt = $pdo->prepare("UPDATE pool_types SET " . implode(', ', $fields) . " WHERE id = ?");
                $stmt->execute($params);

                // Only delete the old image once the new one is safely saved & DB updated
                if ($newAssetPath !== null) {
                    deleteStoredAsset($existing['asset_path']);
                }

                jsonResponse(true, [], 'Pool type updated successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'DELETE':
            try {
                $stmt = $pdo->prepare("SELECT asset_path FROM pool_types WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    jsonResponse(false, [], 'Pool type not found', 404);
                }

                $stmt = $pdo->prepare("DELETE FROM pool_types WHERE id = ?");
                $stmt->execute([$id]);

                deleteStoredAsset($existing['asset_path']);

                jsonResponse(true, [], 'Pool type deleted successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        default:
            jsonResponse(false, [], 'Method not allowed', 405);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Pool Shapes
// ─────────────────────────────────────────────────────────────────────────────

function handlePoolShapesAdmin(string $method)
{
    global $pdo;

    switch ($method) {

        case 'GET':
            try {
                $stmt = $pdo->prepare("
                    SELECT id, label, asset_path, description, is_active
                    FROM pool_shapes
                    ORDER BY id ASC
                ");
                $stmt->execute();
                jsonResponse(true, $stmt->fetchAll(), 'Pool shapes fetched successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'POST':
            try {
                $label = field('label');
                $description = field('description');

                if (!$label) {
                    jsonResponse(false, [], 'label is required', 400);
                }

                $assetPath = handleUploadedImage('image');

                $stmt = $pdo->prepare("
                    INSERT INTO pool_shapes (label, asset_path, description, is_active)
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute([$label, $assetPath, $description]);

                jsonResponse(true, ['id' => (int)$pdo->lastInsertId()], 'Pool shape created successfully', 201);
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        default:
            jsonResponse(false, [], 'Method not allowed', 405);
    }
}

function handlePoolShapeByIdAdmin(string $method, string $id)
{
    global $pdo;

    $id = (int)$id;

    switch ($method) {

        case 'GET':
            try {
                $stmt = $pdo->prepare("SELECT id, label, asset_path, description, is_active FROM pool_shapes WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();

                if (!$row) {
                    jsonResponse(false, [], 'Pool shape not found', 404);
                }

                jsonResponse(true, $row, 'Pool shape fetched successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'POST': // update
            try {
                $stmt = $pdo->prepare("SELECT asset_path FROM pool_shapes WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    jsonResponse(false, [], 'Pool shape not found', 404);
                }

                $label = field('label');
                $description = field('description');
                $isActive = field('is_active');

                $newAssetPath = handleUploadedImage('image');

                $fields = [];
                $params = [];

                if ($label !== null) { $fields[] = 'label = ?'; $params[] = $label; }
                if ($description !== null) { $fields[] = 'description = ?'; $params[] = $description; }
                if ($isActive !== null) { $fields[] = 'is_active = ?'; $params[] = (int)$isActive; }

                if ($newAssetPath !== null) {
                    $fields[] = 'asset_path = ?';
                    $params[] = $newAssetPath;
                }

                if (empty($fields)) {
                    jsonResponse(false, [], 'No fields to update', 400);
                }

                $params[] = $id;

                $stmt = $pdo->prepare("UPDATE pool_shapes SET " . implode(', ', $fields) . " WHERE id = ?");
                $stmt->execute($params);

                if ($newAssetPath !== null) {
                    deleteStoredAsset($existing['asset_path']);
                }

                jsonResponse(true, [], 'Pool shape updated successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'DELETE':
            try {
                $stmt = $pdo->prepare("SELECT asset_path FROM pool_shapes WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    jsonResponse(false, [], 'Pool shape not found', 404);
                }

                $stmt = $pdo->prepare("DELETE FROM pool_shapes WHERE id = ?");
                $stmt->execute([$id]);

                deleteStoredAsset($existing['asset_path']);

                jsonResponse(true, [], 'Pool shape deleted successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        default:
            jsonResponse(false, [], 'Method not allowed', 405);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Pool Models
// ─────────────────────────────────────────────────────────────────────────────

function handlePoolModelsAdmin(string $method)
{
    global $pdo;

    switch ($method) {

        case 'GET':
            try {
                $stmt = $pdo->prepare("
                    SELECT id, pool_type_id, pool_shape_id, title, asset_path,
                           dimension, depth, gallons, shape_image_path, sort_order, is_active
                    FROM pool_models
                    ORDER BY sort_order ASC, id ASC
                ");
                $stmt->execute();
                jsonResponse(true, $stmt->fetchAll(), 'Pool models fetched successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'POST':
            try {
                $poolTypeId = field('pool_type_id');
                $poolShapeId = field('pool_shape_id');
                $title = field('title');
                $dimension = field('dimension');
                $depth = field('depth');
                $gallons = field('gallons');
                $sortOrder = field('sort_order', 0);

                if (!$poolTypeId || !$title) {
                    jsonResponse(false, [], 'pool_type_id and title are required', 400);
                }

                // Two independent image fields for a model: main image + shape image
                $assetPath = handleUploadedImage('image');
                $shapeImagePath = handleUploadedImage('shape_image');

                $stmt = $pdo->prepare("
                    INSERT INTO pool_models
                        (pool_type_id, pool_shape_id, title, asset_path, dimension, depth, gallons, shape_image_path, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    (int)$poolTypeId,
                    $poolShapeId ? (int)$poolShapeId : null,
                    $title,
                    $assetPath,
                    $dimension,
                    $depth,
                    $gallons,
                    $shapeImagePath,
                    (int)$sortOrder,
                ]);

                jsonResponse(true, ['id' => (int)$pdo->lastInsertId()], 'Pool model created successfully', 201);
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        default:
            jsonResponse(false, [], 'Method not allowed', 405);
    }
}

function handlePoolModelByIdAdmin(string $method, string $id)
{
    global $pdo;

    $id = (int)$id;

    switch ($method) {

        case 'GET':
            try {
                $stmt = $pdo->prepare("
                    SELECT id, pool_type_id, pool_shape_id, title, asset_path,
                           dimension, depth, gallons, shape_image_path, sort_order, is_active
                    FROM pool_models WHERE id = ?
                ");
                $stmt->execute([$id]);
                $row = $stmt->fetch();

                if (!$row) {
                    jsonResponse(false, [], 'Pool model not found', 404);
                }

                jsonResponse(true, $row, 'Pool model fetched successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'POST': // update
            try {
                $stmt = $pdo->prepare("SELECT asset_path, shape_image_path FROM pool_models WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    jsonResponse(false, [], 'Pool model not found', 404);
                }

                $poolTypeId = field('pool_type_id');
                $poolShapeId = field('pool_shape_id');
                $title = field('title');
                $dimension = field('dimension');
                $depth = field('depth');
                $gallons = field('gallons');
                $sortOrder = field('sort_order');
                $isActive = field('is_active');

                $newAssetPath = handleUploadedImage('image');
                $newShapeImagePath = handleUploadedImage('shape_image');

                $fields = [];
                $params = [];

                if ($poolTypeId !== null) { $fields[] = 'pool_type_id = ?'; $params[] = (int)$poolTypeId; }
                if ($poolShapeId !== null) { $fields[] = 'pool_shape_id = ?'; $params[] = (int)$poolShapeId; }
                if ($title !== null) { $fields[] = 'title = ?'; $params[] = $title; }
                if ($dimension !== null) { $fields[] = 'dimension = ?'; $params[] = $dimension; }
                if ($depth !== null) { $fields[] = 'depth = ?'; $params[] = $depth; }
                if ($gallons !== null) { $fields[] = 'gallons = ?'; $params[] = $gallons; }
                if ($sortOrder !== null) { $fields[] = 'sort_order = ?'; $params[] = (int)$sortOrder; }
                if ($isActive !== null) { $fields[] = 'is_active = ?'; $params[] = (int)$isActive; }

                if ($newAssetPath !== null) { $fields[] = 'asset_path = ?'; $params[] = $newAssetPath; }
                if ($newShapeImagePath !== null) { $fields[] = 'shape_image_path = ?'; $params[] = $newShapeImagePath; }

                if (empty($fields)) {
                    jsonResponse(false, [], 'No fields to update', 400);
                }

                $params[] = $id;

                $stmt = $pdo->prepare("UPDATE pool_models SET " . implode(', ', $fields) . " WHERE id = ?");
                $stmt->execute($params);

                if ($newAssetPath !== null) {
                    deleteStoredAsset($existing['asset_path']);
                }
                if ($newShapeImagePath !== null) {
                    deleteStoredAsset($existing['shape_image_path']);
                }

                jsonResponse(true, [], 'Pool model updated successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'DELETE':
            try {
                $stmt = $pdo->prepare("SELECT asset_path, shape_image_path FROM pool_models WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    jsonResponse(false, [], 'Pool model not found', 404);
                }

                $stmt = $pdo->prepare("DELETE FROM pool_models WHERE id = ?");
                $stmt->execute([$id]);

                deleteStoredAsset($existing['asset_path']);
                deleteStoredAsset($existing['shape_image_path']);

                jsonResponse(true, [], 'Pool model deleted successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        default:
            jsonResponse(false, [], 'Method not allowed', 405);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Gel Coats
// ─────────────────────────────────────────────────────────────────────────────

function handleGelCoatsAdmin(string $method)
{
    global $pdo;

    switch ($method) {

        case 'GET':
            try {
                $stmt = $pdo->prepare("
                    SELECT id, pool_type_id, title, asset_path, is_active
                    FROM gel_coat_options
                    ORDER BY id ASC
                ");
                $stmt->execute();
                jsonResponse(true, $stmt->fetchAll(), 'Gel coat options fetched successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'POST':
            try {
                $poolTypeId = field('pool_type_id');
                $title = field('title');

                if (!$poolTypeId || !$title) {
                    jsonResponse(false, [], 'pool_type_id and title are required', 400);
                }

                $assetPath = handleUploadedImage('image');

                $stmt = $pdo->prepare("
                    INSERT INTO gel_coat_options (pool_type_id, title, asset_path, is_active)
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute([(int)$poolTypeId, $title, $assetPath]);

                jsonResponse(true, ['id' => (int)$pdo->lastInsertId()], 'Gel coat option created successfully', 201);
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        default:
            jsonResponse(false, [], 'Method not allowed', 405);
    }
}

function handleGelCoatByIdAdmin(string $method, string $id)
{
    global $pdo;

    $id = (int)$id;

    switch ($method) {

        case 'GET':
            try {
                $stmt = $pdo->prepare("SELECT id, pool_type_id, title, asset_path, is_active FROM gel_coat_options WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();

                if (!$row) {
                    jsonResponse(false, [], 'Gel coat option not found', 404);
                }

                jsonResponse(true, $row, 'Gel coat option fetched successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'POST': // update
            try {
                $stmt = $pdo->prepare("SELECT asset_path FROM gel_coat_options WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    jsonResponse(false, [], 'Gel coat option not found', 404);
                }

                $poolTypeId = field('pool_type_id');
                $title = field('title');
                $isActive = field('is_active');

                $newAssetPath = handleUploadedImage('image');

                $fields = [];
                $params = [];

                if ($poolTypeId !== null) { $fields[] = 'pool_type_id = ?'; $params[] = (int)$poolTypeId; }
                if ($title !== null) { $fields[] = 'title = ?'; $params[] = $title; }
                if ($isActive !== null) { $fields[] = 'is_active = ?'; $params[] = (int)$isActive; }

                if ($newAssetPath !== null) {
                    $fields[] = 'asset_path = ?';
                    $params[] = $newAssetPath;
                }

                if (empty($fields)) {
                    jsonResponse(false, [], 'No fields to update', 400);
                }

                $params[] = $id;

                $stmt = $pdo->prepare("UPDATE gel_coat_options SET " . implode(', ', $fields) . " WHERE id = ?");
                $stmt->execute($params);

                if ($newAssetPath !== null) {
                    deleteStoredAsset($existing['asset_path']);
                }

                jsonResponse(true, [], 'Gel coat option updated successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        case 'DELETE':
            try {
                $stmt = $pdo->prepare("SELECT asset_path FROM gel_coat_options WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    jsonResponse(false, [], 'Gel coat option not found', 404);
                }

                $stmt = $pdo->prepare("DELETE FROM gel_coat_options WHERE id = ?");
                $stmt->execute([$id]);

                deleteStoredAsset($existing['asset_path']);

                jsonResponse(true, [], 'Gel coat option deleted successfully');
            } catch (Exception $e) {
                jsonResponse(false, [], $e->getMessage(), 500);
            }
            break;

        default:
            jsonResponse(false, [], 'Method not allowed', 405);
    }
}