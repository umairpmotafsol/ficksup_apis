<?php
// ─────────────────────────────────────────────────────────────────────────────
// api_pool_data_public.php
// Public APIs for Pool Types, Shapes, Models & Gel Coats
//
// Endpoints:
//
// GET /client/Ficksup/api/pool-types
// GET /client/Ficksup/api/pool-shapes
// GET /client/Ficksup/api/pool-models
// GET /client/Ficksup/api/pool-models?pool_type_id=1
// GET /client/Ficksup/api/pool-models?pool_type_id=1&pool_shape_id=2
// GET /client/Ficksup/api/gel-coats?pool_type_id=1
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Get last part of URL
$requestPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$endpoint = basename($requestPath);

// Route Requests
switch ($endpoint) {

    case 'pool-types':
        handlePoolTypesEndpoint();
        break;

    case 'pool-shapes':
        handlePoolShapesEndpoint();
        break;

    case 'pool-models':
        handlePoolModelsEndpoint();
        break;

    case 'gel-coats':
        handleGelCoatsEndpoint();
        break;

    default:
        jsonResponse(false, [], 'Endpoint not found', 404);
}

// ─────────────────────────────────────────────────────────────────────────────
// Pool Types
// ─────────────────────────────────────────────────────────────────────────────

function handlePoolTypesEndpoint()
{
    global $pdo;

    validateMethod('GET');

    try {

        $stmt = $pdo->prepare("
            SELECT
                id,
                label,
                asset_path,
                description
            FROM pool_types
            WHERE is_active = 1
            ORDER BY id ASC
        ");

        $stmt->execute();

        $rows = $stmt->fetchAll();

        $data = [];

        foreach ($rows as $row) {

            $data[] = [
                'id' => (int)$row['id'],
                'asset' => $row['asset_path'],
                'label' => $row['label'],
                'description' => $row['description']
            ];
        }

        jsonResponse(true, $data, 'Pool types fetched successfully');

    } catch (Exception $e) {

        jsonResponse(false, [], $e->getMessage(), 500);

    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Pool Shapes
// ─────────────────────────────────────────────────────────────────────────────

function handlePoolShapesEndpoint()
{
    global $pdo;

    validateMethod('GET');

    try {

        $stmt = $pdo->prepare("
            SELECT
                id,
                label,
                asset_path,
                description
            FROM pool_shapes
            WHERE is_active = 1
            ORDER BY id ASC
        ");

        $stmt->execute();

        $rows = $stmt->fetchAll();

        $data = [];

        foreach ($rows as $row) {

            $data[] = [
                'id' => (int)$row['id'],
                'asset' => $row['asset_path'],
                'label' => $row['label'],
                'description' => $row['description']
            ];
        }

        jsonResponse(true, $data, 'Pool shapes fetched successfully');

    } catch (Exception $e) {

        jsonResponse(false, [], $e->getMessage(), 500);

    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Pool Models
// ─────────────────────────────────────────────────────────────────────────────

function handlePoolModelsEndpoint()
{
    global $pdo;

    validateMethod('GET');

    try {

        $poolTypeId = isset($_GET['pool_type_id']) ? (int)$_GET['pool_type_id'] : null;
        $poolShapeId = isset($_GET['pool_shape_id']) ? (int)$_GET['pool_shape_id'] : null;

        $query = "
            SELECT
                id,
                pool_type_id,
                pool_shape_id,
                title,
                asset_path,
                dimension,
                depth,
                gallons,
                shape_image_path,
                sort_order
            FROM pool_models
            WHERE is_active = 1
        ";

        $params = [];

        if ($poolTypeId) {

            $query .= " AND pool_type_id = ?";
            $params[] = $poolTypeId;

        }

        if ($poolShapeId) {

            $query .= " AND pool_shape_id = ?";
            $params[] = $poolShapeId;

        }

        $query .= " ORDER BY sort_order ASC,id ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();

        $data = [];

        foreach ($rows as $row) {

            $data[] = [

                'id' => (int)$row['id'],
                'pool_type_id' => (int)$row['pool_type_id'],
                'pool_shape_id' => $row['pool_shape_id'] ? (int)$row['pool_shape_id'] : null,

                'asset' => $row['asset_path'],
                'title' => $row['title'],
                'dimension' => $row['dimension'],
                'depth' => $row['depth'],
                'gallons' => $row['gallons'],
                'shapeImage' => $row['shape_image_path']

            ];
        }

        jsonResponse(true, $data, 'Pool models fetched successfully');

    } catch (Exception $e) {

        jsonResponse(false, [], $e->getMessage(), 500);

    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Gel Coats
// ─────────────────────────────────────────────────────────────────────────────

function handleGelCoatsEndpoint()
{
    global $pdo;

    validateMethod('GET');

    try {

        if (!isset($_GET['pool_type_id'])) {

            jsonResponse(false, [], 'pool_type_id is required', 400);

        }

        $poolTypeId = (int)$_GET['pool_type_id'];

        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                asset_path
            FROM gel_coat_options
            WHERE
                pool_type_id = ?
                AND is_active = 1
            ORDER BY id ASC
        ");

        $stmt->execute([$poolTypeId]);

        $rows = $stmt->fetchAll();

        $data = [];

        foreach ($rows as $row) {

            $data[] = [

                'id' => (int)$row['id'],
                'asset' => $row['asset_path'],
                'title' => $row['title']

            ];
        }

        jsonResponse(true, $data, 'Gel coat options fetched successfully');

    } catch (Exception $e) {

        jsonResponse(false, [], $e->getMessage(), 500);

    }
}