<?php

require_once __DIR__ . '/../includes/auth.php';

function get_motors(PDO $db, array $filters = [])
{
    $conditions = ["status = 'tersedia'"];
    $parameters = [];

    $type = isset($filters['type']) && is_string($filters['type']) ? strtolower(trim($filters['type'])) : '';
    if ($type !== '' && in_array($type, get_available_types(), true)) {
        $conditions[] = 'type = :type';
        $parameters[':type'] = $type;
    }

    $keyword = isset($filters['keyword']) && is_string($filters['keyword']) ? trim($filters['keyword']) : '';
    if ($keyword !== '') {
        $conditions[] = '(brand LIKE :keyword_brand OR model LIKE :keyword_model)';
        $parameters[':keyword_brand'] = '%' . $keyword . '%';
        $parameters[':keyword_model'] = '%' . $keyword . '%';
    }

    $sql = 'SELECT id, brand, model, plate_number, type, engine_cc, year, color,
                   description, price_per_day, image, status
            FROM motorcycles
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY brand ASC, model ASC, id ASC';

    if (isset($filters['limit'])) {
        $limit = filter_var($filters['limit'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        if ($limit !== false) {
            $sql .= ' LIMIT ' . $limit;
        }
    }

    $statement = $db->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function get_motor_by_id(PDO $db, $id)
{
    $statement = $db->prepare(
        'SELECT id, brand, model, plate_number, type, engine_cc, year, color,
                description, price_per_day, image, status
         FROM motorcycles
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute([
        ':id' => $id,
    ]);

    return $statement->fetch() ?: null;
}

function get_available_types()
{
    return ['matic', 'manual', 'sport'];
}

function motor_image_url($image)
{
    $placeholder = 'data:image/svg+xml,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500" viewBox="0 0 800 500">'
        . '<rect width="800" height="500" fill="#e9ecef"/>'
        . '<text x="400" y="250" dominant-baseline="middle" text-anchor="middle" '
        . 'font-family="sans-serif" font-size="32" fill="#6c757d">Foto motor belum tersedia</text>'
        . '</svg>'
    );

    if (!is_string($image) || trim($image) === '') {
        return $placeholder;
    }

    $filename = basename(trim($image));
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return $placeholder;
    }

    $imagePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
        . 'motor' . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($imagePath)) {
        return $placeholder;
    }

    return BASE_URL . 'uploads/motor/' . rawurlencode($filename);
}
